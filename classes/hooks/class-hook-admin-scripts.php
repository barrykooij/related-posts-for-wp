<?php

if ( !defined( 'ABSPATH' ) ) {
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
				plugins_url( '/assets/js/edit-post' . ( ( !SCRIPT_DEBUG ) ? '.min' : '' ) . '.js', RP4WP::get_plugin_file() ),
				array( 'jquery', 'jquery-ui-sortable' )
			);

			// Make PL JavaScript strings translatable
			// @todo add JS translation
			//wp_localize_script( 'rp4wp_edit_post_js', 'rp4wp_js', RP4WP_Javascript_Strings::get() );

			// CSS
			wp_enqueue_style(
				'rp4wp_edit_post_css',
				plugins_url( '/assets/css/edit-post.css', RP4WP::get_plugin_file() )
			);
		}

	}
}