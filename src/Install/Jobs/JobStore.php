<?php
/**
 * The install job store class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Install\Jobs;

/**
 * Keeps the installation job in an option, and the lock that lets one request at a time work on it.
 */
class JobStore {

	/**
	 * The option with the job. Not autoloaded.
	 */
	public const OPTION = 'rp4wp_install_job';

	/**
	 * The option with the lock: the Unix timestamp it expires at.
	 */
	public const LOCK = 'rp4wp_install_lock';

	/**
	 * The option the 2.x wizard set while it ran; the notices and premium read it.
	 */
	public const IS_INSTALLING = 'rp4wp_is_installing';

	/**
	 * The job, read from the database, not from the object cache, so a request sees what another one saved.
	 *
	 * @return Job|null
	 */
	public function get(): ?Job {
		wp_cache_delete( self::OPTION, 'options' );

		return Job::from_array( get_option( self::OPTION, null ) );
	}

	/**
	 * Save the job. A running installation sets the 2.x flag, and an installation that ended removes it.
	 *
	 * @param Job $job The job.
	 *
	 * @return void
	 */
	public function save( Job $job ): void {
		update_option( self::OPTION, $job->to_array(), false );

		// Other background jobs, such as premium's refresh, leave the flag alone.
		if ( ! $job->is_install() ) {
			return;
		}

		if ( $job->is_running() ) {
			update_option( self::IS_INSTALLING, 1, false );
		} else {
			delete_option( self::IS_INSTALLING );
		}
	}

	/**
	 * Take the lock, unless another request holds it and it has not expired.
	 *
	 * One query, so two requests can't both take it: the row is added, or its value replaced when it expired.
	 *
	 * @param int $seconds How long the lock lasts, in case the request that holds it dies.
	 *
	 * @return bool Whether this request holds the lock now.
	 */
	public function lock( int $seconds ): bool {
		global $wpdb;

		$now = time();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- An atomic lock; the options API can't do this.
		$affected = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->options} ( option_name, option_value, autoload ) VALUES ( %s, %s, 'off' )
				ON DUPLICATE KEY UPDATE option_value = IF( option_value < %d, VALUES( option_value ), option_value )",
				self::LOCK,
				(string) ( $now + $seconds ),
				$now
			)
		);

		wp_cache_delete( self::LOCK, 'options' );

		// 1 when the row was added, 2 when an expired lock was replaced, 0 when the lock is held.
		return is_int( $affected ) && $affected > 0;
	}

	/**
	 * Whether a request holds the lock now.
	 *
	 * @return bool
	 */
	public function is_locked(): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Read past the object cache; see lock().
		$expires = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::LOCK ) );

		return null !== $expires && (int) $expires >= time();
	}

	/**
	 * Release the lock.
	 *
	 * @return void
	 */
	public function unlock(): void {
		global $wpdb;

		$wpdb->delete( $wpdb->options, [ 'option_name' => self::LOCK ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- See lock().
		wp_cache_delete( self::LOCK, 'options' );
	}
}
