<?php
/**
 * The multisite notice class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Admin\Notices;

/**
 * The free plugin does not support multisite; in the (network) admin it only shows this notice.
 */
class Multisite {

	/**
	 * Show the notice in the site and network admin.
	 *
	 * @return void
	 */
	public static function setup(): void {
		add_action( 'admin_notices', [ self::class, 'display' ] );
		add_action( 'network_admin_notices', [ self::class, 'display' ] );
	}

	/**
	 * The notice.
	 *
	 * @return void
	 */
	public static function display(): void {
		echo '<div class="error"><p>';
		echo esc_html__( "The free version of Related Posts for WordPress doesn't support WordPress Multisite/Network!", 'related-posts-for-wp' );
		echo '<br /><br />';
		printf(
			/* translators: 1: link opening tag, 2: link closing tag */
			esc_html__( '%1$sUpgrade to the premium version%2$s or disable the plugin.', 'related-posts-for-wp' ),
			'<a href="https://www.relatedpostsforwp.com/upgrade-premium/?utm_source=plugin&utm_medium=link&utm_campaign=multisite-notice" target="_blank">',
			'</a>'
		);
		echo '</p></div>';
	}
}
