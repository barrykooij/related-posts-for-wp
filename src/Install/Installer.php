<?php
/**
 * The installer class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Install;

/**
 * Plugin activation.
 */
class Installer {

	/**
	 * Option that sends the admin to the installation wizard on the next admin page.
	 */
	public const OPTION_DO_INSTALL = 'rp4wp_do_install';

	/**
	 * Create the word cache table and start the installation wizard.
	 *
	 * @return void
	 */
	public static function activate(): void {
		Table::create();

		add_option( self::OPTION_DO_INSTALL, true );
	}
}
