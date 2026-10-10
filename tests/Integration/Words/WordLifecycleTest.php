<?php
/**
 * The word lifecycle test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Words;

use LV2\WordPress\RelatedPostsForWP\Install\Table;
use LV2\WordPress\RelatedPostsForWP\Links\PostState;
use LV2\WordPress\RelatedPostsForWP\Related\Finder;
use LV2\WordPress\RelatedPostsForWP\Tests\Integration\TestCase;
use LV2\WordPress\RelatedPostsForWP\Words\Tokenizer;

/**
 * The words of a post in the cache table follow the post (plan section 6.4): they come when it is published, with
 * the language and the version of the tokenizer, and go when it stops being published or is deleted, by anyone
 * (known issue K5).
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Links\PostLifecycle
 * @covers \LV2\WordPress\RelatedPostsForWP\Words\Cache
 * @covers \LV2\WordPress\RelatedPostsForWP\Words\Extractor
 */
final class WordLifecycleTest extends TestCase {

	public function test_a_published_post_gets_its_words_with_their_count_language_and_version(): void {
		$post_id = $this->post( 'Sourdough bread at home', 'The dough needs a lively sourdough starter and a hot oven.' );

		$rows = $this->rows( $post_id );

		$this->assertArrayHasKey( 'sourdough', $rows );
		$this->assertSame( 81, (int) $rows['sourdough']['tf'], 'Once in the content, 80 times in the title.' );
		$this->assertSame( Tokenizer::VERSION, (int) $rows['sourdough']['version'] );
		$this->assertArrayNotHasKey( 'the', $rows );
		$this->assertSame( 'en', PostState::language( $post_id ) );
	}

	public function test_a_post_in_another_language_leaves_out_the_ignored_words_of_that_language(): void {
		$post_id = $this->post( 'Brot backen zu Hause', 'Der Teig braucht einen lebendigen Sauerteig und einen heißen Ofen, und die Zeit, die er braucht.' );

		$rows = $this->rows( $post_id );

		$this->assertSame( 'de', PostState::language( $post_id ) );
		$this->assertArrayHasKey( 'brot', $rows );
		$this->assertArrayNotHasKey( 'und', $rows );
		$this->assertArrayNotHasKey( 'der', $rows );
	}

	/**
	 * A post that stops being published loses its words.
	 *
	 * @dataProvider statuses
	 *
	 * @param string $status The new status.
	 */
	public function test_a_post_that_stops_being_published_loses_its_words( string $status ): void {
		$post_id = $this->post( 'Composting kitchen scraps', 'Compost turns kitchen scraps into soil.' );
		$this->assertNotSame( [], $this->rows( $post_id ) );

		if ( 'trash' === $status ) {
			wp_trash_post( $post_id );
		} else {
			wp_update_post(
				[
					'ID'          => $post_id,
					'post_status' => $status,
				]
			);
		}

		$this->assertSame( [], $this->rows( $post_id ) );
		$this->assertSame( 0, PostState::indexed_at( $post_id ) );
	}

	/**
	 * The statuses a published post can get.
	 *
	 * @return array<string, array{string}>
	 */
	public static function statuses(): array {
		return [
			'draft'   => [ 'draft' ],
			'private' => [ 'private' ],
			'pending' => [ 'pending' ],
			'trash'   => [ 'trash' ],
		];
	}

	public function test_a_post_deleted_without_a_user_loses_its_words_and_marks(): void {
		$post_id = $this->post( 'Pruning roses', 'Prune roses in early spring.' );
		wp_set_current_user( 0 );

		wp_delete_post( $post_id, true );

		$this->assertSame( [], $this->rows( $post_id ) );
		$this->assertSame( 0, PostState::indexed_at( $post_id ) );
		$this->assertSame( '', PostState::language( $post_id ) );
	}

	public function test_a_post_published_again_gets_its_words_back(): void {
		$post_id = $this->post( 'Raised vegetable beds', 'Raised beds warm up early in spring.' );
		wp_update_post(
			[
				'ID'          => $post_id,
				'post_status' => 'draft',
			]
		);

		wp_update_post(
			[
				'ID'          => $post_id,
				'post_status' => 'publish',
			]
		);

		$this->assertArrayHasKey( 'raised', $this->rows( $post_id ) );
	}

	public function test_words_of_an_older_version_are_not_compared(): void {
		global $wpdb;

		$first  = $this->post( 'Climbing roses', 'Climbing roses on a garden wall.' );
		$second = $this->post( 'Climbing roses for walls', 'Climbing roses for a sunny wall.' );
		$this->assertContains( $first, $this->related( $second ) );

		$wpdb->update( Table::name(), [ 'version' => Tokenizer::VERSION - 1 ], [ 'post_id' => $first ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test data.

		$this->assertNotContains( $first, $this->related( $second ) );
	}

	/**
	 * A published post.
	 *
	 * @param string $title   The title.
	 * @param string $content The content.
	 *
	 * @return int
	 */
	private function post( string $title, string $content ): int {
		return self::factory()->post->create(
			[
				'post_title'   => $title,
				'post_content' => $content,
				'post_status'  => 'publish',
			]
		);
	}

	/**
	 * The stored words of a post, by word.
	 *
	 * @param int $post_id The post.
	 *
	 * @return array<string, array<string, string>>
	 */
	private function rows( int $post_id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- Reading our own table.
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT word, weight, tf, version FROM ' . Table::name() . ' WHERE post_id = %d', $post_id ), ARRAY_A );

		return array_column( $rows, null, 'word' );
	}

	/**
	 * The related posts the finder finds.
	 *
	 * @param int $post_id The post.
	 *
	 * @return int[]
	 */
	private function related( int $post_id ): array {
		return array_map( 'intval', array_column( ( new Finder() )->related_posts( $post_id, 5 ), 'ID' ) );
	}
}
