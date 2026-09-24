<?php
/**
 * The deprecated RP4WP_Hook_Related_Save_Words class file.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Compat\Deprecation;
use LV2\WordPress\RelatedPostsForWP\Links\PostLifecycle;

/**
 * The 2.x hook that caches the words of a post when it is published.
 *
 * @deprecated 3.0.0 Use \LV2\WordPress\RelatedPostsForWP\Links\PostLifecycle.
 */
class RP4WP_Hook_Related_Save_Words extends RP4WP_Hook {

	/**
	 * The action.
	 *
	 * @var string
	 */
	protected $tag = 'transition_post_status';

	/**
	 * The number of arguments.
	 *
	 * @var int
	 */
	protected $args = 3;

	/**
	 * Constructor. Adds run() to the action, like 2.x did.
	 */
	public function __construct() {
		Deprecation::class_used( __CLASS__, PostLifecycle::class );

		$this->attach_legacy_hook();
	}

	/**
	 * Cache the words of a published post.
	 *
	 * @deprecated 3.0.0
	 *
	 * @param string  $new_status The new post status.
	 * @param string  $old_status The old post status.
	 * @param WP_Post $post       The post.
	 *
	 * @return void
	 */
	public function run( $new_status, $old_status, $post ) {
		Deprecation::method( __METHOD__, PostLifecycle::class . '::save_words()' );

		PostLifecycle::save_words( $new_status, $old_status, $post );
	}
}
