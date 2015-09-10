<?php
/*
	Plugin Name: Related Posts for WordPress
	Plugin URI: http://www.relatedpostsforwp.com/
	Description: Related Posts for WordPress, the best way to display related posts in WordPress.
	Version: 1.9.1
	Author: Barry Kooij
	Author URI: http://www.barrykooij.com/
	License: GPL v3
	 
	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.
	 
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
	 
	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/


if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

function rp4wp_load_plugin() {

	if ( defined( 'RP4WP_PLUGIN_FILE' ) ) {
		return false;
	}

	// Define
	define( 'RP4WP_PLUGIN_FILE', __FILE__ );

	require dirname( __FILE__ ) . '/vendor/autoload_52.php';
	require dirname( __FILE__ ) . '/includes/functions.php';

	// Instantiate main plugin object
	RP4WP();
}

// Create object - Plugin init
add_action( 'plugins_loaded', 'rp4wp_load_plugin', 20 );

//
if ( is_admin() && ! is_multisite() && ( false === defined( 'DOING_AJAX' ) || false === DOING_AJAX ) ) {

	// Load installer functions
	require_once plugin_dir_path( __FILE__ ) . 'includes/installer-functions.php';

	// Activation hook
	register_activation_hook( __FILE__, 'rp4wp_activate_plugin' );
}