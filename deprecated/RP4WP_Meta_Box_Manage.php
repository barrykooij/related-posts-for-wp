<?php
/**
 * The deprecated RP4WP_Meta_Box_Manage class file.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Admin\MetaBox\ManageLinks;
use LV2\WordPress\RelatedPostsForWP\Compat\Deprecation;

/**
 * The 2.x related posts meta box.
 *
 * @deprecated 3.0.0 Use \LV2\WordPress\RelatedPostsForWP\Admin\MetaBox\ManageLinks.
 */
class RP4WP_Meta_Box_Manage {

	/**
	 * Add the meta box in the admin, like 2.x did.
	 */
	public function __construct() {
		Deprecation::class_used( __CLASS__, ManageLinks::class );

		ManageLinks::init();
	}

	/**
	 * Add the meta box.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return void
	 */
	public function add_meta_box() {
		Deprecation::method( __METHOD__, ManageLinks::class . '::add()' );

		ManageLinks::add();
	}

	/**
	 * The meta box.
	 *
	 * @deprecated 3.0.0
	 *
	 * @param WP_Post $post The post.
	 *
	 * @return void
	 */
	public function callback( $post ) {
		Deprecation::method( __METHOD__, ManageLinks::class . '::render()' );

		ManageLinks::render( $post );
	}
}
