<?php
/**
 * The deprecated RP4WP_Hook_Meta_Box class file.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Admin\MetaBox\ManageLinks;
use LV2\WordPress\RelatedPostsForWP\Compat\Deprecation;

/**
 * The 2.x hook that adds the related posts meta box.
 *
 * @deprecated 3.0.0 Use \LV2\WordPress\RelatedPostsForWP\Admin\MetaBox\ManageLinks.
 */
class RP4WP_Hook_Meta_Box extends RP4WP_Hook {

	/**
	 * The action.
	 *
	 * @var string
	 */
	protected $tag = 'admin_init';

	/**
	 * Constructor. Adds run() to the action, like 2.x did.
	 */
	public function __construct() {
		Deprecation::class_used( __CLASS__, ManageLinks::class );

		$this->attach_legacy_hook();
	}

	/**
	 * Add the meta box to the post editor.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return void
	 */
	public function run() {
		Deprecation::method( __METHOD__, ManageLinks::class . '::init()' );

		ManageLinks::init();
	}
}
