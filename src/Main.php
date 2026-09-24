<?php
/**
 * The main plugin class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP;

use LV2\WordPress\RelatedPostsForWP\Settings\Settings;

/**
 * Boots the plugin.
 *
 * Setup runs in three steps, each marked by a public hook:
 * 1. `rp4wp_register_services`: the premium add-on (or other code) replaces services before anything uses them.
 * 2. Every module from the `rp4wp_modules` filter registers its hooks, in order.
 * 3. `rp4wp_loaded`: the plugin is ready.
 */
class Main {

	/**
	 * The plugin version.
	 */
	public const VERSION = '2.3.1';

	/**
	 * The shared instance.
	 *
	 * @var Main|null
	 */
	private static ?Main $instance = null;

	/**
	 * Whether setup() has run.
	 *
	 * @var bool
	 */
	private bool $is_set_up = false;

	/**
	 * The settings service.
	 *
	 * @var Settings|null
	 */
	private ?Settings $settings = null;

	/**
	 * The shared instance.
	 *
	 * @return Main
	 */
	public static function get(): Main {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Set up the plugin. Runs once; later calls do nothing.
	 *
	 * @return void
	 */
	public function setup(): void {
		if ( $this->is_set_up ) {
			return;
		}

		$this->is_set_up = true;

		/**
		 * Fires before the modules are set up, so services can be replaced.
		 *
		 * @since 3.0.0
		 *
		 * @param Main $main The plugin.
		 */
		do_action( 'rp4wp_register_services', $this );

		foreach ( $this->modules() as $module ) {
			if ( ! is_string( $module ) || ! is_subclass_of( $module, Module::class ) ) {
				_doing_it_wrong( __METHOD__, esc_html( sprintf( 'Modules must implement %s.', Module::class ) ), '3.0.0' );
				continue;
			}

			$module::setup();
		}

		/**
		 * Fires when the plugin is set up.
		 *
		 * @since 3.0.0
		 *
		 * @param Main $main The plugin.
		 */
		do_action( 'rp4wp_loaded', $this );
	}

	/**
	 * Whether setup() has run.
	 *
	 * @return bool
	 */
	public function is_set_up(): bool {
		return $this->is_set_up;
	}

	/**
	 * The modules to set up, in order.
	 *
	 * @return array<int, class-string<Module>>
	 */
	public function modules(): array {
		$modules = [
			// The 2.x bootstrap, until every 2.x class has moved into src/.
			Legacy\Bootstrap::class,
			Links\LinkPostType::class,
			Links\PostLifecycle::class,
			Frontend\ContentFilter::class,
			Frontend\Css::class,
			Frontend\Shortcode::class,
			Frontend\Widgets::class,
		];

		/**
		 * Filters the modules that set up the plugin, in order.
		 *
		 * @since 3.0.0
		 *
		 * @param array<int, class-string<Module>> $modules Module class names.
		 */
		return (array) apply_filters( 'rp4wp_modules', $modules );
	}

	/**
	 * The settings service.
	 *
	 * @return Settings
	 */
	public function settings(): Settings {
		if ( null === $this->settings ) {
			$this->settings = new Settings();
		}

		return $this->settings;
	}

	/**
	 * Replace the settings service. Use it on `rp4wp_register_services`.
	 *
	 * @param Settings $settings The settings service.
	 *
	 * @return void
	 */
	public function set_settings( Settings $settings ): void {
		$this->settings = $settings;
	}

	/**
	 * The main plugin file.
	 *
	 * @return string
	 */
	public static function file(): string {
		return RP4WP_PLUGIN_FILE;
	}
}
