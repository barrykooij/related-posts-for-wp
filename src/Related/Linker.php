<?php
/**
 * The related posts linker class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Related;

use LV2\WordPress\RelatedPostsForWP\Links\LinkPostType;
use LV2\WordPress\RelatedPostsForWP\Links\LinkRepository;

/**
 * Links posts to their most related posts automatically.
 */
class Linker {

	/**
	 * The related posts finder.
	 *
	 * @var Finder
	 */
	private Finder $finder;

	/**
	 * The link repository.
	 *
	 * @var LinkRepository
	 */
	private LinkRepository $links;

	/**
	 * Constructor.
	 *
	 * @param Finder|null         $finder The finder; a new one when null.
	 * @param LinkRepository|null $links  The link repository; a new one when null.
	 */
	public function __construct( ?Finder $finder = null, ?LinkRepository $links = null ) {
		$this->finder = $finder ?? new Finder();
		$this->links  = $links ?? new LinkRepository();
	}

	/**
	 * Link a post to its most related posts and mark it as linked automatically.
	 *
	 * The links are inserted in one query, like 2.x, so rp4wp_after_link_add does not fire for them (known issue K10).
	 *
	 * @param int $post_id The post.
	 * @param int $amount  How many related posts to link.
	 *
	 * @return void
	 */
	public function link_post( int $post_id, int $amount ): void {
		global $wpdb;

		$related_posts = $this->finder->related_posts( $post_id, $amount );

		if ( count( $related_posts ) > 0 ) {
			$batch = [];
			foreach ( $related_posts as $related_post ) {
				$batch[] = $this->links->insert_data( $post_id, (int) $related_post->ID );
			}

			// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Values are built from integers, constants and dates.
			$wpdb->query( "INSERT INTO `{$wpdb->posts}` (`post_date`,`post_date_gmt`,`post_content`,`post_title`,`post_type`,`post_status`) VALUES " . implode( ',', array_column( $batch, 'post' ) ) );

			// The links get consecutive IDs, starting at the insert ID.
			$link_id = (int) $wpdb->insert_id;
			$meta    = [];
			foreach ( $batch as $data ) {
				foreach ( $data['meta'] as $values ) {
					$meta[] = sprintf( $values, $link_id );
				}
				++$link_id;
			}

			$wpdb->query( "INSERT INTO `{$wpdb->postmeta}` (`post_id`,`meta_key`,`meta_value`) VALUES " . implode( ',', $meta ) );
			// phpcs:enable
		}

		update_post_meta( $post_id, LinkPostType::META_AUTO_LINKED, 1 );
	}

	/**
	 * Link every post that was not linked automatically yet.
	 *
	 * @param int $amount      How many related posts to link per post.
	 * @param int $post_amount The maximum number of posts to link; -1 for all.
	 *
	 * @return void
	 */
	public function link_all( int $amount, int $post_amount = -1 ): void {
		foreach ( $this->finder->not_auto_linked_post_ids( $post_amount ) as $post_id ) {
			$this->link_post( (int) $post_id, $amount );
		}
	}
}
