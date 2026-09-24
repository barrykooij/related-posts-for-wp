<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

use LV2\WordPress\RelatedPostsForWP\Frontend\Renderer;
use LV2\WordPress\RelatedPostsForWP\Links\LinkRepository;

/**
 * 2.x link manager. Delegates to LinkRepository and Renderer while the rest of the 2.x code still uses it; it becomes a
 * deprecated shim once nothing in the plugin does.
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
		$renderer = new Renderer( $this->links, \LV2\WordPress\RelatedPostsForWP\Main::get()->settings() );

		return $renderer->related_posts_html( (int) $id, (int) $limit, (int) $offset );
	}

}
