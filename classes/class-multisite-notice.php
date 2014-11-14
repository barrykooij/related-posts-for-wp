<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class RP4WP_Multisite_Notice {
	public static function display() {
		echo '<div class="error"><p>';
		printf( __( "The free version of Related Posts for WordPress doesn't support WordPress Multisite/Network!" ), '<b>', '</b>' );
		echo "<br /><br />";
		printf( __( "%sBuy the premium version%s or disable the plugin." ), '<a href="https://www.relatedpostsforwp.com/upgrade-premium/?utm_source=plugin&utm_medium=link&utm_campaign=multisite-notice" target="_blank">', '</a>' );
		echo "</p></div>";
	}
}