<?php
/**
 * The schema class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Database;

/**
 * The plugin's own tables, and where its data lives on this site.
 *
 * The migrations move the data of 2.x and the 3.0 release candidates into tables, one step at a time, and record each
 * step in the storage level:
 * - 0: everything as in 2.x: the marks on posts are post meta, the links are posts of the type `rp4wp_link`;
 * - 1: the marks on posts are rows of the post state table;
 * - 2: the links are rows of the links table too.
 *
 * Code that reads or writes marks or links asks for the level, so a site keeps working while a migration runs, or when
 * one failed.
 */
final class Schema {

	/**
	 * The word cache.
	 */
	public const CACHE = 'rp4wp_cache';

	/**
	 * The words of the word cache, with in how many posts each is (its document frequency).
	 */
	public const WORDS = 'rp4wp_words';

	/**
	 * The links between posts.
	 */
	public const LINKS = 'rp4wp_links';

	/**
	 * What the plugin knows per post: when its words were cached, and when it was linked.
	 */
	public const POST_STATE = 'rp4wp_post_state';

	/**
	 * The migrations that ran.
	 */
	public const MIGRATIONS = 'rp4wp_migrations';

	/**
	 * The option with the storage level. Autoloaded: every request reads it.
	 */
	public const OPTION_STORAGE = 'rp4wp_storage';

	/**
	 * The marks on posts are in the post state table.
	 */
	public const STORAGE_POST_STATE = 1;

	/**
	 * The links are in the links table.
	 */
	public const STORAGE_LINKS = 2;

	/**
	 * The migration that moves the marks on posts into the post state table.
	 */
	public const MIGRATION_POST_STATE = '2026_10_10_000003_move_post_marks';

	/**
	 * The migration that moves the links into the links table.
	 */
	public const MIGRATION_LINKS = '2026_10_10_000005_move_links';

	/**
	 * The full name of a table of the plugin.
	 *
	 * @param string $name One of the table constants.
	 *
	 * @return string
	 */
	public static function table( string $name ): string {
		global $wpdb;

		return $wpdb->prefix . $name;
	}

	/**
	 * Whether a table exists.
	 *
	 * @param string $name The full table name.
	 *
	 * @return bool
	 */
	public static function has_table( string $name ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Schema check.
		return $name === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $name ) ) );
	}

	/**
	 * The storage level of this site. When its option is gone while the tables stay (a database that was partly
	 * restored, for example), the level follows from the migrations that ran.
	 *
	 * @return int
	 */
	public static function storage(): int {
		$level = get_option( self::OPTION_STORAGE, null );

		if ( null === $level ) {
			$level = self::storage_from_migrations();
			update_option( self::OPTION_STORAGE, $level, true );
		}

		return (int) $level;
	}

	/**
	 * Set the storage level.
	 *
	 * @param int $level The level.
	 *
	 * @return void
	 */
	public static function set_storage( int $level ): void {
		update_option( self::OPTION_STORAGE, $level, true );
	}

	/**
	 * The storage level the recorded migrations of the free plugin lead to.
	 *
	 * @return int
	 */
	private static function storage_from_migrations(): int {
		global $wpdb;

		$table = self::table( self::MIGRATIONS );
		if ( ! self::has_table( $table ) ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The plugin's own table.
		$applied = $wpdb->get_col( $wpdb->prepare( "SELECT migration FROM `{$table}` WHERE plugin = 'free' AND migration IN ( %s, %s )", self::MIGRATION_POST_STATE, self::MIGRATION_LINKS ) );

		if ( in_array( self::MIGRATION_LINKS, $applied, true ) ) {
			return self::STORAGE_LINKS;
		}

		return in_array( self::MIGRATION_POST_STATE, $applied, true ) ? self::STORAGE_POST_STATE : 0;
	}

	/**
	 * Whether the marks on posts are in the post state table.
	 *
	 * @return bool
	 */
	public static function has_post_state(): bool {
		return self::storage() >= self::STORAGE_POST_STATE;
	}

	/**
	 * Whether the links are in the links table.
	 *
	 * @return bool
	 */
	public static function has_links(): bool {
		return self::storage() >= self::STORAGE_LINKS;
	}
}
