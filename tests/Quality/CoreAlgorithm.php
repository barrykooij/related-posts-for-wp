<?php
/**
 * The core algorithm adapter file of the quality harness.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Quality;

use LV2\WordPress\RelatedPostsForWP\Install\Table;
use LV2\WordPress\RelatedPostsForWP\Install\Tasks\WeighWordsTask;
use LV2\WordPress\RelatedPostsForWP\Words\Statistics;
use LV2\WordPress\RelatedPostsForWP\Words\Tokenizer;
use LV2\WordPress\RelatedPostsForWP\Related\Finder;
use LV2\WordPress\RelatedPostsForWP\Words\Cache;

/**
 * The free plugin's way of finding related posts: its word cache and its finder.
 */
final class CoreAlgorithm implements Algorithm {

	/**
	 * The name in the results.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'free';
	}

	/**
	 * Cache the words of the posts.
	 *
	 * @param int[] $post_ids The posts.
	 *
	 * @return void
	 */
	public function index( array $post_ids ): void {
		$cache = new Cache();

		foreach ( $post_ids as $post_id ) {
			$cache->save_post( (int) $post_id );
		}

		// Pass two over every post, as the installation's weighing step does.
		( new WeighWordsTask( 0 ) )->run_batch();
	}

	/**
	 * The related posts of a post.
	 *
	 * @param int $post_id The post.
	 * @param int $limit   How many.
	 *
	 * @return int[]
	 */
	public function related( int $post_id, int $limit ): array {
		return array_map(
			static function ( $row ): int {
				return (int) $row->ID;
			},
			( new Finder() )->related_posts( $post_id, $limit )
		);
	}

	/**
	 * The tokens stored for a post.
	 *
	 * @param int $post_id The post.
	 *
	 * @return string[]
	 */
	public function tokens( int $post_id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- The plugin's own table.
		return array_map( 'strval', $wpdb->get_col( $wpdb->prepare( 'SELECT word FROM ' . Table::name() . ' WHERE post_id = %d', $post_id ) ) );
	}

	/**
	 * Store word vectors directly.
	 *
	 * @param array<int, array<string, int>> $vectors Post ID => word => how many times it counts.
	 *
	 * @return void
	 */
	public function seed( array $vectors ): void {
		global $wpdb;

		$values = [];
		foreach ( $vectors as $post_id => $words ) {
			foreach ( $words as $word => $count ) {
				$values[] = $wpdb->prepare( '(%d, %s, 0, %s, %d, %d)', $post_id, (string) $word, 'post', $count, Tokenizer::VERSION );
			}
		}

		foreach ( array_chunk( $values, 5000 ) as $chunk ) {
			$wpdb->query( 'INSERT INTO ' . Table::name() . ' (post_id, word, weight, post_type, tf, version) VALUES ' . implode( ',', $chunk ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- Each row is prepared above.
		}
	}

	/**
	 * Count the document frequencies and weigh the words of every seeded post.
	 *
	 * @return void
	 */
	public function seeded(): void {
		global $wpdb;

		$statistics = new Statistics();
		$statistics->recount();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- The plugin's own table.
		$post_ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT post_id FROM ' . Table::name() . ' WHERE version = %d ORDER BY post_id', Tokenizer::VERSION ) ) );
		foreach ( array_chunk( $post_ids, 1000 ) as $chunk ) {
			$statistics->weigh( $chunk );
		}
	}
}
