<?php

if ( !defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class RP4WP_Filter_After_Post extends RP4WP_Filter {
	protected $tag = 'the_content';
	protected $priority = 99;

	/**
	 * Generate the post list
	 *
	 * @param $slug
	 * @param $title
	 * @param $posts
	 *
	 * @return string
	 */
	private function create_post_list( $posts, $display_excerpt, $display_image ) {

		$content = "<div class='rp4wp-related-posts'>\n";

		// Output the relation title
		$content .= "<h3>" . __( 'Related Posts', 'related-posts-for-wp' ) . "</h3> \n";

		// Open the list
		$content .= "<ul>\n";

		foreach ( $posts as $pc_post ) {

			// Setup the postdata
			setup_postdata( $pc_post );

			// Output the linked post
			$content .= "<li>";

			if ( '1' == $display_image ) {
				if ( has_post_thumbnail( $pc_post->ID ) ) {

					/**
					 * Filter: 'pc_apdc_thumbnail_size' - Allows changing the thumbnail size of the thumbnail in de APDC section
					 *
					 * @api String $thumbnail_size The current/default thumbnail size.
					 */
					$thumb_size = apply_filters( 'pc_apdc_thumbnail_size', 'post-thumbnail' );

					$content .= "<div class='rp4wp-related-post-image'>" . PHP_EOL;
					$content .= "<a href='" . get_permalink( $pc_post->ID ) . "'>";
					$content .= get_the_post_thumbnail( $pc_post->ID, $thumb_size );
					$content .= "</a>";
					$content .= "</div>" . PHP_EOL;
				}
			}

			$content .= "<div class='rp4wp-related-post-content'>" . PHP_EOL;
			$content .= "<a href='" . get_permalink( $pc_post->ID ) . "'>" . $pc_post->post_title . "</a>";

			if ( '1' == $display_excerpt ) {
				$content .= "<p>" . get_the_excerpt() . "</p>";
			}

			$content .= "</div>" . PHP_EOL;

			$content .= "</li>\n";

			// Reset the postdata
			wp_reset_postdata();
		}

		// Close the wrapper div
		$content .= "</ul>\n";
		$content .= "</div>\n";

		return $content;

	}

	/**
	 * the_content filter that will add linked posts to the bottom of the main post content
	 *
	 * @param $content
	 *
	 * @return string
	 */
	public function run( $content ) {
		/**
		 * Wow, what's going on here?! Well, setup_postdata() sets a lot of variables but does not change the $post variable.
		 * All checks return the main queried ID but we want to check if this specific filter call is the for the 'main' content.
		 * The method setup_postdata() does global and set the $id variable, so we're checking that.
		 */
		global $id;

		// Only run on single
		if ( !is_singular() || !is_main_query() || $id != get_queried_object_id() ) {
			return $content;
		}

		// Post Link Manager
		$pl_manager = new RP4WP_Post_Link_Manager();

		// Get the linked posts
		$related_posts = $pl_manager->get_children( $id );

		// Count
		if ( count( $related_posts ) > 0 ) {
			// Create Post List
			$content .= $this->create_post_list( $related_posts, true, true );
		}

		return $content;
	}
}