<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class SRP_Hook_Delete_Words extends SRP_Hook {
	protected $tag = 'delete_post';
	protected $args = 1;

	public function run( $post_id ) {

		// Check if the current user can delete posts
		if ( ! current_user_can( 'delete_posts' ) ) {
			return;
		}

		// Related Post Manager
		$related_word_manager = new SRP_Related_Word_Manager();
		$related_word_manager->delete_words( $post_id );

	}
}