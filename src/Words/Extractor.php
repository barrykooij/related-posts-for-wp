<?php
/**
 * The word extractor class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Words;

/**
 * Finds the words that say most about a post, with a relative weight each.
 *
 * Words from the title, tags, categories and the titles of linked posts count several times (their weight); words
 * from the content count once. Ignored words and words shorter than two bytes are left out. The most frequent words
 * are kept, each weighted by its share of all words.
 */
class Extractor {

	/**
	 * Weight of a word in a title of a post linked from the content.
	 */
	private const LINKED_TITLE_WEIGHT = 20;

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
	 * The most important words of a post, with their relative weight, most important first.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return array<string, float> Word => weight.
	 */
	public function words_of_post( int $post_id ): array {
		// 2.x sets this locale for the character conversion; restore the previous one afterwards.
		$previous_locale = setlocale( LC_CTYPE, '0' );
		setlocale( LC_CTYPE, 'en_US.UTF8' );

		try {
			return $this->extract( $post_id );
		} finally {
			if ( false !== $previous_locale ) {
				setlocale( LC_CTYPE, $previous_locale );
			}
		}
	}

	/**
	 * Convert a string to plain UTF-8 words: accented letters lose their accent and punctuation becomes a space.
	 *
	 * @param string $text The text.
	 *
	 * @return string
	 */
	public function convert_characters( string $text ): string {
		// Only encode when the text is not UTF-8 yet. This is what 2.x did with utf8_encode().
		if ( 'UTF-8' !== mb_detect_encoding( $text, 'UTF-8', true ) ) {
			$text = mb_convert_encoding( $text, 'UTF-8', 'ISO-8859-1' );
		}

		// Replace accented characters with their plain letter.
		$text = htmlentities( $text, ENT_QUOTES, 'UTF-8' );
		if ( false !== strpos( $text, '&' ) ) {
			$text = html_entity_decode( (string) preg_replace( '~&([a-z]{1,2})(?:acute|cedil|circ|grave|lig|orn|ring|slash|tilde|uml);~iS', '$1', $text ), ENT_QUOTES, 'UTF-8' );
		}

		// Punctuation separates words.
		return (string) preg_replace( '/[;:\'\"\[\]\-\_=\+\.,\/\\<>`~\(\)\!@#$%\^&\*\?\|]+/i', ' ', $text );
	}

	/**
	 * Extract the words of a post.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return array<string, float>
	 */
	private function extract( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return [];
		}

		/**
		 * Filters how many times a word in the post title counts.
		 *
		 * @since 1.0.0
		 *
		 * @param int $weight The weight. Default 80.
		 */
		$title_weight = apply_filters( 'rp4wp_weight_title', 80 );

		/**
		 * Filters how many times a word in a tag of the post counts.
		 *
		 * @since 1.0.0
		 *
		 * @param int $weight The weight. Default 10.
		 */
		$tag_weight = apply_filters( 'rp4wp_weight_tag', 10 );

		/**
		 * Filters how many times a word in a category of the post counts.
		 *
		 * @since 1.0.0
		 *
		 * @param int $weight The weight. Default 20.
		 */
		$cat_weight = apply_filters( 'rp4wp_weight_cat', 20 );

		$raw_words = $this->content_words( $post );

		$raw_words = $this->add_words( $raw_words, explode( ' ', $this->convert_characters( $post->post_title ) ), $title_weight );

		$tags = wp_get_post_tags( $post->ID, [ 'fields' => 'names' ] );
		if ( is_array( $tags ) && count( $tags ) > 0 ) {
			foreach ( $tags as $tag ) {
				$raw_words = $this->add_words( $raw_words, explode( ' ', $this->convert_characters( $tag ) ), $tag_weight );
			}
		}

		$categories = wp_get_post_categories( $post->ID, [ 'fields' => 'all' ] );
		if ( is_array( $categories ) && count( $categories ) > 0 ) {
			foreach ( $categories as $category ) {
				// Skip the default "Uncategorized" category.
				if ( 1 === $category->term_id ) {
					continue;
				}

				$raw_words = $this->add_words( $raw_words, explode( ' ', $this->convert_characters( $category->name ) ), $cat_weight );
			}
		}

		// Count every word.
		$words = [];
		if ( count( $raw_words ) > 0 ) {
			$ignored_words = $this->ignored_words->get();

			foreach ( $raw_words as $word ) {
				$word = mb_strtolower( trim( $word ) );

				// Words of one byte say nothing.
				if ( strlen( $word ) < 2 ) {
					continue;
				}

				// 2.x compares loosely here, so for example "01" matches the ignored word "1". Keep it that way.
				if ( in_array( $word, $ignored_words ) ) { // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict -- See above.
					continue;
				}

				$words[ $word ] = isset( $words[ $word ] ) ? $words[ $word ] + 1 : 1;
			}
		}

		// Most frequent first. Since PHP 8.0 this sort keeps the order of words with the same count.
		arsort( $words );

		/**
		 * Filters how many words are stored per post.
		 *
		 * @since 1.0.0
		 *
		 * @param int $amount The number of words. Default 6.
		 */
		$amount    = apply_filters( 'rp4wp_cache_word_amount', 6 );
		$total     = count( $raw_words );
		$new_words = [];
		$added     = 0;

		foreach ( $words as $word => $count ) {
			$new_words[ (string) $word ] = $count / $total;

			++$added;
			if ( $added >= $amount ) {
				break;
			}
		}

		return $new_words;
	}

	/**
	 * The words of the post content, plus the words of the titles of posts linked from the content.
	 *
	 * @param \WP_Post $post The post.
	 *
	 * @return string[]
	 */
	private function content_words( \WP_Post $post ): array {
		$content = trim( (string) preg_replace( '/\s+/', ' ', $post->post_content ) );

		// Titles of posts this post links to.
		$linked_words = [];
		preg_match_all( '`<a[^>]*href="([^"]+)"[^>]*>[^<]*</a>`iS', $content, $matches );
		foreach ( $matches[1] as $url ) {
			$linked_post_id = url_to_postid( $url );
			if ( 0 === $linked_post_id ) {
				continue;
			}

			$linked_post = get_post( $linked_post_id );
			if ( null !== $linked_post ) {
				$linked_words = $this->add_words( $linked_words, explode( ' ', $this->convert_characters( $linked_post->post_title ) ), self::LINKED_TITLE_WEIGHT );
			}
		}

		// strip_tags(), not wp_strip_all_tags(): the latter also drops the contents of script and style tags.
		$content = strip_tags( $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Same result as 2.x.
		$content = strip_shortcodes( $content );
		$content = str_ireplace( '<!--more-->', '', $content );
		$content = $this->convert_characters( $content );

		return array_merge( explode( ' ', $content ), $linked_words );
	}

	/**
	 * Add words to a list as many times as their weight.
	 *
	 * @param string[] $base   The list to add to.
	 * @param string[] $words  The words to add.
	 * @param mixed    $weight How many times each word counts; from a filter, so not always an int.
	 *
	 * @return string[]
	 */
	private function add_words( array $base, array $words, $weight ): array {
		if ( $weight <= 0 ) {
			return $base;
		}

		foreach ( $words as $word ) {
			if ( empty( $word ) ) {
				continue;
			}

			$base = array_merge( $base, array_fill( 0, (int) $weight, $word ) );
		}

		return $base;
	}
}
