<?php
/**
 * The word cache test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Words;

use LV2\WordPress\RelatedPostsForWP\Install\Table;
use LV2\WordPress\RelatedPostsForWP\Tests\Integration\TestCase;
use LV2\WordPress\RelatedPostsForWP\Words\Cache;

/**
 * Which posts the word cache counts as done: the ones with words, and the ones that yield none (known issue P15).
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Words\Cache
 */
final class CacheTest extends TestCase {

	/**
	 * The word cache.
	 *
	 * @var Cache
	 */
	private Cache $cache;

	public function set_up(): void {
		parent::set_up();

		$this->cache = new Cache();
	}

	public function test_a_post_without_words_is_marked_as_done(): void {
		$post_id = $this->post_without_words();

		$this->assertSame( 1, $this->cache->uncached_post_count() );

		$this->cache->save_all();

		$this->assertSame( 0, $this->cache->uncached_post_count() );
		$this->assertSame( '1', get_post_meta( $post_id, Cache::META_NO_WORDS, true ) );
		$this->assertSame( 0, $this->word_count( $post_id ) );
	}

	public function test_a_post_with_emoji_gets_its_words(): void {
		$post_id = self::factory()->post->create(
			[
				'post_title'   => "Birthday cake \u{1F382} recipes",
				'post_content' => "Bake a chocolate cake\u{1F389} with candles and chocolate frosting.",
			]
		);
		$this->cache->delete_post( $post_id );

		$this->cache->save_post( $post_id );

		// 2.x lost every word here: WordPress refuses an insert with a character the table can't store.
		$this->assertGreaterThan( 0, $this->word_count( $post_id ) );
		$this->assertSame( '', get_post_meta( $post_id, Cache::META_NO_WORDS, true ) );
	}

	public function test_words_that_come_back_clear_the_mark(): void {
		$post_id = $this->post_without_words();
		$this->cache->save_post( $post_id );

		wp_update_post(
			[
				'ID'           => $post_id,
				'post_content' => 'Sourdough bread needs flour, water, salt and a lively starter.',
			]
		);
		$this->cache->save_post( $post_id );

		$this->assertGreaterThan( 0, $this->word_count( $post_id ) );
		$this->assertSame( '', get_post_meta( $post_id, Cache::META_NO_WORDS, true ) );
	}

	public function test_deleting_the_words_of_a_post_clears_the_mark(): void {
		$post_id = $this->post_without_words();
		$this->cache->save_post( $post_id );

		$this->cache->delete_post( $post_id );

		$this->assertSame( '', get_post_meta( $post_id, Cache::META_NO_WORDS, true ) );
		$this->assertSame( 1, $this->cache->uncached_post_count() );
	}

	/**
	 * A published post that yields no words, and has not been looked at yet: its only word is an ignored one.
	 *
	 * @return int
	 */
	private function post_without_words(): int {
		$post_id = self::factory()->post->create(
			[
				'post_title'   => 'The',
				'post_content' => '',
			]
		);
		$this->assertIsInt( $post_id );
		$this->cache->delete_post( $post_id );

		return $post_id;
	}

	/**
	 * The number of stored words of a post.
	 *
	 * @param int $post_id The post.
	 *
	 * @return int
	 */
	private function word_count( int $post_id ): int {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Table::name() . ' WHERE post_id = %d', $post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- Reading our own table.
	}
}
