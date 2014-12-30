<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class RP4WP_Hook_Admin_Scripts extends RP4WP_Hook {
	protected $tag = 'admin_enqueue_scripts';

	public function run() {
		global $pagenow;

		// Post screen
		if ( $pagenow == 'post.php' || $pagenow == 'post-new.php' ) {

			// Load PL JS
			wp_enqueue_script(
				'rp4wp_edit_post_js',
				plugins_url( '/assets/js/edit-post' . ( ( ! SCRIPT_DEBUG ) ? '.min' : '' ) . '.js', RP4WP::get_plugin_file() ),
				array( 'jquery', 'jquery-ui-sortable' ),
				RP4WP::VERSION
			);

			// Make JavaScript strings translatable
			wp_localize_script( 'rp4wp_edit_post_js', 'rp4wp_js', RP4WP_Javascript_Strings::get() );

			// CSS
			wp_enqueue_style(
				'rp4wp_edit_post_css',
				plugins_url( '/assets/css/edit-post.css', RP4WP::get_plugin_file() ),
				array(),
				RP4WP::VERSION
			);
		}

		if ( 'options-general.php' == $pagenow && isset( $_GET['page'] ) && $_GET['page'] === 'rp4wp' ) {

			// Main settings JS
			wp_enqueue_script(
				'rp4wp_settings_js',
				plugins_url( '/assets/js/settings' . ( ( ! SCRIPT_DEBUG ) ? '.min' : '' ) . '.js', RP4WP::get_plugin_file() ),
				array( 'jquery' ),
				RP4WP::VERSION
			);

		}

	}
}