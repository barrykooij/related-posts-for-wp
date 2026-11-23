<?php
/**
 * The query budget test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Frontend;

use LV2\WordPress\RelatedPostsForWP\Links\LinkRepository;
use LV2\WordPress\RelatedPostsForWP\Main;
use LV2\WordPress\RelatedPostsForWP\Tests\Integration\TestCase;
use LV2\WordPress\RelatedPostsForWP\Tests\Support\QueryLog;

/**
 * How many database queries one render of the related posts sends, with images and excerpts, on a site without a
 * persistent object cache. Set RP4WP_QUERY_REPORT=1 to print the counts, or 2 to print the queries too.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Frontend\Renderer::related_posts_html
 * @covers \LV2\WordPress\RelatedPostsForWP\Links\LinkRepository::get_children
 */
final class QueryBudgetTest extends TestCase {

	/**
	 * The most queries a render may send, by the number of related posts: the same for any number, because the
	 * renderer loads the authors, the thumbnails and their meta of all related posts at once (B1 in the 3.1 plan).
	 * 3.0.1 sent 26 for 5 related posts and 46 for 10: six, plus four for every related post.
	 *
	 * @var array<int, int>
	 */
	private const BUDGET = [
		5  => 10,
		10 => 10,
	];

	public function tear_down(): void {
		delete_option( 'rp4wp' );
		wp_reset_postdata();

		parent::tear_down();
	}

	/**
	 * The numbers of related posts to render.
	 *
	 * @return array<string, array{int}>
	 */
	public function amounts(): array {
		return [
			'5 related posts'  => [ 5 ],
			'10 related posts' => [ 10 ],
		];
	}

	/**
	 * @dataProvider amounts
	 *
	 * @param int $amount The number of related posts.
	 */
	public function test_a_render_stays_within_its_query_budget( int $amount ): void {
		$parent = $this->post_with_related_posts( $amount );
		update_option(
			'rp4wp',
			array_merge(
				Main::get()->settings()->get_options(),
				[
					'display_image'  => 1,
					'excerpt_length' => 20,
				]
			)
		);

		$this->cold_cache( $parent );
		$log = QueryLog::of( static fn () => Main::get()->renderer()->render( $parent ) );

		$this->assertSame( $amount, substr_count( (string) $log->result, '<li>' ) );
		$this->assertSame( $amount, substr_count( (string) $log->result, 'rp4wp-related-post-image' ) );
		$this->report( $amount, $log );
		$this->assertLessThanOrEqual( self::BUDGET[ $amount ], $log->count(), (string) $log );
	}

	/**
	 * A post with related posts, each by its own author, with a featured image and some content.
	 *
	 * @param int $amount The number of related posts.
	 *
	 * @return int The post.
	 */
	private function post_with_related_posts( int $amount ): int {
		$parent = self::factory()->post->create( [ 'post_title' => 'The post' ] );
		$links  = new LinkRepository();

		for ( $i = 1; $i <= $amount; $i++ ) {
			$child = self::factory()->post->create(
				[
					'post_title'   => "Related post {$i}",
					'post_content' => str_repeat( "Words of related post {$i}. ", 30 ),
					'post_author'  => self::factory()->user->create( [ 'role' => 'author' ] ),
				]
			);
			$image = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg', $child );
			set_post_thumbnail( $child, $image );
			$links->add( $parent, $child );
		}

		return $parent;
	}

	/**
	 * Empty the object cache, as on a new request without a persistent object cache, and load what every request has
	 * loaded before related posts render: the options and the post itself.
	 *
	 * @param int $post_id The post.
	 *
	 * @return void
	 */
	private function cold_cache( int $post_id ): void {
		wp_cache_flush();
		wp_load_alloptions();
		Main::get()->settings()->get_options();
		get_post( $post_id );
	}

	/**
	 * Print the count when asked to.
	 *
	 * @param int      $amount The number of related posts.
	 * @param QueryLog $log    The queries.
	 *
	 * @return void
	 */
	private function report( int $amount, QueryLog $log ): void {
		$level = (int) getenv( 'RP4WP_QUERY_REPORT' );
		if ( $level > 0 ) {
			fwrite( STDERR, sprintf( "\nQueries for %d related posts: %d\n", $amount, $log->count() ) . ( $level > 1 ? $log . PHP_EOL : '' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Test output.
		}
	}
}
