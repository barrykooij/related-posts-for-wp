<?php
/**
 * The installation wizard page class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Admin\Wizard;

use LV2\WordPress\RelatedPostsForWP\Admin\Settings\Page as SettingsPage;
use LV2\WordPress\RelatedPostsForWP\Compat\LegacyHooks;
use LV2\WordPress\RelatedPostsForWP\Module;
use LV2\WordPress\RelatedPostsForWP\Rest\Routes;

/**
 * The address of the 2.x installation wizard, `?page=rp4wp_install`. The installer is part of the settings screen
 * now and runs in the background, so the page only sends admins there: to the first-run card, or to the installer
 * tools for links that asked to rebuild. It never removes anything itself.
 */
class Page implements Module {

	/**
	 * The admin page slug.
	 */
	public const SLUG = 'rp4wp_install';

	/**
	 * The nonce action of the 2.x wizard links.
	 */
	public const NONCE = 'rp4wp-install-secret';

	/**
	 * Option set while an installation is not finished.
	 */
	public const OPTION_IS_INSTALLING = 'rp4wp_is_installing';

	/**
	 * Register the hidden admin page.
	 *
	 * @return void
	 */
	public static function setup(): void {
		LegacyHooks::add_action(
			'RP4WP_Hook_Page_Install',
			'admin_menu',
			[ self::class, 'register' ],
			10,
			1,
			[
				'enqueue_install_assets' => [ self::class, 'enqueue_assets' ],
				'content'                => [ self::class, 'render' ],
			]
		);
	}

	/**
	 * The URL of the page, with a fresh nonce, as 2.x built it.
	 *
	 * @param array<string, string|int> $args Extra query arguments.
	 *
	 * @return string
	 */
	public static function url( array $args = [] ): string {
		return admin_url() . '?' . http_build_query( array_merge( [ 'page' => self::SLUG ], $args, [ 'rp4wp_nonce' => wp_create_nonce( self::NONCE ) ] ) );
	}

	/**
	 * Register the page, without a menu entry, for admins.
	 *
	 * @return void
	 */
	public static function register(): void {
		$hook = add_submenu_page(
			'',
			/* translators: The title of the settings page: the name of the plugin, best left as it is. */
			_x( 'Related Posts', 'page title', 'related-posts-for-wp' ),
			__( 'Related Posts', 'related-posts-for-wp' ),
			Routes::capability(),
			self::SLUG,
			[ self::class, 'render' ]
		);

		if ( false !== $hook ) {
			add_action( 'load-' . $hook, [ self::class, 'redirect' ] );
		}
	}

	/**
	 * Send the admin to the settings screen, before the page prints anything.
	 *
	 * @return void
	 */
	public static function redirect(): void {
		wp_safe_redirect( self::target() );
		exit;
	}

	/**
	 * Where the page sends the admin.
	 *
	 * @return string
	 */
	public static function target(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only decides where to send the admin; nothing changes.
		return SettingsPage::url( isset( $_GET['reinstall'] ) ? 'installer' : 'setup' );
	}

	/**
	 * The wizard had scripts of its own; the settings screen loads everything now.
	 *
	 * @return void
	 */
	public static function enqueue_assets(): void {
	}

	/**
	 * For when the redirect did not happen: a link to the settings screen.
	 *
	 * @return void
	 */
	public static function render(): void {
		echo '<div class="wrap"><p><a href="' . esc_url( self::target() ) . '">';
		echo esc_html__( 'Go to the settings of Related Posts for WordPress', 'related-posts-for-wp' );
		echo '</a></p></div>';
	}
}
