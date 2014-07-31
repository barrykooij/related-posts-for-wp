<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class SRP_Hook_Related_Save_Words extends SRP_Hook {
	protected $tag = 'save_post';
	protected $args = 2;

	public function run( $post_id, $post ) {

		// verify this is not an auto save routine.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Only count on post type 'post'
		if( 'post' != $post->post_type) {
			return;
		}

		// Check permission
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save Words
		$related_words_manager = new SRP_Related_Words_Manager();
		$related_words_manager->save_words_of_post( $post_id );

	}
}