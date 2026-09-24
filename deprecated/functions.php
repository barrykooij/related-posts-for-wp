<?php
/**
 * The deprecated 2.x functions.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Compat\Deprecation;
use LV2\WordPress\RelatedPostsForWP\Main;

/**
 * The 2.x plugin object.
 *
 * @since 1.0.0
 * @deprecated 3.0.0 Use \LV2\WordPress\RelatedPostsForWP\Main::get().
 *
 * @return RP4WP
 */
function RP4WP() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid -- The 2.x name.
	Deprecation::function_used( __FUNCTION__, Main::class . '::get()' );

	return RP4WP::instance();
}
