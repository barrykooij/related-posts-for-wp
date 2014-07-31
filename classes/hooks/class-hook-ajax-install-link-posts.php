<?php

if ( !defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class SRP_Hook_Ajax_Install_Link_Posts extends SRP_Hook {
	protected $tag = 'wp_ajax_srp_install_link_posts';

	public function run() {

		// Get the rel amount
		$rel_amount = isset( $_POST['rel_amount'] ) ? $_POST['rel_amount'] : 5;

		// Related Post Manager object
		$related_post_manager = new SRP_Related_Post_Manager();

		// Link 200 posts
		if ( true === $related_post_manager->link_related_posts( $rel_amount, 200 ) ) {

			// Check if we're done
			if ( 0 == count( $related_post_manager->get_not_auto_linked_posts( 1 ) ) ) {
				echo 'done';
			} else {
				echo 'more';
			}

		}

		exit;
	}

}