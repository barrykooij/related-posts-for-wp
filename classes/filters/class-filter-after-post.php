<?php

if ( !defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class SRP_Filter_After_Post extends SRP_Filter {
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
	private function create_post_list( $slug, $title, $posts, $display_excerpt, $display_image ) {

		$content = "<div class='pc-post-list pc-{$slug}'>\n";

		// Output the relation title
		$content .= "<h3>" . $title . "</h3> \n";

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

					$content .= "<a href='" . get_permalink( $pc_post->ID ) . "'>";
					$content .= get_the_post_thumbnail( $pc_post->ID, $thumb_size );
					$content .= "</a>";
				}
			}

			$content .= "<a href='" . get_permalink( $pc_post->ID ) . "'>" . $pc_post->post_title . "</a>";

			if ( '1' == $display_excerpt ) {
				$content .= "<p>" . get_the_excerpt() . "</p>";
			}

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

		$ptl_manager = new SP_Connection_Manager();

		// Add a meta query so we only get relations that have the PM_PTL_APDC or PM_PTL_APDP set to true (1)
		$args = array(
			'meta_query' => array(
				'relation' => 'OR',
				array(
					'key'   => SP_Constants::PM_PTL_APDC,
					'value' => '1'
				),
				array(
					'key'   => SP_Constants::PM_PTL_APDP,
					'value' => '1'
				)
			)
		);

		// Get the connections
		$relations = $ptl_manager->get_connections( $args );

		// Check relations
		if ( count( $relations ) > 0 ) {

			// Store children and parents
			$children_relations = array();
			$parent_relations   = array();

			// Post Link Manager
			$pl_manager = new SP_Post_Link_Manager();

			// Current post ID
			$post_id = get_the_ID();

			// Loop relations
			foreach ( $relations as $relation ) {

				// Check if this relation allows children links to show
				if ( '1' === $relation->get_after_post_display_children() ) {
					$children_relations[] = $relation;
				}

				// Check if this relation allows parents links to show
				if ( '1' === $relation->get_after_post_display_parents() ) {
					$parent_relations[] = $relation;
				}

			}

			// Are the relations that want to show linked child posts
			if ( count( $children_relations ) > 0 ) {

				// Opening the wrapper div
				$content .= "<div class='pc-post-children'>\n";

				foreach ( $children_relations as $children_relation ) {

					// Get the linked posts
					$pc_posts = $pl_manager->get_children( $children_relation->get_slug(), $post_id );

					if ( count( $pc_posts ) > 0 ) {

						$content .= $this->create_post_list( $children_relation->get_slug(), $children_relation->get_title(), $pc_posts, $children_relation->get_after_post_display_children_excerpt(), $children_relation->get_after_post_display_children_image() );

					}

				}

				// Close the wrapper div
				$content .= "</div>\n";

			}

			// Are the relations that want to show linked parent posts
			if ( count( $parent_relations ) > 0 ) {

				// Opening the wrapper div
				$content .= "<div class='pc-post-parents'>\n";

				foreach ( $parent_relations as $parent_relation ) {

					// Get the linked posts
					$pc_posts = $pl_manager->get_parents( $parent_relation->get_slug(), $post_id );

					if ( count( $pc_posts ) > 0 ) {

						$content .= $this->create_post_list( $parent_relation->get_slug(), $parent_relation->get_title(), $pc_posts, false, false );

					}

				}

				// Close the wrapper div
				$content .= "</div>\n";

			}

		}

		return $content;
	}
}