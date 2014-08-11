<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class RP4WP_Hook_Related_Save_Words extends RP4WP_Hook {
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

		// Post status must be publish
		if ( 'publish' != $post->post_status ) {
			return;
		}

		// Save Words
		$related_word_manager = new RP4WP_Related_Word_Manager();
		$related_word_manager->save_words_of_post( $post_id );

	}
}