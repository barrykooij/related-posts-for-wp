<?php
/**
 * The deprecated RP4WP_Is_Installing_Notice class file.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Admin\Notices\Installing;
use LV2\WordPress\RelatedPostsForWP\Compat\Deprecation;

/**
 * The 2.x notice that offers to resume the installation wizard.
 *
 * @deprecated 3.0.0 Use \LV2\WordPress\RelatedPostsForWP\Admin\Notices\Installing.
 */
class RP4WP_Is_Installing_Notice {

	/**
	 * Constructor.
	 */
	public function __construct() {
		Deprecation::class_used( __CLASS__, Installing::class );
	}

	/**
	 * Handle a dismissal and show the notice when needed.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return void
	 */
	public function check() {
		Deprecation::method( __METHOD__, Installing::class . '::setup()' );

		Installing::setup();
	}

	/**
	 * The notice.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return void
	 */
	public function content() {
		Deprecation::method( __METHOD__, Installing::class . '::display()' );

		Installing::display();
	}
}
