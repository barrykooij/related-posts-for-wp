<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

use LV2\WordPress\RelatedPostsForWP\PostTypes;
use LV2\WordPress\RelatedPostsForWP\Related\Finder;
use LV2\WordPress\RelatedPostsForWP\Related\Linker;

/**
 * 2.x related post manager. Delegates to the classes in src/Related while the rest of the 2.x code still uses it; it
 * becomes a deprecated shim once nothing in the plugin does.
 */
class RP4WP_Related_Post_Manager {

	/**
	 * Get related posts by post id
	 *
	 * @param int $post_id
	 * @param int $limit
	 *
	 * @return array
	 */
	public function get_related_posts( $post_id, $limit = - 1 ) {
		return ( new Finder() )->related_posts( (int) $post_id, (int) $limit );
	}

	/**
	 * Get non auto linked posts
	 *
	 * @param $limit
	 *
	 * @return array
	 */
	public function get_not_auto_linked_posts_ids( $limit ) {
		return ( new Finder() )->not_auto_linked_post_ids( (int) $limit );
	}

	/**
	 * Deprecated, use get_unlinked_post_count() instead
	 *
	 * @deprecated 1.9.0
	 *
	 * @param $post_type
	 *
	 * @return mixed
	 */
	public function get_uncached_post_count( $post_type ) {

		// Deprecated notice
		_deprecated_function( __FUNCTION__, '1.9.0', __CLASS__ . '->get_uncached_post_count()' );

		return $this->get_unlinked_post_count( $post_type );
	}

	/**
	 * Get the unlinked post count
	 *
	 * @since  1.6.0
	 * @access public
	 *
	 * @return mixed The count as the database returns it: a string, or 0 when nothing is left.
	 */
	public function get_unlinked_post_count() {
		$count = ( new Finder() )->unlinked_post_count();

		return 0 === $count ? 0 : (string) $count;
	}

	/**
	 * Link x related posts to post
	 *
	 * @param $post_id
	 * @param $amount
	 *
	 * @return boolean
	 */
	public function link_related_post( $post_id, $amount ) {
		( new Linker() )->link_post( (int) $post_id, (int) $amount );

		return true;
	}

	/**
	 * Get post batch data
	 *
	 * @param $batch
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return mixed
	 */
	public function batch_data_get_post( $batch ) {
		return $batch['post'];
	}

	/**
	 * Get meta batch data
	 *
	 * @param $batch
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return string
	 */
	public function batch_data_get_meta( $batch ) {
		return implode( ',', $batch['meta'] );
	}

	/**
	 * Set the post ID's in batch data
	 *
	 * @param $batch
	 * @param $pid
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return string
	 */
	public function batch_data_set_pid( $batch, $pid ) {
		return sprintf( $batch, $pid );
	}

	/**
	 * Link x related posts to y not already linked posts
	 *
	 * @param int $rel_amount
	 * @param int $post_amount
	 *
	 * @return boolean
	 */
	public function link_related_posts( $rel_amount, $post_amount = - 1 ) {
		( new Linker() )->link_all( (int) $rel_amount, (int) $post_amount );

		return true;
	}

	/**
	 * Returns array with escaped supported post types
	 * @return array
	 */
	public static function get_supported_post_types() {
		return PostTypes::supported();
	}

}
