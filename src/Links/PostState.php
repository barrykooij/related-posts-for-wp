<?php
/**
 * The post state class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Links;

use LV2\WordPress\RelatedPostsForWP\Database\Schema;
use LV2\WordPress\RelatedPostsForWP\Words\Cache;

/**
 * What the plugin knows per post: when its words were cached (indexed), and when it was linked to its related posts.
 * Both are times in milliseconds; 0 means never.
 *
 * Since storage level 1 this is a row of the post state table. Before that (2.x data that was not migrated yet, or a
 * migration that failed) it is post meta, as in 2.x: `rp4wp_no_words` for a post that yields no words, and
 * `rp4wp_auto_linked` for a linked post.
 */
final class PostState {

	/**
	 * The version of the word extraction that writes the rows; the tokenizer of the quality plan raises it.
	 */
	public const WORDS_VERSION = 1;

	/**
	 * The time to mark something with: milliseconds since the Unix epoch, so two marks in the same second still come
	 * in order.
	 *
	 * @return int
	 */
	public static function now(): int {
		return (int) floor( microtime( true ) * 1000 );
	}

	/**
	 * Whether a post was linked to its related posts.
	 *
	 * @param int $post_id The post.
	 *
	 * @return bool
	 */
	public static function is_linked( int $post_id ): bool {
		return self::linked_at( $post_id ) > 0;
	}

	/**
	 * When a post was linked to its related posts; 0 for never, 1 for a time before 3.0 that is not known.
	 *
	 * @param int $post_id The post.
	 *
	 * @return int Milliseconds.
	 */
	public static function linked_at( int $post_id ): int {
		global $wpdb;

		if ( ! Schema::has_post_state() ) {
			return 1 == get_post_meta( $post_id, LinkPostType::META_AUTO_LINKED, true ) ? 1 : 0; // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- Meta is stored as "1".
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- The plugin's own table, read past caches: jobs write it in other requests.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT linked_at FROM ' . self::table() . ' WHERE post_id = %d', $post_id ) );
	}

	/**
	 * Mark a post as linked.
	 *
	 * @param int      $post_id The post.
	 * @param int|null $time    When, in milliseconds; now by default.
	 *
	 * @return void
	 */
	public static function mark_linked( int $post_id, ?int $time = null ): void {
		if ( ! Schema::has_post_state() ) {
			update_post_meta( $post_id, LinkPostType::META_AUTO_LINKED, 1 );

			return;
		}

		self::upsert( $post_id, 'linked_at', $time ?? self::now() );
	}

	/**
	 * Mark a post as not linked, so it is linked again when it is published.
	 *
	 * @param int $post_id The post.
	 *
	 * @return void
	 */
	public static function unmark_linked( int $post_id ): void {
		global $wpdb;

		if ( ! Schema::has_post_state() ) {
			delete_post_meta( $post_id, LinkPostType::META_AUTO_LINKED );

			return;
		}

		$wpdb->update( self::table(), [ 'linked_at' => 0 ], [ 'post_id' => $post_id ], [ '%d' ], [ '%d' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The plugin's own table.
	}

	/**
	 * When the words of a post were cached; 0 for never, 1 for a time before 3.0 that is not known.
	 *
	 * @param int $post_id The post.
	 *
	 * @return int Milliseconds.
	 */
	public static function indexed_at( int $post_id ): int {
		global $wpdb;

		if ( ! Schema::has_post_state() ) {
			return '' !== (string) get_post_meta( $post_id, Cache::META_NO_WORDS, true ) ? 1 : 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- The plugin's own table, read past caches.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT indexed_at FROM ' . self::table() . ' WHERE post_id = %d', $post_id ) );
	}

	/**
	 * Mark the words of a post as cached.
	 *
	 * @param int      $post_id   The post.
	 * @param bool     $has_words Whether words were stored; a post without words is marked so it does not count as a
	 *                            post to cache (known issue P15).
	 * @param int|null $time      When, in milliseconds; now by default.
	 *
	 * @return void
	 */
	public static function mark_indexed( int $post_id, bool $has_words, ?int $time = null ): void {
		if ( ! Schema::has_post_state() ) {
			// 2.x knows a post with words by its words; only a post without words gets a mark.
			if ( ! $has_words ) {
				update_post_meta( $post_id, Cache::META_NO_WORDS, 1 );
			}

			return;
		}

		self::upsert( $post_id, 'indexed_at', $time ?? self::now() );
	}

	/**
	 * Mark the words of a post as not cached.
	 *
	 * @param int $post_id The post.
	 *
	 * @return void
	 */
	public static function unmark_indexed( int $post_id ): void {
		global $wpdb;

		if ( ! Schema::has_post_state() ) {
			delete_post_meta( $post_id, Cache::META_NO_WORDS );

			return;
		}

		$wpdb->update( self::table(), [ 'indexed_at' => 0 ], [ 'post_id' => $post_id ], [ '%d' ], [ '%d' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The plugin's own table.
	}

	/**
	 * Forget a post, for example when it is deleted.
	 *
	 * @param int $post_id The post.
	 *
	 * @return void
	 */
	public static function forget( int $post_id ): void {
		global $wpdb;

		if ( ! Schema::has_post_state() ) {
			delete_post_meta( $post_id, Cache::META_NO_WORDS );
			delete_post_meta( $post_id, LinkPostType::META_AUTO_LINKED );

			return;
		}

		$wpdb->delete( self::table(), [ 'post_id' => $post_id ], [ '%d' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The plugin's own table.
	}

	/**
	 * Mark every post, or every post of some post types, as not linked and not cached; for a new installation.
	 *
	 * @param string[]|null $post_types The post types; null for all.
	 * @param bool          $linked     Whether to reset the links mark.
	 * @param bool          $indexed    Whether to reset the words mark.
	 *
	 * @return void
	 */
	public static function reset( ?array $post_types = null, bool $linked = true, bool $indexed = true ): void {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- The plugin's own table and post meta; the post types are escaped.
		$types = null === $post_types ? null : "'" . implode( "','", array_map( 'esc_sql', $post_types ) ) . "'";

		if ( ! Schema::has_post_state() ) {
			$keys  = array_filter( [ $linked ? LinkPostType::META_AUTO_LINKED : null, $indexed ? Cache::META_NO_WORDS : null ] );
			$where = null === $types ? '' : " AND post_id IN ( SELECT ID FROM {$wpdb->posts} WHERE post_type IN ( {$types} ) )";

			foreach ( $keys as $key ) {
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s{$where}", $key ) );
			}

			return;
		}

		$set = implode( ', ', array_filter( [ $linked ? 'linked_at = 0' : null, $indexed ? 'indexed_at = 0' : null ] ) );
		if ( '' === $set ) {
			return;
		}

		$wpdb->query( 'UPDATE ' . self::table() . " SET {$set}" . ( null === $types ? '' : " WHERE post_type IN ( {$types} )" ) );
		// phpcs:enable
	}

	/**
	 * An SQL condition: the post is not linked. For queries over posts.
	 *
	 * @param string $alias The alias of the posts table in the query.
	 *
	 * @return string
	 */
	public static function not_linked_sql( string $alias ): string {
		global $wpdb;

		if ( ! Schema::has_post_state() ) {
			return "NOT EXISTS ( SELECT 1 FROM {$wpdb->postmeta} RP4WP_M WHERE RP4WP_M.post_id = {$alias}.ID AND RP4WP_M.meta_key = '" . LinkPostType::META_AUTO_LINKED . "' )";
		}

		return 'NOT EXISTS ( SELECT 1 FROM ' . self::table() . " RP4WP_S WHERE RP4WP_S.post_id = {$alias}.ID AND RP4WP_S.linked_at > 0 )";
	}

	/**
	 * An SQL condition: the post was not linked since a time, or never. For queries over posts.
	 *
	 * @param string $alias The alias of the posts table in the query.
	 * @param int    $since The time, in milliseconds.
	 *
	 * @return string
	 */
	public static function not_linked_since_sql( string $alias, int $since ): string {
		if ( $since < 1 || ! Schema::has_post_state() ) {
			return self::not_linked_sql( $alias );
		}

		return 'NOT EXISTS ( SELECT 1 FROM ' . self::table() . " RP4WP_S WHERE RP4WP_S.post_id = {$alias}.ID AND RP4WP_S.linked_at >= " . (int) $since . ' )';
	}

	/**
	 * An SQL condition: the words of the post were not cached since a time, or never; with time 0, they were never
	 * cached (a post without words counts as cached). For queries over posts.
	 *
	 * @param string $alias The alias of the posts table in the query.
	 * @param int    $since The time, in milliseconds, or 0.
	 *
	 * @return string
	 */
	public static function not_indexed_sql( string $alias, int $since = 0 ): string {
		global $wpdb;

		if ( ! Schema::has_post_state() ) {
			return "NOT EXISTS ( SELECT 1 FROM {$wpdb->postmeta} RP4WP_M WHERE RP4WP_M.post_id = {$alias}.ID AND RP4WP_M.meta_key = '" . Cache::META_NO_WORDS . "' )";
		}

		return 'NOT EXISTS ( SELECT 1 FROM ' . self::table() . " RP4WP_S WHERE RP4WP_S.post_id = {$alias}.ID AND RP4WP_S.indexed_at >= " . max( 1, $since ) . ' )';
	}

	/**
	 * Write one time of a post, adding its row when it has none.
	 *
	 * @param int    $post_id The post.
	 * @param string $column  `linked_at` or `indexed_at`.
	 * @param int    $time    The time, in milliseconds.
	 *
	 * @return void
	 */
	private static function upsert( int $post_id, string $column, int $time ): void {
		global $wpdb;

		$version = 'indexed_at' === $column ? self::WORDS_VERSION : 0;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- The plugin's own table; the column comes from this class.
		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO ' . self::table() . " ( post_id, post_type, {$column}, version ) VALUES ( %d, %s, %d, %d )
				ON DUPLICATE KEY UPDATE {$column} = VALUES( {$column} ), post_type = VALUES( post_type )" . ( $version > 0 ? ', version = VALUES( version )' : '' ),
				$post_id,
				(string) get_post_type( $post_id ),
				$time,
				$version
			)
		);
		// phpcs:enable
	}

	/**
	 * The post state table.
	 *
	 * @return string
	 */
	private static function table(): string {
		return Schema::table( Schema::POST_STATE );
	}
}
