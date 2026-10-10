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
 * The sources of a post are its content, the titles of the posts it links to, its title, tags and categories; each
 * has a weight, how many times its words count (a title word counts 80 times). The tokenizer turns every source into
 * words, the ignored words of the post's language go, and the most frequent words are kept, each weighted by its share
 * of all words. The premium add-on extends this class with its own sources and weights.
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
	protected IgnoredWords $ignored_words;

	/**
	 * The tokenizer.
	 *
	 * @var Tokenizer
	 */
	protected Tokenizer $tokenizer;

	/**
	 * The language detection.
	 *
	 * @var Language
	 */
	protected Language $language;

	/**
	 * Constructor.
	 *
	 * @param IgnoredWords|null $ignored_words The ignored words; a new list when null.
	 * @param Tokenizer|null    $tokenizer     The tokenizer; a new one when null.
	 */
	public function __construct( ?IgnoredWords $ignored_words = null, ?Tokenizer $tokenizer = null ) {
		$this->tokenizer     = $tokenizer ?? new Tokenizer();
		$this->ignored_words = $ignored_words ?? new IgnoredWords( $this->tokenizer );
		$this->language      = new Language( $this->ignored_words );
	}

	/**
	 * The ignored words this extractor uses.
	 *
	 * @return IgnoredWords
	 */
	public function ignored_words(): IgnoredWords {
		return $this->ignored_words;
	}

	/**
	 * The tokenizer this extractor uses.
	 *
	 * @return Tokenizer
	 */
	public function tokenizer(): Tokenizer {
		return $this->tokenizer;
	}

	/**
	 * The most important words of a post, with their relative weight, most important first.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return array<string, float> Word => weight.
	 */
	public function words_of_post( int $post_id ): array {
		$words = $this->post_words( $post_id );

		return null === $words ? [] : $words->weights;
	}

	/**
	 * The most important words of a post, with their weight, how many times they count, and the post's language.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return PostWords|null Null when there is no such post.
	 */
	public function post_words( int $post_id ): ?PostWords {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return null;
		}

		$sources   = [];
		$detection = [];
		$tokens    = 0;
		foreach ( $this->sources( $post ) as $source ) {
			$words     = $this->tokenize( $source['text'] );
			$sources[] = [ $words, (int) $source['weight'] ];
			$tokens   += count( $words );

			if ( ! empty( $source['detect'] ) ) {
				$detection = array_merge( $detection, $words );
			}
		}

		$language = $this->language_of( $post, $detection );
		$ignored  = $this->ignored_words->lookup( $language );

		// Count every word, as many times as the weight of its source. Ignored words count in the total.
		$counts = [];
		$total  = 0;
		foreach ( $sources as [ $words, $weight ] ) {
			if ( $weight <= 0 ) {
				continue;
			}

			foreach ( $words as $word ) {
				$total += $weight;

				if ( ! isset( $ignored[ $word ] ) ) {
					$counts[ $word ] = ( $counts[ $word ] ?? 0 ) + $weight;
				}
			}
		}

		// Most frequent first. Since PHP 8.0 this sort keeps the order of words with the same count.
		arsort( $counts );

		/**
		 * Filters how many words are stored per post.
		 *
		 * @since 1.0.0
		 *
		 * @param int $amount The number of words. Default 6.
		 */
		$amount = (int) apply_filters( 'rp4wp_cache_word_amount', 6 );

		$kept    = [];
		$weights = [];
		foreach ( array_slice( $counts, 0, max( 0, $amount ), true ) as $word => $count ) {
			$kept[ (string) $word ]    = (int) $count;
			$weights[ (string) $word ] = $count / $total;
		}

		return new PostWords( $weights, $kept, $language, $tokens );
	}

	/**
	 * The texts of a post with their weight: how many times each of their words counts, and whether they say which
	 * language the post is in.
	 *
	 * @param \WP_Post $post The post.
	 *
	 * @return array<int, array{text: string, weight: mixed, detect?: bool}>
	 */
	protected function sources( \WP_Post $post ): array {
		$sources = [
			[
				'text'   => (string) $post->post_content,
				'weight' => 1,
				'detect' => true,
			],
		];

		foreach ( $this->linked_titles( $post ) as $title ) {
			$sources[] = [
				'text'   => $title,
				'weight' => $this->weight( 'link' ),
			];
		}

		$sources[] = [
			'text'   => (string) $post->post_title,
			'weight' => $this->weight( 'title' ),
			'detect' => true,
		];

		$tags = wp_get_post_tags( $post->ID, [ 'fields' => 'names' ] );
		if ( is_array( $tags ) ) {
			foreach ( $tags as $tag ) {
				$sources[] = [
					'text'   => (string) $tag,
					'weight' => $this->weight( 'tag' ),
				];
			}
		}

		$categories = wp_get_post_categories( $post->ID, [ 'fields' => 'all' ] );
		if ( is_array( $categories ) ) {
			foreach ( $categories as $category ) {
				// Skip the default "Uncategorized" category.
				if ( $category instanceof \WP_Term && 1 !== $category->term_id ) {
					$sources[] = [
						'text'   => $category->name,
						'weight' => $this->weight( 'cat' ),
					];
				}
			}
		}

		return $sources;
	}

	/**
	 * How many times a word of a kind of source counts: `title`, `tag`, `cat` or `link`.
	 *
	 * @param string $type The kind of source.
	 *
	 * @return mixed From a filter, so not always an int.
	 */
	protected function weight( string $type ) {
		switch ( $type ) {
			case 'title':
				/**
				 * Filters how many times a word in the post title counts.
				 *
				 * @since 1.0.0
				 *
				 * @param int $weight The weight. Default 80.
				 */
				return apply_filters( 'rp4wp_weight_title', 80 );
			case 'tag':
				/**
				 * Filters how many times a word in a tag of the post counts.
				 *
				 * @since 1.0.0
				 *
				 * @param int $weight The weight. Default 10.
				 */
				return apply_filters( 'rp4wp_weight_tag', 10 );
			case 'cat':
				/**
				 * Filters how many times a word in a category of the post counts.
				 *
				 * @since 1.0.0
				 *
				 * @param int $weight The weight. Default 20.
				 */
				return apply_filters( 'rp4wp_weight_cat', 20 );
			case 'link':
				return self::LINKED_TITLE_WEIGHT;
			default:
				return 1;
		}
	}

	/**
	 * The words of a text.
	 *
	 * @param string $text The text.
	 *
	 * @return string[]
	 */
	protected function tokenize( string $text ): array {
		return $this->tokenizer->tokens( $text );
	}

	/**
	 * The language of a post.
	 *
	 * @param \WP_Post $post   The post.
	 * @param string[] $tokens The words of its title and content.
	 *
	 * @return string
	 */
	protected function language_of( \WP_Post $post, array $tokens ): string {
		return $this->language->of_post( $post, $tokens );
	}

	/**
	 * The titles of the posts this post links to from its content.
	 *
	 * @param \WP_Post $post The post.
	 *
	 * @return string[]
	 */
	private function linked_titles( \WP_Post $post ): array {
		if ( false === stripos( (string) $post->post_content, '<a' ) ) {
			return [];
		}

		preg_match_all( '`<a[^>]*href="([^"]+)"[^>]*>[^<]*</a>`iS', (string) preg_replace( '/\s+/', ' ', (string) $post->post_content ), $matches );

		$titles = [];
		foreach ( $matches[1] as $url ) {
			$linked_post_id = url_to_postid( $url );
			if ( 0 === $linked_post_id ) {
				continue;
			}

			$linked_post = get_post( $linked_post_id );
			if ( null !== $linked_post ) {
				$titles[] = (string) $linked_post->post_title;
			}
		}

		return $titles;
	}
}
