<?php
/**
 * The admin assets class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Admin;

use LV2\WordPress\RelatedPostsForWP\Compat\LegacyHooks;
use LV2\WordPress\RelatedPostsForWP\Main;
use LV2\WordPress\RelatedPostsForWP\Module;

/**
 * Scripts and styles for the post editor and the settings page.
 */
class Assets implements Module {

	/**
	 * Enqueue the assets in the admin.
	 *
	 * @return void
	 */
	public static function setup(): void {
		LegacyHooks::add_action( 'RP4WP_Hook_Admin_Scripts', 'admin_enqueue_scripts', [ self::class, 'enqueue' ] );
	}

	/**
	 * Enqueue the assets of the current admin screen.
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		global $pagenow;

		$suffix = SCRIPT_DEBUG ? '' : '.min';

		if ( 'post.php' === $pagenow || 'post-new.php' === $pagenow ) {
			wp_enqueue_script( 'rp4wp_edit_post_js', plugins_url( '/assets/js/edit-post' . $suffix . '.js', Main::file() ), [ 'jquery', 'jquery-ui-sortable' ], Main::VERSION, false );
			wp_localize_script( 'rp4wp_edit_post_js', 'rp4wp_js', self::javascript_strings() );
			wp_enqueue_style( 'rp4wp_edit_post_css', plugins_url( '/assets/css/edit-post.css', Main::file() ), [], Main::VERSION );
		}

		if ( 'options-general.php' === $pagenow && isset( $_GET['page'] ) && 'rp4wp' === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only decides which assets to load.
			wp_enqueue_script( 'rp4wp_settings_js', plugins_url( '/assets/js/settings' . $suffix . '.js', Main::file() ), [ 'jquery' ], Main::VERSION, false );
		}
	}

	/**
	 * Translated strings for the admin scripts, available as the rp4wp_js object.
	 *
	 * @return array<string, string>
	 */
	public static function javascript_strings(): array {
		return [
			'confirm_delete_related_post' => __( 'Are you sure you want to delete this related post?', 'related-posts-for-wp' ),
		];
	}
}
