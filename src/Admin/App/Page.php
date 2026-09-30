<?php
/**
 * The admin app page class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Admin\App;

use LV2\WordPress\RelatedPostsForWP\Module;

/**
 * The page the admin app runs on.
 *
 * While the app is being built it has a hidden page of its own, Settings > Related Posts stays the 2.x screen. The
 * app takes over the `rp4wp` page when it replaces that screen.
 */
class Page implements Module {

	/**
	 * The admin page slug, for as long as the app has a page of its own.
	 */
	public const SLUG = 'rp4wp_app';

	/**
	 * Add the page.
	 *
	 * @return void
	 */
	public static function setup(): void {
		add_action( 'admin_menu', [ self::class, 'register' ] );
	}

	/**
	 * Register the page, hidden from the menu, and its assets for when it loads.
	 *
	 * @return void
	 */
	public static function register(): void {
		$hook = add_submenu_page( '', __( 'Related Posts', 'related-posts-for-wp' ), __( 'Related Posts', 'related-posts-for-wp' ), 'manage_options', self::SLUG, [ self::class, 'render' ] );

		if ( false !== $hook ) {
			add_action( 'load-' . $hook, [ Assets::class, 'enqueue' ] );
		}
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
}
