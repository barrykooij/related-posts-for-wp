<?php
/**
 * The update class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Install;

use LV2\WordPress\RelatedPostsForWP\Admin\Notices\Installing;
use LV2\WordPress\RelatedPostsForWP\Admin\Settings\Page as SettingsPage;
use LV2\WordPress\RelatedPostsForWP\Database\Migrations;
use LV2\WordPress\RelatedPostsForWP\Install\Jobs\Job;
use LV2\WordPress\RelatedPostsForWP\Install\Jobs\Queue;
use LV2\WordPress\RelatedPostsForWP\Module;

/**
 * The update to 3.0 (decision D55): once the migrations are through, a background job reads every post again with the
 * tokenizer of 3.0, weighs the words, and links every post that was linked automatically again, by difference, so no
 * post shows fewer related posts at any moment and links added by hand stay. Free and premium alike: premium's planner
 * plans the job per post type.
 *
 * A migration asks for the job on a site that has data from 2.x or an earlier 3.0 build; this module starts it on the
 * next request, when no other job runs. The job is no installation: it does not start the wizard. A notice tells the
 * admin what is happening, and when it is done.
 */
final class Update implements Module {

	/**
	 * The option with the state of the update: whether its job still has to start, and what the notice shows.
	 * Autoloaded, so the check on every request costs nothing.
	 */
	public const OPTION = 'rp4wp_update';

	/**
	 * The admin-post action of the Resume button.
	 */
	public const RESUME = 'rp4wp_resume_update';

	/**
	 * The admin-post action of the Dismiss link.
	 */
	public const DISMISS = 'rp4wp_dismiss_update';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function setup(): void {
		add_filter( 'rp4wp_job_is_install', [ self::class, 'is_install' ], 10, 2 );
		add_filter( 'rp4wp_job_labels', [ self::class, 'labels' ], 10, 2 );
		add_action( 'rp4wp_install_done', [ self::class, 'done' ] );
		add_action( 'wp_loaded', [ self::class, 'maybe_start' ] );
		add_action( 'admin_notices', [ self::class, 'notice' ] );
		add_action( 'admin_post_' . self::RESUME, [ self::class, 'resume' ] );
		add_action( 'admin_post_' . self::DISMISS, [ self::class, 'dismiss' ] );
	}

	/**
	 * Whether a job is the update.
	 *
	 * @param Job|null $job The job.
	 *
	 * @return bool
	 */
	public static function is_update( ?Job $job ): bool {
		return null !== $job && ! empty( $job->request['update'] );
	}

	/**
	 * Start the job of the update when a migration asked for it, every migration is done (premium's reset of the
	 * weights among them) and no other job runs. Until then, every post keeps the related posts it has.
	 *
	 * @return void
	 */
	public static function maybe_start(): void {
		$state = self::state();
		if ( empty( $state['pending'] ) || Migrations::unfinished() || Queue::running() ) {
			return;
		}

		$job = ( new Queue() )->start( [ 'update' => true ] );
		if ( $job instanceof \WP_Error ) {
			return;
		}

		self::save(
			[
				'pending' => false,
				'notice'  => 'running',
			]
		);
	}

	/**
	 * The update is no installation: it does not set `rp4wp_is_installing` or show the notices about one.
	 *
	 * @param mixed $is_install Whether the job is an installation.
	 * @param mixed $job        The job.
	 *
	 * @return bool
	 */
	public static function is_install( $is_install, $job ): bool {
		return (bool) $is_install && ! ( $job instanceof Job && self::is_update( $job ) );
	}

	/**
	 * What the settings screen calls the update.
	 *
	 * @param mixed $labels The labels.
	 * @param mixed $job    The job.
	 *
	 * @return mixed
	 */
	public static function labels( $labels, $job ) {
		if ( ! $job instanceof Job || ! self::is_update( $job ) ) {
			return $labels;
		}

		return array_merge(
			(array) $labels,
			[
				'running'       => __( 'Updating your related posts', 'related-posts-for-wp' ),
				'done'          => __( 'Your related posts are up to date.', 'related-posts-for-wp' ),
				'failed'        => __( 'The update of your related posts stopped', 'related-posts-for-wp' ),
				'cancelled'     => __( 'The update of your related posts was cancelled', 'related-posts-for-wp' ),
				'cancel'        => __( 'Cancel the update of your related posts? Posts it did not reach keep their old related posts.', 'related-posts-for-wp' ),
				'cancel_button' => __( 'Cancel update', 'related-posts-for-wp' ),
			]
		);
	}

	/**
	 * Remember that the update is done, for the notice.
	 *
	 * @param mixed $job The job.
	 *
	 * @return void
	 */
	public static function done( $job ): void {
		if ( $job instanceof Job && self::is_update( $job ) ) {
			self::save( [ 'notice' => 'done' ] );
		}
	}

	/**
	 * The notice about the update, for admins: while it runs, when it stopped, and once when it is done.
	 *
	 * @return void
	 */
	public static function notice(): void {
		$state = self::state();
		if ( empty( $state['notice'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$queue = new Queue();
		$job   = $queue->job();

		if ( self::is_update( $job ) && null !== $job && $job->is_running() ) {
			if ( ! self::on_settings_screen() ) {
				self::print_notice(
					'info',
					sprintf(
						/* translators: %d: how far the update is, in percent */
						__( 'Related Posts for WordPress is updating your related posts in the background (%d%%). Every post keeps its related posts until it gets its new ones.', 'related-posts-for-wp' ),
						Installing::percent( $queue->status() )
					),
					[ __( 'View progress', 'related-posts-for-wp' ) => SettingsPage::url( 'setup' ) ]
				);
			}

			return;
		}

		if ( self::is_update( $job ) && null !== $job && in_array( $job->status, [ Job::FAILED, Job::CANCELLED ], true ) ) {
			self::print_notice(
				'warning',
				__( 'The update of your related posts stopped. Posts it did not reach keep their old related posts until you resume it.', 'related-posts-for-wp' ),
				[ __( 'Resume', 'related-posts-for-wp' ) => wp_nonce_url( admin_url( 'admin-post.php?action=' . self::RESUME ), self::RESUME ) ]
			);

			return;
		}

		if ( 'done' === $state['notice'] ) {
			self::print_notice(
				'success',
				__( 'Your related posts are up to date. Related Posts for WordPress 3.0 finds related posts in a new, more precise way, and linked your posts again; the related posts you added by hand kept their place.', 'related-posts-for-wp' ),
				[
					__( 'What changed', 'related-posts-for-wp' ) => self::notes_url(),
					__( 'Dismiss', 'related-posts-for-wp' )      => wp_nonce_url( admin_url( 'admin-post.php?action=' . self::DISMISS ), self::DISMISS ),
				]
			);
		}
	}

	/**
	 * The Resume button: go on with a stopped update.
	 *
	 * @return void
	 */
	public static function resume(): void {
		check_admin_referer( self::RESUME );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'related-posts-for-wp' ), 403 );
		}

		$job = ( new Queue() )->job();
		if ( self::is_update( $job ) ) {
			( new Queue() )->retry();
		}

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}

	/**
	 * The Dismiss link: stop showing that the update is done.
	 *
	 * @return void
	 */
	public static function dismiss(): void {
		check_admin_referer( self::DISMISS );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'related-posts-for-wp' ), 403 );
		}

		self::save( [ 'notice' => '' ] );

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}

	/**
	 * Where the notice says what changed.
	 *
	 * @return string
	 */
	private static function notes_url(): string {
		/**
		 * Filters where the notice about the update of 3.0 says what changed.
		 *
		 * @since 3.0.0
		 *
		 * @param string $url The address. Default the releases of the plugin on GitHub.
		 */
		return (string) apply_filters( 'rp4wp_update_notes_url', 'https://github.com/barrykooij/related-posts-for-wp/releases' );
	}

	/**
	 * The state of the update.
	 *
	 * @return array{pending?: bool, notice?: string}
	 */
	private static function state(): array {
		$state = get_option( self::OPTION, [] );

		return is_array( $state ) ? $state : [];
	}

	/**
	 * Change the state of the update.
	 *
	 * @param array<string, mixed> $changes The keys to change.
	 *
	 * @return void
	 */
	private static function save( array $changes ): void {
		update_option( self::OPTION, array_merge( self::state(), $changes ), true );
	}

	/**
	 * Whether this request shows the settings screen, which shows the progress itself.
	 *
	 * @return bool
	 */
	private static function on_settings_screen(): bool {
		return isset( $_GET['page'] ) && SettingsPage::SLUG === $_GET['page']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only decides whether to show a notice.
	}

	/**
	 * Print a notice with links.
	 *
	 * @param string                $type    The type: info, warning or success.
	 * @param string                $message The message.
	 * @param array<string, string> $links   Label => address.
	 *
	 * @return void
	 */
	private static function print_notice( string $type, string $message, array $links ): void {
		$anchors = [];
		foreach ( $links as $label => $url ) {
			$anchors[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}

		echo '<div class="notice notice-' . esc_attr( $type ) . '"><p>' . esc_html( $message ) . ' ' . implode( ' | ', $anchors ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
	}
}
