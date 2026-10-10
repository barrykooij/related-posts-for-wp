<?php
/**
 * Migration: the word cache table for the tokenizer of 3.0.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Database\Migration;
use LV2\WordPress\RelatedPostsForWP\Database\Schema;

return new class() extends Migration {

	/**
	 * What the migration does.
	 *
	 * @return string
	 */
	public function description(): string {
		return 'Upgrade the word cache table: utf8mb4, words of at most 64 characters compared exactly, the columns tf and version, and a covering index instead of the word indexes of premium. Down keeps the words of 3.0 but drops their 4-byte characters.';
	}

	/**
	 * Create the table on a new site, or upgrade the table of 2.x. The words that stay get version 1, which the finder
	 * of 3.0 does not use; the update caches the words of every post again.
	 *
	 * @return bool
	 */
	public function up(): bool {
		$table = $this->table( Schema::CACHE );

		if ( ! $this->has_table( $table ) ) {
			$this->query(
				"CREATE TABLE IF NOT EXISTS `{$table}` (
					post_id bigint(20) unsigned NOT NULL,
					word varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
					weight float unsigned NOT NULL DEFAULT 0,
					post_type varchar(20) NOT NULL,
					tf float unsigned NOT NULL DEFAULT 0,
					version tinyint(3) unsigned NOT NULL DEFAULT 0,
					PRIMARY KEY  (post_id, word),
					KEY word_cover (word, version, post_type, post_id, weight)
				) {$this->charset_collate()}"
			);

			return true;
		}

		if ( $this->has_column( $table, 'version' ) ) {
			return true;
		}

		// Longer words do not fit in the new column.
		$this->query( "DELETE FROM `{$table}` WHERE CHAR_LENGTH( word ) > 64" );

		// Premium's index on the word, and the copies that 2.x added again on every installation (known issue P19).
		$drops = '';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema check; table names can't be placeholders.
		foreach ( array_unique( (array) $this->db->get_col( "SHOW INDEX FROM `{$table}`", 2 ) ) as $index ) {
			if ( 1 === preg_match( '/^word(_[0-9]+)?$/', (string) $index ) ) {
				$drops .= "DROP INDEX `{$index}`, ";
			}
		}

		// One rebuild of the table. Each text column gets its character set here: CONVERT TO would override the binary
		// collation of the word.
		$this->query(
			"ALTER TABLE `{$table}` {$drops}
				DEFAULT CHARACTER SET utf8mb4 COLLATE {$this->collation()},
				MODIFY word varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
				MODIFY post_type varchar(20) CHARACTER SET utf8mb4 COLLATE {$this->collation()} NOT NULL,
				MODIFY weight float unsigned NOT NULL DEFAULT 0,
				ADD COLUMN tf float unsigned NOT NULL DEFAULT 0,
				ADD COLUMN version tinyint(3) unsigned NOT NULL DEFAULT 1,
				ADD KEY word_cover (word, version, post_type, post_id, weight)"
		);

		// New rows say their version; the default is for rows that do not.
		$this->query( "ALTER TABLE `{$table}` ALTER COLUMN version SET DEFAULT 0" );

		return true;
	}

	/**
	 * Back to the table of 2.x: utf8, words of up to 255 characters, no new columns or index. Words with characters
	 * that utf8 can't store go.
	 *
	 * @return void
	 */
	public function down(): void {
		$table = $this->table( Schema::CACHE );

		if ( ! $this->has_table( $table ) || ! $this->has_column( $table, 'version' ) ) {
			return;
		}

		$this->query( "DELETE FROM `{$table}` WHERE HEX( word ) <> HEX( CONVERT( word USING utf8 ) )" );

		$this->query(
			"ALTER TABLE `{$table}`
				DROP INDEX word_cover,
				DROP COLUMN tf,
				DROP COLUMN version,
				DEFAULT CHARACTER SET utf8,
				MODIFY word varchar(255) CHARACTER SET utf8 NOT NULL,
				MODIFY post_type varchar(20) CHARACTER SET utf8 NOT NULL,
				MODIFY weight float unsigned NOT NULL"
		);
	}

	/**
	 * The collation of the other text columns: the site's when it is utf8mb4.
	 *
	 * @return string
	 */
	private function collation(): string {
		$collation = (string) $this->db->collate;

		return 0 === strpos( $collation, 'utf8mb4_' ) && 1 === preg_match( '/^[a-z0-9_]+$/', $collation ) ? $collation : 'utf8mb4_unicode_ci';
	}
};
