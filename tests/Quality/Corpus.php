<?php
/**
 * The quality corpus class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Quality;

/**
 * A corpus of the quality harness: posts, what counts as related, and the site it simulates (language, locale,
 * permalinks).
 *
 * Corpora are built by `scripts/quality/build-corpora.php` into `artifacts/quality/corpora/` (or the folder in
 * RP4WP_QUALITY_CORPORA): `<name>.json` says what the corpus is, the posts are in the JSON lines file it names. In a
 * topic corpus, posts with the same label are related, and the label is never given to the plugin. A hand-labelled
 * corpus reads its labels from `labels/<label set>.json` next to the corpora folder.
 */
final class Corpus {

	/**
	 * The order in which corpora run; others follow by name.
	 */
	private const ORDER = [ 'golden', 'bbc', 'gnad', 'livedoor', 'blog', 'blog-nl' ];

	/**
	 * The name, for example `bbc`.
	 *
	 * @var string
	 */
	public string $name = '';

	/**
	 * What the corpus is.
	 *
	 * @var string
	 */
	public string $title = '';

	/**
	 * The language of the posts, for example `en`.
	 *
	 * @var string
	 */
	public string $language = 'en';

	/**
	 * The locale of the simulated site, for example `en_US`.
	 *
	 * @var string
	 */
	public string $locale = 'en_US';

	/**
	 * Whether the labels were made by hand (`labels/<label set>.json`) rather than taken from the topics.
	 *
	 * @var bool
	 */
	public bool $hand_labelled = false;

	/**
	 * The URL the posts link to themselves with, rewritten to the test site on import; null for none.
	 *
	 * @var string|null
	 */
	public ?string $site_url = null;

	/**
	 * The permalink structure of the simulated site, so links between posts resolve; null for the default.
	 *
	 * @var string|null
	 */
	public ?string $permalink_structure = null;

	/**
	 * The posts.
	 *
	 * @var array<int, array{id: string, slug: string, title: string, content: string, excerpt: string, date: string, label: string|null, categories: string[], tags: string[]}>
	 */
	public array $posts = [];

	/**
	 * Hand labels: corpus ID => the IDs of the posts that count as related to it.
	 *
	 * @var array<string, string[]>
	 */
	public array $labels = [];

	/**
	 * The folder with the corpora.
	 *
	 * @return string
	 */
	public static function directory(): string {
		$directory = getenv( 'RP4WP_QUALITY_CORPORA' );

		return is_string( $directory ) && '' !== $directory ? rtrim( $directory, '/' ) : dirname( __DIR__, 2 ) . '/artifacts/quality/corpora';
	}

	/**
	 * The names of the corpora that were built, in their order. RP4WP_QUALITY_ONLY limits them, as a comma-separated
	 * list of names.
	 *
	 * @return string[]
	 */
	public static function available(): array {
		$names = [];

		foreach ( (array) glob( self::directory() . '/*.json' ) as $file ) {
			$name = basename( (string) $file, '.json' );
			if ( 'stopwords-iso' !== $name ) {
				$names[] = $name;
			}
		}

		$only = getenv( 'RP4WP_QUALITY_ONLY' );
		if ( is_string( $only ) && '' !== $only ) {
			$names = array_values( array_intersect( $names, array_map( 'trim', explode( ',', $only ) ) ) );
		}

		usort(
			$names,
			static function ( string $a, string $b ): int {
				$position_a = array_search( $a, self::ORDER, true );
				$position_b = array_search( $b, self::ORDER, true );

				return [ false === $position_a ? PHP_INT_MAX : $position_a, $a ] <=> [ false === $position_b ? PHP_INT_MAX : $position_b, $b ];
			}
		);

		return $names;
	}

	/**
	 * Load a corpus.
	 *
	 * @param string $name The name.
	 *
	 * @return self
	 *
	 * @throws \RuntimeException When the corpus can't be read.
	 */
	public static function load( string $name ): self {
		$meta = self::read_json( self::directory() . '/' . $name . '.json' );

		$corpus                      = new self();
		$corpus->name                = $name;
		$corpus->title               = (string) ( $meta['title'] ?? $name );
		$corpus->language            = (string) ( $meta['language'] ?? 'en' );
		$corpus->locale              = (string) ( $meta['locale'] ?? 'en_US' );
		$corpus->hand_labelled       = 'hand' === ( $meta['labels'] ?? 'topic' );
		$corpus->site_url            = isset( $meta['site_url'] ) ? (string) $meta['site_url'] : null;
		$corpus->permalink_structure = isset( $meta['permalink_structure'] ) ? (string) $meta['permalink_structure'] : null;

		$lines = file( self::directory() . '/' . (string) ( $meta['data'] ?? $name . '.jsonl' ), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		if ( false === $lines ) {
			throw new \RuntimeException( esc_html( "The posts of corpus {$name} can't be read." ) );
		}

		foreach ( $lines as $line ) {
			$post = json_decode( $line, true );
			if ( is_array( $post ) ) {
				$corpus->posts[] = $post + [
					'excerpt'    => '',
					'label'      => null,
					'categories' => [],
					'tags'       => [],
				];
			}
		}

		if ( $corpus->hand_labelled ) {
			$corpus->labels = self::read_labels( (string) ( $meta['label_set'] ?? $name ) );
		}

		return $corpus;
	}

	/**
	 * What counts as related: per post, the posts with the same topic, or the posts labelled as related by hand.
	 *
	 * @return array<string, string[]> Corpus ID => corpus IDs.
	 */
	public function relevant(): array {
		if ( $this->hand_labelled ) {
			return $this->labels;
		}

		$by_label = [];
		foreach ( $this->posts as $post ) {
			if ( null !== $post['label'] ) {
				$by_label[ $post['label'] ][] = $post['id'];
			}
		}

		$relevant = [];
		foreach ( $this->posts as $post ) {
			if ( null !== $post['label'] ) {
				$relevant[ $post['id'] ] = $by_label[ $post['label'] ];
			}
		}

		return $relevant;
	}

	/**
	 * The reference stop words of the language of the corpus (stopwords-iso), or null when there are none.
	 *
	 * @return array<string, bool>|null
	 */
	public function stop_words(): ?array {
		$file = self::directory() . '/stopwords-iso.json';
		if ( ! is_file( $file ) ) {
			return null;
		}

		$lists = self::read_json( $file );

		return isset( $lists[ $this->language ] ) && is_array( $lists[ $this->language ] ) ? Metrics::stop_set( $lists[ $this->language ] ) : null;
	}

	/**
	 * The hand labels of a label set: `{ "posts": { "<id>": { "good": [ "<id>", ... ] } } }`, or `{ "<id>": [ ... ] }`.
	 * A label with `"reviewed": false` is a suggestion that was not confirmed, and is left out.
	 *
	 * @param string $set The label set.
	 *
	 * @return array<string, string[]>
	 */
	private static function read_labels( string $set ): array {
		$file = dirname( self::directory() ) . '/labels/' . $set . '.json';
		if ( ! is_file( $file ) ) {
			return [];
		}

		$data   = self::read_json( $file );
		$posts  = isset( $data['posts'] ) && is_array( $data['posts'] ) ? $data['posts'] : $data;
		$labels = [];

		foreach ( $posts as $id => $label ) {
			// Suggested labels the owner has not confirmed yet don't count.
			if ( is_array( $label ) && false === ( $label['reviewed'] ?? true ) ) {
				continue;
			}

			$good = is_array( $label ) && isset( $label['good'] ) ? $label['good'] : $label;
			if ( is_array( $good ) && count( $good ) > 0 ) {
				$labels[ (string) $id ] = array_values( array_map( 'strval', $good ) );
			}
		}

		return $labels;
	}

	/**
	 * Read a JSON file.
	 *
	 * @param string $file The file.
	 *
	 * @return array<mixed>
	 *
	 * @throws \RuntimeException When it can't be read.
	 */
	private static function read_json( string $file ): array {
		$data = is_file( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local file.

		if ( ! is_array( $data ) ) {
			throw new \RuntimeException( esc_html( "{$file} can't be read." ) );
		}

		return $data;
	}
}
