<?php
/**
 * The deprecated RP4WP_Cap_Manager class file.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Compat\Deprecation;

/**
 * A 2.x helper that the plugin no longer uses.
 *
 * @deprecated 3.0.0 No replacement.
 */
class RP4WP_Cap_Manager {

	/**
	 * The capability to edit posts of the post type of a post.
	 *
	 * @deprecated 3.0.0
	 *
	 * @param int $post_id The post.
	 *
	 * @return string
	 */
	public static function get_capability( $post_id ) {
		Deprecation::method( __METHOD__ );

		$post_type     = ( isset( $post_id ) ) ? get_post_type( $post_id ) : 'post';
		$post_type_obj = get_post_type_object( $post_type );

		return ( ( null !== $post_type_obj ) ? $post_type_obj->cap->edit_posts : 'edit_posts' );
	}
}
