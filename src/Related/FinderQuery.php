<?php
/**
 * The finder query class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Related;

use LV2\WordPress\RelatedPostsForWP\Install\Table;
use LV2\WordPress\RelatedPostsForWP\Words\Statistics;
use LV2\WordPress\RelatedPostsForWP\Words\Tokenizer;

/**
 * The query that finds the posts most related to a post (decision D49), for the finders of both plugins.
 *
 * The score of a related post is the cosine similarity of the two posts' word vectors: the sum of the products of the
 * weights of the words they share, between 0 and 1. Only words of the current version count, and words in more posts
 * than the ceiling of the word statistics are skipped. Posts with the same score come in the order of their ID, so the
 * result is the same every time. There is no minimum score: a weak match only takes a place no stronger match claims.
 * It selects no text column, so the database groups in memory.
 */
final class FinderQuery {

	/**
	 * The query.
	 *
	 * @param int                                    $post_id    The post.
	 * @param string[]                               $post_types The post types of the related posts.
	 * @param array{join: string[], where: string[]} $clauses    Extra joins and conditions, as SQL that is safe already.
	 * @param int                                    $limit      The maximum number of posts; -1 for all.
	 *
	 * @return string
	 */
	public static function sql( int $post_id, array $post_types, array $clauses, int $limit ): string {
		global $wpdb;

		$cache   = Table::name();
		$words   = Statistics::words_table();
		$types   = "'" . implode( "','", array_map( 'esc_sql', $post_types ) ) . "'";
		$joins   = implode( "\n", array_map( 'strval', (array) ( $clauses['join'] ?? [] ) ) );
		$wheres  = implode( '', array_map( static fn( $where ) => "\n\t\t\tAND " . $where, (array) ( $clauses['where'] ?? [] ) ) );
		$version = (int) Tokenizer::VERSION;
		$ceiling = ( new Statistics() )->ceiling();

		// Every value is an integer; the clauses come from the finders and their filter, which escape what they add.
		$sql = "SELECT R.post_id AS ID, SUM( O.weight * R.weight ) AS CMS
			FROM {$cache} O
			INNER JOIN {$words} S ON S.word = O.word AND S.df <= {$ceiling}
			INNER JOIN {$cache} R ON R.word = O.word AND R.version = {$version}
			INNER JOIN {$wpdb->posts} P ON P.ID = R.post_id AND P.post_status = 'publish'
			{$joins}
			WHERE O.post_id = {$post_id} AND O.version = {$version}
			AND R.post_type IN ( {$types} )
			AND R.post_id != {$post_id}{$wheres}
			GROUP BY R.post_id
			ORDER BY CMS DESC, R.post_id ASC";

		return $limit > 0 ? $sql . ' LIMIT 0, ' . (int) $limit : $sql;
	}
}
