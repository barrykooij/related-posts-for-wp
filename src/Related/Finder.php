<?php
/**
 * The related posts finder class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Related;

use LV2\WordPress\RelatedPostsForWP\Install\Table;
use LV2\WordPress\RelatedPostsForWP\Links\LinkPostType;
use LV2\WordPress\RelatedPostsForWP\PostTypes;

/**
 * Finds related posts through the word cache: posts that share the most important words score highest.
 */
class Finder {

	/**
	 * The posts related to a post, most related first.
	 *
	 * Each result has ID, post_title and CMS (the score).
	 *
	 * @param int $post_id The post.
	 * @param int $limit   The maximum number of posts; -1 for all.
	 *
	 * @return object[]
	 */
	public function related_posts( int $post_id, int $limit = -1 ): array {
		global $wpdb;

		$table = Table::name();

		$sql = "
		SELECT P.`ID`, P.`post_title`, ( SUM( O.`weight` ) *  SUM( R.`weight` ) ) AS `CMS`
		FROM `{$table}` O
		INNER JOIN `{$table}` R ON R.`word` = O.`word`
		INNER JOIN `{$wpdb->posts}` P ON P.`ID` = R.`post_id`
		WHERE 1=1
		AND O.`post_id` = %d
		AND R.`post_type` = %s
		AND R.`post_id` != %d
		AND P.`post_status` = 'publish'
		GROUP BY P.`id`
		ORDER BY `CMS` DESC
		";

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery -- Our own table; values go through prepare().
		if ( -1 !== $limit ) {
			$sql = $wpdb->prepare( $sql . 'LIMIT 0,%d', $post_id, get_post_type( $post_id ), $post_id, $limit );
		} else {
			$sql = $wpdb->prepare( $sql, $post_id, get_post_type( $post_id ), $post_id );
		}

		return (array) $wpdb->get_results( $sql );
		// phpcs:enable
	}

	/**
	 * Published posts of the supported post types that were not linked automatically yet.
	 *
	 * @param int $limit The maximum number of posts; -1 for all.
	 *
	 * @return int[]
	 */
	public function not_auto_linked_post_ids( int $limit ): array {
		return get_posts(
			[
				'fields'         => 'ids',
				'post_type'      => PostTypes::supported(),
				'posts_per_page' => $limit,
				'post_status'    => 'publish',
				'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_query -- Finds posts without the flag.
					[
						'key'     => LinkPostType::META_AUTO_LINKED,
						'compare' => 'NOT EXISTS',
						'value'   => '',
					],
				],
			]
		);
	}

	/**
	 * The number of published posts of the supported post types that were not linked automatically yet.
	 *
	 * @return int
	 */
	public function unlinked_post_count(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- Constants and escaped post types.
		$count = $wpdb->get_var( 'SELECT COUNT(P.ID) FROM ' . $wpdb->posts . ' P LEFT JOIN ' . $wpdb->postmeta . " PM ON (P.ID = PM.post_id AND PM.meta_key = '" . LinkPostType::META_AUTO_LINKED . "') WHERE 1=1 AND P.post_type IN ('" . implode( "','", PostTypes::supported() ) . "') AND P.post_status = 'publish' AND PM.post_id IS NULL GROUP BY P.post_status" );

		return is_numeric( $count ) ? (int) $count : 0;
	}
}
