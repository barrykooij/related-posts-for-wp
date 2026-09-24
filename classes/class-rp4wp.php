<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * The 2.x plugin class.
 *
 * The plugin boots through LV2\WordPress\RelatedPostsForWP\Main now. This class only keeps RP4WP()->settings and the
 * plugin file available for code written for 2.x, and is created when such code first asks for it.
 */
class RP4WP {

	private static $instance = null;

	const VERSION = \LV2\WordPress\RelatedPostsForWP\Main::VERSION;

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
		return \LV2\WordPress\RelatedPostsForWP\Main::file();
	}

	/**
	 * The constructor
	 *
	 * Like 2.x, the settings object exists from init on.
	 */
	private function __construct() {
		if ( did_action( 'init' ) ) {
			$this->setup_settings();
		} else {
			add_action( 'init', array( $this, 'setup_settings' ) );
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
