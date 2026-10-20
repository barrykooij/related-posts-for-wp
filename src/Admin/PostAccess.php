<?php
/**
 * The post access class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Admin;

/**
 * Which posts the current user may see on the admin screens of the plugin, so linking a post never shows its title to
 * someone who can't read it.
 */
class PostAccess {

	/**
	 * Whether the current user can read a post: anyone can read a post the site shows publicly; a draft, a pending,
	 * scheduled or private post, a post in the trash or a post of a post type that isn't public needs `read_post`.
	 *
	 * @param \WP_Post|int|null $post The post, or its ID.
	 *
	 * @return bool
	 */
	public static function can_read( $post ): bool {
		// get_post() of 0 or null is the global post, so only look up real IDs.
		if ( ! $post instanceof \WP_Post ) {
			$post = is_numeric( $post ) && (int) $post > 0 ? get_post( (int) $post ) : null;
		}

		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		return is_post_publicly_viewable( $post ) || current_user_can( 'read_post', $post->ID );
	}
}
