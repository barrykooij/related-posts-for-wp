<?php

if ( !defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class SRP_Hook_Ajax_Install_Save_Words extends SRP_Hook {
	protected $tag = 'wp_ajax_srp_install_save_words';

	public function run() {
		$req_nr = isset( $_POST['req_nr'] ) ? $_POST['req_nr'] : 0;
		$offset = 200 * $req_nr;

		if ( 1 == mt_rand( 0, 1 ) ) {
			echo 'next';
		} else {
			echo 'done';
		}

		exit;
	}

}