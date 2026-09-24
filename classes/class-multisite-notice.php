<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * 2.x multisite notice. Delegates to LV2\WordPress\RelatedPostsForWP\Admin\Notices\Multisite.
 */
class RP4WP_Multisite_Notice {
	public static function display() {
		\LV2\WordPress\RelatedPostsForWP\Admin\Notices\Multisite::display();
	}
}
