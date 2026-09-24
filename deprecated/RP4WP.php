<?php
/**
 * The deprecated RP4WP class file.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Compat\Deprecation;
use LV2\WordPress\RelatedPostsForWP\Main;

/**
 * The 2.x plugin class. It keeps RP4WP()->settings and the plugin file available for code written for 2.x.
 *
 * @deprecated 3.0.0 Use \LV2\WordPress\RelatedPostsForWP\Main.
 *
 * @property RP4WP_Settings|null $settings The 2.x settings object, from init on.
 */
class RP4WP {

	/**
	 * The plugin version.
	 */
	public const VERSION = Main::VERSION;

	/**
	 * The shared instance.
	 *
	 * @var RP4WP|null
	 */
	private static $instance = null;

	/**
	 * The 2.x settings object, created when code first reads it.
	 *
	 * @var RP4WP_Settings|null
	 */
	private $settings_object = null;

	/**
	 * The shared instance.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return RP4WP
	 */
	public static function get() {
		Deprecation::method( __METHOD__, Main::class . '::get()' );

		return self::instance();
	}

	/**
	 * The shared instance, without a notice: RP4WP() reports its own use.
	 *
	 * @internal
	 *
	 * @return RP4WP
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * The main plugin file.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return string
	 */
	public static function get_plugin_file() {
		Deprecation::method( __METHOD__, Main::class . '::file()' );

		return Main::file();
	}

	/**
	 * Create the 2.x settings object.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return void
	 */
	public function setup_settings() {
		Deprecation::method( __METHOD__, Main::class . '::settings()' );

		$this->settings_object = new RP4WP_Settings();
	}

	/**
	 * Read RP4WP()->settings. Like 2.x, it is null before init.
	 *
	 * @param string $name The property.
	 *
	 * @return mixed
	 */
	public function __get( $name ) {
		if ( 'settings' !== $name ) {
			return null;
		}

		if ( null === $this->settings_object && did_action( 'init' ) ) {
			$this->settings_object = new RP4WP_Settings();
		}

		return $this->settings_object;
	}

	/**
	 * Replace RP4WP()->settings, which 2.x allowed.
	 *
	 * @param string $name  The property.
	 * @param mixed  $value The value.
	 *
	 * @return void
	 */
	public function __set( $name, $value ) {
		if ( 'settings' === $name ) {
			$this->settings_object = $value;
		}
	}

	/**
	 * Whether RP4WP()->settings is set.
	 *
	 * @param string $name The property.
	 *
	 * @return bool
	 */
	public function __isset( $name ) {
		return 'settings' === $name && null !== $this->__get( $name );
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
	}
}
