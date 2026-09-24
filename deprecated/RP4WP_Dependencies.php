<?php
/**
 * The deprecated RP4WP_Dependencies class file.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Admin\Notices\Mbstring;
use LV2\WordPress\RelatedPostsForWP\Compat\Deprecation;

/**
 * The 2.x check for the mbstring extension.
 *
 * @deprecated 3.0.0 Use \LV2\WordPress\RelatedPostsForWP\Admin\Notices\Mbstring.
 */
class RP4WP_Dependencies {

	/**
	 * Constructor.
	 */
	public function __construct() {
		Deprecation::class_used( __CLASS__, Mbstring::class );
	}

	/**
	 * Show a notice when the mbstring extension is missing.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return void
	 */
	public function check() {
		Deprecation::method( __METHOD__, Mbstring::class . '::setup()' );

		Mbstring::setup();
	}

	/**
	 * The notice.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return void
	 */
	public function display_mbstring_error() {
		Deprecation::method( __METHOD__, Mbstring::class . '::display()' );

		Mbstring::display();
	}
}
