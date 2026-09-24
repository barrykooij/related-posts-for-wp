<?php
/**
 * Related Posts for WordPress
 *
 * @package   RelatedPostsForWP
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3 or higher
 *
 * @wordpress-plugin
 * Plugin Name:       Related Posts for WordPress
 * Plugin URI:        http://www.relatedpostsforwp.com/
 * Description:       Related Posts for WordPress, the best way to display related posts in WordPress.
 * Version:           2.3.1
 * Author:            Never5
 * Author URI:        http://www.never5.com/
 * Requires at least: 6.6
 * Requires PHP:      8.0
 * License:           GPL v3
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       related-posts-for-wp
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/*
 * Keep this file parseable by PHP 7.0: a site that gets this version through FTP or WP-CLI on an old PHP version must
 * see the requirements notice instead of a fatal error. WordPress itself blocks updating and activating on old versions.
 */

/**
 * Whether this site meets the requirements of this version of the plugin.
 *
 * @return bool
 */
function rp4wp_meets_requirements() {
	return version_compare( PHP_VERSION, '8.0', '>=' ) && version_compare( get_bloginfo( 'version' ), '6.6', '>=' );
}

/**
 * Tell admins why the plugin does not run.
 *
 * @return void
 */
function rp4wp_requirements_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	echo '<div class="notice notice-error"><p>';
	printf(
		/* translators: 1: required PHP version, 2: required WordPress version */
		esc_html__( 'Related Posts for WordPress needs PHP %1$s and WordPress %2$s or newer. It does not run on this site until they are updated.', 'related-posts-for-wp' ),
		'8.0',
		'6.6'
	);
	echo '</p></div>';
}

/**
 * Boot the plugin on plugins_loaded. Kept as a named callback, so it can still be unhooked with remove_action().
 *
 * @return void
 */
function rp4wp_load_plugin() {
	if ( ! rp4wp_meets_requirements() ) {
		add_action( 'admin_notices', 'rp4wp_requirements_notice' );

		return;
	}

	// The premium plugin up to 2.x is a full copy of this plugin and boots first, defining this constant. Stay active but
	// dormant next to it instead of deactivating like 2.x did, so premium 3.x can build on this plugin later.
	if ( defined( 'RP4WP_PLUGIN_FILE' ) ) {
		return;
	}

	define( 'RP4WP_PLUGIN_FILE', __FILE__ );

	require __DIR__ . '/vendor/autoload.php';
	require __DIR__ . '/includes/functions.php';

	\LV2\WordPress\RelatedPostsForWP\Main::get()->setup();
}

add_action( 'plugins_loaded', 'rp4wp_load_plugin', 20 );

// The activation hook is only registered on normal admin requests, like 2.x (see known issue K3).
if ( is_admin() && ! is_multisite() && ! wp_doing_ajax() ) {
	require_once __DIR__ . '/includes/installer-functions.php';

	register_activation_hook( __FILE__, 'rp4wp_activate_plugin' );
}
