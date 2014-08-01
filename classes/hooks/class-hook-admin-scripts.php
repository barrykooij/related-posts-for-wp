<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class SRP_Hook_Admin_Scripts extends SRP_Hook {
	protected $tag = 'admin_enqueue_scripts';

	public function run() {
		global $pagenow;

		// Post screen
		if ( $pagenow == 'post.php' || $pagenow == 'post-new.php' ) {

			// Load PL JS
			wp_enqueue_script(
				'srp_edit_post_js',
				plugins_url( '/assets/js/edit-post.js', Simple_Related_Posts::get_plugin_file() ),
				array( 'jquery', 'jquery-ui-sortable' )
			);

			// Make PL JavaScript strings translatable
			// @todo add JS translation
			//wp_localize_script( 'srp_edit_post_js', 'srp_js', SRP_Javascript_Strings::get() );

			// CSS
			wp_enqueue_style(
				'srp_edit_post_css',
				plugins_url( '/assets/css/edit-post.css', Simple_Related_Posts::get_plugin_file() )
			);
		}

	}
}