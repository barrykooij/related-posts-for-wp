<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! class_exists( 'RP4WP_Nag_Manager' ) ) {
	class RP4WP_Nag_Manager {

		/**
		 * Get the admin query string
		 *
		 * @since  1.3.0
		 * @access public
		 *
		 * @return mixed
		 */
		private function get_admin_query_string_array() {
			parse_str( $_SERVER['QUERY_STRING'], $params );

			return $params;
		}

		/**
		 * Insert the install date
		 *
		 * @since  1.3.0
		 * @access public
		 *
		 * @return string
		 */
		private function insert_install_date() {
			$datetime_now = new DateTime();
			$date_string  = $datetime_now->format( 'Y-m-d' );
			add_option( RP4WP_Constants::OPTION_INSTALL_DATE, $date_string, '', 'no' );

			return $date_string;
		}

		/**
		 * get the install date
		 *
		 * @since  1.3.0
		 * @access private
		 *
		 * @return DateTime
		 */
		private function get_install_date() {
			$date_string = get_option( RP4WP_Constants::OPTION_INSTALL_DATE, '' );
			if ( $date_string == '' ) {
				// There is no install date, plugin was installed before version 1.2.0. Add it now.
				$date_string = $this->insert_install_date();
			}

			return new DateTime( $date_string );
		}

		/**
		 * Setup the nag manager
		 *
		 * @since  1.3.0
		 * @access public
		 *
		 * @return bool
		 */
		public function setup() {

			// Check user rights
			if ( current_user_can( 'install_plugins' ) ) {

				// Get current user
				$current_user = wp_get_current_user();

				// Get user meta
				$hide_notice = get_user_meta( $current_user->ID, RP4WP_Constants::OPTION_ADMIN_NOTICE_KEY, true );

				// Check if the notice is already dismissed
				if ( '' == $hide_notice ) {
					// Get installation date
					$datetime_install = $this->get_install_date();
					$datetime_past    = new DateTime( '-10 days' );

					if ( $datetime_past >= $datetime_install ) {
						// 10 or more days ago, show admin notice
						add_action( 'admin_notices', array( $this, 'display_admin_notice' ) );
					}
				}

				// Catch the hide notice
				$this->catch_hide_notice();
			}
		}

		/**
		 * Display the admin notice
		 *
		 * @since  1.3.0
		 * @access public
		 */
		public function display_admin_notice() {
			$query_params = $this->get_admin_query_string_array();
			$query_string = '?' . http_build_query( array_merge( $query_params, array( RP4WP_Constants::OPTION_ADMIN_NOTICE_KEY => '1' ) ) );

			echo '<div class="updated"><p>';
			printf( __( "You've been using %sRelated Posts for WordPress%s for some time now, could you please give it a review at wordpress.org?", 'related-posts-for-wp' ), '<b>', '</b>' );
			echo "<br /><br />";
			printf( __( "%sYes, take me there!%s - %sI've already done this!%s", 'related-posts-for-wp' ), '<a href="http://wordpress.org/support/view/plugin-reviews/related-posts-for-wp" target="_blank">', '</a>', '<a href="' . $query_string . '">', '</a>' );
			echo "</p></div>";
		}

		/**
		 * Catch the hide notice click
		 *
		 * @since  1.0.0
		 * @access public
		 */
		public function catch_hide_notice() {
			if ( isset( $_GET[RP4WP_Constants::OPTION_ADMIN_NOTICE_KEY] ) && current_user_can( 'install_plugins' ) ) {
				// Add user meta
				global $current_user;
				add_user_meta( $current_user->ID, RP4WP_Constants::OPTION_ADMIN_NOTICE_KEY, '1', true );

				// Build redirect URL
				$query_params = $this->get_admin_query_string_array();
				unset( $query_params[RP4WP_Constants::OPTION_ADMIN_NOTICE_KEY] );
				$query_string = http_build_query( $query_params );
				if ( $query_string != '' ) {
					$query_string = '?' . $query_string;
				}

				$redirect_url = 'http';
				if ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] == 'on' ) {
					$redirect_url .= 's';
				}
				$redirect_url .= '://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . $query_string;

				// Redirect
				wp_redirect( $redirect_url );
				exit;
			}
		}

	}
}