<?php
/**
 * WordPress test suite configuration for the integration tests.
 *
 * The config that wp-env ships shares the site's database and `wp_` table prefix, and the test suite drops every table
 * with its prefix on install. That would wipe the site the E2E tests run against, so this config uses its own prefix.
 *
 * @package RelatedPostsForWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', getenv( 'WP_ABSPATH' ) ? getenv( 'WP_ABSPATH' ) : '/var/www/html/' );
}

define( 'DB_NAME', getenv( 'WORDPRESS_DB_NAME' ) ? getenv( 'WORDPRESS_DB_NAME' ) : 'wordpress' );
define( 'DB_USER', getenv( 'WORDPRESS_DB_USER' ) ? getenv( 'WORDPRESS_DB_USER' ) : 'root' );
define( 'DB_PASSWORD', getenv( 'WORDPRESS_DB_PASSWORD' ) ? getenv( 'WORDPRESS_DB_PASSWORD' ) : 'password' );
define( 'DB_HOST', getenv( 'WORDPRESS_DB_HOST' ) ? getenv( 'WORDPRESS_DB_HOST' ) : 'mysql' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wptests_';

define( 'WP_DEFAULT_THEME', 'default' );
define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );
define( 'WP_DEBUG', true );
