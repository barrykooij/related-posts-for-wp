<?php
/**
 * The word cache table class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Install;

/**
 * The word cache table: the most important words of every post, with their weight.
 */
class Table {

	/**
	 * The table name without the WordPress prefix.
	 */
	public const NAME = 'rp4wp_cache';

	/**
	 * The full table name.
	 *
	 * @return string
	 */
	public static function name(): string {
		global $wpdb;

		return $wpdb->prefix . self::NAME;
	}

	/**
	 * Create the table if it does not exist. The schema is the same as in 2.x.
	 *
	 * @return void
	 */
	public static function create(): void {
		global $wpdb;

		$table = self::name();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema setup of our own table; the name cannot be a placeholder.
		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS `{$table}` (
  `post_id` bigint(20) unsigned NOT NULL,
  `word` varchar(255) CHARACTER SET utf8 NOT NULL,
  `weight` float unsigned NOT NULL,
  `post_type` varchar(20) CHARACTER SET utf8 NOT NULL,
  PRIMARY KEY (`post_id`,`word`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8;"
		);
		// phpcs:enable
	}
}
