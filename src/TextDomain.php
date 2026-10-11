<?php
/**
 * The text domain class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP;

/**
 * Loads the plugin translations: the ones in the plugin's languages folder, which are complete for its version, and a
 * language pack from translate.wordpress.org for what they lack.
 */
class TextDomain implements Module {

	/**
	 * The text domain.
	 */
	public const DOMAIN = 'related-posts-for-wp';

	/**
	 * Load the translations on init, and again for another locale.
	 *
	 * @return void
	 */
	public static function setup(): void {
		add_action( 'init', [ self::class, 'load' ] );
		add_action( 'change_locale', [ self::class, 'reload' ] );
	}

	/**
	 * Load the translations. WordPress loads a language pack instead of the translations in the plugin when there is
	 * one, also an older one that lacks the strings of this version. So the plugin's own translations are loaded first,
	 * and then the language pack, which only adds the strings they lack: the first file that has a string wins.
	 *
	 * @return void
	 */
	public static function load(): void {
		// The folder, for the translations WordPress loads just in time.
		load_plugin_textdomain( self::DOMAIN, false, dirname( plugin_basename( Main::file() ) ) . '/languages/' );

		self::load_files( determine_locale() );
	}

	/**
	 * Load the translations again after a switch to another locale, in the same order as load(). WordPress reloads them
	 * itself from the language pack alone when there is one.
	 *
	 * @param mixed $locale The new locale.
	 *
	 * @return void
	 */
	public static function reload( $locale ): void {
		unload_textdomain( self::DOMAIN, true );

		self::load_files( (string) $locale );
	}

	/**
	 * Load the plugin's translation of a locale, then the language pack of it.
	 *
	 * @param string $locale The locale.
	 *
	 * @return void
	 */
	private static function load_files( string $locale ): void {
		/** This filter is documented in wp-includes/l10n.php */
		$locale = (string) apply_filters( 'plugin_locale', $locale, self::DOMAIN ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- The core filter WordPress applies to plugin translations too.
		$file   = self::DOMAIN . '-' . $locale . '.mo';

		load_textdomain( self::DOMAIN, self::directory() . $file, $locale );
		load_textdomain( self::DOMAIN, WP_LANG_DIR . '/plugins/' . $file, $locale );
	}

	/**
	 * The plugin's languages folder, with a trailing slash. The admin app gets the translations of its script from
	 * here too.
	 *
	 * @return string
	 */
	public static function directory(): string {
		return plugin_dir_path( Main::file() ) . 'languages/';
	}
}
