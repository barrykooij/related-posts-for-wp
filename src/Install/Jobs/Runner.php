<?php
/**
 * The install runner class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Install\Jobs;

use LV2\WordPress\RelatedPostsForWP\Module;

/**
 * Runs the background actions of the installer when Action Scheduler calls them.
 */
class Runner implements Module {

	/**
	 * Listen for the background action.
	 *
	 * @return void
	 */
	public static function setup(): void {
		add_action( Queue::HOOK, [ self::class, 'run' ] );
	}

	/**
	 * Work on the job.
	 *
	 * @param mixed $job_id The job the action was queued for.
	 *
	 * @return void
	 */
	public static function run( $job_id ): void {
		( new Queue() )->run( (string) $job_id );
	}
}
