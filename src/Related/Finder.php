<?php
/**
 * The related posts finder class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Related;

use LV2\WordPress\RelatedPostsForWP\Links\PostState;
use LV2\WordPress\RelatedPostsForWP\PostTypes;

/**
 * Finds related posts through the word cache: posts whose words are most like the post's words score highest.
 */
class Finder {

	/**
	 * The posts related to a post, most related first: those of its post type whose words are most like its words (see
	 * FinderQuery). Only words of the current version count, so a post never gets related posts from a mix of old and
	 * new words.
	 *
	 * Each result has ID, post_title and CMS (the score, from 0 to 1).
	 *
	 * @param int   $post_id The post.
	 * @param int   $limit   The maximum number of posts; -1 for all.
	 * @param int[] $exclude Posts to leave out, such as the posts a relink keeps.
	 *
	 * @return object[]
	 */
	public function related_posts( int $post_id, int $limit = -1, array $exclude = [] ): array {
		global $wpdb;

		$post_type = (string) get_post_type( $post_id );
		$clauses   = [
			'join'  => [],
			'where' => [],
		];

		if ( count( $exclude ) > 0 ) {
			$clauses['where'][] = 'R.post_id NOT IN (' . implode( ',', array_map( 'intval', $exclude ) ) . ')';
		}

		/**
		 * Filters the extra joins and conditions of the query that finds the related posts of a post. The query
		 * names the post's words `O`, the related posts' words `R`, the words table `S` and the related posts `P`.
		 * Replaces `rp4wp_get_related_posts_sql` of 2.x, which got the finished query.
		 *
		 * @since 3.0.0
		 *
		 * @param array  $clauses   `join` and `where`: lists of SQL, escaped already.
		 * @param int    $post_id   The post.
		 * @param string $post_type The post type of the post.
		 */
		$clauses = (array) apply_filters( 'rp4wp_finder_clauses', $clauses, $post_id, $post_type );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- Built by FinderQuery from integers and escaped values.
		$results = (array) $wpdb->get_results( FinderQuery::sql( $post_id, [ $post_type ], $clauses, $limit ) );

		return $this->with_titles( $results );
	}

	/**
	 * The results with the title of each post. A second query: the title in the grouped query of the finder would make
	 * the database sort on disk.
	 *
	 * @param object[] $results The results.
	 *
	 * @return object[]
	 */
	private function with_titles( array $results ): array {
		global $wpdb;

		if ( count( $results ) < 1 ) {
			return $results;
		}

		$ids = implode( ',', array_map( 'intval', array_column( $results, 'ID' ) ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Integers.
		$titles = (array) $wpdb->get_results( "SELECT ID, post_title FROM {$wpdb->posts} WHERE ID IN ({$ids})", OBJECT_K );

		foreach ( $results as $result ) {
			$result->post_title = isset( $titles[ $result->ID ] ) ? (string) $titles[ $result->ID ]->post_title : '';
		}

		return $results;
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
	 * Published posts of the supported post types that were linked automatically before a time, newest first: the
	 * posts the update of 3.0 links again.
	 *
	 * @param int $since The time, in milliseconds.
	 * @param int $limit The maximum number of posts.
	 *
	 * @return int[]
	 */
	public function linked_before_post_ids( int $since, int $limit ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- Escaped post types, a condition from PostState and an integer.
		return array_map( 'intval', $wpdb->get_col( $this->posts_sql( 'P.ID', PostState::linked_before_sql( 'P', $since ) ) . ' ORDER BY P.ID DESC LIMIT ' . max( 1, $limit ) ) );
	}

	/**
	 * How many published posts of the supported post types were linked automatically before a time.
	 *
	 * @param int $since The time, in milliseconds.
	 *
	 * @return int
	 */
	public function linked_before_post_count( int $since ): int {
		global $wpdb;

		return (int) $wpdb->get_var( $this->posts_sql( 'COUNT(P.ID)', PostState::linked_before_sql( 'P', $since ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- See linked_before_post_ids().
	}

	/**
	 * The query for published posts of the supported post types that meet a condition.
	 *
	 * @param string $select    What to select.
	 * @param string $condition The condition.
	 *
	 * @return string
	 */
	private function posts_sql( string $select, string $condition ): string {
		global $wpdb;

		return "SELECT {$select} FROM {$wpdb->posts} P WHERE P.post_type IN ('" . implode( "','", array_map( 'esc_sql', PostTypes::supported() ) ) . "') AND P.post_status = 'publish' AND " . $condition;
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
