<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * Class RP4WP_Settings
 *
 * @todo Make class for each input type with own sanitize method.
 */
class RP4WP_Settings {

	const PREFIX = 'rp4wp_';

	const PAGE = 'rp4wp';

	/**
	 * Constructor
	 *
	 * Builds the settings on init, like 2.x, so rp4wp_settings_sections fires at the same moment. The settings page
	 * module registers them with the Settings API on admin_init.
	 */
	public function __construct() {
		\LV2\WordPress\RelatedPostsForWP\Main::get()->settings()->sections();
	}

	/**
	 * Setup the settings
	 *
	 * @since  1.1.0
	 * @access public
	 */
	public function setup() {
		\LV2\WordPress\RelatedPostsForWP\Admin\Settings\Fields::register();
	}

	/**
	 * Method that is called when adding a section
	 *
	 * @param $section
	 *
	 * @since  1.1.0
	 * @access public
	 */
	public function section_intro( $section ) {
		\LV2\WordPress\RelatedPostsForWP\Admin\Settings\Fields::section_intro( $section );
	}

	/**
	 * Method that outputs the correct field
	 *
	 * @param $field
	 *
	 * @since  1.1.0
	 * @access public
	 */
	public function do_field( $field ) {
		\LV2\WordPress\RelatedPostsForWP\Admin\Settings\Fields::render_field( $field );
	}

	/**
	 * Sanitize the option value
	 *
	 * @param array $post_data
	 *
	 * @since  1.1.0
	 * @access public
	 *
	 * @return array
	 */
	public function sanitize_option( $post_data ) {
		return \LV2\WordPress\RelatedPostsForWP\Main::get()->settings()->sanitize( $post_data );
	}

	/**
	 * Get the plugin options
	 *
	 * @since  1.1.0
	 * @access public
	 *
	 * @return mixed|void
	 */
	public function get_options() {
		return \LV2\WordPress\RelatedPostsForWP\Main::get()->settings()->get_options();
	}

	/**
	 * Return a single option
	 *
	 * @param $option
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return mixed|bool
	 */
	public function get_option( $option ) {
		return \LV2\WordPress\RelatedPostsForWP\Main::get()->settings()->get( (string) $option );
	}

}
