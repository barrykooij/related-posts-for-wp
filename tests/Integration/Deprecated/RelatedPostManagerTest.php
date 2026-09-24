<?php
/**
 * The deprecated related post manager test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Deprecated;

use LV2\WordPress\RelatedPostsForWP\Links\LinkRepository;
use LV2\WordPress\RelatedPostsForWP\PostTypes;
use LV2\WordPress\RelatedPostsForWP\Related\Finder;
use LV2\WordPress\RelatedPostsForWP\Words\Cache;

/**
 * RP4WP_Related_Post_Manager.
 *
 * @covers \RP4WP_Related_Post_Manager
 */
final class RelatedPostManagerTest extends ShimTestCase {

	/**
	 * The 2.x related post manager.
	 *
	 * @var \RP4WP_Related_Post_Manager
	 */
	private \RP4WP_Related_Post_Manager $manager;

	/**
	 * Posts about the same topic, with their words cached.
	 *
	 * @var int[]
	 */
	private array $posts;

	public function set_up(): void {
		parent::set_up();

		$this->posts = [
			self::factory()->post->create( [ 'post_content' => 'Tomatoes need sun, water and rich soil to grow in the garden.' ] ),
			self::factory()->post->create( [ 'post_content' => 'Grow tomatoes in rich soil with plenty of sun in your garden.' ] ),
			self::factory()->post->create( [ 'post_content' => 'Garden soil and water for growing tomatoes and peppers.' ] ),
		];

		$cache = new Cache();
		foreach ( $this->posts as $post_id ) {
			$cache->save_post( $post_id );
		}

		$this->expect_deprecated( 'RP4WP_Related_Post_Manager' );
		$this->manager = new \RP4WP_Related_Post_Manager();
	}

	public function test_get_related_posts_finds_the_related_posts(): void {
		$this->expect_deprecated( 'RP4WP_Related_Post_Manager::get_related_posts' );

		$related = $this->manager->get_related_posts( $this->posts[0], 2 );

		$this->assertCount( 2, $related );
		$this->assertEquals( ( new Finder() )->related_posts( $this->posts[0], 2 ), $related );
	}

	public function test_get_not_auto_linked_posts_ids_lists_posts_to_link(): void {
		$this->expect_deprecated( 'RP4WP_Related_Post_Manager::get_not_auto_linked_posts_ids' );

		$this->assertSame( ( new Finder() )->not_auto_linked_post_ids( 10 ), $this->manager->get_not_auto_linked_posts_ids( 10 ) );
	}

	public function test_the_unlinked_post_count_keeps_its_2x_shape(): void {
		$this->expect_deprecated( 'RP4WP_Related_Post_Manager::get_unlinked_post_count', 'RP4WP_Related_Post_Manager::get_uncached_post_count' );

		$count    = ( new Finder() )->unlinked_post_count();
		$expected = 0 === $count ? 0 : (string) $count;

		$this->assertSame( $expected, $this->manager->get_unlinked_post_count() );
		$this->assertSame( $expected, $this->manager->get_uncached_post_count( 'post' ) );
	}

	public function test_link_related_post_links_one_post(): void {
		$this->expect_deprecated( 'RP4WP_Related_Post_Manager::link_related_post' );

		$this->assertTrue( $this->manager->link_related_post( $this->posts[0], 1 ) );
		$this->assertCount( 1, ( new LinkRepository() )->get_children( $this->posts[0] ) );
	}

	public function test_link_related_posts_links_every_post(): void {
		$this->expect_deprecated( 'RP4WP_Related_Post_Manager::link_related_posts' );

		$this->assertTrue( $this->manager->link_related_posts( 2 ) );

		foreach ( $this->posts as $post_id ) {
			$this->assertCount( 2, ( new LinkRepository() )->get_children( $post_id ) );
		}
	}

	public function test_the_batch_helpers_keep_working(): void {
		$this->expect_deprecated( 'RP4WP_Related_Post_Manager::batch_data_get_post', 'RP4WP_Related_Post_Manager::batch_data_get_meta', 'RP4WP_Related_Post_Manager::batch_data_set_pid' );

		$batch = [
			'post' => '(1, 2)',
			'meta' => [ '(3)', '(4)' ],
		];

		$this->assertSame( '(1, 2)', $this->manager->batch_data_get_post( $batch ) );
		$this->assertSame( '(3),(4)', $this->manager->batch_data_get_meta( $batch ) );
		$this->assertSame( '(5)', $this->manager->batch_data_set_pid( '(%d)', 5 ) );
	}

	public function test_get_supported_post_types_lists_the_post_types(): void {
		$this->expect_deprecated( 'RP4WP_Related_Post_Manager::get_supported_post_types' );

		$this->assertSame( PostTypes::supported(), \RP4WP_Related_Post_Manager::get_supported_post_types() );
	}
}
