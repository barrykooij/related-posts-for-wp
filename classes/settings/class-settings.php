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

	private $sections;
	private $defaults;

	/**
	 * Constructor
	 *
	 * Builds the settings on init, like 2.x, so rp4wp_settings_sections fires at the same moment.
	 */
	public function __construct() {
		$settings       = \LV2\WordPress\RelatedPostsForWP\Main::get()->settings();
		$this->sections = $settings->sections();
		$this->defaults = $settings->defaults();

		// Setup settings
		add_action( 'admin_init', array( $this, 'setup' ) );
	}

	/**
	 * Setup the settings
	 *
	 * @since  1.1.0
	 * @access public
	 */
	public function setup() {
		if ( count( $this->sections ) > 0 ) {
			foreach ( $this->sections as $section ) {

				// Add the section
				add_settings_section(
					$section['id'],
					$section['label'],
					array( $this, 'section_intro' ),
					self::PAGE
				);

				// Check & Loop
				if ( count( $section['fields'] ) > 0 ) {
					foreach ( $section['fields'] as $field ) {

						// Add section
						add_settings_field(
							$field['id'],
							$field['label'],
							array( $this, 'do_field' ),
							self::PAGE,
							$section['id'],
							$field
						);

					}
				}

			}

			// Register section setting
			register_setting( self::PAGE, self::PAGE, array( $this, 'sanitize_option' ) );
		}
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
		echo '<p>' . $this->sections[$section['id']]['description'] . '</p>' . PHP_EOL;
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

		// For now we just do a simple switch here, make this more OOP in future version
		switch ( $field['type'] ) {
			case 'checkbox':
				echo '<input type="checkbox" name="' . self::PAGE . '[' . $field['id'] . ']' . '" id="' . $field['id'] . '" value="1" ' . checked( 1, $this->get_option( $field['id'] ), false ) . ' />';
				break;
			case 'text':
				echo '<input type="text" name="' . self::PAGE . '[' . $field['id'] . ']' . '" id="' . $field['id'] . '" value="' . esc_attr( $this->get_option( $field['id'] ) ) . '" class="rp4wp-input-text" />';
				break;
			case 'textarea':
				echo '<textarea name="' . self::PAGE . '[' . $field['id'] . ']' . '" id="' . $field['id'] . '">' . esc_html( $this->get_option( $field['id'] ) ) . '</textarea>';
				break;
			case 'button_link':
				echo '<a href="' . esc_attr( $field['href'] ) . '" class="button">' . esc_html( $field['default'] ) . '</a>';
				break;
		}

		// Description
		if ( isset( $field['description'] ) && '' != $field['description'] ) {
			echo '<label class="rp4wp-description" for="' . $field['id'] . '">' . $field['description'] . '</label>';
		}

		// Check if this option is being filtered
		if ( has_filter( 'rp4wp_' . $field['id'] ) ) {
			echo '<small>This option is overwritten by a filter.</small>';
		}

		// End of line
		echo PHP_EOL;

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
