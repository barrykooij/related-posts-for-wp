<?php
/**
 * The deprecated RP4WP_Post_Link_Manager class file.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Compat\Deprecation;
use LV2\WordPress\RelatedPostsForWP\Frontend\Renderer;
use LV2\WordPress\RelatedPostsForWP\Links\LinkRepository;
use LV2\WordPress\RelatedPostsForWP\Main;

/**
 * The 2.x link manager.
 *
 * @deprecated 3.0.0 Use \LV2\WordPress\RelatedPostsForWP\Links\LinkRepository.
 */
class RP4WP_Post_Link_Manager {

	/**
	 * The order sort_get_children_children() sorts by. Nothing sets it since 3.0.0.
	 *
	 * @var array<int, int>
	 */
	private $temp_child_order = array();

	/**
	 * The link repository.
	 *
	 * @var LinkRepository
	 */
	private $links;

	/**
	 * Constructor.
	 */
	public function __construct() {
		Deprecation::class_used( __CLASS__, LinkRepository::class );

		$this->links = new LinkRepository();
	}

	/**
	 * Link a child post to a parent post.
	 *
	 * @deprecated 3.0.0
	 *
	 * @param int  $parent_id The parent post.
	 * @param int  $child_id  The child post.
	 * @param bool $batch     Whether to return the insert data instead of inserting the link.
	 *
	 * @return int|array The link ID, or the insert data when $batch is true.
	 */
	public function add( $parent_id, $child_id, $batch = false ) {
		Deprecation::method( __METHOD__, LinkRepository::class . '::add()' );

		if ( true === $batch ) {
			return $this->links->insert_data( absint( $parent_id ), absint( $child_id ) );
		}

		return $this->links->add( absint( $parent_id ), absint( $child_id ) );
	}

	/**
	 * Delete a link.
	 *
	 * @deprecated 3.0.0
	 *
	 * @param int $link_id The link.
	 *
	 * @return void
	 */
	public function delete( $link_id ) {
		Deprecation::method( __METHOD__, LinkRepository::class . '::delete()' );

		$this->links->delete( (int) $link_id );
	}

	/**
	 * The children of a post, in order. $extra_args are added to the query.
	 *
	 * @deprecated 3.0.0
	 *
	 * @param int   $parent_id  The parent post.
	 * @param array $extra_args Extra WP_Query arguments.
	 *
	 * @return array
	 */
	public function get_children( $parent_id, $extra_args = array() ) {
		Deprecation::method( __METHOD__, LinkRepository::class . '::get_children()' );

		return $this->links->get_children( (int) $parent_id, (array) $extra_args );
	}

	/**
	 * The posts that link to a post.
	 *
	 * @deprecated 3.0.0
	 *
	 * @param int $child_id The child post.
	 *
	 * @return array
	 */
	public function get_parents( $child_id ) {
		Deprecation::method( __METHOD__, LinkRepository::class . '::get_parents()' );

		return $this->links->get_parents( (int) $child_id );
	}

	/**
	 * The usort() callback 2.x ordered children with.
	 *
	 * @deprecated 3.0.0
	 *
	 * @param WP_Post $a A post.
	 * @param WP_Post $b Another post.
	 *
	 * @return int
	 */
	public function sort_get_children_children( $a, $b ) {
		Deprecation::method( __METHOD__ );

		return (int) array_search( $a->ID, $this->temp_child_order ) - (int) array_search( $b->ID, $this->temp_child_order ); // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict -- Same as 2.x.
	}

	/**
	 * Delete every link from and to a post.
	 *
	 * @deprecated 3.0.0
	 *
	 * @param int $post_id The post.
	 *
	 * @return void
	 */
	public function delete_links_related_to( $post_id ) {
		Deprecation::method( __METHOD__, LinkRepository::class . '::delete_links_related_to()' );

		$this->links->delete_links_related_to( (int) $post_id );
	}

	/**
	 * The HTML of the related posts of a post.
	 *
	 * @deprecated 3.0.0
	 *
	 * @param int $id     The post.
	 * @param int $limit  The maximum number of related posts; -1 for all.
	 * @param int $offset How many related posts to skip.
	 *
	 * @return string
	 */
	public function generate_children_list( $id, $limit = -1, $offset = 0 ) {
		Deprecation::method( __METHOD__, Renderer::class . '::related_posts_html()' );

		return ( new Renderer( $this->links, Main::get()->settings() ) )->related_posts_html( (int) $id, (int) $limit, (int) $offset );
	}
}
