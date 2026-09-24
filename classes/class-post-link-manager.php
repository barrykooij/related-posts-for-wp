<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

use LV2\WordPress\RelatedPostsForWP\Links\LinkRepository;

/**
 * 2.x link manager. The link data methods delegate to LinkRepository; the list rendering moves in a later step.
 */
class RP4WP_Post_Link_Manager {

	private $temp_child_order;

	/**
	 * The link repository.
	 *
	 * @var LinkRepository
	 */
	private $links;

	public function __construct() {
		$this->links = new LinkRepository();
	}

	/**
	 * Method to add a PostLink
	 *
	 * @access public
	 *
	 * @param  int  $parent_id
	 * @param  int  $child_id
	 * @param  boolean  $batch
	 *
	 * @return int|array The link ID, or the insert data when $batch is true.
	 */
	public function add( $parent_id, $child_id, $batch = false ) {
		if ( true === $batch ) {
			return $this->links->insert_data( absint( $parent_id ), absint( $child_id ) );
		}

		return $this->links->add( absint( $parent_id ), absint( $child_id ) );
	}

	/**
	 * Delete a link
	 *
	 * @access public
	 *
	 * @param  int  $link_id
	 *
	 * @return void
	 */
	public function delete( $link_id ) {
		$this->links->delete( (int) $link_id );
	}

	/**
	 * Get children based on parent_id.
	 * It's possible to add extra arguments to the WP_Query with the $extra_args argument
	 *
	 * @access public
	 *
	 * @param  int  $parent_id
	 * @param  array  $extra_args
	 *
	 * @return array
	 */
	public function get_children( $parent_id, $extra_args = array() ) {
		return $this->links->get_children( (int) $parent_id, (array) $extra_args );
	}

	/**
	 * Get parents based on link_id and child_id.
	 *
	 * @access public
	 *
	 * @param  int  $child_id
	 *
	 * @return array
	 */
	public function get_parents( $child_id ) {
		return $this->links->get_parents( (int) $child_id );
	}

	/**
	 * Custom sort method to reorder children
	 *
	 * @param $a
	 * @param $b
	 *
	 * @return mixed
	 */
	public function sort_get_children_children( $a, $b ) {
		return array_search( $a->ID, $this->temp_child_order ) - array_search( $b->ID, $this->temp_child_order );
	}

	/**
	 * Delete all links involved in given post_id
	 *
	 * @access public
	 *
	 * @param  int  $post_id
	 */
	public function delete_links_related_to( $post_id ) {
		$this->links->delete_links_related_to( (int) $post_id );
	}

	/**
	 * Show some love
	 */
	private function show_love() {

		if ( '1' != RP4WP::get()->settings->get_option( 'show_love' ) ) {
			return;
		}

		// Base
		$base_url     = "https://www.relatedpostsforwp.com";
		$query_string = "?";

		// Allow affiliates to add affiliate ID to Power By link
		$ref = apply_filters( 'rp4wp_poweredby_affiliate_id', '' );
		if ( '' !== $ref ) {
			$ref          = intval( $ref );
			$query_string .= "ref=" . $ref . '&';
		}

		// The UTM campaign stuff
		$query_string .= sprintf( "utm_source=%s&utm_medium=link&utm_campaign=poweredby",
			strtolower( preg_replace( "`[^A-z0-9\-.]+`i", '',
				str_ireplace( ' ', '-', html_entity_decode( get_bloginfo( 'name' ) ) ) ) ) );

		// The URL
		$url = $base_url . htmlentities( $query_string );

		// Display

		return '<small><a href="' . $url . '" target="_blank">Powered By Related Posts for WordPress</a></small>';

	}

	/**
	 * Get link for related post by ID
	 *
	 * @param $post_id
	 *
	 * @return string
	 */
	private function get_related_post_link( $post_id ) {
		return apply_filters( 'rp4wp_post_link', get_permalink( $post_id ), $post_id );
	}

	/**
	 * Generate the children list
	 *
	 * @param  int  $id
	 * @param  int  $limit
	 * @param  int  $offset
	 *
	 * @return string
	 * @since  1.0.0
	 * @access public
	 *
	 */
	public function generate_children_list( $id, $limit = - 1, $offset = 0 ) {

		ob_start();

		// Get the children
		$related_posts = $this->get_children( $id, [ 'posts_per_page' => $limit, 'offset' => $offset ] );

		// Count
		if ( count( $related_posts ) > 0 ) {

			// The rp4wp block
			echo "<div class='rp4wp-related-posts'>\n";

			// Get the heading text
			$heading_text = RP4WP::get()->settings->get_option( 'heading_text' );

			// Check if there is a heading text
			if ( '' != $heading_text ) {

				// Add heading text plus heading elements
				$heading_text = '<h3>' . esc_html( $heading_text ) . '</h3>' . PHP_EOL;
			}

			// Filter complete heading
			echo apply_filters( 'rp4wp_heading', $heading_text );

			// Open the list
			echo "<ul>\n";


			foreach ( $related_posts as $rp4wp_post ) {

				// Setup the postdata
				setup_postdata( $rp4wp_post );

				do_action( 'rp4wp_before_content', $rp4wp_post );

				// Output the linked post
				echo "<li>";

				if ( 1 == RP4WP::get()->settings->get_option( 'display_image' ) ) {
					if ( has_post_thumbnail( $rp4wp_post->ID ) ) {

						/**
						 * Filter: 'rp4wp_apdc_thumbnail_size' - Allows changing the thumbnail size of the thumbnail in de APDC section
						 *
						 * @api String $thumbnail_size The current/default thumbnail size.
						 */
						$thumb_size = apply_filters( 'rp4wp_thumbnail_size', 'thumbnail' );

						do_action( 'rp4wp_before_image', $rp4wp_post );

						echo "<div class='rp4wp-related-post-image'>" . PHP_EOL;
						echo "<a href='" . $this->get_related_post_link( $rp4wp_post->ID ) . "'>";
						echo get_the_post_thumbnail( $rp4wp_post->ID, $thumb_size );
						echo "</a>";
						echo "</div>" . PHP_EOL;

						do_action( 'rp4wp_after_image', $rp4wp_post );
					}
				}

				echo "<div class='rp4wp-related-post-content'>" . PHP_EOL;
				echo apply_filters(
					"rp4wp_post_title_html_values",
					sprintf(
						apply_filters(
							"rp4wp_post_title_html",
							"<a href='%s'>%s</a>",
							$rp4wp_post
						),
						$this->get_related_post_link( $rp4wp_post->ID ),
						apply_filters(
							'rp4wp_post_title',
							$rp4wp_post->post_title,
							$rp4wp_post
						)
					),
					$rp4wp_post
				);

				$excerpt_length = RP4WP::get()->settings->get_option( 'excerpt_length' );
				if ( $excerpt_length > 0 ) {
					$excerpt = wp_trim_words( strip_tags( strip_shortcodes( ( ( '' != $rp4wp_post->post_excerpt ) ? $rp4wp_post->post_excerpt : $rp4wp_post->post_content ) ) ),
						$excerpt_length );
					echo "<p>" . apply_filters( 'rp4wp_post_excerpt', $excerpt, $rp4wp_post->ID ) . "</p>";
				}

				echo "</div>" . PHP_EOL;

				echo "</li>\n";

				do_action( 'rp4wp_after_content', $rp4wp_post );

				// Reset the postdata
				wp_reset_postdata();
			}

			// Close the wrapper div
			echo "</ul>\n";

			echo $this->show_love();

			echo "</div>\n";

		}

		return trim( ob_get_clean() );

	}

}
