<?php
/**
 * The installing notice class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Admin\Notices;

use LV2\WordPress\RelatedPostsForWP\Admin\Settings\Page as SettingsPage;
use LV2\WordPress\RelatedPostsForWP\Admin\Wizard\Page;
use LV2\WordPress\RelatedPostsForWP\Install\Jobs\Job;
use LV2\WordPress\RelatedPostsForWP\Install\Jobs\Queue;
use LV2\WordPress\RelatedPostsForWP\Module;

/**
 * Tells admins on other screens how the background installation is doing, or that it stopped. The settings screen
 * shows the progress itself.
 *
 * A 2.x wizard that was left unfinished before the update also left the option behind; the notice then points to the
 * settings screen, and can be dismissed.
 */
class Installing implements Module {

	/**
	 * The nonce action of the dismiss link.
	 */
	public const NONCE = 'rp4wp_hide_is_installing';

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

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- The dismissal checks its nonce; the rest only decides whether to show a notice.
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( isset( $_GET['rp4wp_hide_is_installing'] ) && false !== wp_verify_nonce( $nonce, self::NONCE ) ) {
			delete_option( Page::OPTION_IS_INSTALLING );
		}

		$on_settings = isset( $_GET['page'] ) && in_array( $_GET['page'], [ SettingsPage::SLUG, Page::SLUG ], true );
		// phpcs:enable

		if ( ! $on_settings ) {
			add_action( 'admin_notices', [ self::class, 'display' ] );
		}
	}

	/**
	 * The notice, if there is something to tell.
	 *
	 * @return void
	 */
	public static function display(): void {
		$queue = new Queue();
		$job   = $queue->job();

		if ( null !== $job && $job->is_running() ) {
			self::print_notice(
				'info',
				sprintf(
					/* translators: %d: how far the installation is, in percent */
					__( 'Related Posts for WordPress is linking your posts in the background (%d%%).', 'related-posts-for-wp' ),
					self::percent( $queue->status() )
				),
				__( 'View progress', 'related-posts-for-wp' ),
				SettingsPage::url( 'setup' )
			);

			return;
		}

		if ( null !== $job && Job::FAILED === $job->status ) {
			self::print_notice(
				'error',
				__( 'The installation of Related Posts for WordPress stopped with an error.', 'related-posts-for-wp' ),
				__( 'See what happened', 'related-posts-for-wp' ),
				SettingsPage::url( 'setup' )
			);

			return;
		}

		// Left behind by a 2.x wizard that was not finished.
		if ( false != get_option( Page::OPTION_IS_INSTALLING, false ) ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- Stored as "1".
			self::print_notice(
				'warning',
				__( "Woah! Looks like we weren't able to finish your Related Posts for WordPress installation wizard!", 'related-posts-for-wp' ),
				__( 'Finish the installation', 'related-posts-for-wp' ),
				SettingsPage::url( 'setup' ),
				wp_nonce_url( add_query_arg( 'rp4wp_hide_is_installing', 1 ), self::NONCE )
			);
		}
	}

	/**
	 * How far an installation is, in percent, counting each step the same.
	 *
	 * @param array<string, mixed>|null $status The status of the job.
	 *
	 * @return int
	 */
	public static function percent( ?array $status ): int {
		$steps = (array) ( $status['steps'] ?? [] );

		if ( count( $steps ) < 1 ) {
			return 0;
		}

		$done = 0.0;

		foreach ( $steps as $step ) {
			if ( ! empty( $step['done'] ) ) {
				++$done;
			} elseif ( ! empty( $step['total'] ) && null !== $step['remaining'] ) {
				$done += max( 0, $step['total'] - $step['remaining'] ) / $step['total'];
			}
		}

		return (int) floor( 100 * $done / count( $steps ) );
	}

	/**
	 * Print a notice.
	 *
	 * @param string      $type    The notice type: info, warning or error.
	 * @param string      $message The message.
	 * @param string      $label   The text of the link.
	 * @param string      $url     Where the link goes.
	 * @param string|null $dismiss The URL that dismisses the notice, if it can be dismissed.
	 *
	 * @return void
	 */
	private static function print_notice( string $type, string $message, string $label, string $url, ?string $dismiss = null ): void {
		echo '<div class="notice notice-' . esc_attr( $type ) . '"><p>' . esc_html( $message ) . ' <a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';

		if ( null !== $dismiss ) {
			echo ' | <a href="' . esc_url( $dismiss ) . '">' . esc_html__( 'Dismiss', 'related-posts-for-wp' ) . '</a>';
		}

		echo '</p></div>';
	}
}
