<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class RP4WP_Dependencies {

	/**
	 * Check for dependencies
	 */
	public function check() {
		\LV2\WordPress\RelatedPostsForWP\Admin\Notices\Mbstring::setup();
	}

	/**
	 * Display error if mbstring is not loaded
	 */
	public function display_mbstring_error() {
		\LV2\WordPress\RelatedPostsForWP\Admin\Notices\Mbstring::display();
	}

}
