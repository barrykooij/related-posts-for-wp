<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class RP4WP_Hook_Ajax_Install_Save_Words extends RP4WP_Hook {
	protected $tag = 'wp_ajax_rp4wp_install_save_words';

	public function run() {

		// Get the PPR
		$ppr = isset( $_POST['ppr'] ) ? $_POST['ppr'] : 25;

		// Related Post Manager
		$related_word_manager = new RP4WP_Related_Word_Manager();

		// Save $rel_amount words
		if ( true === $related_word_manager->save_all_words( $ppr ) ) {

			// Check if we're done
			if ( 0 == count( $related_word_manager->get_uncached_post_ids( 1 ) ) ) {
				echo 'done';
			} else {
				echo 'more';
			}

		}

		exit;
	}

}