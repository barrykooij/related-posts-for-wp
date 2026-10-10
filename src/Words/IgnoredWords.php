<?php
/**
 * The ignored words class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Words;

/**
 * Common words per language that say nothing about what a post is about ("the", "and", ...), from the lists in
 * `resources/ignored-words/{language}.php` (57 languages, ISO 639-1 codes).
 *
 * The lists are stored normalized the way the tokenizer normalizes words; words that the `rp4wp_ignored_words` filter
 * adds are normalized when the list is loaded, so every word can match. The 2.x files per locale (`en_US.php`, ...)
 * stay one release as aliases of the language files.
 */
class IgnoredWords {

	/**
	 * The shipped lists, flipped, by language, once loaded; shared by every instance in a request.
	 *
	 * @var array<string, array<string, true>>
	 */
	private static array $shipped = [];

	/**
	 * Whether every shipped list is loaded.
	 *
	 * @var bool
	 */
	private static bool $all_loaded = false;

	/**
	 * The lists with the filter applied, flipped, by language.
	 *
	 * @var array<string, array<string, true>>
	 */
	private array $lists = [];

	/**
	 * The tokenizer that normalizes added words.
	 *
	 * @var Tokenizer
	 */
	protected Tokenizer $tokenizer;

	/**
	 * Constructor.
	 *
	 * @param Tokenizer|null $tokenizer The tokenizer; a new one when null.
	 */
	public function __construct( ?Tokenizer $tokenizer = null ) {
		$this->tokenizer = $tokenizer ?? new Tokenizer();
	}

	/**
	 * The ignored words of a language, normalized.
	 *
	 * @param string $language The language, or a locale such as "en_US"; the site's language by default.
	 *
	 * @return string[]
	 */
	public function get( string $language = '' ): array {
		return array_map( 'strval', array_keys( $this->lookup( $language ) ) );
	}

	/**
	 * The ignored words of a language, normalized, as the keys of an array, for isset().
	 *
	 * @param string $language The language, or a locale such as "en_US"; the site's language by default.
	 *
	 * @return array<string, true>
	 */
	public function lookup( string $language = '' ): array {
		$language = Language::code( '' !== $language ? $language : get_locale() );

		if ( ! isset( $this->lists[ $language ] ) ) {
			$shipped = self::shipped( $language );
			$words   = [];

			foreach ( $this->words( $language ) as $word ) {
				$word = (string) $word;

				// The shipped words are normalized already.
				if ( ! isset( $shipped[ $word ] ) ) {
					$word = $this->tokenizer->word( $word );
				}

				if ( '' !== $word ) {
					$words[ $word ] = true;
				}
			}

			$this->lists[ $language ] = $words;
		}

		return $this->lists[ $language ];
	}

	/**
	 * Whether a word is an ignored word of a language.
	 *
	 * @param string $word     A token.
	 * @param string $language The language.
	 *
	 * @return bool
	 */
	public function is_ignored( string $word, string $language ): bool {
		return isset( $this->lookup( $language )[ $word ] );
	}

	/**
	 * The languages whose shipped list has a word, for language detection.
	 *
	 * @param string $word A token.
	 *
	 * @return string[]
	 */
	public function languages_of( string $word ): array {
		self::load_all();

		$languages = [];
		foreach ( self::$shipped as $language => $words ) {
			if ( isset( $words[ $word ] ) ) {
				$languages[] = (string) $language;
			}
		}

		return $languages;
	}

	/**
	 * The languages with a shipped list.
	 *
	 * @return string[]
	 */
	public static function languages(): array {
		return array_map(
			static function ( $file ) {
				return basename( $file, '.php' );
			},
			(array) glob( self::directory() . '/[a-z][a-z].php' )
		);
	}

	/**
	 * The words of a language before they are normalized: the shipped list, with the filter applied.
	 *
	 * @param string $language The language.
	 *
	 * @return string[]
	 */
	protected function words( string $language ): array {
		/**
		 * Filters the words that are left out when caching the words of a post.
		 *
		 * @since 1.0.0
		 * @since 3.0.0 Per language, with the language as the second argument. Added words are normalized like the
		 *              words of posts.
		 *
		 * @param string[] $words    The ignored words.
		 * @param string   $language The language, as an ISO 639-1 code such as "en".
		 */
		return (array) apply_filters( 'rp4wp_ignored_words', array_keys( self::shipped( $language ) ), $language );
	}

	/**
	 * The shipped list of a language, flipped; empty when there is none.
	 *
	 * @param string $language The language.
	 *
	 * @return array<string, true>
	 */
	protected static function shipped( string $language ): array {
		if ( ! isset( self::$shipped[ $language ] ) ) {
			self::$shipped[ $language ] = [];

			if ( 1 === preg_match( '/^[a-z]{2,3}$/', $language ) ) {
				$file = self::directory() . "/{$language}.php";
				if ( is_file( $file ) ) {
					$words = require $file;
					if ( is_array( $words ) ) {
						self::$shipped[ $language ] = array_fill_keys( array_map( 'strval', $words ), true );
					}
				}
			}
		}

		return self::$shipped[ $language ];
	}

	/**
	 * Load every shipped list.
	 *
	 * @return void
	 */
	private static function load_all(): void {
		if ( self::$all_loaded ) {
			return;
		}

		foreach ( self::languages() as $language ) {
			self::shipped( $language );
		}

		self::$all_loaded = true;
	}

	/**
	 * Where the lists are.
	 *
	 * @return string
	 */
	private static function directory(): string {
		return dirname( __DIR__, 2 ) . '/resources/ignored-words';
	}
}
