<?php

if ( !defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class SRP_Hook_Ajax_Delete_Link extends SRP_Hook {
	protected $tag = 'wp_ajax_srp_delete_link';

	/**
	 * Hook into admin AJAX to delete a link
	 *
	 * @access public
	 * @return void
	 */
	public function run() {

		// id,
		if ( !isset( $_POST['id'] ) ) {
			exit;
		}

		// Post id into $post_id
		$post_id = $_POST['id'];

		// Check nonce
		check_ajax_referer( 'srp-ajax-nonce-omgrandomword', 'nonce' );

		// Check if user is allowed to do this
		if ( !current_user_can( 'edit_posts' ) ) {
			return;
		}

		//  Load post
		$target_post = get_post( $post_id );

		// Only delete post type we control
		if ( $target_post->post_type != SRP_Constants::LINK_PT ) {
			return;
		}

		// Delete link
		$post_link_manager = new SRP_Post_Link_Manager();
		$post_link_manager->delete( $target_post->ID );

		// Generate JSON response
		$response = json_encode( array( 'success' => true ) );
		header( 'Content-Type: application/json' );
		echo $response;

		// Bye
		exit();
	}

}