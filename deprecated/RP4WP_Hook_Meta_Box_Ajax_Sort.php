<?php
/**
 * The deprecated RP4WP_Hook_Meta_Box_Ajax_Sort class file.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Admin\MetaBox\Ajax;
use LV2\WordPress\RelatedPostsForWP\Compat\Deprecation;

/**
 * The 2.x AJAX hook that saves the order of the related posts.
 *
 * @deprecated 3.0.0 Use \LV2\WordPress\RelatedPostsForWP\Admin\MetaBox\Ajax.
 */
class RP4WP_Hook_Meta_Box_Ajax_Sort extends RP4WP_Hook {

	/**
	 * The action.
	 *
	 * @var string
	 */
	protected $tag = 'wp_ajax_rp4wp_related_sort';

	/**
	 * Constructor. Adds run() to the action, like 2.x did.
	 */
	public function __construct() {
		Deprecation::class_used( __CLASS__, Ajax::class );

		$this->attach_legacy_hook();
	}

	/**
	 * Save the posted order.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return void
	 */
	public function run() {
		Deprecation::method( __METHOD__, Ajax::class . '::sort()' );

		Ajax::sort();
	}
}
