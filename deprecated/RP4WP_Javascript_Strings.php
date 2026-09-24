<?php
/**
 * The deprecated RP4WP_Javascript_Strings class file.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Admin\Assets;
use LV2\WordPress\RelatedPostsForWP\Compat\Deprecation;

/**
 * The 2.x strings of the admin scripts.
 *
 * @deprecated 3.0.0 Use \LV2\WordPress\RelatedPostsForWP\Admin\Assets::javascript_strings().
 */
class RP4WP_Javascript_Strings {

	/**
	 * The translated strings, available to the admin scripts as the rp4wp_js object.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return array
	 */
	public static function get() {
		Deprecation::method( __METHOD__, Assets::class . '::javascript_strings()' );

		return Assets::javascript_strings();
	}
}
