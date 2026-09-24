<?php
/**
 * The deprecated RP4WP_Hook_Related_Update_Link class file.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Compat\Deprecation;
use LV2\WordPress\RelatedPostsForWP\Links\PostLifecycle;

/**
 * The 2.x hook that updates the links of a post when it is published again.
 *
 * @deprecated 3.0.0 Use \LV2\WordPress\RelatedPostsForWP\Links\PostLifecycle.
 */
class RP4WP_Hook_Related_Update_Link extends RP4WP_Hook {

	/**
	 * The action.
	 *
	 * @var string
	 */
	protected $tag = 'transition_post_status';

	/**
	 * The priority.
	 *
	 * @var int
	 */
	protected $priority = 11;

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
	 * Update the links of a post that is published again.
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
		Deprecation::method( __METHOD__, PostLifecycle::class . '::update_links()' );

		PostLifecycle::update_links( $new_status, $old_status, $post );
	}
}
