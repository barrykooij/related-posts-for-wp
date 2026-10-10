<?php
/**
 * A migration for the migrator tests: One that needs three slices.
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
		return 'One that needs three slices.';
	}

	/**
	 * Note that it ran.
	 *
	 * @return bool
	 */
	public function up(): bool {
		$slice = (int) $this->cursor( 'slice', 0 ) + 1;
		$this->save_cursor( 'slice', $slice );

		$log   = (array) get_option( 'rp4wp_test_migrations', [] );
		$log[] = 'slice ' . $slice;
		update_option( 'rp4wp_test_migrations', $log );

		return $slice >= 3;
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
