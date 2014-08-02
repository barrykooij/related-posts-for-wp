<?php

if ( !defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class RP4WP_Hook_Ajax_Install_Save_Words extends RP4WP_Hook {
	protected $tag = 'wp_ajax_rp4wp_install_save_words';

	public function run() {

		// Related Post Manager
		$related_word_manager = new RP4WP_Related_Word_Manager();

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