<?php
/**
 * The deprecated RP4WP_Hook_Post_Type class file.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Compat\Deprecation;
use LV2\WordPress\RelatedPostsForWP\Links\LinkPostType;

/**
 * The 2.x hook that registers the link post type.
 *
 * @deprecated 3.0.0 Use \LV2\WordPress\RelatedPostsForWP\Links\LinkPostType.
 */
class RP4WP_Hook_Post_Type extends RP4WP_Hook {

	/**
	 * The action.
	 *
	 * @var string
	 */
	protected $tag = 'init';

	/**
	 * Constructor. Adds run() to the action, like 2.x did.
	 */
	public function __construct() {
		Deprecation::class_used( __CLASS__, LinkPostType::class );

		$this->attach_legacy_hook();
	}

	/**
	 * Register the post type.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return void
	 */
	public function run() {
		Deprecation::method( __METHOD__, LinkPostType::class . '::register()' );

		LinkPostType::register();
	}
}
