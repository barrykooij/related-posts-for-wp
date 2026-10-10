<?php
/**
 * The core algorithm adapter file of the quality harness.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Quality;

use LV2\WordPress\RelatedPostsForWP\Install\Table;
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
	 * @param array<int, array<string, float>> $vectors Post ID => word => weight.
	 *
	 * @return void
	 */
	public function seed( array $vectors ): void {
		global $wpdb;

		$values = [];
		foreach ( $vectors as $post_id => $words ) {
			foreach ( $words as $word => $weight ) {
				$values[] = $wpdb->prepare( '(%d, %s, %f, %s, %d)', $post_id, (string) $word, $weight, 'post', Tokenizer::VERSION );
			}
		}

		foreach ( array_chunk( $values, 5000 ) as $chunk ) {
			$wpdb->query( 'INSERT INTO ' . Table::name() . ' (post_id, word, weight, post_type, version) VALUES ' . implode( ',', $chunk ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- Each row is prepared above.
		}
	}
}
