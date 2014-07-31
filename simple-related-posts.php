<?php

/*
	Plugin Name: Simple Related Posts
	Plugin URI: http://www.barrykooij.com/
	Description: Simple Related Posts description
	Version: 1.0 
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

class Simple_Related_Posts {

	/**
	 * Get the plugin file
	 *
	 * @access public
	 * @static
	 * @return String
	 */
	public static function get_plugin_file() {
		return __FILE__;
	}

	/**
	 * A static method that will setup the autoloader
	 */
	private static function setup_autoloader() {
		require_once( plugin_dir_path( self::get_plugin_file() ) . '/classes/class-autoloader.php' );
		$autoloader = new SRP_Autoloader( plugin_dir_path( self::get_plugin_file() ) . 'classes/' );
		spl_autoload_register( array( $autoloader, 'load' ) );
	}

	/**
	 * This method runs on plugin activation
	 */
	public static function activation() {

		// Setup autoloader
		self::setup_autoloader();

		// Run the installer
		$installer = new SRP_Installer();
		$installer->install();

		// Redirect to installation wizard
		add_site_option( SRP_Constants::OPTION_DO_INSTALL, true );


		/*
		// Load Cron Schedules Filter
		$manager_filter = new SP_Manager_Filter( self::get_premium_dir() . 'classes/filters/' );
		$manager_filter->load_filter( 'class-filter-cron-schedules' );

		// Setup Cron
		$cron_manager = new SRP_Cron_Manager();
		$cron_manager->setup_cron();
		*/
	}

	/**
	 * This method runs on deactivation
	 */
	public static function deactivation() {

		// Setup autoloader
		self::setup_autoloader();

		// Clear cronjobs
		$cron_manager = new SRP_Cron_Manager();
		$cron_manager->remove_cron();

	}

	/**
	 * The constructor
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Initialize the plugin
	 */
	private function init() {

		// Setup the autolaoder
		self::setup_autoloader();

		// Check if we need to run the installer
		if ( get_site_option( SRP_Constants::OPTION_DO_INSTALL, false ) ) {

			// Delete site option
			delete_site_option( SRP_Constants::OPTION_DO_INSTALL );

			// Redirect to installation wizard
			wp_redirect( admin_url() . '?page=srp_install', 301 );
			exit;
		}

		// Filters
		$manager_filter = new SRP_Manager_Filter( plugin_dir_path( __FILE__ ) . 'classes/filters/' );
		$manager_filter->load_filters();

		// Hooks
		$manager_hook = new SRP_Manager_Hook( plugin_dir_path( __FILE__ ) . 'classes/hooks/' );
		$manager_hook->load_hooks();
	}

}

function __simple_related_posts_main() {
	new Simple_Related_Posts();
}

// Create object - Plugin init
add_action( 'plugins_loaded', '__simple_related_posts_main' );

// Activation hook
register_activation_hook( __FILE__, array( 'Simple_Related_Posts', 'activation' ) );

// Deactivation hook
register_deactivation_hook( __FILE__, array( 'Simple_Related_Posts', 'deactivation' ) );