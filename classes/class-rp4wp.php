<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class RP4WP {

	private static $instance = null;

	const VERSION = '1.9.0';

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
	 * The constructor
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Initialize the plugin
	 */
	private function init() {

		// Load plugin text domain
		load_plugin_textdomain( 'related-posts-for-wp', false, dirname( plugin_basename( RP4WP_PLUGIN_FILE ) ) . '/languages/' );

		// Check for multisite, we don't support that
		if ( is_multisite() && ( is_admin() || is_network_admin() ) ) {
			add_action( 'admin_notices', array( 'RP4WP_Multisite_Notice', 'display' ) );
			add_action( 'network_admin_notices', array( 'RP4WP_Multisite_Notice', 'display' ) );
			return;
		}

		// Check if we need to run the installer
		if ( is_admin() && get_option( RP4WP_Constants::OPTION_DO_INSTALL, false ) ) {

			// Delete do install site option
			delete_option( RP4WP_Constants::OPTION_DO_INSTALL );

			// Redirect to installation wizard
			wp_redirect( admin_url() . '?page=rp4wp_install&rp4wp_nonce=' . wp_create_nonce( RP4WP_Constants::NONCE_INSTALL ), 307 );
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
		$filters = include dirname( RP4WP_PLUGIN_FILE ) .'/includes/filters.php';
		$manager_filter = new RP4WP_Manager_Filter( $filters );
		$manager_filter->load_filters();

		// Hooks
		$actions = include dirname( RP4WP_PLUGIN_FILE ) .'/includes/actions.php';
		$manager_hook = new RP4WP_Manager_Hook( $actions );
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