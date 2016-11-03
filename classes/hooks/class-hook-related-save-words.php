<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class RP4WP_Hook_Related_Save_Words extends RP4WP_Hook {
	protected $tag = 'transition_post_status';
	protected $args = 3;

	public function run( $new_status, $old_status, $post ) {

		// verify this is not an auto save routine.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Only count on post type 'post'
		if ( ! in_array( $post->post_type, RP4WP_Related_Post_Manager::get_supported_post_types() ) ) {
			return;
		}

		// Post status must be publish
		if ( 'publish' != $new_status ) {
			return;
		}

		// Save Words
		$related_word_manager = new RP4WP_Related_Word_Manager();
		$related_word_manager->save_words_of_post( $post->ID );

	}
}