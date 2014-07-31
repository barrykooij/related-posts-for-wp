<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class SRP_Filter_Cron_Schedules extends SRP_Filter {
	protected $tag = 'cron_schedules';

	/**
	 * Add custom schedules
	 *
	 * @param array $schedules
	 *
	 * @return array
	 */
	public function run( $schedules ) {

		$schedules['srp_quarter'] = array(
				'interval' => 900,
				'display' => __( 'Every 15 minutes' )
		);

		return $schedules;
	}
}