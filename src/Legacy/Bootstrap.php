<?php
/**
 * The legacy bootstrap module file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Legacy;

use LV2\WordPress\RelatedPostsForWP\Module;

/**
 * Runs the 2.x bootstrap while its classes move into src/ one by one.
 *
 * Every 2.x class that moves takes its hook registration with it; when nothing is left, this module goes away.
 */
class Bootstrap implements Module {

	/**
	 * Boot the 2.x code.
	 *
	 * @return void
	 */
	public static function setup(): void {
		\RP4WP::get();
	}
}
