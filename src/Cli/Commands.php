<?php
/**
 * The command line class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Cli;

use LV2\WordPress\RelatedPostsForWP\Module;

/**
 * The WP-CLI commands of the free plugin: `wp rp4wp migrate`. The premium add-on registers its commands as `rp4wp`;
 * WP-CLI keeps `migrate` under it, whichever of the two is registered first.
 */
final class Commands implements Module {

	/**
	 * Register the commands on the command line.
	 *
	 * @return void
	 */
	public static function setup(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'rp4wp migrate', MigrateCommand::class );
		}
	}
}
