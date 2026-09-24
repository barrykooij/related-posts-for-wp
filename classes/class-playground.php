<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * 2.x Playground helper. Delegates to LV2\WordPress\RelatedPostsForWP\Admin\Notices\Playground.
 */
class RP4WP_Playground {

	/**
	 * Check if we're in playground
	 *
	 * @return bool
	 */
	public static function is_playground() {
		return \LV2\WordPress\RelatedPostsForWP\Admin\Notices\Playground::is_playground();
	}

	/**
	 * Add the admin notice
	 *
	 * @return void
	 */
	public static function add_admin_notice() {
		\LV2\WordPress\RelatedPostsForWP\Admin\Notices\Playground::setup();
	}
}
