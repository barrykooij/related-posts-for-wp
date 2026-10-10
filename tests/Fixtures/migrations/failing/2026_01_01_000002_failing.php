<?php
/**
 * A migration for the migrator tests: One that fails.
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
		return 'One that fails.';
	}

	/**
	 * Fail, as a migration does when the database refuses a query.
	 *
	 * @return bool
	 *
	 * @throws \RuntimeException Always.
	 */
	public function up(): bool {
		if ( '' !== $this->name() ) {
			throw new \RuntimeException( 'The disk is full.' );
		}

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
