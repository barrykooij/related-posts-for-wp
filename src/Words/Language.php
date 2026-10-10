<?php
/**
 * The language class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Words;

/**
 * The language of a post, which picks its ignored words: the language WPML or Polylang report for the post; otherwise
 * the language whose ignored words make up the largest share of the post's words, when that share is large enough;
 * otherwise the language of the site.
 *
 * Languages are the codes of the ignored word lists: ISO 639-1, such as "en", "de" or "ja".
 */
class Language {

	/**
	 * The share of a post's words that must be ignored words of a language for the post to count as that language.
	 */
	public const THRESHOLD = 0.1;

	/**
	 * At most this many words of a post are looked at.
	 */
	private const SAMPLE = 2000;

	/**
	 * Codes that WordPress, WPML or Polylang use for a language whose list has another code.
	 */
	private const ALIASES = [
		'nb' => 'no',
		'nn' => 'no',
		'iw' => 'he',
		'in' => 'id',
	];

	/**
	 * The ignored words.
	 *
	 * @var IgnoredWords
	 */
	private IgnoredWords $ignored_words;

	/**
	 * Constructor.
	 *
	 * @param IgnoredWords|null $ignored_words The ignored words; a new list when null.
	 */
	public function __construct( ?IgnoredWords $ignored_words = null ) {
		$this->ignored_words = $ignored_words ?? new IgnoredWords();
	}

	/**
	 * The language of a post.
	 *
	 * @param \WP_Post $post   The post.
	 * @param string[] $tokens The words of its title and content, as the tokenizer gives them.
	 *
	 * @return string
	 */
	public function of_post( \WP_Post $post, array $tokens ): string {
		$language = $this->reported( (int) $post->ID );

		if ( '' === $language ) {
			$language = $this->detect( $tokens );
		}

		if ( '' === $language ) {
			$language = self::code( get_locale() );
		}

		/**
		 * Filters the language of a post, which picks its ignored words.
		 *
		 * @since 3.0.0
		 *
		 * @param string $language The language, as an ISO 639-1 code such as "en".
		 * @param int    $post_id  The post.
		 */
		return self::code( (string) apply_filters( 'rp4wp_post_language', $language, (int) $post->ID ) );
	}

	/**
	 * The language whose ignored words make up the largest share of the words, if that share reaches the threshold.
	 *
	 * @param string[] $tokens The words.
	 *
	 * @return string The language, or an empty string.
	 */
	public function detect( array $tokens ): string {
		$tokens = array_slice( $tokens, 0, self::SAMPLE );
		if ( count( $tokens ) < 1 ) {
			return '';
		}

		$hits = [];
		foreach ( array_count_values( $tokens ) as $token => $count ) {
			foreach ( $this->ignored_words->languages_of( (string) $token ) as $language ) {
				$hits[ $language ] = ( $hits[ $language ] ?? 0 ) + $count;
			}
		}

		if ( count( $hits ) < 1 ) {
			return '';
		}

		// The site's language wins a tie.
		$site = self::code( get_locale() );
		arsort( $hits );
		$best = (string) array_key_first( $hits );
		if ( isset( $hits[ $site ] ) && $hits[ $site ] === $hits[ $best ] ) {
			$best = $site;
		}

		/**
		 * Filters the share of a post's words that must be ignored words of a language for the post to count as that
		 * language.
		 *
		 * @since 3.0.0
		 *
		 * @param float $threshold The share, from 0 to 1. Default 0.1.
		 */
		$threshold = (float) apply_filters( 'rp4wp_language_detection_threshold', self::THRESHOLD );

		return $hits[ $best ] / count( $tokens ) >= $threshold ? $best : '';
	}

	/**
	 * The language code of a locale or language tag: "en_US", "pt-br" and "en" give "en", "nb_NO" gives "no".
	 *
	 * @param string $locale The locale or language tag.
	 *
	 * @return string
	 */
	public static function code( string $locale ): string {
		$code = strtolower( (string) preg_split( '/[_-]/', trim( $locale ) )[0] );
		if ( 1 !== preg_match( '/^[a-z]{2,3}$/', $code ) ) {
			return '';
		}

		return self::ALIASES[ $code ] ?? $code;
	}

	/**
	 * The language WPML or Polylang report for a post.
	 *
	 * @param int $post_id The post.
	 *
	 * @return string The language, or an empty string.
	 */
	private function reported( int $post_id ): string {
		$details = apply_filters( 'wpml_post_language_details', null, $post_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- The filter of WPML.
		if ( is_array( $details ) && ! empty( $details['language_code'] ) ) {
			return self::code( (string) $details['language_code'] );
		}

		if ( function_exists( 'pll_get_post_language' ) ) {
			$locale = pll_get_post_language( $post_id, 'locale' );
			if ( is_string( $locale ) && '' !== $locale ) {
				return self::code( $locale );
			}
		}

		return '';
	}
}
