<?php
/**
 * Migration: the table that records the migrations.
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
		return 'Create the table that records the migrations.';
	}

	/**
	 * Create the table.
	 *
	 * @return bool
	 */
	public function up(): bool {
		$table = $this->table( Schema::MIGRATIONS );

		$this->query(
			"CREATE TABLE IF NOT EXISTS `{$table}` (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				plugin varchar(32) NOT NULL,
				migration varchar(191) NOT NULL,
				batch int(10) unsigned NOT NULL,
				applied_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY plugin_migration (plugin, migration)
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
		$this->query( 'DROP TABLE IF EXISTS `' . $this->table( Schema::MIGRATIONS ) . '`' );
	}
};
