<?php
/**
 * The quality metrics class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Quality;

/**
 * The numbers the quality harness reports for one run of an algorithm on a corpus. Pure functions, without WordPress,
 * so they are unit tested on their own.
 *
 * Precision at k is normalized: the hits in the first k related posts are divided by k, or by the number of related
 * posts the labels know when that is smaller, so a perfect result is 1 also for a post with only two labelled
 * matches. A missing related post counts as a miss.
 */
final class Metrics {

	/**
	 * How many related posts a post shows by default; the link metrics look at this many.
	 */
	public const SHOWN = 3;

	/**
	 * Tokens that are not words: the taxonomy and post tokens of the new algorithm (`cat:12`, `post:34`).
	 */
	private const NOT_A_WORD = '/^(cat|tag|tax|post):/';

	/**
	 * Han, Hiragana, Katakana and Hangul: scripts written without spaces.
	 */
	private const NO_SPACES = '/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}]/u';

	/**
	 * Every metric of one run.
	 *
	 * @param int[]                    $post_ids   Every post of the corpus.
	 * @param array<int, int[]>        $rankings   Post ID => its related posts, most related first.
	 * @param array<int, int[]>        $relevant   Post ID => the posts that count as related to it. Only these posts
	 *                                             are used for the precision metrics.
	 * @param array<int, string[]>     $tokens     Post ID => the tokens stored for it.
	 * @param array<int, string>       $titles     Post ID => its title.
	 * @param array<string, bool>|null $stop_words The reference stop words (see stop_set()), or null when the language
	 *                                             has none.
	 *
	 * @return array<string, float|int|null>
	 */
	public static function compute( array $post_ids, array $rankings, array $relevant, array $tokens, array $titles, ?array $stop_words ): array {
		return array_merge(
			self::precision( count( $post_ids ), $rankings, $relevant ),
			self::links( $post_ids, $rankings ),
			self::words_metrics( $tokens, $titles, $stop_words )
		);
	}

	/**
	 * Precision at 3 and 5, success at 3 and the precision at 3 of a random pick, over the posts with labels.
	 *
	 * @param int               $count    The number of posts in the corpus.
	 * @param array<int, int[]> $rankings Post ID => its related posts.
	 * @param array<int, int[]> $relevant Post ID => the posts that count as related to it.
	 *
	 * @return array<string, float|int|null>
	 */
	public static function precision( int $count, array $rankings, array $relevant ): array {
		$p3      = [];
		$p5      = [];
		$success = [];
		$random  = [];

		foreach ( $relevant as $post_id => $good ) {
			$good = array_values( array_diff( array_unique( array_map( 'intval', $good ) ), [ (int) $post_id ] ) );
			if ( 0 === count( $good ) ) {
				continue;
			}

			$set     = array_flip( $good );
			$ranking = array_map( 'intval', $rankings[ $post_id ] ?? [] );
			$hits3   = self::hits( $ranking, $set, 3 );

			$p3[]      = $hits3 / min( 3, count( $good ) );
			$p5[]      = self::hits( $ranking, $set, 5 ) / min( 5, count( $good ) );
			$success[] = $hits3 > 0 ? 1 : 0;
			$random[]  = min( 1, ( 3 * count( $good ) / max( 1, $count - 1 ) ) / min( 3, count( $good ) ) );
		}

		return [
			'evaluated'           => count( $p3 ),
			'precision_at_3'      => self::mean( $p3 ),
			'precision_at_5'      => self::mean( $p5 ),
			'success_at_3'        => self::mean( $success ),
			'random_precision_at_3' => self::mean( $random ),
		];
	}

	/**
	 * What the shown links look like: full lists, coverage, hubs and symmetry.
	 *
	 * @param int[]             $post_ids Every post of the corpus.
	 * @param array<int, int[]> $rankings Post ID => its related posts.
	 *
	 * @return array<string, float|int|null>
	 */
	public static function links( array $post_ids, array $rankings ): array {
		$count   = count( $post_ids );
		$shown   = [];
		$inbound = array_fill_keys( $post_ids, 0 );
		$full    = 0;
		$links   = 0;
		$mutual  = 0;

		foreach ( $post_ids as $post_id ) {
			$shown[ $post_id ] = array_slice( array_map( 'intval', $rankings[ $post_id ] ?? [] ), 0, self::SHOWN );
		}

		foreach ( $shown as $post_id => $children ) {
			if ( count( $children ) >= self::SHOWN ) {
				++$full;
			}

			foreach ( $children as $child ) {
				++$links;
				$inbound[ $child ] = ( $inbound[ $child ] ?? 0 ) + 1;

				if ( in_array( $post_id, $shown[ $child ] ?? [], true ) ) {
					++$mutual;
				}
			}
		}

		rsort( $inbound );
		$hubs = (int) max( 1, ceil( $count / 100 ) );

		return [
			'full_lists'  => self::ratio( $full, $count ),
			'coverage'    => self::ratio( count( array_filter( $inbound ) ), $count ),
			'hub_share'   => self::ratio( array_sum( array_slice( $inbound, 0, $hubs ) ), $links ),
			'max_inbound' => (int) ( $inbound[0] ?? 0 ),
			'symmetry'    => self::ratio( $mutual, $links ),
		];
	}

	/**
	 * What the stored words look like: stop words that leaked in, the share that comes from the title, and how many.
	 *
	 * @param array<int, string[]>     $tokens     Post ID => the tokens stored for it.
	 * @param array<int, string>       $titles     Post ID => its title.
	 * @param array<string, bool>|null $stop_words The reference stop words, or null.
	 *
	 * @return array<string, float|int|null>
	 */
	public static function words_metrics( array $tokens, array $titles, ?array $stop_words ): array {
		$words  = 0;
		$leaked = 0;
		$title  = 0;

		foreach ( $tokens as $post_id => $list ) {
			$title_text  = self::fold( $titles[ $post_id ] ?? '' );
			$title_words = array_fill_keys( self::words( $titles[ $post_id ] ?? '' ), true );

			foreach ( $list as $token ) {
				$token = (string) $token;
				if ( 1 === preg_match( self::NOT_A_WORD, $token ) ) {
					continue;
				}

				++$words;
				$folded = self::fold( $token );

				if ( null !== $stop_words && isset( $stop_words[ $folded ] ) ) {
					++$leaked;
				}

				// Words of scripts without spaces are pieces of the title rather than whole words of it.
				$in_title = 1 === preg_match( self::NO_SPACES, $folded ) ? false !== mb_strpos( $title_text, $folded ) : isset( $title_words[ $folded ] );
				if ( $in_title ) {
					++$title;
				}
			}
		}

		return [
			'stop_word_leak' => null === $stop_words ? null : self::ratio( $leaked, $words ),
			'title_share'    => self::ratio( $title, $words ),
			'words_per_post' => count( $tokens ) > 0 ? round( $words / count( $tokens ), 2 ) : null,
		];
	}

	/**
	 * A stop word list as a set, with each word in every spelling the plugins may store it in: accents removed ("fur")
	 * and German umlauts written out ("fuer").
	 *
	 * @param string[] $words The stop words.
	 *
	 * @return array<string, bool>
	 */
	public static function stop_set( array $words ): array {
		$set = [];

		foreach ( $words as $word ) {
			foreach ( self::spellings( (string) $word ) as $spelling ) {
				if ( '' !== $spelling ) {
					$set[ $spelling ] = true;
				}
			}
		}

		return $set;
	}

	/**
	 * The words of a text, lower case, in every spelling (see stop_set()).
	 *
	 * @param string $text The text.
	 *
	 * @return string[]
	 */
	public static function words( string $text ): array {
		$words = [];

		foreach ( (array) preg_split( '/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY ) as $word ) {
			foreach ( self::spellings( (string) $word ) as $spelling ) {
				$words[] = $spelling;
			}
		}

		return array_values( array_unique( $words ) );
	}

	/**
	 * Lower case, without accents.
	 *
	 * @param string $text The text.
	 *
	 * @return string
	 */
	public static function fold( string $text ): string {
		$text = mb_strtolower( $text, 'UTF-8' );

		if ( class_exists( \Normalizer::class ) ) {
			$decomposed = \Normalizer::normalize( $text, \Normalizer::FORM_D );
			if ( is_string( $decomposed ) ) {
				$text = (string) preg_replace( '/\p{Mn}+/u', '', $decomposed );
			}
		}

		return $text;
	}

	/**
	 * A word without accents, and with German umlauts and ß written out.
	 *
	 * @param string $word The word.
	 *
	 * @return string[]
	 */
	private static function spellings( string $word ): array {
		$lower = mb_strtolower( $word, 'UTF-8' );

		return array_values(
			array_unique(
				[
					self::fold( $lower ),
					self::fold(
						strtr(
							$lower,
							[
								'ä' => 'ae',
								'ö' => 'oe',
								'ü' => 'ue',
								'ß' => 'ss',
							]
						)
					),
				]
			)
		);
	}

	/**
	 * How many of the first posts of a ranking are in a set.
	 *
	 * @param int[]           $ranking The ranking.
	 * @param array<int, int> $set     The set, as keys.
	 * @param int             $first   How many to look at.
	 *
	 * @return int
	 */
	private static function hits( array $ranking, array $set, int $first ): int {
		$hits = 0;

		foreach ( array_slice( $ranking, 0, $first ) as $post_id ) {
			if ( isset( $set[ $post_id ] ) ) {
				++$hits;
			}
		}

		return $hits;
	}

	/**
	 * The mean, rounded, or null for no values.
	 *
	 * @param array<int, int|float> $values The values.
	 *
	 * @return float|null
	 */
	private static function mean( array $values ): ?float {
		return count( $values ) > 0 ? round( array_sum( $values ) / count( $values ), 4 ) : null;
	}

	/**
	 * A share, rounded, or null when there is nothing to divide by.
	 *
	 * @param int|float $part  The part.
	 * @param int|float $whole The whole.
	 *
	 * @return float|null
	 */
	private static function ratio( $part, $whole ): ?float {
		return $whole > 0 ? round( $part / $whole, 4 ) : null;
	}
}
