<?php
/**
 * The tokenizer class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Words;

/**
 * Turns text into the words (tokens) the word cache stores, the same way for every source and for the ignored words.
 *
 * In order: script and style elements lose their contents, tags and shortcodes go, HTML entities are decoded, the text
 * is normalized (NFKC, with intl), lower-cased and Latin accents are folded ("café" is "cafe", "für" is "fuer"). Then it
 * is split into words: letters and digits, with apostrophes inside a word dropped ("don't" is "dont") and decimal
 * numbers kept whole ("1.5"). Scripts written without spaces are segmented: Chinese and Japanese, and Thai, Lao, Khmer
 * and Burmese, into words by ICU when intl is there; without intl, Chinese and Japanese become pairs of characters
 * (bigrams). A word has 2 to 64 characters.
 *
 * Every other script is split the same way with or without intl, so a site gets the same words on any server.
 */
class Tokenizer {

	/**
	 * The version of the tokenization: the rows of the word cache carry it, and the finder only joins rows of this
	 * version. Raise it when the tokens change.
	 */
	public const VERSION = 2;

	/**
	 * The shortest word, in characters.
	 */
	public const MIN_LENGTH = 2;

	/**
	 * The longest word, in characters (the column of the word cache).
	 */
	public const MAX_LENGTH = 64;

	/**
	 * Words: a decimal number, or letters, marks and digits, with apostrophes inside.
	 */
	private const WORD = '/\p{N}+(?:[.,]\p{N}+)+|[\p{L}\p{M}\p{N}]+(?:[\'\x{2019}\x{02BC}][\p{L}\p{M}\p{N}]+)*/u';

	/**
	 * Runs of a script written without spaces between words.
	 */
	private const UNSPACED = '/[\p{Han}\p{Hiragana}\p{Katakana}\x{30FC}\p{Thai}\p{Lao}\p{Khmer}\p{Myanmar}]+/u';

	/**
	 * Runs of Chinese and Japanese, which become pairs of characters without intl.
	 */
	private const CJ = '/^[\p{Han}\p{Hiragana}\p{Katakana}\x{30FC}]+$/u';

	/**
	 * Latin letters with an accent or a special form, lower case, and their plain spelling.
	 */
	private const FOLD = [
		'á' => 'a',
		'à' => 'a',
		'ă' => 'a',
		'â' => 'a',
		'å' => 'a',
		'ã' => 'a',
		'ą' => 'a',
		'ā' => 'a',
		'ä' => 'ae',
		'æ' => 'ae',
		'ḃ' => 'b',
		'ć' => 'c',
		'ĉ' => 'c',
		'č' => 'c',
		'ċ' => 'c',
		'ç' => 'c',
		'ď' => 'd',
		'ḋ' => 'd',
		'đ' => 'd',
		'ð' => 'dh',
		'é' => 'e',
		'è' => 'e',
		'ĕ' => 'e',
		'ê' => 'e',
		'ě' => 'e',
		'ë' => 'e',
		'ė' => 'e',
		'ę' => 'e',
		'ē' => 'e',
		'ḟ' => 'f',
		'ƒ' => 'f',
		'ğ' => 'g',
		'ĝ' => 'g',
		'ġ' => 'g',
		'ģ' => 'g',
		'ĥ' => 'h',
		'ħ' => 'h',
		'í' => 'i',
		'ì' => 'i',
		'î' => 'i',
		'ï' => 'i',
		'ĩ' => 'i',
		'į' => 'i',
		'ī' => 'i',
		'ı' => 'i',
		'ĵ' => 'j',
		'ķ' => 'k',
		'ĺ' => 'l',
		'ľ' => 'l',
		'ļ' => 'l',
		'ł' => 'l',
		'ṁ' => 'm',
		'ń' => 'n',
		'ň' => 'n',
		'ñ' => 'n',
		'ņ' => 'n',
		'ó' => 'o',
		'ò' => 'o',
		'ô' => 'o',
		'ő' => 'o',
		'õ' => 'o',
		'ø' => 'oe',
		'ō' => 'o',
		'ơ' => 'o',
		'ö' => 'oe',
		'œ' => 'oe',
		'ṗ' => 'p',
		'ŕ' => 'r',
		'ř' => 'r',
		'ŗ' => 'r',
		'ś' => 's',
		'ŝ' => 's',
		'š' => 's',
		'ṡ' => 's',
		'ş' => 's',
		'ș' => 's',
		'ß' => 'ss',
		'ť' => 't',
		'ṫ' => 't',
		'ţ' => 't',
		'ț' => 't',
		'ŧ' => 't',
		'ú' => 'u',
		'ù' => 'u',
		'ŭ' => 'u',
		'û' => 'u',
		'ů' => 'u',
		'ű' => 'u',
		'ũ' => 'u',
		'ų' => 'u',
		'ū' => 'u',
		'ư' => 'u',
		'ü' => 'ue',
		'ẃ' => 'w',
		'ẁ' => 'w',
		'ŵ' => 'w',
		'ẅ' => 'w',
		'ý' => 'y',
		'ỳ' => 'y',
		'ŷ' => 'y',
		'ÿ' => 'y',
		'ź' => 'z',
		'ž' => 'z',
		'ż' => 'z',
		'þ' => 'th',
	];

	/**
	 * Whether intl is used: NFKC normalization and ICU word segmentation.
	 *
	 * @var bool
	 */
	private bool $intl;

	/**
	 * The ICU word iterator, once created.
	 *
	 * @var \IntlBreakIterator|null
	 */
	private ?\IntlBreakIterator $iterator = null;

	/**
	 * Constructor.
	 *
	 * @param bool|null $intl Whether to use intl when it is there; by default the `rp4wp_tokenizer_use_intl` filter
	 *                        decides.
	 */
	public function __construct( ?bool $intl = null ) {
		if ( null === $intl ) {
			/**
			 * Filters whether the tokenizer uses the intl extension when it is there, for NFKC normalization and the
			 * segmentation of Chinese, Japanese, Thai, Lao, Khmer and Burmese. Turn it off to get the words a server
			 * without intl gets.
			 *
			 * @since 3.0.0
			 *
			 * @param bool $use Whether to use intl. Default true.
			 */
			$intl = (bool) apply_filters( 'rp4wp_tokenizer_use_intl', true );
		}

		$this->intl = $intl && class_exists( \Normalizer::class ) && class_exists( \IntlBreakIterator::class );
	}

	/**
	 * Whether intl is used.
	 *
	 * @return bool
	 */
	public function uses_intl(): bool {
		return $this->intl;
	}

	/**
	 * The words of a text, in order, with repeats.
	 *
	 * @param string $text     The text; it may contain HTML.
	 * @param string $language The language of the text, for the segmentation of scripts without spaces; optional.
	 *
	 * @return string[]
	 */
	public function tokens( string $text, string $language = '' ): array {
		$text = $this->normalize( $this->plain_text( $text ) );
		if ( '' === $text ) {
			return [];
		}

		preg_match_all( self::WORD, $text, $matches );

		$tokens = [];
		foreach ( $matches[0] as $word ) {
			foreach ( $this->segment( $word, $language ) as $token ) {
				$token = $this->without_apostrophes( $token );

				if ( $this->fits( $token ) ) {
					$tokens[] = $token;
				}
			}
		}

		return $tokens;
	}

	/**
	 * One word, normalized the way tokens() normalizes words, for lists of words such as the ignored words. Not split:
	 * a word that is not one word as a whole gives an empty string.
	 *
	 * @param string $word The word.
	 *
	 * @return string The word, or an empty string.
	 */
	public function word( string $word ): string {
		$word = trim( $this->normalize( $this->decode( $word ) ) );

		if ( 1 !== preg_match( self::WORD, $word, $match ) || $match[0] !== $word ) {
			return '';
		}

		$word = $this->without_apostrophes( $word );

		return $this->fits( $word ) ? $word : '';
	}

	/**
	 * The text of HTML: script and style elements lose their contents, tags, comments and shortcodes go, entities are
	 * decoded, and text that is not UTF-8 is read as Latin-1.
	 *
	 * @param string $html The HTML.
	 *
	 * @return string
	 */
	public function plain_text( string $html ): string {
		if ( ! mb_check_encoding( $html, 'UTF-8' ) ) {
			$html = mb_convert_encoding( $html, 'UTF-8', 'ISO-8859-1' );
		}

		if ( false !== strpos( $html, '<' ) ) {
			$html = (string) preg_replace( '@<(script|style)[^>]*?>.*?</\1>@si', ' ', $html );
			// A space before every tag, so the words on both sides of a tag stay apart.
			$html = strip_tags( str_replace( '<', ' <', $html ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- The contents of script and style are gone already.
		}

		if ( false !== strpos( $html, '[' ) && function_exists( 'strip_shortcodes' ) ) {
			$html = strip_shortcodes( $html );
		}

		return $this->decode( $html );
	}

	/**
	 * Decode HTML entities, twice for text that was encoded twice ("&amp;amp;").
	 *
	 * @param string $text The text.
	 *
	 * @return string
	 */
	private function decode( string $text ): string {
		for ( $i = 0; $i < 2 && false !== strpos( $text, '&' ); $i++ ) {
			$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}

		return $text;
	}

	/**
	 * Normalize text: NFKC (with intl), lower case, Latin accents folded, and the marks that are left on Latin letters
	 * removed.
	 *
	 * @param string $text The text.
	 *
	 * @return string
	 */
	private function normalize( string $text ): string {
		if ( $this->intl ) {
			$normalized = \Normalizer::normalize( $text, \Normalizer::FORM_KC );
			if ( is_string( $normalized ) ) {
				$text = $normalized;
			}
		}

		$text = mb_strtolower( $text, 'UTF-8' );

		// Accents written as a separate mark after a Latin letter, which NFKC joins to the letter: without intl, an umlaut
		// becomes an "e" like "ä" does, and other marks go.
		if ( 1 === preg_match( '/[a-z]\p{Mn}/u', $text ) ) {
			$text = (string) preg_replace( '/([aou])\x{0308}/u', '$1e', $text );
			$text = (string) preg_replace( '/(?<=[a-z])\p{Mn}+/u', '', $text );
		}

		return strtr( $text, self::FOLD );
	}

	/**
	 * Split a word of a script written without spaces into words; other words stay as they are.
	 *
	 * @param string $word     The word.
	 * @param string $language The language of the text.
	 *
	 * @return string[]
	 */
	private function segment( string $word, string $language ): array {
		if ( 1 !== preg_match( self::UNSPACED, $word ) ) {
			return [ $word ];
		}

		$parts = preg_split( '/(' . substr( self::UNSPACED, 1, -2 ) . ')/u', $word, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
		if ( false === $parts ) {
			return [ $word ];
		}

		$words = [];
		foreach ( $parts as $part ) {
			if ( 1 !== preg_match( self::UNSPACED, $part ) ) {
				$words[] = $part;
			} elseif ( $this->intl ) {
				$words = array_merge( $words, $this->icu_words( $part, $language ) );
			} elseif ( 1 === preg_match( self::CJ, $part ) ) {
				$words = array_merge( $words, $this->bigrams( $part ) );
			} else {
				$words[] = $part;
			}
		}

		return $words;
	}

	/**
	 * The words ICU finds in a run of text.
	 *
	 * @param string $text     The text.
	 * @param string $language The language of the text.
	 *
	 * @return string[]
	 */
	private function icu_words( string $text, string $language ): array {
		if ( null === $this->iterator ) {
			$this->iterator = \IntlBreakIterator::createWordInstance( '' !== $language ? $language : 'en' );
		}

		$iterator = $this->iterator;
		if ( null === $iterator ) {
			return [ $text ];
		}

		$iterator->setText( $text );

		$words = [];
		$start = $iterator->first();
		for ( $end = $iterator->next(); \IntlBreakIterator::DONE !== $end; $end = $iterator->next() ) {
			$word = substr( $text, $start, $end - $start );
			if ( 1 === preg_match( '/[\p{L}\p{N}]/u', $word ) ) {
				$words[] = $word;
			}

			$start = $end;
		}

		return $words;
	}

	/**
	 * Pairs of characters of a run of Chinese or Japanese ("東京都" gives "東京" and "京都").
	 *
	 * @param string $text The text.
	 *
	 * @return string[]
	 */
	private function bigrams( string $text ): array {
		$characters = mb_str_split( $text, 1, 'UTF-8' );
		if ( count( $characters ) < 2 ) {
			return $characters;
		}

		$bigrams = [];
		for ( $i = 0, $last = count( $characters ) - 1; $i < $last; $i++ ) {
			$bigrams[] = $characters[ $i ] . $characters[ $i + 1 ];
		}

		return $bigrams;
	}

	/**
	 * A word without the apostrophes inside it.
	 *
	 * @param string $word The word.
	 *
	 * @return string
	 */
	private function without_apostrophes( string $word ): string {
		return str_replace( [ "'", "\u{2019}", "\u{02BC}" ], '', $word );
	}

	/**
	 * Whether a word has an allowed length.
	 *
	 * @param string $word The word.
	 *
	 * @return bool
	 */
	private function fits( string $word ): bool {
		$length = mb_strlen( $word, 'UTF-8' );

		return $length >= self::MIN_LENGTH && $length <= self::MAX_LENGTH;
	}
}
