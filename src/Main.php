<?php
/**
 * The main plugin class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP;

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
	public const VERSION = '3.0.0-rc.1';

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
	 * @var Settings\Settings|null
	 */
	private ?Settings\Settings $settings = null;

	/**
	 * The renderer of the related posts; the default one is created when first needed.
	 *
	 * @var Contracts\Renderer|null
	 */
	private ?Contracts\Renderer $renderer = null;

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
	 * Whether the plugin runs on this request. Like 2.x, it does not run inside WordPress Playground, and the free
	 * plugin does not run in a multisite (network) admin; both only show a notice.
	 *
	 * @return bool
	 */
	public function should_run(): bool {
		if ( Admin\Notices\Playground::is_playground() ) {
			return false;
		}

		/**
		 * Filters whether the plugin runs in the admin of a multisite network. The free plugin does not support
		 * multisite there; the premium add-on does, and switches this on.
		 *
		 * @since 3.0.0
		 *
		 * @param bool $supported Whether multisite is supported. Default false.
		 */
		if ( true === apply_filters( 'rp4wp_supports_multisite', false ) ) {
			return true;
		}

		return ! ( is_multisite() && ( is_admin() || is_network_admin() ) );
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

		if ( Admin\Notices\Playground::is_playground() ) {
			Admin\Notices\Playground::setup();

			return;
		}

		TextDomain::setup();

		if ( ! $this->should_run() ) {
			Admin\Notices\Multisite::setup();

			return;
		}

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
			// Runs first: it may redirect to the installation wizard and stop.
			Admin\Wizard\Redirect::class,
			Admin\Notices\Installing::class,
			Admin\Notices\Mbstring::class,
			Admin\Notices\Review::class,
			Settings\Controller::class,
			Links\LinkPostType::class,
			Links\PostLifecycle::class,
			Frontend\ContentFilter::class,
			Frontend\Css::class,
			Frontend\Shortcode::class,
			Frontend\Widgets::class,
			Admin\Assets::class,
			Admin\MetaBox\ManageLinks::class,
			Admin\MetaBox\Ajax::class,
			Admin\Wizard\Page::class,
			Admin\Wizard\Ajax::class,
			Admin\LinkScreen\Page::class,
			Admin\Settings\Fields::class,
			Admin\Settings\Page::class,
			Admin\PluginLinks::class,
			Integrations\YoastDuplicatePost::class,
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
	 * @return Settings\Settings
	 */
	public function settings(): Settings\Settings {
		if ( null === $this->settings ) {
			$this->settings = new Settings\Settings();
		}

		return $this->settings;
	}

	/**
	 * Replace the settings service. Use it on `rp4wp_register_services`.
	 *
	 * @param Settings\Settings $settings The settings service.
	 *
	 * @return void
	 */
	public function set_settings( Settings\Settings $settings ): void {
		$this->settings = $settings;
	}

	/**
	 * The renderer of the related posts.
	 *
	 * @return Contracts\Renderer
	 */
	public function renderer(): Contracts\Renderer {
		if ( null === $this->renderer ) {
			$this->renderer = new Frontend\Renderer( new Links\LinkRepository(), $this->settings() );
		}

		return $this->renderer;
	}

	/**
	 * Replace the renderer of the related posts; the premium add-on does this on `rp4wp_register_services`.
	 *
	 * @param Contracts\Renderer $renderer The renderer.
	 *
	 * @return void
	 */
	public function set_renderer( Contracts\Renderer $renderer ): void {
		$this->renderer = $renderer;
	}

	/**
	 * The main file of this plugin. RP4WP_PLUGIN_FILE points to the premium plugin when that is active, like in 2.x,
	 * so the core uses its own constant for its files.
	 *
	 * @return string
	 */
	public static function file(): string {
		return RP4WP_FREE_PLUGIN_FILE;
	}
}
