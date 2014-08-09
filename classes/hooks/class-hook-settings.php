<?php

if ( !defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class RP4WP_Hook_Settings extends RP4WP_Hook {
	protected $tag = 'admin_init';

	/**
	 * Hook callback, add the submenu page
	 *
	 * @since  1.1.0
	 * @access public
	 */
	public function run() {

		// Section
		add_settings_section(
			'rp4wp_setting_section',
			'Automatic post linking',
			array( $this, 'setting_section_intro' ),
			'rp4wp'
		);

		// Field
		add_settings_field(
			'rp4wp_automatic_linking',
			'Automatic post linking',
			array( $this, 'rp4wp_setting_automatic_linking' ),
			'rp4wp',
			'rp4wp_setting_section'
		);

		// Setting
		register_setting( 'reading', 'rp4wp_automatic_linking' );
	}

	public function setting_section_intro() {
		echo '<p>This is the automatic post link section.</p>';
	}

	public function rp4wp_setting_automatic_linking() {
		echo '<input name="rp4wp_automatic_linking" id="rp4wp_automatic_linking" type="checkbox" value="1" class="code" ' . checked( 1, get_option( 'rp4wp_automatic_linking' ), false ) . ' /> Enabling this will automatically link posts to new posts.';
	}

}