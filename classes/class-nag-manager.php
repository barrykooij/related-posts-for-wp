<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! class_exists( 'RP4WP_Nag_Manager' ) ) {
	class RP4WP_Nag_Manager {

		/**
		 * Setup the nag manager
		 *
		 * @since  1.3.0
		 * @access public
		 *
		 * @return bool
		 */
		public function setup() {
			\LV2\WordPress\RelatedPostsForWP\Admin\Notices\Review::setup();
		}

		/**
		 * Display the admin notice
		 *
		 * @since  1.3.0
		 * @access public
		 */
		public function display_admin_notice() {
			\LV2\WordPress\RelatedPostsForWP\Admin\Notices\Review::display();
		}

		/**
		 * Catch the hide notice click
		 *
		 * @since  1.0.0
		 * @access public
		 */
		public function catch_hide_notice() {
			\LV2\WordPress\RelatedPostsForWP\Admin\Notices\Review::handle_dismissal();
		}

	}
}
