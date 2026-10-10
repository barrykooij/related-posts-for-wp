<?php
/**
 * Migration: the words table.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Database\Migration;
use LV2\WordPress\RelatedPostsForWP\Database\Schema;
use LV2\WordPress\RelatedPostsForWP\Words\Statistics;

return new class() extends Migration {

	/**
	 * What the migration does.
	 *
	 * @return string
	 */
	public function description(): string {
		return 'Create the words table: per word of the word cache, in how many posts it is (its document frequency), counted from the words that are there.';
	}

	/**
	 * Create the table, and count the words of the current version that the cache has.
	 *
	 * @return bool
	 */
	public function up(): bool {
		$table = $this->table( Schema::WORDS );

		$this->query(
			"CREATE TABLE IF NOT EXISTS `{$table}` (
				word varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
				df int(10) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (word),
				KEY df (df)
			) {$this->charset_collate()}"
		);

		// The words that are cached already, with the version of 3.0; the update caches every post again anyway.
		$cache = $this->table( Schema::CACHE );
		$this->query( "DELETE FROM `{$table}`" );
		$this->query( "INSERT INTO `{$table}` (word, df) SELECT word, COUNT(*) FROM `{$cache}` WHERE version = 2 GROUP BY word" );
		update_option( Statistics::OPTION_POSTS, (int) $this->db->get_var( "SELECT COUNT( DISTINCT post_id ) FROM `{$cache}` WHERE version = 2" ), true ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The plugin's own table.

		return true;
	}

	/**
	 * Drop the table.
	 *
	 * @return void
	 */
	public function down(): void {
		$this->query( 'DROP TABLE IF EXISTS `' . $this->table( Schema::WORDS ) . '`' );
		delete_option( Statistics::OPTION_POSTS );
	}
};
