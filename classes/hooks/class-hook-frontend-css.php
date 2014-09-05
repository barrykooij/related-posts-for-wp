<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class RP4WP_Hook_Frontend_Css extends RP4WP_Hook {
	protected $tag = 'wp_head';

	public function run() {
		if ( is_single() ) {
			$css = trim( RP4WP::get()->settings->get_option( 'css' ) );
			if ( '' != $css ) {
				echo "<style type='text/css'>" . $css . "</style>" . PHP_EOL;
			}
		}
	}
}