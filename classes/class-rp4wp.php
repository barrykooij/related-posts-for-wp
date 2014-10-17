<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class RP4WP {

	private static $instance = null;

	const VERSION = '1.7.1';

	/**
	 * @var RP4WP_Settings
	 */
	public $settings = null;

	/**
	 * Singleton get method
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return RP4WP
	 */
	public static function get() {
		if ( null == self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Get the plugin file
	 *
	 * @access public
	 * @static
	 * @return String
	 */
	public static function get_plugin_file() {
		return RP4WP_PLUGIN_FILE;
	}

	/**
	 * A static method that will setup the autoloader
	 */
	private static function setup_autoloader() {
		require_once( plugin_dir_path( self::get_plugin_file() ) . '/classes/class-autoloader.php' );
		$autoloader = new RP4WP_Autoloader( plugin_dir_path( self::get_plugin_file() ) . 'classes/' );
		spl_autoload_register( array( $autoloader, 'load' ) );
	}

	/**
	 * The constructor
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Initialize the plugin
	 */
	private function init() {

		// Setup the autoloader
		self::setup_autoloader();

		// Load plugin text domain
		load_plugin_textdomain( 'related-posts-for-wp', false, dirname( plugin_basename( RP4WP_PLUGIN_FILE ) ) . '/languages/' );

		// Check if we need to run the installer
		if ( is_admin() && get_site_option( RP4WP_Constants::OPTION_DO_INSTALL, false ) ) {

			// Delete do install site option
			delete_site_option( RP4WP_Constants::OPTION_DO_INSTALL );

			// Redirect to installation wizard
			wp_redirect( admin_url() . '?page=rp4wp_install', 307 );
			exit;
		}

		if ( is_admin() ) {
			// Check if we need to display an 'is installing' notice
			$is_installing_notice = new RP4WP_Is_Installing_Notice();
			$is_installing_notice->check();
		}

		// Setup settings
		add_action( 'init', array( $this, 'setup_settings' ) );

		// Filters
		$manager_filter = new RP4WP_Manager_Filter( plugin_dir_path( RP4WP_PLUGIN_FILE ) . 'classes/filters/' );
		$manager_filter->load_filters();

		// Hooks
		$manager_hook = new RP4WP_Manager_Hook( plugin_dir_path( RP4WP_PLUGIN_FILE ) . 'classes/hooks/' );
		$manager_hook->load_hooks();

		// Include template functions
		if ( ! is_admin() ) {
			require_once( plugin_dir_path( self::get_plugin_file() ) . '/includes/template-functions.php' );
		}

		// Setup the nag
		if ( is_admin() ) {
			$nag_manager = new RP4WP_Nag_Manager();
			$nag_manager->setup();
		}

	}

	/**
	 * Setup the settings
	 *
	 * @since  1.6.2
	 * @access public
	 */
	public function setup_settings() {
		$this->settings = new RP4WP_Settings();
	}

}

/**
 * @since  1.0.0
 * @access public
 *
 * @return RP4WP
 */
function RP4WP() {
	return RP4WP::get();
}