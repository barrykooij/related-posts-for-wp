<?php
/**
 * Constants PHPStan needs to know about. The plugin defines them at runtime.
 *
 * @package RelatedPostsForWP
 */

if ( ! defined( 'RP4WP_FREE_PLUGIN_FILE' ) ) {
	define( 'RP4WP_FREE_PLUGIN_FILE', dirname( __DIR__ ) . '/related-posts-for-wp.php' );
}

if ( ! defined( 'RP4WP_PLUGIN_FILE' ) ) {
	define( 'RP4WP_PLUGIN_FILE', dirname( __DIR__ ) . '/related-posts-for-wp.php' );
}

if ( ! defined( 'WP_TESTS_CONFIG_FILE_PATH' ) ) {
	define( 'WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/wp-tests-config.php' );
}
