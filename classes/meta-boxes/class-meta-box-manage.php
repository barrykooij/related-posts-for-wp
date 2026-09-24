<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * 2.x meta box. Delegates to LV2\WordPress\RelatedPostsForWP\Admin\MetaBox\ManageLinks.
 */
class RP4WP_Meta_Box_Manage {

	public function __construct() {

		// Check if we're in the admin/backend
		if ( ! is_admin() ) {
			return;
		}

		// Add meta boxes
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );

	}

	/**
	 * Add metabox to dashboard
	 *
	 * @access public
	 * @return void
	 */
	public function add_meta_box() {
		\LV2\WordPress\RelatedPostsForWP\Admin\MetaBox\ManageLinks::add();
	}

	/**
	 * Metabox content
	 *
	 * @access public
	 * @return void
	 */
	public function callback( $post ) {
		\LV2\WordPress\RelatedPostsForWP\Admin\MetaBox\ManageLinks::render( $post );
	}

}
