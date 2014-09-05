<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class RP4WP_Hook_Widget extends RP4WP_Hook {
	protected $tag = 'widgets_init';

	public function run() {
		register_widget( 'RP4WP_Related_Posts_Widget' );
	}
}