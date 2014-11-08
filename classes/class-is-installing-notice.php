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
	class RP4WP_Is_Installing_Notice {

		/**
		 * Get the admin query string
		 *
		 * @since  1.4.0
		 * @access public
		 *
		 * @return mixed
		 */
		private function get_admin_query_string_array() {
			parse_str( $_SERVER['QUERY_STRING'], $params );

			return $params;
		}

		/**
		 * Check if we need to do anything related to this notice
		 * @since  1.4.0
		 * @access public
		 *
		 */
		public function check() {

			// Check if we need to dismiss the notice
			if ( isset( $_GET['rp4wp_hide_is_installing'] ) ) {
				delete_option( RP4WP_Constants::OPTION_IS_INSTALLING );
			}

			// Check if we are currently installing
			if ( false != get_option( RP4WP_Constants::OPTION_IS_INSTALLING, false ) && ( ! isset( $_GET['page'] ) || 'rp4wp_install' != $_GET['page'] ) ) {
				$this->display();
			}

		}

		/**
		 * Display the admin notice
		 *
		 * @since  1.4.0
		 * @access private
		 *
		 */
		private function display() {
			add_action( 'admin_notices', array( $this, 'content' ) );
		}

		/**
		 * The admin notice content
		 *
		 * @since  1.4.0
		 * @access public
		 */
		public function content() {
			$query_params         = $this->get_admin_query_string_array();
			$install_query_string = '?' . http_build_query( array_merge( $query_params, array( 'page' => 'rp4wp_install' ) ) );
			$dismiss_query_string = '?' . http_build_query( array_merge( $query_params, array( 'rp4wp_hide_is_installing' => 1 ) ) );

			echo '<div class="error"><p>';
			printf( __( "Woah! Looks like we weren't able to finish your Related Posts for WordPress installation wizard!" ), '<b>', '</b>' );
			echo "<br /><br />";
			printf( __( "%sResume the installation wizard%s or %sdismiss this notice%s" ), '<a href="' . $install_query_string . '">', '</a>', '<a href="' . $dismiss_query_string . '">', '</a>' );
			echo "</p></div>";
		}

	}

}