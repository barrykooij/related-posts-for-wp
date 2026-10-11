<?php
/**
 * Migration: the notice of the update gets an option of its own.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Database\Migration;
use LV2\WordPress\RelatedPostsForWP\Install\Update;

return new class() extends Migration {

	/**
	 * What the migration does.
	 *
	 * @return string
	 */
	public function description(): string {
		return 'Move what the notice of the update shows out of the autoloaded option rp4wp_update into rp4wp_update_notice, which is not autoloaded, so a stale copy of the autoloaded options in an object cache can not hide it.';
	}

	/**
	 * Split the state, as the database has it: a stale cache may still say the job runs.
	 *
	 * @return bool
	 */
	public function up(): bool {
		$state = $this->stored();
		if ( null === $state ) {
			return true;
		}

		$notice = (string) ( $state['notice'] ?? '' );
		if ( '' !== $notice ) {
			update_option( Update::NOTICE_OPTION, $notice, false );
		}

		update_option( Update::OPTION, [ 'pending' => ! empty( $state['pending'] ) ], true );

		return true;
	}

	/**
	 * Put the notice back into rp4wp_update.
	 *
	 * @return void
	 */
	public function down(): void {
		$state = $this->stored();
		if ( null === $state ) {
			return;
		}

		update_option(
			Update::OPTION,
			[
				'pending' => ! empty( $state['pending'] ),
				'notice'  => (string) get_option( Update::NOTICE_OPTION, '' ),
			],
			true
		);
		delete_option( Update::NOTICE_OPTION );
	}

	/**
	 * The state of the update in the database, past the cache; null when there is none.
	 *
	 * @return array<string, mixed>|null
	 */
	private function stored(): ?array {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Past the cache on purpose.
		$value = $this->db->get_var( $this->db->prepare( "SELECT option_value FROM {$this->db->options} WHERE option_name = %s", Update::OPTION ) );
		$state = null === $value ? null : maybe_unserialize( (string) $value );

		return is_array( $state ) ? $state : null;
	}
};
