<?php

if ( !defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class RP4WP_Hook_Meta_Box extends RP4WP_Hook {
	protected $tag = 'admin_init';

	public function run() {
		new RP4WP_Meta_Box_Manage();
	}
}