<?php
/**
 * The related posts widget class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Frontend;

use LV2\WordPress\RelatedPostsForWP\Main;

/**
 * The classic "Related Posts for WordPress" widget. It has no options.
 */
class Widget extends \WP_Widget {

	/**
	 * The widget ID base. Saved widgets are stored under it, so it never changes.
	 */
	public const ID_BASE = 'rp4wp_related_posts_widget';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			self::ID_BASE,
			__( 'Related Posts for WordPress', 'related-posts-for-wp' ),
			[ 'description' => __( 'Display related posts.', 'related-posts-for-wp' ) ]
		);
	}

	/**
	 * Show the related posts of the current post; nothing on the blog index.
	 *
	 * @param array<string, string> $args     The sidebar arguments.
	 * @param array<string, mixed>  $instance The widget settings.
	 *
	 * @return void
	 */
	public function widget( $args, $instance ) {
		if ( is_front_page() && false === is_page() ) {
			return;
		}

		$content = Main::get()->renderer()->render( (int) get_the_ID() );

		if ( '' !== $content ) {
			echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Theme markup.
			echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by the renderer.
			echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Theme markup.
		}
	}
}
