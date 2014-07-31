<?php

if ( !defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class SRP_Cron_Manager {

	const CRON_HOOK = 'srp_count_words';

	/**
	 * Remove the cron
	 *
	 */
	public function remove_cron() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Setup the cron
	 */
	public function setup_cron() {
		if ( !wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'srp_quarter', self::CRON_HOOK );
		}
	}

}