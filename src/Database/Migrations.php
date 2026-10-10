<?php
/**
 * The migrations module class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Database;

use LV2\WordPress\RelatedPostsForWP\Module;

/**
 * Keeps the database up to date with the code: runs the pending migrations of the free plugin, and then of the
 * add-ons, when the plugin boots, in the background, for new sites of a network, and when an administrator asks.
 *
 * A request spends a few seconds at most; a migration that needs more goes on in a background action of Action
 * Scheduler. A migration that fails is tried again after ten minutes, or when an administrator clicks Try again in the
 * notice about it. Until the migrations are done, the plugin works with the data where it is (see Schema).
 */
final class Migrations implements Module {

	/**
	 * The Action Scheduler hook of the background action.
	 */
	public const ACTION = 'rp4wp_migrate';

	/**
	 * The Action Scheduler group of the background action; not `rp4wp`, which the installer clears when a job ends.
	 */
	public const GROUP = 'rp4wp-db';

	/**
	 * The admin-post action of the Try again button.
	 */
	public const RETRY = 'rp4wp_retry_migrations';

	/**
	 * Seconds a normal request spends on migrations.
	 */
	private const BOOT_BUDGET = 3.0;

	/**
	 * Seconds a background action or a Try again click spends on migrations.
	 */
	private const LONG_BUDGET = 20.0;

	/**
	 * Seconds before a failed migration is tried again by itself.
	 */
	private const RETRY_AFTER = 600;

	/**
	 * Whether migrations are left after this request ran its share.
	 *
	 * @var bool
	 */
	private static bool $unfinished = false;

	/**
	 * Hook in: the background action, new sites, the notice and its button.
	 *
	 * @return void
	 */
	public static function setup(): void {
		add_action( self::ACTION, [ self::class, 'background' ] );
		add_action( 'init', [ self::class, 'schedule' ], 20 );
		add_action( 'wp_initialize_site', [ self::class, 'new_site' ], 20 );
		add_action( 'admin_notices', [ self::class, 'notice' ] );
		add_action( 'network_admin_notices', [ self::class, 'notice' ] );
		add_action( 'admin_post_' . self::RETRY, [ self::class, 'retry' ] );
	}

	/**
	 * The migrators, in the order they run: the free plugin first.
	 *
	 * @return Migrator[]
	 */
	public static function migrators(): array {
		/**
		 * Filters the folders with migrations, by plugin, in the order they run. The migrations of a plugin run once
		 * those of the plugins before it are done. The premium add-on adds its own.
		 *
		 * @since 3.0.0
		 *
		 * @param array<string, string> $sets Plugin (`free`, `premium`) => folder.
		 */
		$sets = (array) apply_filters( 'rp4wp_migration_sets', [ 'free' => dirname( __DIR__, 2 ) . '/migrations' ] );

		$migrators = [];
		foreach ( $sets as $plugin => $directory ) {
			$migrators[] = new Migrator( (string) $plugin, (string) $directory );
		}

		return $migrators;
	}

	/**
	 * Whether migrations were left for later in this request: they go on in the background.
	 *
	 * @return bool
	 */
	public static function unfinished(): bool {
		return self::$unfinished;
	}

	/**
	 * Run the pending migrations when the plugin sets up, before the modules: the callback of `rp4wp_setup_database`.
	 *
	 * @return void
	 */
	public static function on_setup(): void {
		self::boot();
	}

	/**
	 * Run the pending migrations, within a budget. Called when the plugin boots, before the modules set up.
	 *
	 * @param float|null $budget The seconds to spend; 0 for no limit. By default a few seconds, and no limit on the
	 *                           command line.
	 *
	 * @return bool Whether every migration is applied.
	 */
	public static function boot( ?float $budget = null ): bool {
		$budget = $budget ?? ( defined( 'WP_CLI' ) && WP_CLI ? 0.0 : self::BOOT_BUDGET );

		foreach ( self::migrators() as $migrator ) {
			if ( ! $migrator->needs_run() ) {
				continue;
			}

			$failure = $migrator->failure();
			if ( null !== $failure && time() - (int) $failure['time'] < self::RETRY_AFTER ) {
				self::$unfinished = true;

				return false;
			}

			if ( ! $migrator->run( $budget ) ) {
				self::$unfinished = true;

				return false;
			}
		}

		self::$unfinished = false;

		return true;
	}

	/**
	 * Queue the background action when migrations are left: at once when a migration needs more time, or when a failed
	 * one may be tried again.
	 *
	 * @return void
	 */
	public static function schedule(): void {
		if ( ! self::$unfinished || ! function_exists( 'as_has_scheduled_action' ) || as_has_scheduled_action( self::ACTION, [], self::GROUP ) ) {
			return;
		}

		$when = time();
		foreach ( self::migrators() as $migrator ) {
			$failure = $migrator->failure();
			if ( null !== $failure ) {
				$when = max( $when, (int) $failure['time'] + self::RETRY_AFTER );
			}
		}

		if ( $when > time() ) {
			as_schedule_single_action( $when, self::ACTION, [], self::GROUP );
		} else {
			as_enqueue_async_action( self::ACTION, [], self::GROUP );
		}
	}

	/**
	 * The background action: go on with the migrations, and queue the next action when they are not done.
	 *
	 * @return void
	 */
	public static function background(): void {
		self::boot( self::LONG_BUDGET );
		self::schedule();
	}

	/**
	 * Create the tables of a new site of a network.
	 *
	 * @param \WP_Site $site The site.
	 *
	 * @return void
	 */
	public static function new_site( $site ): void {
		if ( ! $site instanceof \WP_Site ) {
			return;
		}

		switch_to_blog( (int) $site->blog_id );
		self::boot( 0.0 );
		restore_current_blog();
	}

	/**
	 * The notice about a migration that failed, with a button to try again.
	 *
	 * @return void
	 */
	public static function notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		foreach ( self::migrators() as $migrator ) {
			$failure = $migrator->failure();
			if ( null === $failure ) {
				continue;
			}

			$url = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::RETRY ), self::RETRY );

			echo '<div class="notice notice-error"><p>';
			esc_html_e( 'Related Posts for WordPress could not update its database tables. It keeps working with the data it has, and tries again every ten minutes.', 'related-posts-for-wp' );
			echo '</p><p><code>' . esc_html( $failure['migration'] . ': ' . $failure['message'] ) . '</code></p>';
			echo '<p><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Try again', 'related-posts-for-wp' ) . '</a></p></div>';

			return;
		}
	}

	/**
	 * The Try again button: forget the failures and run the migrations now.
	 *
	 * @return void
	 */
	public static function retry(): void {
		check_admin_referer( self::RETRY );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'related-posts-for-wp' ), 403 );
		}

		foreach ( self::migrators() as $migrator ) {
			$migrator->clear_failure();
		}

		self::boot( self::LONG_BUDGET );
		self::schedule();

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}
}
