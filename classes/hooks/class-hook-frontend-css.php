<?php

if ( !defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class SRP_Hook_Frontend_Css extends SRP_Hook {
	protected $tag = 'wp_head';

	public function run() {
		echo "<style type='text/css'>.srp-related-posts ul {padding:0;margin:0;}.srp-related-posts li{list-style:none;}.srp-related-post-image{width:35%;padding-right:25px;-moz-box-sizing: border-box;-webkit-box-sizing: border-box;box-sizing: border-box;float:left;}.srp-related-post-content{width: 65%;-moz-box-sizing: border-box;-webkit-box-sizing: border-box;box-sizing: border-box;float:left;}</style>" . PHP_EOL;

	}
}