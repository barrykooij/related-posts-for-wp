<?php
/**
 * The word cache table class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Install;

/**
 * The word cache table: the most important words of every post, with their weight, how many times they count and the
 * version of the tokenizer that found them. The migrations of 3.0 create and upgrade it (see the migrations folder).
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
	 * Create the table if it does not exist, in the shape the migrations give it.
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
  `word` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `weight` float unsigned NOT NULL DEFAULT 0,
  `post_type` varchar(20) NOT NULL,
  `tf` float unsigned NOT NULL DEFAULT 0,
  `version` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`post_id`,`word`),
  KEY `word_cover` (`word`,`version`,`post_type`,`post_id`,`weight`) ) " . $wpdb->get_charset_collate() . ';'
		);
		// phpcs:enable
	}
}
