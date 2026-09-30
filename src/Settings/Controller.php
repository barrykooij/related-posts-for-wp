<?php
/**
 * The settings controller class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Settings;

use LV2\WordPress\RelatedPostsForWP\Main;
use LV2\WordPress\RelatedPostsForWP\Module;

/**
 * Builds the settings on init, like 2.x, so `rp4wp_settings_sections` runs at the same moment for code that filters
 * it, and after the current user is known for the nonce in the "Rebuild" link.
 *
 * In REST requests, which have no admin_init, it also registers the option, so saving it through the REST API runs
 * the same sanitize callback as the settings screen.
 */
class Controller implements Module {

	/**
	 * Build the settings on init.
	 *
	 * @return void
	 */
	public static function setup(): void {
		add_action( 'init', [ self::class, 'build' ] );
		add_action( 'rest_api_init', [ self::class, 'register_option' ] );
	}

	/**
	 * Register the option with its sanitize callback.
	 *
	 * @return void
	 */
	public static function register_option(): void {
		// Registered already, with its sanitize callback, when the settings screen registered it.
		if ( false !== has_filter( 'sanitize_option_' . Settings::OPTION ) ) {
			return;
		}

		// A group of its own: the `rp4wp` group is what options.php lets the settings screen save.
		register_setting( 'rp4wp_rest', Settings::OPTION, [ 'sanitize_callback' => [ Main::get()->settings(), 'sanitize' ] ] );
	}

	/**
	 * Build the setting sections and their defaults.
	 *
	 * @return void
	 */
	public static function build(): void {
		Main::get()->settings()->sections();
	}
}
