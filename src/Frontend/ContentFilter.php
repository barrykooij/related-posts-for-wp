<?php
/**
 * The content filter class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Frontend;

use LV2\WordPress\RelatedPostsForWP\Compat\LegacyHooks;
use LV2\WordPress\RelatedPostsForWP\Links\LinkRepository;
use LV2\WordPress\RelatedPostsForWP\Main;
use LV2\WordPress\RelatedPostsForWP\Module;

/**
 * Adds the related posts below the content of single posts.
 */
class ContentFilter implements Module {

	/**
	 * Filter the content late, after other plugins, like 2.x.
	 *
	 * @return void
	 */
	public static function setup(): void {
		LegacyHooks::add_filter( 'RP4WP_Filter_After_Post', 'the_content', [ self::class, 'append' ], 99, 1 );
	}

	/**
	 * Add the related posts to the content of the main post on a single post page.
	 *
	 * @param string $content The content.
	 *
	 * @return string
	 */
	public static function append( $content ) {
		// setup_postdata() sets the global $id but not $post; comparing it with the queried object tells whether this
		// call is for the main content and not, for example, for an excerpt in a sidebar.
		global $id;

		if ( ! is_singular() || ! is_main_query() || $id != get_queried_object_id() ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- $id may be a string.
			return $content;
		}

		/**
		 * Filters whether the related posts are added below the content.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $append Whether to add them. Default true.
		 */
		if ( false === apply_filters( 'rp4wp_append_content', true ) ) {
			return $content;
		}

		return $content . ( new Renderer( new LinkRepository(), Main::get()->settings() ) )->related_posts_html( (int) $id );
	}
}
