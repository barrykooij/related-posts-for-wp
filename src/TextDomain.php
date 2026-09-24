<?php
/**
 * The text domain class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP;

/**
 * Loads the plugin translations.
 */
class TextDomain implements Module {

	/**
	 * The text domain.
	 */
	public const DOMAIN = 'related-posts-for-wp';

	/**
	 * Load the translations on init.
	 *
	 * @return void
	 */
	public static function setup(): void {
		add_action( 'init', [ self::class, 'load' ] );
	}

	/**
	 * Load the translations from the plugin's languages folder.
	 *
	 * @return void
	 */
	public static function load(): void {
		load_plugin_textdomain( self::DOMAIN, false, dirname( plugin_basename( Main::file() ) ) . '/languages/' );
	}
}
