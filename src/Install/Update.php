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
 * The update job (decisions D55 and D72): once the migrations are through, a background job brings the related posts up
 * to date with a new version, so no post shows fewer related posts at any moment and links added by hand stay. Free and
 * premium alike: premium's planner plans the job per post type.
 *
 * A migration of either plugin asks for the job with request(), saying how much has to be done (the scope):
 * - `read`: read every post again (a new way of finding words), weigh the words and relink every post that was linked
 *   automatically, by difference; the update to 3.0 was one;
 * - `weigh`: weigh the words again and relink (a new way of weighing them);
 * - `relink`: relink only, every post or some posts.
 * Requests merge into one job that does the most any of them asked for. This module starts it on the next request,
 * when no other job runs. The job is no installation: it does not start the wizard. A notice tells the admin what is
 * happening, and when it is done, unless every request asked for no notice.
 *
 * With a persistent object cache, an autoloaded option can come back stale: every autoloaded option sits in one cached
 * entry, and a request that loaded it earlier can write its older copy back. So the notice keeps its state in an option
 * of its own that is not autoloaded, the notice trusts the job over that state, and the job only starts when the
 * database, not just the cache, says it should.
 */
final class Update implements Module {

	/**
	 * The option that says whether the job of the update still has to start, as `pending`, and what it does: `scope`,
	 * `versions` (the versions that asked for it), `post_ids` (null for every post) and `notice`. Autoloaded, so the
	 * check on every request costs nothing.
	 */
	public const OPTION = 'rp4wp_update';

	/**
	 * The scopes, from the least to the most work.
	 */
	public const SCOPES = [ 'relink', 'weigh', 'read' ];

	/**
	 * The option with what the notice shows: `running` or `done`; gone once the notice is dismissed. Not autoloaded, so
	 * it has a cache entry of its own, and only the admin reads it.
	 */
	public const NOTICE_OPTION = 'rp4wp_update_notice';

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
	 * Ask for the update job, from a migration. Requests merge with what is pending (see merge()); the job starts on a
	 * request after the migrations, when no other job runs.
	 *
	 * @since 3.1.0
	 *
	 * @param string     $scope    What has to be done: `read`, `weigh` or `relink` (see SCOPES).
	 * @param string     $version  The version that asks, for the notice, for example `3.1.0`.
	 * @param int[]|null $post_ids For `relink`: the posts to relink; null for every post linked automatically.
	 * @param bool       $notice   Whether the admin sees a notice while it runs and when it is done.
	 *
	 * @return void
	 */
	public static function request( string $scope, string $version, ?array $post_ids = null, bool $notice = true ): void {
		update_option( self::OPTION, self::merge( self::state(), $scope, $version, $post_ids, $notice ), true );
	}

	/**
	 * A request merged into the state of the update: the widest scope wins, the versions add up, a list of posts only
	 * counts for `relink` (and every post wins over a list), and the notice shows when any request wants it. A state that
	 * is not pending starts over.
	 *
	 * @param array<string, mixed> $state    The state, as stored (see state()).
	 * @param string               $scope    The scope of the request.
	 * @param string               $version  The version that asks.
	 * @param int[]|null           $post_ids The posts to relink, or null for every post.
	 * @param bool                 $notice   Whether the request wants the notice.
	 *
	 * @return array{pending: true, scope: string, versions: string[], post_ids: int[]|null, notice: bool}
	 */
	public static function merge( array $state, string $scope, string $version, ?array $post_ids, bool $notice ): array {
		$scope = in_array( $scope, self::SCOPES, true ) ? $scope : 'read';
		$ids   = null === $post_ids ? null : array_values( array_unique( array_map( 'intval', $post_ids ) ) );

		if ( empty( $state['pending'] ) ) {
			return [
				'pending'  => true,
				'scope'    => $scope,
				'versions' => [ $version ],
				'post_ids' => 'relink' === $scope ? $ids : null,
				'notice'   => $notice,
			];
		}

		$current = self::scope_of( $state );
		$wider   = array_search( $scope, self::SCOPES, true ) > array_search( $current, self::SCOPES, true ) ? $scope : $current;
		$before  = array_key_exists( 'post_ids', $state ) && is_array( $state['post_ids'] ) ? array_map( 'intval', $state['post_ids'] ) : null;

		$merged_ids = null;
		if ( 'relink' === $wider && null !== $before && null !== $ids ) {
			$merged_ids = array_values( array_unique( array_merge( $before, $ids ) ) );
		}

		return [
			'pending'  => true,
			'scope'    => $wider,
			'versions' => array_values( array_unique( array_merge( self::versions_of( $state ), [ $version ] ) ) ),
			'post_ids' => $merged_ids,
			'notice'   => $notice || ! array_key_exists( 'notice', $state ) || ! empty( $state['notice'] ),
		];
	}

	/**
	 * Start the job of the update when a migration asked for it, every migration is done (premium's reset of the
	 * weights among them) and no other job runs. Until then, every post keeps the related posts it has.
	 *
	 * @return void
	 */
	public static function maybe_start(): void {
		if ( ! self::pending() || Migrations::unfinished() || Queue::running() ) {
			return;
		}

		// The cache can say pending after the job started; only the database decides.
		$state = self::stored_in_database();
		if ( null === $state || empty( $state['pending'] ) ) {
			wp_cache_delete( 'alloptions', 'options' );
			wp_cache_delete( self::OPTION, 'options' );

			return;
		}

		$scope  = self::scope_of( $state );
		$notice = ! array_key_exists( 'notice', $state ) || ! empty( $state['notice'] );
		$job    = ( new Queue() )->start(
			[
				'update'   => true,
				'scope'    => $scope,
				'versions' => self::versions_of( $state ),
				'post_ids' => 'relink' === $scope && isset( $state['post_ids'] ) && is_array( $state['post_ids'] ) ? array_map( 'intval', $state['post_ids'] ) : null,
				'notice'   => $notice,
			]
		);
		if ( $job instanceof \WP_Error ) {
			return;
		}

		// The versions stay, for the notice once the job is done.
		update_option(
			self::OPTION,
			[
				'pending'  => false,
				'versions' => self::versions_of( $state ),
			],
			true
		);

		if ( $notice ) {
			self::set_notice( 'running' );
		}
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
		if ( $job instanceof Job && self::is_update( $job ) && ( ! array_key_exists( 'notice', $job->request ) || ! empty( $job->request['notice'] ) ) ) {
			self::set_notice( 'done' );
		}
	}

	/**
	 * The notice about the update, for admins: while it runs, when it stopped, and once when it is done.
	 *
	 * @return void
	 */
	public static function notice(): void {
		$notice = (string) get_option( self::NOTICE_OPTION, '' );
		if ( '' === $notice || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$queue = new Queue();
		$job   = $queue->job();

		// The job is the truth: when the update is done but the notice did not hear of it, it is done.
		if ( 'running' === $notice && self::is_update( $job ) && null !== $job && Job::DONE === $job->status ) {
			$notice = 'done';
			self::set_notice( $notice );
		}

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

		if ( 'done' === $notice ) {
			$versions = self::versions_of( self::state() );
			$version  = (string) end( $versions );
			$message  = in_array( '3.0.0', $versions, true )
				? __( 'Your related posts are up to date. Related Posts for WordPress 3.0 finds related posts in a new, more precise way, and linked your posts again; the related posts you added by hand kept their place.', 'related-posts-for-wp' )
				: sprintf(
					/* translators: %s: the version, for example 3.1 */
					__( 'Your related posts are up to date. Related Posts for WordPress %s improved how it finds related posts and linked your posts again; the related posts you added by hand kept their place.', 'related-posts-for-wp' ),
					self::short_version( $version )
				);

			self::print_notice(
				'success',
				$message,
				[
					__( 'What changed', 'related-posts-for-wp' ) => self::notes_url( in_array( '3.0.0', $versions, true ) ? '3.0.0' : $version ),
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

		delete_option( self::NOTICE_OPTION );

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}

	/**
	 * Where the notice says what changed in a version.
	 *
	 * @param string $version The version, for example 3.1.0.
	 *
	 * @return string
	 */
	private static function notes_url( string $version ): string {
		$page = '3.0.0' === $version || '' === $version ? '3-0' : str_replace( '.', '-', self::short_version( $version ) );

		/**
		 * Filters where the notice about the update says what changed.
		 *
		 * @since 3.0.0
		 * @since 3.1.0 The version that asked for the update.
		 *
		 * @param string $url     The address. Default the page about the version on relatedpostsforwp.com.
		 * @param string $version The version, for example 3.1.0.
		 */
		return (string) apply_filters( 'rp4wp_update_notes_url', 'https://www.relatedpostsforwp.com/related-posts-for-wordpress-version-' . $page . '/?utm_source=plugin&utm_medium=link&utm_campaign=update-notice', $version );
	}

	/**
	 * A version without its patch number: 3.1.0 gives 3.1.
	 *
	 * @param string $version The version.
	 *
	 * @return string
	 */
	private static function short_version( string $version ): string {
		$parts = explode( '.', $version );

		return implode( '.', array_slice( $parts, 0, 2 ) );
	}

	/**
	 * The state of the update, as the options (and so the cache) say.
	 *
	 * @return array<string, mixed>
	 */
	private static function state(): array {
		$state = get_option( self::OPTION, [] );

		return is_array( $state ) ? $state : [];
	}

	/**
	 * The scope of a state; a state of 3.0, which had none, reads every post again.
	 *
	 * @param array<string, mixed> $state The state.
	 *
	 * @return string
	 */
	private static function scope_of( array $state ): string {
		$scope = $state['scope'] ?? 'read';

		return is_string( $scope ) && in_array( $scope, self::SCOPES, true ) ? $scope : 'read';
	}

	/**
	 * The versions of a state; a state of 3.0, which had none, is the update to 3.0.
	 *
	 * @param array<string, mixed> $state The state.
	 *
	 * @return string[]
	 */
	private static function versions_of( array $state ): array {
		if ( ! isset( $state['versions'] ) || ! is_array( $state['versions'] ) || [] === $state['versions'] ) {
			return [ '3.0.0' ];
		}

		return array_values( array_map( 'strval', $state['versions'] ) );
	}

	/**
	 * Whether the job of the update still has to start, as the options (and so the cache) say.
	 *
	 * @return bool
	 */
	private static function pending(): bool {
		return ! empty( self::state()['pending'] );
	}

	/**
	 * The state of the update in the database, past the cache; null when there is none.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function stored_in_database(): ?array {
		global $wpdb;

		$value = maybe_unserialize( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name = %s', $wpdb->options, self::OPTION ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Past the cache on purpose.

		return is_array( $value ) ? $value : null;
	}

	/**
	 * Set what the notice shows.
	 *
	 * @param string $notice `running` or `done`.
	 *
	 * @return void
	 */
	private static function set_notice( string $notice ): void {
		update_option( self::NOTICE_OPTION, $notice, false );
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
			// A page outside the admin opens in a new tab, like the other links to relatedpostsforwp.com.
			$target    = 0 === strpos( $url, admin_url() ) ? '' : ' target="_blank" rel="noopener"';
			$anchors[] = '<a href="' . esc_url( $url ) . '"' . $target . '>' . esc_html( $label ) . '</a>';
		}

		echo '<div class="notice notice-' . esc_attr( $type ) . '"><p>' . esc_html( $message ) . ' ' . implode( ' | ', $anchors ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
	}
}
