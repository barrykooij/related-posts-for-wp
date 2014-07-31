<?php

if ( !defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class SRP_Hook_Cron_Save_Words extends SRP_Hook {
	protected $tag = 'srp_count_words';

	public function run() {

		// Related Post Manager
		$related_post_manager = new SRP_Related_Words_Manager();

		// If true is returned, te script completed
		if ( true === $related_post_manager->save_all_words() ) {
			// Remove the cron
			$cron_manager = new SRP_Cron_Manager();
			$cron_manager->remove_cron();
		}

	}

}