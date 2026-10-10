<?php
/**
 * A migration for the migrator tests: The second.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Database\Migration;

return new class() extends Migration {

	/**
	 * What the migration does.
	 *
	 * @return string
	 */
	public function description(): string {
		return 'The second.';
	}

	/**
	 * Note that it ran.
	 *
	 * @return bool
	 */
	public function up(): bool {
		$log   = (array) get_option( 'rp4wp_test_migrations', [] );
		$log[] = 'up ' . $this->name();
		update_option( 'rp4wp_test_migrations', $log );

		return true;
	}

	/**
	 * Note that it was undone.
	 *
	 * @return void
	 */
	public function down(): void {
		$log   = (array) get_option( 'rp4wp_test_migrations', [] );
		$log[] = 'down ' . $this->name();
		update_option( 'rp4wp_test_migrations', $log );
	}
};
