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

		// Save words
		$related_word_manager->save_all_words( $ppr );

		// Get uncached post count
		$uncached_post_count  = $related_word_manager->get_uncached_post_count();

		// Echo the uncached posts
		echo $uncached_post_count;

		exit;
	}

}