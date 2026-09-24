<?php
/**
 * Bootstrap for the integration suite: loads the WordPress test suite and the plugin.
 *
 * Run it inside wp-env with `npm run test:integration`. wp-env provides the WordPress test suite that matches the
 * WordPress version under test in WP_TESTS_DIR; the wp-phpunit Composer package is the fallback outside wp-env.
 *
 * @package RelatedPostsForWP
 */

$rp4wp_root      = dirname( __DIR__ );
$rp4wp_tests_dir = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : $rp4wp_root . '/vendor/wp-phpunit/wp-phpunit';

if ( ! file_exists( $rp4wp_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "The WordPress test suite was not found in {$rp4wp_tests_dir}. Run the integration suite with `npm run test:integration`.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI output before WordPress loads.
	exit( 1 );
}

require_once $rp4wp_root . '/vendor/autoload.php';

// Legacy handlers end with exit(); make sure one reached by a test fails the run instead of ending it quietly.
\LV2\WordPress\RelatedPostsForWP\Tests\Support\ExitGuard::register();

define( 'WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/wp-tests-config.php' );
define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $rp4wp_root . '/vendor/yoast/phpunit-polyfills' );

require_once $rp4wp_tests_dir . '/includes/functions.php';

// Load the plugin like WordPress would, so it boots on plugins_loaded exactly as on a real site.
tests_add_filter(
	'muplugins_loaded',
	static function () use ( $rp4wp_root ) {
		require $rp4wp_root . '/related-posts-for-wp.php';
	}
);

// The activation hook only registers in admin requests, so create the cache table the same way activation does.
tests_add_filter(
	'plugins_loaded',
	static function () use ( $rp4wp_root ) {
		require_once $rp4wp_root . '/includes/installer-functions.php';
		rp4wp_activate_plugin();
		delete_option( 'rp4wp_do_install' );
	},
	30
);

require $rp4wp_tests_dir . '/includes/bootstrap.php';
