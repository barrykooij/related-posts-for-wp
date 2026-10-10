<?php
/**
 * Migration: the links table.
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
		return 'Create the links table: per link, the post, its related post, the position and whether it was added by hand.';
	}

	/**
	 * Create the table.
	 *
	 * @return bool
	 */
	public function up(): bool {
		$table = $this->table( Schema::LINKS );

		$this->query(
			"CREATE TABLE IF NOT EXISTS `{$table}` (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				parent_id bigint(20) unsigned NOT NULL,
				child_id bigint(20) unsigned NOT NULL,
				position smallint(5) unsigned NOT NULL DEFAULT 0,
				is_manual tinyint(3) unsigned NOT NULL DEFAULT 0,
				parent_type varchar(20) NOT NULL DEFAULT '',
				created datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY parent_child (parent_id, child_id),
				KEY parent_position (parent_id, position),
				KEY child (child_id),
				KEY parent_type (parent_type, is_manual)
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
		$this->query( 'DROP TABLE IF EXISTS `' . $this->table( Schema::LINKS ) . '`' );
	}
};
