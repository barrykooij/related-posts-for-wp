<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class RP4WP {

	private static $instance = null;

	const VERSION = '2.3.1';

	/**
	 * @var RP4WP_Settings
	 */
	public $settings = null;

	/**
	 * Singleton get method
	 *
	 * @return RP4WP
	 * @since  1.0.0
	 * @access public
	 *
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

		// Main handles WordPress Playground, multisite and the text domain; nothing below runs where Main does not.
		if ( ! \LV2\WordPress\RelatedPostsForWP\Main::get()->should_run() ) {
			return;
		}

		// Setup settings
		add_action( 'init', array( $this, 'setup_settings' ) );

		// Filters
		$filters        = include dirname( RP4WP_PLUGIN_FILE ) . '/includes/filters.php';
		$manager_filter = new RP4WP_Manager_Filter( $filters );
		$manager_filter->load_filters();

		// Hooks
		$actions      = include dirname( RP4WP_PLUGIN_FILE ) . '/includes/actions.php';
		$manager_hook = new RP4WP_Manager_Hook( $actions );
		$manager_hook->load_hooks();

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
