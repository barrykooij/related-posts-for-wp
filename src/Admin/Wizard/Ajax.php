<?php
/**
 * The installation wizard AJAX class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Admin\Wizard;

use LV2\WordPress\RelatedPostsForWP\Admin\MetaBox\Ajax as MetaBoxAjax;
use LV2\WordPress\RelatedPostsForWP\Compat\LegacyHooks;
use LV2\WordPress\RelatedPostsForWP\Main;
use LV2\WordPress\RelatedPostsForWP\Module;
use LV2\WordPress\RelatedPostsForWP\Related\Finder;
use LV2\WordPress\RelatedPostsForWP\Related\Linker;
use LV2\WordPress\RelatedPostsForWP\Settings\Settings;
use LV2\WordPress\RelatedPostsForWP\Words\Cache;

/**
 * The batches the wizard runs from the browser. Each answers with the number of posts still to do.
 */
class Ajax implements Module {

	/**
	 * Register the AJAX actions.
	 *
	 * @return void
	 */
	public static function setup(): void {
		LegacyHooks::add_action( 'RP4WP_Hook_Ajax_Install_Link_Posts', 'wp_ajax_rp4wp_install_link_posts', [ self::class, 'link_posts' ] );
		LegacyHooks::add_action( 'RP4WP_Hook_Ajax_Install_Save_Words', 'wp_ajax_rp4wp_install_save_words', [ self::class, 'save_words' ] );
	}

	/**
	 * Cache the words of the next batch of posts.
	 *
	 * @return void
	 */
	public static function save_words(): void {
		self::check_access();

		$per_request = isset( $_POST['ppr'] ) ? absint( $_POST['ppr'] ) : 25; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in check_access().

		$cache = new Cache();
		$cache->save_all( $per_request );

		wp_die( esc_html( (string) $cache->uncached_post_count() ) );
	}

	/**
	 * Link the next batch of posts. When all posts are linked, save the chosen amount as the setting.
	 *
	 * @return void
	 */
	public static function link_posts(): void {
		self::check_access();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Checked in check_access().
		$per_request = isset( $_POST['ppr'] ) ? absint( $_POST['ppr'] ) : 5;
		$amount      = isset( $_POST['rel_amount'] ) ? absint( $_POST['rel_amount'] ) : 3;
		// phpcs:enable

		( new Linker() )->link_all( $amount, $per_request );

		$left = ( new Finder() )->unlinked_post_count();

		if ( 0 === $left ) {
			$options                                  = Main::get()->settings()->get_options();
			$options['automatic_linking_post_amount'] = $amount;
			update_option( Settings::OPTION, $options );
		}

		wp_die( esc_html( (string) $left ) );
	}

	/**
	 * Only admins with a valid nonce can run the wizard.
	 *
	 * @return void
	 */
	private static function check_access(): void {
		check_ajax_referer( MetaBoxAjax::NONCE, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to run the installation wizard.', 'related-posts-for-wp' ) );
		}
	}
}
