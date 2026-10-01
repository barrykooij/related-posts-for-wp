<?php
/**
 * The post-activation redirect class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Admin\Wizard;

use LV2\WordPress\RelatedPostsForWP\Admin\Settings\Page as SettingsPage;
use LV2\WordPress\RelatedPostsForWP\Install\Installer;
use LV2\WordPress\RelatedPostsForWP\Module;

/**
 * Sends the admin to the first-run card of the settings screen on the first admin page after activation.
 */
class Redirect implements Module {

	/**
	 * Redirect right away. Unlike other modules this does its work during setup, at the same moment as 2.x did.
	 *
	 * @return void
	 */
	public static function setup(): void {
		if ( ! is_admin() || ! get_option( Installer::OPTION_DO_INSTALL, false ) ) {
			return;
		}

		delete_option( Installer::OPTION_DO_INSTALL );

		wp_safe_redirect( SettingsPage::url( 'setup' ), 307 );
		exit;
	}
}
