<?php
/**
 * The deprecated RP4WP_Settings class file.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Admin\Settings\Fields;
use LV2\WordPress\RelatedPostsForWP\Admin\Settings\Page;
use LV2\WordPress\RelatedPostsForWP\Compat\Deprecation;
use LV2\WordPress\RelatedPostsForWP\Main;
use LV2\WordPress\RelatedPostsForWP\Settings\Settings;

/**
 * The 2.x settings.
 *
 * @deprecated 3.0.0 Use \LV2\WordPress\RelatedPostsForWP\Settings\Settings, from Main::get()->settings().
 */
class RP4WP_Settings {

	/**
	 * The prefix of the filters that override one setting.
	 */
	public const PREFIX = 'rp4wp_';

	/**
	 * The settings page, which is also the option.
	 */
	public const PAGE = Page::SLUG;

	/**
	 * Constructor.
	 */
	public function __construct() {
		Deprecation::class_used( __CLASS__, Settings::class );
	}

	/**
	 * Register the settings with the Settings API.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return void
	 */
	public function setup() {
		Deprecation::method( __METHOD__, Fields::class . '::register()' );

		Fields::register();
	}

	/**
	 * The description below a section title.
	 *
	 * @deprecated 3.0.0
	 *
	 * @param array $section The section.
	 *
	 * @return void
	 */
	public function section_intro( $section ) {
		Deprecation::method( __METHOD__, Fields::class . '::section_intro()' );

		Fields::section_intro( (array) $section );
	}

	/**
	 * A settings field.
	 *
	 * @deprecated 3.0.0
	 *
	 * @param array $field The field.
	 *
	 * @return void
	 */
	public function do_field( $field ) {
		Deprecation::method( __METHOD__, Fields::class . '::render_field()' );

		Fields::render_field( (array) $field );
	}

	/**
	 * Sanitize the settings posted from the settings page.
	 *
	 * @deprecated 3.0.0
	 *
	 * @param array $post_data The posted settings.
	 *
	 * @return array
	 */
	public function sanitize_option( $post_data ) {
		Deprecation::method( __METHOD__, Settings::class . '::sanitize()' );

		return Main::get()->settings()->sanitize( $post_data );
	}

	/**
	 * All settings.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return mixed
	 */
	public function get_options() {
		Deprecation::method( __METHOD__, Settings::class . '::get_options()' );

		return Main::get()->settings()->get_options();
	}

	/**
	 * One setting.
	 *
	 * @deprecated 3.0.0
	 *
	 * @param string $option The setting.
	 *
	 * @return mixed
	 */
	public function get_option( $option ) {
		Deprecation::method( __METHOD__, Settings::class . '::get()' );

		return Main::get()->settings()->get( (string) $option );
	}
}
