<?php
/**
 * Plugin Name: RP4WP upgrade test: no background requests
 * Description: Test-only. Action Scheduler runs its actions only when the test runs them with WP-CLI, so the site is
 * compared before the update of the related posts starts, and the test decides when it runs. WP-Cron is off in the
 * wp-env config.
 *
 * @package RelatedPostsForWP
 */

add_filter( 'action_scheduler_allow_async_request_runner', '__return_false' );
