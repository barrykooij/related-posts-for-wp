<?php
/**
 * The WordPress Playground notice class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Admin\Notices;

/**
 * The plugin does not run inside WordPress Playground; this notice explains why.
 */
class Playground {

	/**
	 * Whether the site runs inside WordPress Playground.
	 *
	 * @return bool
	 */
	public static function is_playground(): bool {
		return isset( $_SERVER['HTTP_HOST'] ) && false !== strpos( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ), 'playground.wordpress.net' );
	}

	/**
	 * Show the notice in the admin.
	 *
	 * @return void
	 */
	public static function setup(): void {
		add_action( 'admin_notices', [ self::class, 'display' ] );
	}

	/**
	 * The notice.
	 *
	 * @return void
	 */
	public static function display(): void {
		// The line break and spaces in this string are part of the 2.x translation string; keep them.
		$body = __(
			"We noticed you're running the plugin in the WordPress playground.
                    Our plugin currently doesn't work there, due to limitation on the side of the WP playground.",
			'related-posts-for-wp'
		);
		?>
		<div class="notice notice-error">
			<h3><?php esc_html_e( 'Related Posts for WP is not available inside the WordPress Playground!', 'related-posts-for-wp' ); ?></h3>
			<p><?php echo esc_html( $body ); ?></p>
			<p><?php esc_html_e( 'For more information regarding our plugin:', 'related-posts-for-wp' ); ?></p>
			<p>
				<a href="https://www.relatedpostsforwp.com/tour/?utm_source=playground&utm_medium=button&utm_campaign=notice-box" target="_blank" class="button button-primary button-large"><?php esc_html_e( 'View the tour on relatedpostsforwp.com', 'related-posts-for-wp' ); ?></a>
			</p>
		</div>
		<?php
	}
}
