<?php
/**
 * Template tags for themes.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Frontend\Renderer;
use LV2\WordPress\RelatedPostsForWP\Links\LinkRepository;
use LV2\WordPress\RelatedPostsForWP\Main;

if ( ! function_exists( 'rp4wp_children' ) ) {
	/**
	 * Show or return the related posts of a post.
	 *
	 * @since 1.0.0
	 *
	 * @param int|false $id     The post; the current post when false.
	 * @param bool      $output Whether to print the list instead of returning it.
	 *
	 * @return string|void The list when $output is false.
	 */
	function rp4wp_children( $id = false, $output = true ) {
		if ( false === $id ) {
			$id = get_the_ID();
		}

		$content = ( new Renderer( new LinkRepository(), Main::get()->settings() ) )->related_posts_html( (int) $id );

		if ( ! $output ) {
			return $content;
		}

		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by the renderer.
	}
}
