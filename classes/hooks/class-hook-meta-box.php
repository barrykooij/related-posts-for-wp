<?php

if ( !defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class SRP_Hook_Meta_Box extends SRP_Hook {
	protected $tag = 'admin_init';

	public function run() {
		new SRP_Meta_Box_Manage();
	}
}