<?php
/**
 * The mbstring notice class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Admin\Notices;

use LV2\WordPress\RelatedPostsForWP\Module;

/**
 * Warns that the mbstring PHP extension is missing. Finding related words needs it.
 */
class Mbstring implements Module {

	/**
	 * Show the notice when the extension is missing.
	 *
	 * @return void
	 */
	public static function setup(): void {
		if ( ! extension_loaded( 'mbstring' ) ) {
			add_action( 'admin_notices', [ self::class, 'display' ] );
		}
	}

	/**
	 * The notice.
	 *
	 * @return void
	 */
	public static function display(): void {
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'Error:', 'related-posts-for-wp' ); ?></strong>
				<?php
				/* translators: %s: the name of the PHP extension */
				printf( esc_html__( 'The %s extension needs to be installed and activated for Related Posts for WP to work!', 'related-posts-for-wp' ), '<strong>mbstring</strong>' );
				?>
			</p>
			<p>
				<?php
				/* translators: %s: the name of the PHP extension */
				printf( esc_html__( 'Please contact your host and ask them to install the %s PHP extension.', 'related-posts-for-wp' ), '<strong>mbstring</strong>' );
				?>
			</p>
		</div>
		<?php
	}
}
