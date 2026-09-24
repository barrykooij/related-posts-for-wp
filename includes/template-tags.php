<?php
/**
 * Template tags for themes.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Main;

if ( ! function_exists( 'rp4wp_children' ) ) {
	/**
	 * Show or return the related posts of a post.
	 *
	 * The signature is the one of the premium plugin, so themes can call it the same way with either edition. The free
	 * plugin has one layout: it uses `$limit` and `$offset`, and ignores `$template` and `$heading_text`.
	 *
	 * @since 1.0.0
	 *
	 * @param int|false   $id           The post; the current post when false.
	 * @param bool        $output       Whether to print the list instead of returning it.
	 * @param string      $template     The premium template.
	 * @param int         $limit        The maximum number of related posts; -1 for all.
	 * @param string|null $heading_text The premium heading; null for the setting.
	 * @param int         $offset       How many related posts to skip.
	 *
	 * @return string The list when $output is false, and an empty string after printing it, like premium 2.x.
	 */
	function rp4wp_children( $id = false, $output = true, $template = 'related-posts-default.php', $limit = -1, $heading_text = null, $offset = 0 ) {
		if ( false === $id ) {
			$id = get_the_ID();
		}

		$content = Main::get()->renderer()->render(
			(int) $id,
			[
				'template'     => $template,
				'limit'        => (int) $limit,
				'heading_text' => $heading_text,
				'offset'       => (int) $offset,
			]
		);

		if ( ! $output ) {
			return $content;
		}

		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by the renderer.

		return '';
	}
}
