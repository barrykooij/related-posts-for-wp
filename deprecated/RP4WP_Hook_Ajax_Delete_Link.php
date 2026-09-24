<?php
/**
 * The deprecated RP4WP_Hook_Ajax_Delete_Link class file.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Admin\MetaBox\Ajax;
use LV2\WordPress\RelatedPostsForWP\Compat\Deprecation;

/**
 * The 2.x AJAX hook that deletes a link from the meta box.
 *
 * @deprecated 3.0.0 Use \LV2\WordPress\RelatedPostsForWP\Admin\MetaBox\Ajax.
 */
class RP4WP_Hook_Ajax_Delete_Link extends RP4WP_Hook {

	/**
	 * The action.
	 *
	 * @var string
	 */
	protected $tag = 'wp_ajax_rp4wp_delete_link';

	/**
	 * Constructor. Adds run() to the action, like 2.x did.
	 */
	public function __construct() {
		Deprecation::class_used( __CLASS__, Ajax::class );

		$this->attach_legacy_hook();
	}

	/**
	 * Delete the posted link.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return void
	 */
	public function run() {
		Deprecation::method( __METHOD__, Ajax::class . '::delete_link()' );

		Ajax::delete_link();
	}
}
