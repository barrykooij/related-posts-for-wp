<?php
/**
 * The post types class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP;

/**
 * The post types the plugin relates to each other.
 */
class PostTypes {

	/**
	 * The supported post types, escaped for use in SQL. Filterable through `rp4wp_supported_post_types`.
	 *
	 * @return string[]
	 */
	public static function supported(): array {
		/**
		 * Filters the post types that get related posts.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $post_types The post types.
		 */
		$post_types = apply_filters( 'rp4wp_supported_post_types', [ 'post' ] );

		// At least one post type is needed.
		if ( ! is_array( $post_types ) || count( $post_types ) < 1 ) {
			$post_types = [ 'post' ];
		}

		foreach ( $post_types as $key => $post_type ) {
			$post_types[ $key ] = esc_sql( $post_type );
		}

		return $post_types;
	}
}
