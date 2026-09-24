<?php
/**
 * The deprecated RP4WP_Playground class file.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Admin\Notices\Playground;
use LV2\WordPress\RelatedPostsForWP\Compat\Deprecation;

/**
 * The 2.x WordPress Playground helper.
 *
 * @deprecated 3.0.0 Use \LV2\WordPress\RelatedPostsForWP\Admin\Notices\Playground.
 */
class RP4WP_Playground {

	/**
	 * Whether the site runs in WordPress Playground.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return bool
	 */
	public static function is_playground() {
		Deprecation::method( __METHOD__, Playground::class . '::is_playground()' );

		return Playground::is_playground();
	}

	/**
	 * Show the notice that the plugin does not run in WordPress Playground.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return void
	 */
	public static function add_admin_notice() {
		Deprecation::method( __METHOD__, Playground::class . '::setup()' );

		Playground::setup();
	}
}
