<?php

if ( !defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class SRP_Hook_Meta_Box_AJAX_Sort extends SRP_Hook {
	protected $tag = 'wp_ajax_srp_related_sort';

	public function run() {
		global $wpdb;

		// Check nonce
		check_ajax_referer( 'srp-ajax-nonce-omgrandomword', 'nonce' );

		// Check if user is allowed to do this
		if ( !current_user_can( 'edit_posts' ) ) {
			return;
		}

		// Check if the items are set
		if ( !isset( $_POST['srp_items'] ) ) {
			return;
		}

		// Boom
		$items = explode( ',', $_POST['srp_items'] );

		// Check if there are items posted
		if ( count( $items ) == 0 ) {
			return;
		}

		// Change order
		$counter = 0;
		foreach ( $items as $item_id ) {
			$wpdb->update( $wpdb->posts, array( 'menu_order' => $counter ), array( 'ID' => $item_id ) );
			$counter ++;
		}

		// Generate JSON response
		$response = json_encode( array( 'success' => true ) );
		header( 'Content-Type: application/json' );
		echo $response;

		// Bye
		exit();
	}

}