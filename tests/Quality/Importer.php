<?php
/**
 * The corpus importer class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Quality;

/**
 * Imports the posts of a corpus into the test site, published, with their categories and tags.
 *
 * The hooks of the related posts plugins are off while it imports: on a real site, the words of a post are cached when
 * it is published, but only an installation caches the words of every post with every other post in place (links
 * between posts resolve, for example). The algorithm builds that state afterwards.
 */
final class Importer {

	/**
	 * The hooks the plugins act on when a post is saved.
	 */
	private const HOOKS = [ 'transition_post_status', 'save_post', 'wp_insert_post', 'wp_after_insert_post' ];

	/**
	 * The term IDs of categories by name, created as needed.
	 *
	 * @var array<string, int>
	 */
	private array $categories = [];

	/**
	 * Import the posts of a corpus.
	 *
	 * @param Corpus $corpus The corpus.
	 * @param int    $author The author of the posts, an administrator, so the content is not filtered.
	 *
	 * @return array<string, int> Corpus ID => post ID, in the order of the corpus.
	 *
	 * @throws \RuntimeException When a post can't be inserted.
	 */
	public function import( Corpus $corpus, int $author ): array {
		$detached = self::detach( self::HOOKS );
		$ids      = [];

		wp_defer_term_counting( true );

		try {
			foreach ( $corpus->posts as $post ) {
				$post_id = wp_insert_post(
					wp_slash(
						[
							'post_type'     => 'post',
							'post_status'   => 'publish',
							'post_author'   => $author,
							'post_title'    => $post['title'],
							'post_content'  => $this->content( $corpus, $post['content'] ),
							'post_excerpt'  => $post['excerpt'],
							'post_name'     => $post['slug'],
							'post_date'     => $post['date'],
							'post_category' => array_map( [ $this, 'category' ], $post['categories'] ),
							'tags_input'    => $post['tags'],
						]
					),
					true
				);

				if ( is_wp_error( $post_id ) ) {
					throw new \RuntimeException( esc_html( "Post {$post['id']} can't be inserted: " . $post_id->get_error_message() ) );
				}

				$ids[ $post['id'] ] = (int) $post_id;
			}
		} finally {
			wp_defer_term_counting( false );
			self::attach( $detached );
		}

		return $ids;
	}

	/**
	 * The term ID of a category, created when it does not exist yet.
	 *
	 * @param string $name The name.
	 *
	 * @return int
	 *
	 * @throws \RuntimeException When it can't be created.
	 */
	public function category( string $name ): int {
		if ( ! isset( $this->categories[ $name ] ) ) {
			$term = term_exists( $name, 'category' );
			if ( ! is_array( $term ) ) {
				$term = wp_insert_term( $name, 'category' );
			}

			if ( is_wp_error( $term ) ) {
				throw new \RuntimeException( esc_html( "Category {$name} can't be created: " . $term->get_error_message() ) );
			}

			$this->categories[ $name ] = (int) $term['term_id'];
		}

		return $this->categories[ $name ];
	}

	/**
	 * The content of a post, with links to the original site pointing to the test site.
	 *
	 * @param Corpus $corpus  The corpus.
	 * @param string $content The content.
	 *
	 * @return string
	 */
	private function content( Corpus $corpus, string $content ): string {
		if ( null === $corpus->site_url ) {
			return $content;
		}

		$host = preg_replace( '/^www\./', '', (string) wp_parse_url( $corpus->site_url, PHP_URL_HOST ) );

		return (string) preg_replace( '#https?://(www\.)?' . preg_quote( (string) $host, '#' ) . '#i', untrailingslashit( home_url() ), $content );
	}

	/**
	 * Take the callbacks of the related posts plugins off some hooks.
	 *
	 * @param string[] $tags The hooks.
	 *
	 * @return array<int, array{string, callable, int, int}> What was taken off: hook, callback, priority, arguments.
	 */
	private static function detach( array $tags ): array {
		global $wp_filter;

		$detached = [];

		foreach ( $tags as $tag ) {
			if ( ! isset( $wp_filter[ $tag ] ) ) {
				continue;
			}

			foreach ( $wp_filter[ $tag ]->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $callback ) {
					if ( self::is_plugin_callback( $callback['function'] ) ) {
						remove_filter( $tag, $callback['function'], $priority );
						$detached[] = [ $tag, $callback['function'], (int) $priority, (int) $callback['accepted_args'] ];
					}
				}
			}
		}

		return $detached;
	}

	/**
	 * Put callbacks back that detach() took off.
	 *
	 * @param array<int, array{string, callable, int, int}> $detached What was taken off.
	 *
	 * @return void
	 */
	private static function attach( array $detached ): void {
		foreach ( $detached as [ $tag, $callback, $priority, $arguments ] ) {
			add_filter( $tag, $callback, $priority, $arguments );
		}
	}

	/**
	 * Whether a callback belongs to the free or the premium plugin.
	 *
	 * @param mixed $callback The callback.
	 *
	 * @return bool
	 */
	private static function is_plugin_callback( $callback ): bool {
		$class = null;

		if ( is_array( $callback ) && isset( $callback[0] ) ) {
			$class = is_object( $callback[0] ) ? get_class( $callback[0] ) : (string) $callback[0];
		} elseif ( is_string( $callback ) && false !== strpos( $callback, '::' ) ) {
			$class = (string) strstr( $callback, '::', true );
		}

		return null !== $class && 0 === strpos( $class, 'LV2\\WordPress\\RelatedPostsForWP' );
	}
}
