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
 */
class Controller implements Module {

	/**
	 * Build the settings on init.
	 *
	 * @return void
	 */
	public static function setup(): void {
		add_action( 'init', [ self::class, 'build' ] );
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
