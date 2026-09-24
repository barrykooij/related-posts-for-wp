<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! class_exists( 'RP4WP_Javascript_Strings' ) ) {
	/**
	 * 2.x JavaScript strings. Delegates to LV2\WordPress\RelatedPostsForWP\Admin\Assets.
	 */
	class RP4WP_Javascript_Strings {

		public static function get() {
			return \LV2\WordPress\RelatedPostsForWP\Admin\Assets::javascript_strings();
		}

	}
}
