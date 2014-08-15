<?php

if ( !defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class RP4WP_Filter_After_Post extends RP4WP_Filter {
	protected $tag = 'the_content';
	protected $priority = 99;

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

			// The rp4wp block
			$content .= "<div class='rp4wp-related-posts'>\n";

			// Get the heading text
			$heading_text = RP4WP::get()->settings->get_option( 'heading_text' );

			// Check if there is a heading text
			if ( '' != $heading_text ) {

				// Add heading text plus heading elements
				$heading_text = '<h3>' . $heading_text . '</h3>' . PHP_EOL;
			}

			// Filter complete heading
			$content .= apply_filters( 'rp4wp_heading', $heading_text );

			// Open the list
			$content .= "<ul>\n";


			foreach ( $related_posts as $rp4wp_post ) {

				// Setup the postdata
				setup_postdata( $rp4wp_post );

				// Output the linked post
				$content .= "<li>";

				if ( 1 == RP4WP::get()->settings->get_option( 'display_image' ) ) {
					if ( has_post_thumbnail( $rp4wp_post->ID ) ) {

						/**
						 * Filter: 'rp4wp_apdc_thumbnail_size' - Allows changing the thumbnail size of the thumbnail in de APDC section
						 *
						 * @api String $thumbnail_size The current/default thumbnail size.
						 */
						$thumb_size = apply_filters( 'rp4wp_thumbnail_size', 'post-thumbnail' );

						$content .= "<div class='rp4wp-related-post-image'>" . PHP_EOL;
						$content .= "<a href='" . get_permalink( $rp4wp_post->ID ) . "'>";
						$content .= get_the_post_thumbnail( $rp4wp_post->ID, $thumb_size );
						$content .= "</a>";
						$content .= "</div>" . PHP_EOL;
					}
				}

				$content .= "<div class='rp4wp-related-post-content'>" . PHP_EOL;
				$content .= "<a href='" . get_permalink( $rp4wp_post->ID ) . "'>" . $rp4wp_post->post_title . "</a>";

				$excerpt_length = RP4WP::get()->settings->get_option( 'excerpt_length' );
				if ( $excerpt_length > 0 ) {
					$excerpt = ( ( '' != $rp4wp_post->post_excerpt ) ? $rp4wp_post->post_excerpt : wp_trim_words( $rp4wp_post->post_content, $excerpt_length ) );
					$content .= "<p>" . $excerpt . "</p>";
				}

				$content .= "</div>" . PHP_EOL;

				$content .= "</li>\n";

				// Reset the postdata
				wp_reset_postdata();
			}

			// Close the wrapper div
			$content .= "</ul>\n";
			$content .= "</div>\n";

		}

		return $content;
	}
}