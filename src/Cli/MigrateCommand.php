<?php
/**
 * The migrate command class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Cli;

use LV2\WordPress\RelatedPostsForWP\Database\Migrations;
use LV2\WordPress\RelatedPostsForWP\Database\Migrator;
use LV2\WordPress\RelatedPostsForWP\Database\Schema;

/**
 * Shows and runs the database migrations of Related Posts for WordPress and its add-ons.
 *
 * Migrations run by themselves when the plugin is updated; these commands are for checking them, and for undoing them
 * on a test site or before installing an older version.
 */
final class MigrateCommand {

	/**
	 * Show every migration and whether it ran.
	 *
	 * ## EXAMPLES
	 *
	 *     wp rp4wp migrate status
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 *
	 * @return void
	 */
	public function status( $args, $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed,Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- The signature of a WP-CLI subcommand.
		$rows = [];

		foreach ( Migrations::migrators() as $migrator ) {
			foreach ( $migrator->status() as $status ) {
				$rows[] = [
					'plugin'      => $migrator->plugin(),
					'migration'   => $status['migration'],
					'batch'       => null === $status['batch'] ? 'pending' : (string) $status['batch'],
					'description' => $status['description'],
				];
			}

			$failure = $migrator->failure();
			if ( null !== $failure ) {
				\WP_CLI::warning( sprintf( '%s failed: %s', $failure['migration'], $failure['message'] ) );
			}
		}

		\WP_CLI\Utils\format_items( 'table', $rows, [ 'plugin', 'migration', 'batch', 'description' ] );
		\WP_CLI::line( sprintf( 'Storage level: %d (0: 2.x, 1: marks in the post state table, 2: links in the links table too).', Schema::storage() ) );
	}

	/**
	 * Run the pending migrations.
	 *
	 * ## EXAMPLES
	 *
	 *     wp rp4wp migrate up
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 *
	 * @return void
	 */
	public function up( $args, $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed,Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- The signature of a WP-CLI subcommand.
		foreach ( Migrations::migrators() as $migrator ) {
			$migrator->clear_failure();
			$pending = array_keys( $migrator->pending() );

			if ( ! $migrator->run() ) {
				$failure = $migrator->failure();
				\WP_CLI::error( null === $failure ? sprintf( 'The migrations of %s did not finish; another request may be running them.', $migrator->plugin() ) : sprintf( '%s failed: %s', $failure['migration'], $failure['message'] ) );
			}

			foreach ( $pending as $name ) {
				\WP_CLI::line( sprintf( 'Migrated: %s', $name ) );
			}
		}

		\WP_CLI::success( 'The database is up to date.' );
	}

	/**
	 * Undo migrations, newest first. The next request runs them again, unless an older version of the plugin is
	 * installed first.
	 *
	 * ## OPTIONS
	 *
	 * [--steps=<number>]
	 * : How many migrations to undo. Default: the last batch.
	 *
	 * [--plugin=<plugin>]
	 * : Whose migrations to undo: free or premium. Default: the last plugin with migrations that ran.
	 *
	 * ## EXAMPLES
	 *
	 *     wp rp4wp migrate rollback --steps=2
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 *
	 * @return void
	 */
	public function rollback( $args, $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- The signature of a WP-CLI subcommand.
		$migrator = $this->migrator( $assoc_args['plugin'] ?? null );
		$steps    = isset( $assoc_args['steps'] ) ? max( 1, (int) $assoc_args['steps'] ) : null;

		try {
			$undone = $migrator->rollback( $steps );
		} catch ( \Throwable $error ) {
			\WP_CLI::error( $error->getMessage() );
		}

		foreach ( $undone as $name ) {
			\WP_CLI::line( sprintf( 'Rolled back: %s', $name ) );
		}

		\WP_CLI::success( sprintf( 'Rolled back %d migrations of %s.', count( $undone ), $migrator->plugin() ) );
	}

	/**
	 * Undo the last batch of migrations, or a number of them, and run them again.
	 *
	 * ## OPTIONS
	 *
	 * [--steps=<number>]
	 * : How many migrations to undo. Default: the last batch.
	 *
	 * [--plugin=<plugin>]
	 * : Whose migrations: free or premium. Default: the last plugin with migrations that ran.
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 *
	 * @return void
	 */
	public function redo( $args, $assoc_args ): void {
		$this->rollback( $args, $assoc_args );
		$this->up( $args, $assoc_args );
	}

	/**
	 * The migrator of a plugin, or of the last plugin with migrations that ran.
	 *
	 * @param string|null $plugin The plugin.
	 *
	 * @return Migrator
	 */
	private function migrator( ?string $plugin ): Migrator {
		$migrators = Migrations::migrators();

		foreach ( null === $plugin ? array_reverse( $migrators ) : $migrators as $migrator ) {
			if ( null === $plugin ? count( $migrator->applied() ) > 0 : $migrator->plugin() === $plugin ) {
				return $migrator;
			}
		}

		\WP_CLI::error( null === $plugin ? 'No migrations ran yet.' : sprintf( 'There are no migrations of %s.', $plugin ) );
	}
}
