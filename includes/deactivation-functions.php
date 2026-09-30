<?php
/**
 * The deactivation callback. Kept as a named function, registered with register_deactivation_hook().
 *
 * Keep this file parseable by PHP 7.0, like the main file that loads it.
 *
 * @package RelatedPostsForWP
 */

/**
 * Stop the background installer: remove its actions that did not run yet, and mark a running installation as
 * cancelled, so it can be resumed after activating the plugin again.
 *
 * @return void
 */
function rp4wp_deactivate_plugin() {
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		// By group: with a hook, Action Scheduler only matches actions with exactly the arguments given.
		as_unschedule_all_actions( '', array(), 'rp4wp' );
	}

	$job = get_option( 'rp4wp_install_job' );

	if ( is_array( $job ) && isset( $job['status'] ) && 'running' === $job['status'] ) {
		$job['status'] = 'cancelled';
		$job['ended']  = time();
		update_option( 'rp4wp_install_job', $job, false );
	}

	delete_option( 'rp4wp_is_installing' );
}
