<?php
/**
 * The review notice class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Admin\Notices;

use LV2\WordPress\RelatedPostsForWP\Module;

/**
 * Asks for a review on WordPress.org, from ten days after installation until the user dismisses it.
 */
class Review implements Module {

	/**
	 * The option with the installation date, as Y-m-d.
	 */
	public const OPTION_INSTALL_DATE = 'rp4wp_install_date';

	/**
	 * The user meta that hides the notice, which is also the query argument that dismisses it.
	 */
	public const DISMISS_KEY = 'rp4wp_hide_nag';

	/**
	 * The number of days after installation before the notice shows.
	 */
	private const DAYS = 10;

	/**
	 * Show the notice when it is due, and handle a dismissal. Like 2.x this does its work during setup, for users who
	 * can install plugins.
	 *
	 * @return void
	 */
	public static function setup(): void {
		if ( ! is_admin() || ! current_user_can( 'install_plugins' ) ) {
			return;
		}

		if ( self::is_due() ) {
			add_action( 'admin_notices', [ self::class, 'display' ] );
		}

		self::handle_dismissal();
	}

	/**
	 * The notice.
	 *
	 * @return void
	 */
	public static function display(): void {
		echo '<div class="updated"><p>';
		printf(
			/* translators: 1: bold opening tag, 2: bold closing tag */
			esc_html__( "You've been using %sRelated Posts for WordPress%s for some time now, could you please give it a review at wordpress.org?", 'related-posts-for-wp' ), // phpcs:ignore WordPress.WP.I18n.UnorderedPlaceholdersText -- The 2.x string; translations exist for it.
			'<b>',
			'</b>'
		);
		echo '<br /><br />';
		printf(
			/* translators: 1: review link opening tag, 2: link closing tag, 3: dismiss link opening tag, 4: link closing tag */
			esc_html__( "%sYes, take me there!%s - %sI've already done this!%s", 'related-posts-for-wp' ), // phpcs:ignore WordPress.WP.I18n.UnorderedPlaceholdersText -- The 2.x string; translations exist for it.
			'<a href="http://wordpress.org/support/view/plugin-reviews/related-posts-for-wp" target="_blank">',
			'</a>',
			'<a href="' . esc_url( add_query_arg( self::DISMISS_KEY, '1' ) ) . '">',
			'</a>'
		);
		echo '</p></div>';
	}

	/**
	 * Hide the notice for the current user when they dismissed it, then reload the page without the argument.
	 *
	 * @return void
	 */
	public static function handle_dismissal(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only hides a notice for the current user, like 2.x.
		if ( ! isset( $_GET[ self::DISMISS_KEY ] ) || ! current_user_can( 'install_plugins' ) ) {
			return;
		}

		add_user_meta( get_current_user_id(), self::DISMISS_KEY, '1', true );

		wp_safe_redirect( remove_query_arg( self::DISMISS_KEY ) );
		exit;
	}

	/**
	 * Whether the current user should see the notice.
	 *
	 * @return bool
	 */
	private static function is_due(): bool {
		if ( '' !== get_user_meta( get_current_user_id(), self::DISMISS_KEY, true ) ) {
			return false;
		}

		return new \DateTimeImmutable( '-' . self::DAYS . ' days' ) >= self::install_date();
	}

	/**
	 * The installation date. Sites installed before 1.2.0 have none; for them the count starts now.
	 *
	 * @return \DateTimeImmutable
	 */
	private static function install_date(): \DateTimeImmutable {
		$today = new \DateTimeImmutable( 'today' );
		$date  = get_option( self::OPTION_INSTALL_DATE, '' );

		if ( '' === $date || ! is_string( $date ) ) {
			add_option( self::OPTION_INSTALL_DATE, $today->format( 'Y-m-d' ), '', false );

			return $today;
		}

		try {
			return new \DateTimeImmutable( $date );
		} catch ( \Exception $e ) {
			// An unreadable date would stop every admin page; count from today instead.
			return $today;
		}
	}
}
