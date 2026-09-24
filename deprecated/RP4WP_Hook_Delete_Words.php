<?php
/**
 * The deprecated RP4WP_Hook_Delete_Words class file.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Compat\Deprecation;
use LV2\WordPress\RelatedPostsForWP\Links\PostLifecycle;

/**
 * The 2.x hook that deletes the cached words of a deleted post.
 *
 * @deprecated 3.0.0 Use \LV2\WordPress\RelatedPostsForWP\Links\PostLifecycle.
 */
class RP4WP_Hook_Delete_Words extends RP4WP_Hook {

	/**
	 * The action.
	 *
	 * @var string
	 */
	protected $tag = 'delete_post';

	/**
	 * Constructor. Adds run() to the action, like 2.x did.
	 */
	public function __construct() {
		Deprecation::class_used( __CLASS__, PostLifecycle::class );

		$this->attach_legacy_hook();
	}

	/**
	 * Delete the cached words of a post.
	 *
	 * @deprecated 3.0.0
	 *
	 * @param int $post_id The post.
	 *
	 * @return void
	 */
	public function run( $post_id ) {
		Deprecation::method( __METHOD__, PostLifecycle::class . '::delete_words()' );

		PostLifecycle::delete_words( $post_id );
	}
}
