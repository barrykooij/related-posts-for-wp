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
 * The words of a post come from its content, its title, the names of its tags and categories and the titles of the
 * posts it links to; each source has a weight, how many times its words count (a word in the content counts once). The
 * tokenizer turns them into words, and the ignored words of the post's language go. The word cache keeps the words with
 * the highest `tf * idf` and weighs them (see Statistics).
 *
 * On top of its words a post has tokens for what it is part of and points to (decision D53): `cat:{term_id}` for each
 * category but the default one, `tag:{term_id}` for each tag, `post:{ID}` for itself and `post:{ID}` for each post it
 * links to, each counting as many times as the weight of its kind. So posts in the same category, with the same tag,
 * that link to each other or to the same post are related, and a category that half the posts are in counts for
 * little. The names stay words too: they match the content of related posts that do not have the term (the quality
 * plan, section 13.4). The premium add-on extends this class with its own sources, tokens and weights.
 */
class Extractor {

	/**
	 * How many times a post linked from the content counts: its token, and the words of its title.
	 */
	private const LINK_WEIGHT = 10;

	/**
	 * The start of the token of a post: the post itself, or a post it links to.
	 */
	public const TOKEN_POST = 'post:';

	/**
	 * The start of the token of a category.
	 */
	public const TOKEN_CATEGORY = 'cat:';

	/**
	 * The start of the token of a tag.
	 */
	public const TOKEN_TAG = 'tag:';

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
	 * The posts the last post links to, by post and content.
	 *
	 * @var array<string, int[]>
	 */
	private array $linked = [];

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
	 * The words of a post the word cache would store, with their weight as the word statistics are now, most
	 * important first.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return array<string, float> Word => weight.
	 */
	public function words_of_post( int $post_id ): array {
		$words = $this->post_words( $post_id );
		if ( null === $words || count( $words->counts ) + count( $words->terms ) < 1 ) {
			return [];
		}

		$statistics = new Statistics();
		$df         = $statistics->df( array_keys( $words->counts ) );
		$posts      = $statistics->posts();

		$df = $df + $statistics->df( array_keys( $words->terms ) );

		return Statistics::weights( Statistics::pick( $words->counts, $df, $posts, Statistics::amount() ) + $words->terms, $df, $posts );
	}

	/**
	 * Every word of a post that is not an ignored word, with how many times it counts, and the post's language.
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

		// Count every word, as many times as the weight of its source.
		$counts = [];
		foreach ( $sources as [ $words, $weight ] ) {
			if ( $weight <= 0 ) {
				continue;
			}

			foreach ( $words as $word ) {
				if ( ! isset( $ignored[ $word ] ) ) {
					$counts[ $word ] = ( $counts[ $word ] ?? 0 ) + $weight;
				}
			}
		}

		// Most frequent first. Since PHP 8.0 this sort keeps the order of words with the same count.
		arsort( $counts );

		return new PostWords( $counts, $language, $tokens, $this->terms( $post ) );
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
			[
				'text'   => (string) $post->post_title,
				'weight' => $this->weight( 'title' ),
				'detect' => true,
			],
		];

		$tags = wp_get_post_tags( $post->ID, [ 'fields' => 'names' ] );
		foreach ( is_array( $tags ) ? $tags : [] as $name ) {
			$sources[] = [
				'text'   => (string) $name,
				'weight' => $this->weight( 'tag' ),
			];
		}

		$default = (int) get_option( 'default_category' );
		foreach ( (array) wp_get_post_categories( $post->ID, [ 'fields' => 'all' ] ) as $category ) {
			if ( $category instanceof \WP_Term && $category->term_id !== $default ) {
				$sources[] = [
					'text'   => $category->name,
					'weight' => $this->weight( 'cat' ),
				];
			}
		}

		foreach ( $this->linked_post_ids( $post ) as $linked_post_id ) {
			$sources[] = [
				'text'   => (string) get_post_field( 'post_title', $linked_post_id ),
				'weight' => $this->weight( 'link' ),
			];
		}

		return $sources;
	}

	/**
	 * The tokens of a post for what it is part of and points to, with how many times each counts: itself, the posts it
	 * links to, its categories but the default one, and its tags.
	 *
	 * @param \WP_Post $post The post.
	 *
	 * @return array<string, int> Token => how many times it counts.
	 */
	protected function terms( \WP_Post $post ): array {
		$terms = [ self::TOKEN_POST . $post->ID => 1 ];

		$link = (int) $this->weight( 'link' );
		if ( $link > 0 ) {
			foreach ( $this->linked_post_ids( $post ) as $linked_post_id ) {
				$terms[ self::TOKEN_POST . $linked_post_id ] = $link;
			}
		}

		$category = (int) $this->weight( 'cat' );
		if ( $category > 0 ) {
			$default = (int) get_option( 'default_category' );
			foreach ( (array) wp_get_post_categories( $post->ID ) as $term_id ) {
				if ( (int) $term_id !== $default ) {
					$terms[ self::TOKEN_CATEGORY . (int) $term_id ] = $category;
				}
			}
		}

		$tag = (int) $this->weight( 'tag' );
		if ( $tag > 0 ) {
			$tags = wp_get_post_tags( $post->ID, [ 'fields' => 'ids' ] );
			foreach ( is_array( $tags ) ? $tags : [] as $term_id ) {
				$terms[ self::TOKEN_TAG . (int) $term_id ] = $tag;
			}
		}

		return $terms;
	}

	/**
	 * How many times a kind of source counts, its words and its token: `title` (a word of the title), `tag`, `cat` or
	 * `link` (a post the content links to).
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
				 * @since 3.0.0 The default is 5: the word statistics weigh rare words up, and a title word that counts 80
				 *              times outweighs the rest of the post.
				 *
				 * @param int $weight The weight. Default 5.
				 */
				return apply_filters( 'rp4wp_weight_title', 5 );
			case 'tag':
				/**
				 * Filters how many times a tag of the post counts: the words of its name, and its token.
				 *
				 * @since 1.0.0
				 * @since 3.0.0 The tag also counts as a token, `tag:{term_id}`. The default is 5.
				 *
				 * @param int $weight The weight. Default 5.
				 */
				return apply_filters( 'rp4wp_weight_tag', 5 );
			case 'cat':
				/**
				 * Filters how many times a category of the post counts: the words of its name, and its token.
				 *
				 * @since 1.0.0
				 * @since 3.0.0 The category also counts as a token, `cat:{term_id}`. The default is 10.
				 *
				 * @param int $weight The weight. Default 10.
				 */
				return apply_filters( 'rp4wp_weight_cat', 10 );
			case 'link':
				return self::LINK_WEIGHT;
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
	 * The posts this post links to from its content, each once.
	 *
	 * @param \WP_Post $post The post.
	 *
	 * @return int[]
	 */
	protected function linked_post_ids( \WP_Post $post ): array {
		$content = (string) $post->post_content;
		if ( false === stripos( $content, '<a' ) ) {
			return [];
		}

		// The words and the tokens of a post both need them.
		$key = $post->ID . ':' . md5( $content );
		if ( isset( $this->linked[ $key ] ) ) {
			return $this->linked[ $key ];
		}

		preg_match_all( '`<a\s[^>]*href\s*=\s*["\']([^"\']+)["\']`iS', $content, $matches );

		$post_ids = [];
		foreach ( array_unique( $matches[1] ) as $url ) {
			$linked_post_id = url_to_postid( html_entity_decode( (string) $url ) );
			if ( $linked_post_id > 0 && $linked_post_id !== (int) $post->ID && null !== get_post( $linked_post_id ) ) {
				$post_ids[ $linked_post_id ] = $linked_post_id;
			}
		}

		$this->linked = [ $key => array_values( $post_ids ) ];

		return $this->linked[ $key ];
	}
}
