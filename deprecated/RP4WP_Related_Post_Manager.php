<?php
/**
 * The deprecated RP4WP_Related_Post_Manager class file.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Compat\Deprecation;
use LV2\WordPress\RelatedPostsForWP\PostTypes;
use LV2\WordPress\RelatedPostsForWP\Related\Finder;
use LV2\WordPress\RelatedPostsForWP\Related\Linker;

/**
 * The 2.x related post manager.
 *
 * @deprecated 3.0.0 Use \LV2\WordPress\RelatedPostsForWP\Related\Finder and \LV2\WordPress\RelatedPostsForWP\Related\Linker.
 */
class RP4WP_Related_Post_Manager {

	/**
	 * Constructor.
	 */
	public function __construct() {
		Deprecation::class_used( __CLASS__, Finder::class );
	}

	/**
	 * The posts most related to a post, with their score in the CMS column.
	 *
	 * @deprecated 3.0.0
	 *
	 * @param int $post_id The post.
	 * @param int $limit   The maximum number of posts; -1 for all.
	 *
	 * @return array
	 */
	public function get_related_posts( $post_id, $limit = -1 ) {
		Deprecation::method( __METHOD__, Finder::class . '::related_posts()' );

		return ( new Finder() )->related_posts( (int) $post_id, (int) $limit );
	}

	/**
	 * The IDs of posts that were not linked automatically yet.
	 *
	 * @deprecated 3.0.0
	 *
	 * @param int $limit The maximum number of IDs.
	 *
	 * @return array
	 */
	public function get_not_auto_linked_posts_ids( $limit ) {
		Deprecation::method( __METHOD__, Finder::class . '::not_auto_linked_post_ids()' );

		return ( new Finder() )->not_auto_linked_post_ids( (int) $limit );
	}

	/**
	 * The number of posts that were not linked automatically yet.
	 *
	 * @deprecated 1.9.0
	 *
	 * @param string $post_type Not used.
	 *
	 * @return mixed
	 */
	public function get_uncached_post_count( $post_type ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- The 2.x signature.
		Deprecation::method( __METHOD__, Finder::class . '::unlinked_post_count()', '1.9.0' );

		return self::unlinked_post_count();
	}

	/**
	 * The number of posts that were not linked automatically yet.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return mixed The count as the database returned it in 2.x: a string, or 0 when nothing is left.
	 */
	public function get_unlinked_post_count() {
		Deprecation::method( __METHOD__, Finder::class . '::unlinked_post_count()' );

		return self::unlinked_post_count();
	}

	/**
	 * Link the most related posts to a post.
	 *
	 * @deprecated 3.0.0
	 *
	 * @param int $post_id The post.
	 * @param int $amount  The number of related posts.
	 *
	 * @return bool
	 */
	public function link_related_post( $post_id, $amount ) {
		Deprecation::method( __METHOD__, Linker::class . '::link_post()' );

		( new Linker() )->link_post( (int) $post_id, (int) $amount );

		return true;
	}

	/**
	 * The post data of a batch insert.
	 *
	 * @deprecated 3.0.0
	 *
	 * @param array $batch The batch data.
	 *
	 * @return mixed
	 */
	public function batch_data_get_post( $batch ) {
		Deprecation::method( __METHOD__ );

		return $batch['post'];
	}

	/**
	 * The meta data of a batch insert.
	 *
	 * @deprecated 3.0.0
	 *
	 * @param array $batch The batch data.
	 *
	 * @return string
	 */
	public function batch_data_get_meta( $batch ) {
		Deprecation::method( __METHOD__ );

		return implode( ',', $batch['meta'] );
	}

	/**
	 * Put a post ID into batch data.
	 *
	 * @deprecated 3.0.0
	 *
	 * @param string $batch The batch data, with a placeholder for the post ID.
	 * @param int    $pid   The post ID.
	 *
	 * @return string
	 */
	public function batch_data_set_pid( $batch, $pid ) {
		Deprecation::method( __METHOD__ );

		return sprintf( $batch, $pid );
	}

	/**
	 * Link the most related posts to posts that were not linked automatically yet.
	 *
	 * @deprecated 3.0.0
	 *
	 * @param int $rel_amount  The number of related posts per post.
	 * @param int $post_amount The maximum number of posts to link; -1 for all.
	 *
	 * @return bool
	 */
	public function link_related_posts( $rel_amount, $post_amount = -1 ) {
		Deprecation::method( __METHOD__, Linker::class . '::link_all()' );

		( new Linker() )->link_all( (int) $rel_amount, (int) $post_amount );

		return true;
	}

	/**
	 * The post types the plugin works with.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return array
	 */
	public static function get_supported_post_types() {
		Deprecation::method( __METHOD__, PostTypes::class . '::supported()' );

		return PostTypes::supported();
	}

	/**
	 * The unlinked post count in its 2.x shape.
	 *
	 * @return mixed
	 */
	private static function unlinked_post_count() {
		$count = ( new Finder() )->unlinked_post_count();

		return 0 === $count ? 0 : (string) $count;
	}
}
