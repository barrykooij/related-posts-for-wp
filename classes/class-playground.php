<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class RP4WP_Playground {

	/**
	 * Check if we're in playground
	 *
	 * @return bool
	 */
	public static function is_playground() {
		if ( isset( $_SERVER['HTTP_HOST'] ) && strpos( $_SERVER['HTTP_HOST'], "playground.wordpress.net" ) !== false ) {
			return true;
		}

		return false;
	}

	/**
	 * Add the admin notice
	 *
	 * @return void
	 */
	public static function add_admin_notice() {
		add_action( 'admin_notices', function () {
			?>
			<div class="notice notice-error">
				<h3><?php esc_html_e( 'Related Posts for WP is not available inside the WordPress Playground!',
						'related-posts-for-wp' ); ?></h3>
				<p><?php esc_html_e( "We noticed you're running the plugin in the WordPress playground.
                    Our plugin currently doesn't work there, due to limitation on the side of the WP playground.",
						'related-posts-for-wp' ); ?></p>
				<p><?php esc_html_e( 'For more information regarding our plugin:', 'related-posts-for-wp' ); ?></p>
				<p>
					<a href="https://www.relatedpostsforwp.com/tour/?utm_source=playground&utm_medium=button&utm_campaign=notice-box"
					   target="_blank" class="button button-primary button-large"><?php esc_html_e( "View the tour on relatedpostsforwp.com",
							'related-posts-for-wp' ); ?></a></p>
			</div>
			<?php
		} );
	}
}