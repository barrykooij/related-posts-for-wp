<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly


if ( ! class_exists( 'RP4WP_Is_Installing_Notice' ) ) {

	/**
	 * Class RP4WP_Is_Installing_Notice
	 *
	 * @since 1.4.0
	 */
	/**
	 * 2.x installing notice. Delegates to LV2\WordPress\RelatedPostsForWP\Admin\Notices\Installing.
	 */
	class RP4WP_Is_Installing_Notice {

		/**
		 * Check if we need to do anything related to this notice
		 * @since  1.4.0
		 * @access public
		 *
		 */
		public function check() {
			\LV2\WordPress\RelatedPostsForWP\Admin\Notices\Installing::setup();
		}

		/**
		 * The admin notice content
		 *
		 * @since  1.4.0
		 * @access public
		 */
		public function content() {
			\LV2\WordPress\RelatedPostsForWP\Admin\Notices\Installing::display();
		}

	}

}
