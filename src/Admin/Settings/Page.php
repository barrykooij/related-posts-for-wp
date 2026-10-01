<?php
/**
 * The settings page class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Admin\Settings;

use LV2\WordPress\RelatedPostsForWP\Admin\App\Assets;
use LV2\WordPress\RelatedPostsForWP\Compat\LegacyHooks;
use LV2\WordPress\RelatedPostsForWP\Module;
use LV2\WordPress\RelatedPostsForWP\Rest\Routes;

/**
 * The Settings > Related Posts screen: the admin app (assets/src/admin), with the settings, the installer and its
 * progress. The app reads and saves everything through the REST API.
 */
class Page implements Module {

	/**
	 * The admin page slug, which is also the Settings API page and option group.
	 */
	public const SLUG = 'rp4wp';

	/**
	 * Add the page to the Settings menu.
	 *
	 * @return void
	 */
	public static function setup(): void {
		LegacyHooks::add_action(
			'RP4WP_Hook_Settings_Page',
			'admin_menu',
			[ self::class, 'register' ],
			10,
			1,
			[
				'enqueue_assets' => [ self::class, 'enqueue_assets' ],
				'screen'         => [ self::class, 'render' ],
			]
		);
	}

	/**
	 * Register the page, and its assets for when it loads.
	 *
	 * @return void
	 */
	public static function register(): void {
		$hook = add_submenu_page(
			'options-general.php',
			/* translators: The title of the settings page: the name of the plugin, best left as it is. */
			_x( 'Related Posts', 'page title', 'related-posts-for-wp' ),
			/* translators: The label of the settings page in the Settings menu: the name of the plugin, best left as it is. */
			_x( 'Related Posts', 'menu label', 'related-posts-for-wp' ),
			Routes::capability(),
			self::SLUG,
			[ self::class, 'render' ]
		);

		if ( false !== $hook ) {
			add_action( 'load-' . $hook, [ self::class, 'enqueue_assets' ] );
		}
	}

	/**
	 * The admin app.
	 *
	 * @return void
	 */
	public static function enqueue_assets(): void {
		Assets::enqueue();
	}

	/**
	 * The element the app mounts on.
	 *
	 * @return void
	 */
	public static function render(): void {
		echo '<div class="wrap"><div id="rp4wp-admin"></div><noscript><p>';
		echo esc_html__( 'The settings of Related Posts for WordPress need JavaScript.', 'related-posts-for-wp' );
		echo '</p></noscript></div>';
	}

	/**
	 * The URL of the page, at a screen of the app.
	 *
	 * @param string $route The route of the app, for example `setup`; empty for the first screen.
	 *
	 * @return string
	 */
	public static function url( string $route = '' ): string {
		$url = admin_url( 'options-general.php?page=' . self::SLUG );

		return '' === $route ? $url : $url . '#/' . $route;
	}
}
