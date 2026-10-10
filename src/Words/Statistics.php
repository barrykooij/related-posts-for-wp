<?php
/**
 * The word statistics class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Words;

use LV2\WordPress\RelatedPostsForWP\Database\Schema;
use LV2\WordPress\RelatedPostsForWP\Database\Transaction;
use LV2\WordPress\RelatedPostsForWP\Install\Table;

/**
 * What the word cache knows about its words as a whole, and the weights it gives them (decisions D49, D50 and D54).
 *
 * The weight of a word of a post is `tf * idf / norm`: `tf` is how many times the word counts in the post (a title word
 * counts 80 times), `idf = ln( ( N + 1 ) / df )` says how rare the word is (`N` posts have words, `df` of them have
 * this one), and `norm` is the length of the post's vector, so the weights of a post square-sum to 1 and the score of
 * two posts, the sum of the products of the weights of the words they share, is their cosine similarity.
 *
 * Caching a post has two passes. Pass one picks the words with the highest `tf * idf` and stores them with their `tf`,
 * and keeps `df` (the words table) and `N` up to date. Pass two weighs: it needs only the tables. One post is weighed
 * when it is saved; every post is weighed again when an installation or a refresh has saved them all, because the
 * `df` of every word changed a little.
 */
final class Statistics {

	/**
	 * The option with the number of posts that have words of the current version: `N`.
	 */
	public const OPTION_POSTS = 'rp4wp_cached_posts';

	/**
	 * How many words a post stores by default.
	 */
	public const AMOUNT = 25;

	/**
	 * The ceiling for the document frequency of the words the finder compares is at least this.
	 */
	private const CEILING_MINIMUM = 25;

	/**
	 * Words of more than this share of all posts are not compared by the finder, unless that is below the minimum.
	 */
	private const CEILING_SHARE = 0.02;

	/**
	 * How many words a post stores.
	 *
	 * @return int
	 */
	public static function amount(): int {
		/**
		 * Filters how many words are stored per post: the words with the highest `tf * idf`.
		 *
		 * @since 1.0.0
		 * @since 3.0.0 The default is 25, picked by `tf * idf`.
		 *
		 * @param int $amount The number of words. Default 25.
		 */
		return max( 1, (int) apply_filters( 'rp4wp_cache_word_amount', self::AMOUNT ) );
	}

	/**
	 * How rare a word is: `ln( ( N + 1 ) / df )`. Above 0 for every word of a post that has words, also on a site with
	 * one post.
	 *
	 * @param int $df    In how many posts the word is.
	 * @param int $posts How many posts have words (`N`).
	 *
	 * @return float
	 */
	public static function idf( int $df, int $posts ): float {
		return max( 0.0, log( ( max( 0, $posts ) + 1 ) / max( 1, $df ) ) );
	}

	/**
	 * The words a post stores: those with the highest `tf * idf`. On an empty words table every `idf` is the same, so
	 * the words that count most are picked; words with the same `tf * idf` keep their order.
	 *
	 * @param array<int|string, int> $counts The words of the post and how many times each counts, most first.
	 * @param array<string, int>     $df     In how many posts each word is.
	 * @param int                    $posts  How many posts have words (`N`).
	 * @param int                    $amount How many words to pick.
	 *
	 * @return array<string, int> Word => how many times it counts.
	 */
	public static function pick( array $counts, array $df, int $posts, int $amount ): array {
		$scores = [];
		$index  = 0;
		foreach ( $counts as $word => $count ) {
			$word     = (string) $word;
			$scores[] = [ $word, (int) $count, $count * self::idf( $df[ $word ] ?? 0, $posts ), $index++ ];
		}

		usort(
			$scores,
			static function ( array $a, array $b ): int {
				return [ $b[2], $a[3] ] <=> [ $a[2], $b[3] ];
			}
		);

		$picked = [];
		foreach ( array_slice( $scores, 0, max( 0, $amount ) ) as [ $word, $count ] ) {
			$picked[ $word ] = $count;
		}

		return $picked;
	}

	/**
	 * The weights of the words of a post: `tf * idf / norm`. Pass two does the same in the database.
	 *
	 * @param array<int|string, int> $counts The words of the post and how many times each counts.
	 * @param array<string, int>     $df     In how many posts each word is.
	 * @param int                    $posts  How many posts have words (`N`).
	 *
	 * @return array<string, float> Word => weight.
	 */
	public static function weights( array $counts, array $df, int $posts ): array {
		$weights = [];
		foreach ( $counts as $word => $count ) {
			$weights[ (string) $word ] = $count * self::idf( $df[ (string) $word ] ?? 1, $posts );
		}

		$norm = sqrt(
			array_sum(
				array_map(
					static function ( float $weight ): float {
						return $weight * $weight;
					},
					$weights
				)
			)
		);

		foreach ( $weights as $word => $weight ) {
			$weights[ $word ] = $norm > 0 ? $weight / $norm : 0.0;
		}

		return $weights;
	}

	/**
	 * How many posts have words of the current version: `N`. Counted when it is not known yet.
	 *
	 * @return int
	 */
	public function posts(): int {
		$posts = get_option( self::OPTION_POSTS, null );
		if ( null === $posts || false === $posts ) {
			$posts = $this->count_posts();
			update_option( self::OPTION_POSTS, $posts, true );
		}

		return max( 0, (int) $posts );
	}

	/**
	 * Count posts with words in or out of `N`, after their words were stored or removed.
	 *
	 * @param int $change How many posts to add; negative to remove.
	 *
	 * @return void
	 */
	public function add_posts( int $change ): void {
		if ( 0 !== $change ) {
			update_option( self::OPTION_POSTS, max( 0, $this->posts() + $change ), true );
		}
	}

	/**
	 * In how many posts each of some words is.
	 *
	 * @param array<int|string> $words The words.
	 *
	 * @return array<string, int> Word => document frequency, for the words the table has.
	 */
	public function df( array $words ): array {
		global $wpdb;

		$words = array_values( array_unique( array_map( 'strval', $words ) ) );
		if ( count( $words ) < 1 ) {
			return [];
		}

		$df = [];
		foreach ( array_chunk( $words, 500 ) as $chunk ) {
			$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQL.NotPrepared -- The plugin's own table; the placeholders are built from the word count.
			foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT word, df FROM ' . self::words_table() . " WHERE word IN ({$placeholders})", $chunk ) ) as $row ) {
				$df[ (string) $row->word ] = (int) $row->df;
			}
		}

		return $df;
	}

	/**
	 * Update the document frequencies when a post's words change. Inside the transaction that changes the words, so a
	 * failed write undoes both.
	 *
	 * @param array<int|string> $removed The words the post no longer has.
	 * @param array<int|string> $added   The words the post has now and did not have.
	 *
	 * @return void
	 *
	 * @throws \RuntimeException When a write fails.
	 */
	public function change( array $removed, array $added ): void {
		global $wpdb;

		$table   = self::words_table();
		$removed = array_values( array_unique( array_map( 'strval', $removed ) ) );
		$added   = array_values( array_unique( array_map( 'strval', $added ) ) );

		foreach ( array_chunk( $removed, 500 ) as $chunk ) {
			$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQL.NotPrepared -- The plugin's own table; the placeholders are built from the word count.
			Transaction::query( $wpdb->prepare( "UPDATE {$table} SET df = df - 1 WHERE df > 0 AND word IN ({$placeholders})", $chunk ) );
			Transaction::query( $wpdb->prepare( "DELETE FROM {$table} WHERE df = 0 AND word IN ({$placeholders})", $chunk ) );
			// phpcs:enable
		}

		foreach ( array_chunk( $added, 500 ) as $chunk ) {
			$values = implode( ',', array_fill( 0, count( $chunk ), '(%s, 1)' ) );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQL.NotPrepared -- The plugin's own table; the placeholders are built from the word count.
			Transaction::query( $wpdb->prepare( "INSERT INTO {$table} (word, df) VALUES {$values} ON DUPLICATE KEY UPDATE df = df + 1", $chunk ) );
		}
	}

	/**
	 * Count every document frequency and `N` again from the word cache, which corrects what interrupted requests left
	 * behind. Readers see the old counts until the new ones are complete.
	 *
	 * @return void
	 */
	public function recount(): void {
		global $wpdb;

		$table = self::words_table();
		$cache = Table::name();

		Transaction::run(
			static function () use ( $wpdb, $table, $cache ) {
				Transaction::query( "DELETE FROM {$table}" );
				Transaction::query( $wpdb->prepare( "INSERT INTO {$table} (word, df) SELECT word, COUNT(*) FROM {$cache} WHERE version = %d GROUP BY word", Tokenizer::VERSION ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The plugin's own tables.
			}
		);

		update_option( self::OPTION_POSTS, $this->count_posts(), true );
	}

	/**
	 * Pass two: weigh the words of posts with the document frequencies and `N` as they are now, in one query.
	 *
	 * @param int[] $post_ids The posts.
	 *
	 * @return void
	 */
	public function weigh( array $post_ids ): void {
		global $wpdb;

		$post_ids = array_values( array_unique( array_filter( array_map( 'intval', $post_ids ) ) ) );
		if ( count( $post_ids ) < 1 ) {
			return;
		}

		$ids   = implode( ',', $post_ids );
		$cache = Table::name();
		$words = self::words_table();
		$total = (float) ( $this->posts() + 1 );
		$idf   = static function ( string $alias ) use ( $total ): string {
			return sprintf( 'GREATEST( LN( %F / GREATEST( COALESCE( %s.df, 1 ), 1 ) ), 0 )', $total, $alias );
		};

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- The plugin's own tables; integers and a float from this class.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$cache} C
				INNER JOIN (
					SELECT C2.post_id, SQRT( SUM( POW( C2.tf * {$idf( 'S2' )}, 2 ) ) ) AS norm
					FROM {$cache} C2
					LEFT JOIN {$words} S2 ON S2.word = C2.word
					WHERE C2.post_id IN ({$ids}) AND C2.version = %d
					GROUP BY C2.post_id
				) X ON X.post_id = C.post_id
				LEFT JOIN {$words} S ON S.word = C.word
				SET C.weight = IF( X.norm > 0, C.tf * {$idf( 'S' )} / X.norm, 0 )
				WHERE C.post_id IN ({$ids}) AND C.version = %d",
				Tokenizer::VERSION,
				Tokenizer::VERSION
			)
		);
		// phpcs:enable
	}

	/**
	 * The highest document frequency of the words the finder compares: rarer words decide what is related, and the
	 * most common words are skipped, which keeps the finder fast on large sites. At least 25, or 2 percent of `N`.
	 *
	 * @return int
	 */
	public function ceiling(): int {
		$posts = $this->posts();

		/**
		 * Filters the highest document frequency of the words the finder compares. Words in more posts are skipped.
		 *
		 * @since 3.0.0
		 *
		 * @param int $ceiling The ceiling.
		 * @param int $posts   How many posts have words.
		 */
		return (int) apply_filters( 'rp4wp_word_df_ceiling', max( self::CEILING_MINIMUM, (int) ceil( self::CEILING_SHARE * $posts ) ), $posts );
	}

	/**
	 * The words table.
	 *
	 * @return string
	 */
	public static function words_table(): string {
		return Schema::table( Schema::WORDS );
	}

	/**
	 * Count the posts that have words of the current version.
	 *
	 * @return int
	 */
	private function count_posts(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- The plugin's own table.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT( DISTINCT post_id ) FROM ' . Table::name() . ' WHERE version = %d', Tokenizer::VERSION ) );
	}
}
