<?php
/**
 * The migrator class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Database;

/**
 * Runs the migrations of one plugin, in the order of their names, and records each in the migrations table.
 *
 * A run applies the pending migrations under a lock, until all are applied, a migration needs another slice, or one
 * fails. The migrations applied in one run form a batch, which a rollback undoes. What happened is kept in an
 * autoloaded option, so a request can tell from one option whether there is anything to do.
 */
final class Migrator {

	/**
	 * The option with, per plugin, the migrations it last ran with and whether that run finished or failed.
	 */
	public const OPTION_STATE = 'rp4wp_db_state';

	/**
	 * The lock, shared by the migrators of all plugins.
	 */
	private const LOCK = 'rp4wp_migrate_lock';

	/**
	 * How long the lock lasts when the request that holds it dies.
	 */
	private const LOCK_SECONDS = 600;

	/**
	 * The migrations loaded in this request, by file; a file is loaded once.
	 *
	 * @var array<string, Migration>
	 */
	private static array $loaded = [];

	/**
	 * The plugin the migrations belong to: `free` or `premium`.
	 *
	 * @var string
	 */
	private string $plugin;

	/**
	 * The folder with the migration files.
	 *
	 * @var string
	 */
	private string $directory;

	/**
	 * Set up.
	 *
	 * @param string $plugin    The plugin the migrations belong to.
	 * @param string $directory The folder with the migration files.
	 */
	public function __construct( string $plugin, string $directory ) {
		$this->plugin    = $plugin;
		$this->directory = rtrim( $directory, '/' );
	}

	/**
	 * The plugin the migrations belong to.
	 *
	 * @return string
	 */
	public function plugin(): string {
		return $this->plugin;
	}

	/**
	 * The migration files, in order.
	 *
	 * @return array<string, string> Name => file.
	 */
	public function files(): array {
		$files = [];

		foreach ( (array) glob( $this->directory . '/*.php' ) as $file ) {
			$files[ basename( (string) $file, '.php' ) ] = (string) $file;
		}

		ksort( $files, SORT_STRING );

		return $files;
	}

	/**
	 * The migrations that ran, in order.
	 *
	 * @return array<string, int> Name => batch.
	 */
	public function applied(): array {
		global $wpdb;

		$table = Schema::table( Schema::MIGRATIONS );
		if ( ! Schema::has_table( $table ) ) {
			return [];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The plugin's own table.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT migration, batch FROM `{$table}` WHERE plugin = %s ORDER BY id", $this->plugin ) );

		$applied = [];
		foreach ( (array) $rows as $row ) {
			$applied[ (string) $row->migration ] = (int) $row->batch;
		}

		return $applied;
	}

	/**
	 * The migrations that did not run yet, in order.
	 *
	 * @return array<string, string> Name => file.
	 */
	public function pending(): array {
		return array_diff_key( $this->files(), $this->applied() );
	}

	/**
	 * Whether there may be work: the migration files changed since the last run that finished, or that run did not
	 * finish. Reads only the autoloaded state and the folder.
	 *
	 * @return bool
	 */
	public function needs_run(): bool {
		$state = $this->state();

		return empty( $state['complete'] ) || ( $state['hash'] ?? '' ) !== $this->hash();
	}

	/**
	 * The last failure, while it stands: the migration, the message and when.
	 *
	 * @return array{migration: string, message: string, time: int}|null
	 */
	public function failure(): ?array {
		$failure = $this->state()['failure'] ?? null;

		return is_array( $failure ) ? $failure : null;
	}

	/**
	 * Forget the last failure, so the next run tries again at once.
	 *
	 * @return void
	 */
	public function clear_failure(): void {
		$state            = $this->state();
		$state['failure'] = null;

		$this->save_state( $state );
	}

	/**
	 * Apply the pending migrations, in order.
	 *
	 * @param float $budget The seconds to spend; 0 for no limit. A migration that converts data checks it between
	 *                      slices, so a run can take a little longer.
	 *
	 * @return bool Whether every migration is applied now.
	 */
	public function run( float $budget = 0.0 ): bool {
		if ( ! self::lock() ) {
			return false;
		}

		try {
			$deadline = $budget > 0 ? microtime( true ) + $budget : 0.0;
			$batch    = null;

			foreach ( $this->pending() as $name => $file ) {
				if ( 0.0 !== $deadline && microtime( true ) >= $deadline ) {
					$this->finish( false );

					return false;
				}

				try {
					$migration = $this->load( $name, $file );
					$migration->set_deadline( $deadline );

					if ( ! $migration->up() ) {
						$this->finish( false );

						return false;
					}
				} catch ( \Throwable $error ) {
					$this->finish( false, $name, $error->getMessage() );

					return false;
				}

				$migration->clear_cursors();

				$batch = $batch ?? $this->next_batch();
				$this->record( $name, $batch );
			}

			$this->finish( true );

			return true;
		} finally {
			self::unlock();
		}
	}

	/**
	 * Undo migrations, newest first.
	 *
	 * @param int|null $steps How many migrations to undo; null for the last batch.
	 *
	 * @return string[] The names of the migrations that were undone.
	 *
	 * @throws \RuntimeException When the lock is held, a migration file is gone, or a migration fails.
	 */
	public function rollback( ?int $steps = null ): array {
		if ( ! self::lock() ) {
			throw new \RuntimeException( 'Another request is migrating the database.' );
		}

		try {
			$applied = array_reverse( $this->applied(), true );
			$files   = $this->files();
			$last    = count( $applied ) > 0 ? max( $applied ) : 0;
			$undone  = [];

			foreach ( $applied as $name => $batch ) {
				if ( null === $steps ? $batch !== $last : count( $undone ) >= $steps ) {
					break;
				}

				if ( ! isset( $files[ $name ] ) ) {
					throw new \RuntimeException( esc_html( "The file of migration {$name} is missing." ) );
				}

				$migration = $this->load( $name, $files[ $name ] );
				$migration->down();
				$migration->clear_cursors();
				$this->forget( $name );

				$undone[] = $name;
			}

			$this->finish( false );

			return $undone;
		} finally {
			self::unlock();
		}
	}

	/**
	 * The migrations and their state, for `wp rp4wp migrate status`.
	 *
	 * @return array<int, array{migration: string, batch: int|null, description: string}>
	 */
	public function status(): array {
		$applied = $this->applied();
		$status  = [];

		foreach ( $this->files() as $name => $file ) {
			$status[] = [
				'migration'   => $name,
				'batch'       => $applied[ $name ] ?? null,
				'description' => $this->load( $name, $file )->description(),
			];
		}

		return $status;
	}

	/**
	 * One migration, loaded from its file; for the command line and tests.
	 *
	 * @param string $name The name.
	 *
	 * @return Migration
	 *
	 * @throws \RuntimeException When there is no such migration.
	 */
	public function migration( string $name ): Migration {
		$files = $this->files();
		if ( ! isset( $files[ $name ] ) ) {
			throw new \RuntimeException( esc_html( "There is no migration {$name}." ) );
		}

		return $this->load( $name, $files[ $name ] );
	}

	/**
	 * Load a migration from its file.
	 *
	 * @param string $name The name.
	 * @param string $file The file.
	 *
	 * @return Migration
	 *
	 * @throws \RuntimeException When the file does not return a migration.
	 */
	private function load( string $name, string $file ): Migration {
		if ( ! isset( self::$loaded[ $file ] ) ) {
			$migration = require $file;

			if ( ! $migration instanceof Migration ) {
				throw new \RuntimeException( esc_html( "Migration {$name} does not return a migration." ) );
			}

			$migration->set_name( $name );
			self::$loaded[ $file ] = $migration;
		}

		return self::$loaded[ $file ];
	}

	/**
	 * The batch number for the migrations of this run.
	 *
	 * @return int
	 */
	private function next_batch(): int {
		global $wpdb;

		$table = Schema::table( Schema::MIGRATIONS );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The plugin's own table.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE( MAX( batch ), 0 ) + 1 FROM `{$table}` WHERE plugin = %s", $this->plugin ) );
	}

	/**
	 * Record that a migration ran.
	 *
	 * @param string $name  The migration.
	 * @param int    $batch The batch.
	 *
	 * @return void
	 *
	 * @throws \RuntimeException When it can't be recorded.
	 */
	private function record( string $name, int $batch ): void {
		global $wpdb;

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The plugin's own table.
			Schema::table( Schema::MIGRATIONS ),
			[
				'plugin'     => $this->plugin,
				'migration'  => $name,
				'batch'      => $batch,
				'applied_at' => current_time( 'mysql', true ),
			],
			[ '%s', '%s', '%d', '%s' ]
		);

		if ( false === $inserted ) {
			throw new \RuntimeException( esc_html( "Migration {$name} ran but can't be recorded: {$wpdb->last_error}" ) );
		}
	}

	/**
	 * Remove the record of a migration that was undone. The first migration drops the table itself.
	 *
	 * @param string $name The migration.
	 *
	 * @return void
	 */
	private function forget( string $name ): void {
		global $wpdb;

		$table = Schema::table( Schema::MIGRATIONS );
		if ( Schema::has_table( $table ) ) {
			$wpdb->delete( $table, [ 'plugin' => $this->plugin, 'migration' => $name ], [ '%s', '%s' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- The plugin's own table.
		}
	}

	/**
	 * Store how a run ended.
	 *
	 * @param bool        $complete Whether every migration is applied.
	 * @param string|null $failed   The migration that failed, if one did.
	 * @param string      $message  Why it failed.
	 *
	 * @return void
	 */
	private function finish( bool $complete, ?string $failed = null, string $message = '' ): void {
		$this->save_state(
			[
				'hash'     => $this->hash(),
				'complete' => $complete,
				'failure'  => null === $failed ? null : [
					'migration' => $failed,
					'message'   => $message,
					'time'      => time(),
				],
			]
		);
	}

	/**
	 * A fingerprint of the migration files.
	 *
	 * @return string
	 */
	private function hash(): string {
		return md5( implode( ',', array_keys( $this->files() ) ) );
	}

	/**
	 * The stored state of this plugin's migrations.
	 *
	 * @return array<string, mixed>
	 */
	private function state(): array {
		$states = get_option( self::OPTION_STATE, [] );

		return is_array( $states ) && isset( $states[ $this->plugin ] ) && is_array( $states[ $this->plugin ] ) ? $states[ $this->plugin ] : [];
	}

	/**
	 * Store the state of this plugin's migrations.
	 *
	 * @param array<string, mixed> $state The state.
	 *
	 * @return void
	 */
	private function save_state( array $state ): void {
		$states = get_option( self::OPTION_STATE, [] );
		$states = is_array( $states ) ? $states : [];

		$states[ $this->plugin ] = $state;

		update_option( self::OPTION_STATE, $states, true );
	}

	/**
	 * Take the lock, unless another request holds it.
	 *
	 * @return bool Whether this request holds the lock now.
	 */
	private static function lock(): bool {
		global $wpdb;

		$now = time();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- An atomic lock; the options API can't do this.
		$affected = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->options} ( option_name, option_value, autoload ) VALUES ( %s, %s, 'off' )
				ON DUPLICATE KEY UPDATE option_value = IF( option_value < %d, VALUES( option_value ), option_value )",
				self::LOCK,
				(string) ( $now + self::LOCK_SECONDS ),
				$now
			)
		);

		wp_cache_delete( self::LOCK, 'options' );

		// 1 when the row was added, 2 when an expired lock was replaced, 0 when the lock is held.
		return is_int( $affected ) && $affected > 0;
	}

	/**
	 * Release the lock.
	 *
	 * @return void
	 */
	private static function unlock(): void {
		global $wpdb;

		$wpdb->delete( $wpdb->options, [ 'option_name' => self::LOCK ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- See lock().
		wp_cache_delete( self::LOCK, 'options' );
	}
}
