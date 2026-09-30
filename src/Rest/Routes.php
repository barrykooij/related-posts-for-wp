<?php
/**
 * The REST routes class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Rest;

use LV2\WordPress\RelatedPostsForWP\Module;

/**
 * The REST API of the admin app, in the `rp4wp/v1` namespace. Every route is for administrators only
 * (`manage_options`); with cookie authentication WordPress also requires the `wp_rest` nonce.
 */
class Routes implements Module {

	/**
	 * The namespace.
	 */
	public const NAMESPACE = 'rp4wp/v1';

	/**
	 * Register the routes when the REST API starts.
	 *
	 * @return void
	 */
	public static function setup(): void {
		add_action( 'rest_api_init', [ self::class, 'register' ] );
	}

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public static function register(): void {
		( new SettingsController() )->register();
		( new InstallController() )->register();
	}

	/**
	 * The permission callback of every route: the user may manage the site's options.
	 *
	 * WordPress answers 401 to a visitor who is not logged in (or sent no valid nonce), and 403 to a user without the
	 * capability.
	 *
	 * @return bool
	 */
	public static function can_manage(): bool {
		return current_user_can( self::capability() );
	}

	/**
	 * The capability the admin app and its REST API need. Fixed, like the 2.x wizard since 2.3.1: installing removes
	 * and creates links on every post.
	 *
	 * @return string
	 */
	public static function capability(): string {
		return 'manage_options';
	}
}
