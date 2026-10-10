<?php
/**
 * The related posts finder class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Related;

use LV2\WordPress\RelatedPostsForWP\Install\Table;
use LV2\WordPress\RelatedPostsForWP\Links\PostState;
use LV2\WordPress\RelatedPostsForWP\PostTypes;
use LV2\WordPress\RelatedPostsForWP\Words\Tokenizer;

/**
 * Finds related posts through the word cache: posts that share the most important words score highest.
 */
class Finder {

	/**
	 * The posts related to a post, most related first.
	 *
	 * Each result has ID, post_title and CMS (the score). Only words of the current version of the tokenizer count, so a
	 * post never gets related posts from a mix of old and new words.
	 *
	 * @param int   $post_id The post.
	 * @param int   $limit   The maximum number of posts; -1 for all.
	 * @param int[] $exclude Posts to leave out, such as the posts a relink keeps.
	 *
	 * @return object[]
	 */
	public function related_posts( int $post_id, int $limit = -1, array $exclude = [] ): array {
		global $wpdb;

		$table   = Table::name();
		$exclude = count( $exclude ) > 0 ? 'AND R.`post_id` NOT IN (' . implode( ',', array_map( 'intval', $exclude ) ) . ')' : '';

		$sql = "
		SELECT P.`ID`, P.`post_title`, ( SUM( O.`weight` ) *  SUM( R.`weight` ) ) AS `CMS`
		FROM `{$table}` O
		INNER JOIN `{$table}` R ON R.`word` = O.`word`
		INNER JOIN `{$wpdb->posts}` P ON P.`ID` = R.`post_id`
		WHERE 1=1
		AND O.`post_id` = %d
		AND O.`version` = %d
		AND R.`version` = %d
		AND R.`post_type` = %s
		AND R.`post_id` != %d
		AND P.`post_status` = 'publish'
		{$exclude}
		GROUP BY P.`id`
		ORDER BY `CMS` DESC
		";

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery -- Our own table; values go through prepare().
		if ( -1 !== $limit ) {
			$sql = $wpdb->prepare( $sql . 'LIMIT 0,%d', $post_id, Tokenizer::VERSION, Tokenizer::VERSION, get_post_type( $post_id ), $post_id, $limit );
		} else {
			$sql = $wpdb->prepare( $sql, $post_id, Tokenizer::VERSION, Tokenizer::VERSION, get_post_type( $post_id ), $post_id );
		}

		return (array) $wpdb->get_results( $sql );
		// phpcs:enable
	}

	/**
	 * Published posts of the supported post types that were not linked automatically yet, newest first.
	 *
	 * @param int $limit The maximum number of posts; -1 for all.
	 *
	 * @return int[]
	 */
	public function not_auto_linked_post_ids( int $limit ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- Escaped post types, and conditions from PostState.
		return array_map( 'intval', $wpdb->get_col( $this->not_linked_sql( 'P.ID' ) . ' ORDER BY P.post_date DESC, P.ID DESC' . ( $limit > 0 ? $wpdb->prepare( ' LIMIT %d', $limit ) : '' ) ) );
	}

	/**
	 * The number of published posts of the supported post types that were not linked automatically yet.
	 *
	 * @return int
	 */
	public function unlinked_post_count(): int {
		global $wpdb;

		return (int) $wpdb->get_var( $this->not_linked_sql( 'COUNT(P.ID)' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- See not_auto_linked_post_ids().
	}

	/**
	 * The query for published posts of the supported post types that were not linked automatically yet.
	 *
	 * @param string $select What to select.
	 *
	 * @return string
	 */
	private function not_linked_sql( string $select ): string {
		global $wpdb;

		return "SELECT {$select} FROM {$wpdb->posts} P WHERE P.post_type IN ('" . implode( "','", PostTypes::supported() ) . "') AND P.post_status = 'publish' AND " . PostState::not_linked_sql( 'P' );
	}
}
