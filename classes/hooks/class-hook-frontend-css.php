<?php

if ( !defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class RP4WP_Hook_Frontend_Css extends RP4WP_Hook {
	protected $tag = 'wp_head';

	public function run() {
		echo "<style type='text/css'>.rp4wp-related-posts ul {padding:0;margin:0;float:left;}.rp4wp-related-posts li{list-style:none;}.rp4wp-related-post-image{width:35%;padding-right:25px;-moz-box-sizing: border-box;-webkit-box-sizing: border-box;box-sizing: border-box;float:left;}.rp4wp-related-post-content{width: 65%;-moz-box-sizing: border-box;-webkit-box-sizing: border-box;box-sizing: border-box;float:left;}</style>" . PHP_EOL;

	}
}