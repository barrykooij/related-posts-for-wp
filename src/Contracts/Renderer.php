<?php
/**
 * The renderer contract file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Contracts;

/**
 * Builds the HTML of the related posts of a post, for the content filter, the shortcode, the widget and the template
 * tag. The premium add-on replaces it with its templates and components through `Main::set_renderer()`.
 */
interface Renderer {

	/**
	 * The HTML of the related posts of a post; empty when it has none.
	 *
	 * @param int                  $post_id The post.
	 * @param array<string, mixed> $args    What to show: `limit` (-1 for all) and `offset`. The premium add-on also
	 *                                      reads `template` and `heading_text` (null for the setting).
	 *
	 * @return string
	 */
	public function render( int $post_id, array $args = [] ): string;
}
