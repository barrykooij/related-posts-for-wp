<?php
/**
 * The installing notice class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Admin\Notices;

use LV2\WordPress\RelatedPostsForWP\Admin\Wizard\Page;
use LV2\WordPress\RelatedPostsForWP\Module;

/**
 * Offers to resume the installation wizard when it was not finished.
 */
class Installing implements Module {

	/**
	 * Handle a dismissal and show the notice when needed. Unlike other modules this does its work during setup, at
	 * the same moment as 2.x did.
	 *
	 * @return void
	 */
	public static function setup(): void {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Dismissing a notice, like 2.x (known issue K9).
		if ( isset( $_GET['rp4wp_hide_is_installing'] ) ) {
			delete_option( Page::OPTION_IS_INSTALLING );
		}

		$on_wizard = isset( $_GET['page'] ) && Page::SLUG === $_GET['page'];
		// phpcs:enable

		if ( false != get_option( Page::OPTION_IS_INSTALLING, false ) && ! $on_wizard ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- Stored as "1".
			add_action( 'admin_notices', [ self::class, 'display' ] );
		}
	}

	/**
	 * The notice.
	 *
	 * @return void
	 */
	public static function display(): void {
		$resume  = add_query_arg(
			[
				'page'        => Page::SLUG,
				'rp4wp_nonce' => wp_create_nonce( Page::NONCE ),
			]
		);
		$dismiss = add_query_arg( 'rp4wp_hide_is_installing', 1 );

		echo '<div class="error"><p>';
		echo esc_html__( "Woah! Looks like we weren't able to finish your Related Posts for WordPress installation wizard!", 'related-posts-for-wp' );
		echo '<br /><br />';
		printf(
			/* translators: 1: resume link opening tag, 2: link closing tag, 3: dismiss link opening tag, 4: link closing tag */
			esc_html__( '%1$sResume the installation wizard%2$s or %3$sdismiss this notice%4$s', 'related-posts-for-wp' ),
			'<a href="' . esc_url( $resume ) . '">',
			'</a>',
			'<a href="' . esc_url( $dismiss ) . '">',
			'</a>'
		);
		echo '</p></div>';
	}
}
