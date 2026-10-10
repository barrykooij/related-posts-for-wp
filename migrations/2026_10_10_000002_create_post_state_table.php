<?php
/**
 * Migration: the post state table.
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
		return 'Create the post state table: per post, when its words were cached and when it was linked.';
	}

	/**
	 * Create the table.
	 *
	 * @return bool
	 */
	public function up(): bool {
		$table = $this->table( Schema::POST_STATE );

		$this->query(
			"CREATE TABLE IF NOT EXISTS `{$table}` (
				post_id bigint(20) unsigned NOT NULL,
				post_type varchar(20) NOT NULL DEFAULT '',
				language varchar(12) NOT NULL DEFAULT '',
				tokens int(10) unsigned NOT NULL DEFAULT 0,
				version tinyint(3) unsigned NOT NULL DEFAULT 0,
				indexed_at bigint(20) unsigned NOT NULL DEFAULT 0,
				linked_at bigint(20) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (post_id),
				KEY type_linked (post_type, linked_at),
				KEY type_indexed (post_type, indexed_at)
			) {$this->charset_collate()}"
		);

		return true;
	}

	/**
	 * Drop the table.
	 *
	 * @return void
	 */
	public function down(): void {
		$this->query( 'DROP TABLE IF EXISTS `' . $this->table( Schema::POST_STATE ) . '`' );
	}
};
