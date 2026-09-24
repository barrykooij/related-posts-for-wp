<?php
/**
 * Bootstrap for the unit suite. No WordPress runtime: WordPress functions are mocked with Brain Monkey.
 *
 * @package RelatedPostsForWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! defined( 'RP4WP_PLUGIN_FILE' ) ) {
	define( 'RP4WP_PLUGIN_FILE', dirname( __DIR__ ) . '/related-posts-for-wp.php' );
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
