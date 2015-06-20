<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class RP4WP_Filter_Set_Screen_Option extends RP4WP_Filter {
	protected $tag = 'set-screen-option';
	protected $args = 3;

	/**
	 * Save custom screen options
	 *
	 * @param $status
	 * @param $option
	 * @param $value
	 *
	 * @return string
	 */
	public function run( $status, $option, $value ) {
		if ( 'rp4wp_per_page' == $option ) {
			return $value;
		}

		return $status;
	}
}