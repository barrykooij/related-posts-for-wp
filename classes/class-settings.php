<?php

class RP4WP_Settings {

	const PREFIX = 'rp4wp_';

	const PAGE = 'rp4wp';

	private $sections;
	private $defaults;

	/**
	 * Constructor
	 */
	public function __construct() {

		// The fields
		$this->sections = array(
			self::PREFIX . 'automatic_linking' => array(
				'id'          => 'automatic_linking',
				'label'       => __( 'Automatic post linking', 'related-posts-for-wp' ),
				'description' => __( 'This is the automatic post link section.', 'related-posts-for-wp' ),
				'fields'      => array(
					array(
						'id'          => 'automatic_linking',
						'label'       => __( 'Enable', 'related-posts-for-wp' ),
						'description' => __( 'Checking this will enable automatically linking posts to new posts', 'related-posts-for-wp' ),
						'type'        => 'checkbox',
						'default'     => 1,
					),
					array(
						'id'          => 'automatic_linking_post_amount',
						'label'       => __( 'Amount of Posts', 'related-posts-for-wp' ),
						'description' => __( 'The amount of automatically linked post', 'related-posts-for-wp' ),
						'type'        => 'text',
						'default'     => '',
					)
				) )
		);

		// Set defaults
		foreach ( $this->sections as $section ) {
			foreach ( $section['fields'] as $field ) {
				$this->defaults[$field['id']] = $field['default'];
			}
		}

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
					self::PREFIX . $section['id'],
					$section['label'],
					array( $this, 'section_intro' ),
					self::PAGE
				);

				// Check & Loop
				if ( count( $section['fields'] ) > 0 ) {
					foreach ( $section['fields'] as $field ) {

						// Add section
						add_settings_field(
							self::PREFIX . $field['id'],
							$field['label'],
							array( $this, 'do_field' ),
							self::PAGE,
							self::PREFIX . $section['id'],
							$field
						);

					}
				}

			}


			// Register section setting
			register_setting( self::PAGE, self::PAGE );
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
	 * @since  1.0.0
	 * @access public
	 */
	public function do_field( $field ) {

		// For now we just do a simple switch here, make this more OOP in future version
		switch ( $field['type'] ) {
			case 'checkbox':
				echo '<input type="checkbox" name="' . self::PAGE . '[' . $field['id'] . ']' . '" id="' . $field['id'] . '" value="1" ' . checked( 1, $this->get_option( $field['id'] ), false ) . ' />';
				break;
			case 'text':
				echo '<input type="text" name="' . self::PAGE . '[' . $field['id'] . ']' . '" id="' . $field['id'] . '" value="' . $this->get_option( $field['id'] ) . '" />';
				break;
		}

		// Description
		if ( isset( $field['description'] ) && '' != $field['description'] ) {
			echo '<label class="rp4wp-description" for="' . $field['id'] . '">' . $field['description'] . '</label>';
		}

		// End of line
		echo PHP_EOL;

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
		return apply_filters( 'rp4wp_options', wp_parse_args( get_option( self::PAGE, array() ), $this->defaults ) );
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
		$options = $this->get_options();

		return isset( $options[$option] ) ? $options[$option] : false;
	}

}