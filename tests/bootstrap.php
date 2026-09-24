<?php
/**
 * Bootstrap for the unit suite. No WordPress runtime: WordPress functions are mocked with Brain Monkey.
 *
 * @package RelatedPostsForWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
