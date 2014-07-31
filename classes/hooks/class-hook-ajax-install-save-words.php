<?php

if ( !defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class SRP_Hook_Ajax_Install_Save_Words extends SRP_Hook {
	protected $tag = 'wp_ajax_srp_install_save_words';

	public function run() {

		// Related Post Manager
		$related_word_manager = new SRP_Related_Words_Manager();

		// Save 200 words
		if ( true === $related_word_manager->save_all_words( 200 ) ) {

			// Check if we're done
			if ( 0 == count( $related_word_manager->get_uncached_posts( 1 ) ) ) {
				echo 'done';
			} else {
				echo 'more';
			}

		}

		exit;
	}

}