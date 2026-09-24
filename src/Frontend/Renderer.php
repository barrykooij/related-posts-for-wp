<?php
/**
 * The related posts renderer class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Frontend;

use LV2\WordPress\RelatedPostsForWP\Links\LinkRepository;
use LV2\WordPress\RelatedPostsForWP\Settings\Settings;

/**
 * Renders the list of related posts. The markup and hooks are the same as in 2.x; themes style it.
 */
class Renderer {

	/**
	 * The link repository.
	 *
	 * @var LinkRepository
	 */
	private LinkRepository $links;

	/**
	 * The settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param LinkRepository $links    The link repository.
	 * @param Settings       $settings The settings.
	 */
	public function __construct( LinkRepository $links, Settings $settings ) {
		$this->links    = $links;
		$this->settings = $settings;
	}

	/**
	 * The HTML of the related posts of a post; empty when it has none.
	 *
	 * @param int $post_id The post.
	 * @param int $limit   The maximum number of related posts; -1 for all.
	 * @param int $offset  How many related posts to skip.
	 *
	 * @return string
	 */
	public function related_posts_html( int $post_id, int $limit = -1, int $offset = 0 ): string {
		$related_posts = $this->links->get_children(
			$post_id,
			[
				'posts_per_page' => $limit,
				'offset'         => $offset,
			]
		);

		if ( count( $related_posts ) < 1 ) {
			return '';
		}

		// The markup is kept byte for byte from 2.x, including its escaping, because themes and caches depend on it.
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is buffered and returned; see above.
		ob_start();

		echo "<div class='rp4wp-related-posts'>\n";

		$heading_text = $this->settings->get( 'heading_text' );
		if ( '' != $heading_text ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- Filters may return other types; 2.x compares loosely.
			$heading_text = '<h3>' . esc_html( $heading_text ) . '</h3>' . PHP_EOL;
		}

		/**
		 * Filters the heading above the related posts, including its HTML.
		 *
		 * @since 1.0.0
		 *
		 * @param string $heading_text The heading HTML; empty when the heading text is empty.
		 */
		echo apply_filters( 'rp4wp_heading', $heading_text );

		echo "<ul>\n";

		foreach ( $related_posts as $related_post ) {
			setup_postdata( $related_post );

			/**
			 * Fires before a related post is rendered.
			 *
			 * @since 1.0.0
			 *
			 * @param \WP_Post $related_post The related post.
			 */
			do_action( 'rp4wp_before_content', $related_post );

			echo '<li>';

			if ( 1 == $this->settings->get( 'display_image' ) && has_post_thumbnail( $related_post->ID ) ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- The option is stored as 1 or "1".
				/**
				 * Filters the image size of related post thumbnails.
				 *
				 * @since 1.0.0
				 *
				 * @param string $size The image size. Default 'thumbnail'.
				 */
				$thumb_size = apply_filters( 'rp4wp_thumbnail_size', 'thumbnail' );

				/**
				 * Fires before the image of a related post.
				 *
				 * @since 1.0.0
				 *
				 * @param \WP_Post $related_post The related post.
				 */
				do_action( 'rp4wp_before_image', $related_post );

				echo "<div class='rp4wp-related-post-image'>" . PHP_EOL;
				echo "<a href='" . $this->post_link( $related_post->ID ) . "'>";
				echo get_the_post_thumbnail( $related_post->ID, $thumb_size );
				echo '</a>';
				echo '</div>' . PHP_EOL;

				/**
				 * Fires after the image of a related post.
				 *
				 * @since 1.0.0
				 *
				 * @param \WP_Post $related_post The related post.
				 */
				do_action( 'rp4wp_after_image', $related_post );
			}

			echo "<div class='rp4wp-related-post-content'>" . PHP_EOL;

			/**
			 * Filters the finished title HTML of a related post.
			 *
			 * @since 2.1.4
			 *
			 * @param string   $html         The title HTML.
			 * @param \WP_Post $related_post The related post.
			 */
			echo apply_filters(
				'rp4wp_post_title_html_values',
				sprintf(
					/**
					 * Filters the sprintf() format of the title HTML: %1$s is the link, %2$s the title.
					 *
					 * @since 2.1.1
					 *
					 * @param string   $format       The format.
					 * @param \WP_Post $related_post The related post.
					 */
					apply_filters( 'rp4wp_post_title_html', "<a href='%s'>%s</a>", $related_post ),
					$this->post_link( $related_post->ID ),
					/**
					 * Filters the title of a related post.
					 *
					 * @since 1.0.0
					 *
					 * @param string   $title        The title.
					 * @param \WP_Post $related_post The related post.
					 */
					apply_filters( 'rp4wp_post_title', $related_post->post_title, $related_post )
				),
				$related_post
			);

			$excerpt_length = $this->settings->get( 'excerpt_length' );
			if ( $excerpt_length > 0 ) {
				$excerpt = wp_trim_words( strip_tags( strip_shortcodes( ( '' != $related_post->post_excerpt ) ? $related_post->post_excerpt : $related_post->post_content ) ), $excerpt_length ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags,Universal.Operators.StrictComparisons.LooseNotEqual -- Same as 2.x.

				/**
				 * Filters the excerpt of a related post.
				 *
				 * @since 1.0.0
				 *
				 * @param string $excerpt The excerpt.
				 * @param int    $post_id The related post ID.
				 */
				echo '<p>' . apply_filters( 'rp4wp_post_excerpt', $excerpt, $related_post->ID ) . '</p>';
			}

			echo '</div>' . PHP_EOL;

			echo "</li>\n";

			/**
			 * Fires after a related post is rendered.
			 *
			 * @since 1.0.0
			 *
			 * @param \WP_Post $related_post The related post.
			 */
			do_action( 'rp4wp_after_content', $related_post );

			wp_reset_postdata();
		}

		echo "</ul>\n";

		echo $this->show_love();

		echo "</div>\n";

		// phpcs:enable

		return trim( (string) ob_get_clean() );
	}

	/**
	 * The "Powered by" line, when the site owner turned it on.
	 *
	 * @return string
	 */
	private function show_love(): string {
		if ( '1' != $this->settings->get( 'show_love' ) ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- The option is stored as 1 or "1".
			return '';
		}

		$query_string = '?';

		/**
		 * Filters the affiliate ID added to the "Powered by" link.
		 *
		 * @since 1.3.0
		 *
		 * @param string $ref The affiliate ID. Default empty.
		 */
		$ref = apply_filters( 'rp4wp_poweredby_affiliate_id', '' );
		if ( '' !== $ref ) {
			$query_string .= 'ref=' . intval( $ref ) . '&';
		}

		$query_string .= sprintf(
			'utm_source=%s&utm_medium=link&utm_campaign=poweredby',
			strtolower( (string) preg_replace( '`[^A-z0-9\-.]+`i', '', str_ireplace( ' ', '-', html_entity_decode( get_bloginfo( 'name' ) ) ) ) )
		);

		return '<small><a href="https://www.relatedpostsforwp.com' . htmlentities( $query_string ) . '" target="_blank">Powered By Related Posts for WordPress</a></small>';
	}

	/**
	 * The link to a related post, filterable through `rp4wp_post_link`.
	 *
	 * @param int $post_id The related post.
	 *
	 * @return string
	 */
	private function post_link( int $post_id ): string {
		/**
		 * Filters the link to a related post.
		 *
		 * @since 2.1.1
		 *
		 * @param string $link    The link.
		 * @param int    $post_id The related post.
		 */
		return (string) apply_filters( 'rp4wp_post_link', get_permalink( $post_id ), $post_id );
	}
}
