<?php
/**
 * The deprecated word manager test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Deprecated;

use LV2\WordPress\RelatedPostsForWP\Install\Table;
use LV2\WordPress\RelatedPostsForWP\Words\Cache;
use LV2\WordPress\RelatedPostsForWP\Words\Extractor;

/**
 * RP4WP_Related_Word_Manager. dedupe_and_order_ignored_words() ends the request, so only the shim check covers it.
 *
 * @covers \RP4WP_Related_Word_Manager
 */
final class RelatedWordManagerTest extends ShimTestCase {

	/**
	 * The 2.x word manager.
	 *
	 * @var \RP4WP_Related_Word_Manager
	 */
	private \RP4WP_Related_Word_Manager $manager;

	/**
	 * A post with words.
	 *
	 * @var int
	 */
	private int $post;

	public function set_up(): void {
		parent::set_up();

		$this->post = self::factory()->post->create( [ 'post_content' => 'Espresso needs finely ground coffee beans and hot water.' ] );
		// Publishing cached the words; start without them.
		( new Cache() )->delete_post( $this->post );

		$this->expect_deprecated( 'RP4WP_Related_Word_Manager' );
		$this->manager = new \RP4WP_Related_Word_Manager();
	}

	public function test_the_table_is_the_word_cache(): void {
		$this->expect_deprecated( 'RP4WP_Related_Word_Manager::get_database_table' );

		$this->assertSame( Table::name(), \RP4WP_Related_Word_Manager::get_database_table() );
		$this->assertSame( 'rp4wp_cache', \RP4WP_Related_Word_Manager::DB_TABLE );
	}

	public function test_get_words_of_post_extracts_the_words(): void {
		$this->expect_deprecated( 'RP4WP_Related_Word_Manager::get_words_of_post' );

		$words = $this->manager->get_words_of_post( $this->post );

		$this->assertNotEmpty( $words );
		$this->assertSame( ( new Extractor() )->words_of_post( $this->post ), $words );
	}

	public function test_words_are_saved_and_deleted(): void {
		$this->expect_deprecated( 'RP4WP_Related_Word_Manager::save_words_of_post', 'RP4WP_Related_Word_Manager::delete_words' );

		$this->manager->save_words_of_post( $this->post );
		$this->assertGreaterThan( 0, $this->cached_words() );

		$this->manager->delete_words( $this->post );
		$this->assertSame( 0, $this->cached_words() );
	}

	public function test_the_counts_keep_their_2x_shape(): void {
		$this->expect_deprecated( 'RP4WP_Related_Word_Manager::get_uncached_post_count', 'RP4WP_Related_Word_Manager::get_word_count' );

		$cache = new Cache();

		$this->assertSame( (string) $cache->uncached_post_count(), $this->manager->get_uncached_post_count() );
		$this->assertSame( (string) $cache->word_count(), $this->manager->get_word_count() );
	}

	public function test_get_uncached_post_ids_lists_posts_to_cache(): void {
		$this->expect_deprecated( 'RP4WP_Related_Word_Manager::get_uncached_post_ids' );

		$this->assertContains( $this->post, array_map( 'intval', $this->manager->get_uncached_post_ids() ) );
	}

	public function test_save_all_words_caches_every_post(): void {
		$this->expect_deprecated( 'RP4WP_Related_Word_Manager::save_all_words' );

		$this->assertTrue( $this->manager->save_all_words() );
		$this->assertSame( 0, ( new Cache() )->uncached_post_count() );
	}

	/**
	 * The number of cached words of the post.
	 *
	 * @return int
	 */
	private function cached_words(): int {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Table::name() . ' WHERE post_id = %d', $this->post ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- Reading our own table.
	}
}
