<?php
/**
 * The deprecated RP4WP_Nag_Manager class file.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Admin\Notices\Review;
use LV2\WordPress\RelatedPostsForWP\Compat\Deprecation;

/**
 * The 2.x notice that asks for a review.
 *
 * @deprecated 3.0.0 Use \LV2\WordPress\RelatedPostsForWP\Admin\Notices\Review.
 */
class RP4WP_Nag_Manager {

	/**
	 * Constructor.
	 */
	public function __construct() {
		Deprecation::class_used( __CLASS__, Review::class );
	}

	/**
	 * Show the notice when it is due, and handle a dismissal.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return void
	 */
	public function setup() {
		Deprecation::method( __METHOD__, Review::class . '::setup()' );

		Review::setup();
	}

	/**
	 * The notice.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return void
	 */
	public function display_admin_notice() {
		Deprecation::method( __METHOD__, Review::class . '::display()' );

		Review::display();
	}

	/**
	 * Hide the notice for the current user when they dismissed it.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return void
	 */
	public function catch_hide_notice() {
		Deprecation::method( __METHOD__, Review::class . '::handle_dismissal()' );

		Review::handle_dismissal();
	}
}
