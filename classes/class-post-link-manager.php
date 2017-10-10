<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class RP4WP_Post_Link_Manager {

	private $temp_child_order;

	public function __construct() {
	}

	/**
	 * Create query arguments used to fetch links
	 *
	 * @access private
	 *
	 * @param int $post_id
	 * @param string $meta_key
	 *
	 * @return array
	 */
	private function create_link_args( $meta_key, $post_id ) {
		$args = array(
			'post_type'      => RP4WP_Constants::LINK_PT,
			'posts_per_page' => - 1,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
			'meta_query'     => array(
				array(
					'key'     => $meta_key,
					'value'   => $post_id,
					'compare' => '=',
				)
			)
		);

		return $args;
	}

	/**
	 * Get amount of links based on post type link id and (post) parent id
	 *
	 * @access private
	 *
	 * @param int $parent_id
	 *
	 * @return int
	 */
	private function get_link_count( $parent_id ) {
		$link_query = new WP_Query(
			array(
				'fields'         => 'ids',
				'post_type'      => RP4WP_Constants::LINK_PT,
				'posts_per_page' => - 1,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
				'meta_query'     => array(
					array(
						'key'     => RP4WP_Constants::PM_PARENT,
						'value'   => $parent_id,
						'compare' => '=',
					),
				)
			)
		);

		// Reset global post variables
		wp_reset_postdata();

		return $link_query->found_posts;
	}

	/**
	 * Method to add a PostLink
	 *
	 * @access public
	 *
	 * @param int $parent_id
	 * @param int $child_id
	 * @param boolean $batch
	 *
	 * @return int ($link_id)
	 */
	public function add( $parent_id, $child_id, $batch = false ) {
		global $wpdb;

		// Setup the insert data
		$data = array(
			'post' => "('" . current_time( 'mysql', 0 ) . "', '" . current_time( 'mysql', 1 ) . "','','Related Posts for WordPress Link','" . RP4WP_Constants::LINK_PT . "','publish')",
			'meta' => array(
				"(%d, '" . RP4WP_Constants::PM_PARENT . "', '$parent_id')",
				"(%d, '" . RP4WP_Constants::PM_CHILD . "', '$child_id')",
			)
		);

		// If this is a batch insert, return data
		if ( true === $batch ) {
			return $data;
		}

		// Create post link
		$wpdb->query( "	INSERT INTO `$wpdb->posts`
						(`post_date`,`post_date_gmt`,`post_content`,`post_title`,`post_type`,`post_status`)
						VALUES
						{$data['post']}
						" );

		$link_id = $wpdb->insert_id;

		// Create post meta
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO `$wpdb->postmeta`
				(`post_id`,`meta_key`,`meta_value`)
				VALUES
				{$data['meta'][0]},
				{$data['meta'][1]}
				", $link_id, $link_id ) );

		// Do action rp4wp_after_link_add
		do_action( 'rp4wp_after_link_add', $link_id );

		// Return link id
		return $link_id;
	}

	/**
	 * Delete a link
	 *
	 * @access public
	 *
	 * @param id $link_id
	 *
	 * @return void
	 */
	public function delete( $link_id ) {
		// Action
		do_action( 'rp4wp_before_link_delete', $link_id );

		// Delete link
		wp_delete_post( $link_id, true );

		// Action
		do_action( 'rp4wp_after_link_delete', $link_id );

		return;
	}

	/**
	 * Get children based on parent_id.
	 * It's possible to add extra arguments to the WP_Query with the $extra_args argument
	 *
	 * @access public
	 *
	 * @param int $parent_id
	 * @param array $extra_args
	 *
	 * @return array
	 */
	public function get_children( $parent_id, $extra_args = array() ) {

		// Do WP_Query
		$link_args = $this->create_link_args( RP4WP_Constants::PM_PARENT, $parent_id );

		/*
		 * Check $extra_args for `posts_per_page`.
		 * This is the only arg that should be added to link query instead of the child query
		 */
		if ( isset( $extra_args['posts_per_page'] ) ) {

			// Set posts_per_page to link arguments
			$link_args['posts_per_page'] = $extra_args['posts_per_page'];
			unset( $extra_args['posts_per_page'] );
		}

		/*
		 * Check $extra_args for `order`.
		 * If 'order' is set without 'orderby', we should add it to the link arguments
		 */
		if ( isset( $extra_args['order'] ) && ! isset( $extra_args['orderby'] ) ) {
			$link_args['order'] = $extra_args['order'];
			unset( $extra_args['order'] );
		}

		/**
		 * Filter args for link query
		 */
		$link_args = apply_filters( 'rp4wp_get_children_link_args', $link_args, $parent_id );

		// Create link query
		$wp_query = new WP_Query();
		$posts = $wp_query->query( $link_args );

		// Store child ids
		$child_ids = array();
		foreach( $posts as $post ) {
			$child_ids[ $post->ID ] = get_post_meta( $post->ID, RP4WP_Constants::PM_CHILD, true );
		}

		// Get children with custom args
		if ( is_array( $extra_args ) && count( $extra_args ) > 0 ) {

			if ( ! isset( $extra_args['orderby'] ) ) {
				$this->temp_child_order = array_values( $child_ids );
			}

			// Get child again, but this time by $extra_args
			$children = array();

			//Child WP_Query arguments
			if ( count( $child_ids ) > 0 ) {
				$child_args      = array(
					'post_type'           => 'post',
					'posts_per_page'      => -1,
					'ignore_sticky_posts' => 1,
					'post__in'            => $child_ids,
				);

				// Extra arguments
				$child_args = array_merge_recursive( $child_args, $extra_args );

				/**
				 * Filter args for child query
				 */
				$child_args = apply_filters( 'rp4wp_get_children_child_args', $child_args, $parent_id );

				// Child Query
				$wp_query = new WP_Query;
				$posts = $wp_query->query( $child_args );
				foreach( $posts as $post ) {
					$children[ $post->ID ] = $post;
				}

				// Fix sorting
				if ( ! isset( $extra_args['orderby'] ) ) {
					uasort( $children, array( $this, 'sort_get_children_children' ) );
				}

			}
		} else {
			// No custom arguments found, get all objects of stored ID's
			$children = array_map( 'get_post', $child_ids );
		}

		// Return children
		return $children;
	}


	/**
	 * Get parents based on link_id and child_id.
	 *
	 * @access public
	 *
	 * @param int $child_id
	 *
	 * @return array
	 */
	public function get_parents( $child_id ) {

		// build link args
		$link_args = $this->create_link_args( RP4WP_Constants::PM_CHILD, $child_id );
		$link_args['fields'] = 'ids';

		/**
		 * Filter args for link query
		 */
		$link_args = apply_filters( 'rp4wp_get_parents_link_args', $link_args, $child_id );

		// Create link query
		$wp_query = new WP_Query();
		$link_post_ids = $wp_query->query( $link_args );

		$parents = array();
		if ( ! empty( $link_post_ids ) ) {
			foreach ( $link_post_ids as $link_post_id ) {
				// Add post to correct original sort key
				$parents[ $link_post_id ] = get_post( get_post_meta( $link_post_id, RP4WP_Constants::PM_PARENT, true ) );
			}
		}

		return $parents;
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
	 * @param $post_id
	 */
	public function delete_links_related_to( $post_id ) {
		$involved_query = new WP_Query();
		$posts = $involved_query->query( array(
			'post_type'      => RP4WP_Constants::LINK_PT,
			'posts_per_page' => - 1,
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'     => RP4WP_Constants::PM_PARENT,
					'value'   => $post_id,
					'compare' => '=',
				),
				array(
					'key'     => RP4WP_Constants::PM_CHILD,
					'value'   => $post_id,
					'compare' => '=',
				)
			)
		) );

		foreach( $posts as $post ) {
			wp_delete_post( $post->ID, true );
		}
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
			$ref = intval( $ref );
			$query_string .= "ref=" . $ref . '&';
		}

		// The UTM campaign stuff
		$query_string .= sprintf( "utm_source=%s&utm_medium=link&utm_campaign=poweredby", strtolower( preg_replace( "`[^A-z0-9\-.]+`i", '', str_ireplace( ' ', '-', html_entity_decode( get_bloginfo( 'name' ) ) ) ) ) );

		// The URL
		$url = $base_url . htmlentities( $query_string );

		// Display

		return '<small><a href="' . $url . '" target="_blank">Powered By Related Posts for WordPress</a></small>';

	}

	/**
	 * Generate the children list
	 *
	 * @param int $id
	 * @param int $limit
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return string
	 */
	public function generate_children_list( $id, $limit = -1 ) {

		// The content
		$content = '';

		// Get the children
		$related_posts = $this->get_children( $id, array( 'posts_per_page' => $limit ) );

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
						$thumb_size = apply_filters( 'rp4wp_thumbnail_size', 'thumbnail' );

						$content .= "<div class='rp4wp-related-post-image'>" . PHP_EOL;
						$content .= "<a href='" . get_permalink( $rp4wp_post->ID ) . "'>";
						$content .= get_the_post_thumbnail( $rp4wp_post->ID, $thumb_size );
						$content .= "</a>";
						$content .= "</div>" . PHP_EOL;
					}
				}

				$content .= "<div class='rp4wp-related-post-content'>" . PHP_EOL;
				$content .= "<a href='" . get_permalink( $rp4wp_post->ID ) . "'>" . apply_filters( 'rp4wp_post_title', $rp4wp_post->post_title, $rp4wp_post ) . "</a>";

				$excerpt_length = RP4WP::get()->settings->get_option( 'excerpt_length' );
				if ( $excerpt_length > 0 ) {
					$excerpt = wp_trim_words( strip_tags( strip_shortcodes( ( ( '' != $rp4wp_post->post_excerpt ) ? $rp4wp_post->post_excerpt : $rp4wp_post->post_content ) ) ), $excerpt_length );
					$content .= "<p>" . apply_filters( 'rp4wp_post_excerpt', $excerpt, $rp4wp_post->ID ) . "</p>";
				}

				$content .= "</div>" . PHP_EOL;

				$content .= "</li>\n";

				// Reset the postdata
				wp_reset_postdata();
			}

			// Close the wrapper div
			$content .= "</ul>\n";

			$content .= $this->show_love();

			$content .= "</div>\n";

		}

		return $content;

	}

}