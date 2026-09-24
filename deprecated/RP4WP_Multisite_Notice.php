<?php
/**
 * The deprecated RP4WP_Multisite_Notice class file.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Admin\Notices\Multisite;
use LV2\WordPress\RelatedPostsForWP\Compat\Deprecation;

/**
 * The 2.x notice that the free plugin does not support multisite.
 *
 * @deprecated 3.0.0 Use \LV2\WordPress\RelatedPostsForWP\Admin\Notices\Multisite.
 */
class RP4WP_Multisite_Notice {

	/**
	 * The notice.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return void
	 */
	public static function display() {
		Deprecation::method( __METHOD__, Multisite::class . '::display()' );

		Multisite::display();
	}
}
