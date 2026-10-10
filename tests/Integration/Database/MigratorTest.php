<?php
/**
 * The migrator test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Database;

use LV2\WordPress\RelatedPostsForWP\Database\Migrator;
use LV2\WordPress\RelatedPostsForWP\Tests\Integration\TestCase;

/**
 * The migrator runs migration files in order, once each, in batches; it goes on with a migration that needs another
 * slice, keeps a failure, and undoes migrations newest first. The fixture migrations only write an option, so every
 * test stays inside its transaction.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Database\Migrator
 * @covers \LV2\WordPress\RelatedPostsForWP\Database\Migration
 */
final class MigratorTest extends TestCase {

	public function set_up(): void {
		parent::set_up();

		delete_option( 'rp4wp_test_migrations' );
	}

	public function test_runs_the_pending_migrations_in_order_and_records_them_in_one_batch(): void {
		$migrator = $this->migrator( 'ok' );

		$this->assertTrue( $migrator->needs_run() );
		$this->assertTrue( $migrator->run() );

		$this->assertSame( [ 'up 2026_01_01_000001_first', 'up 2026_01_01_000002_second' ], $this->log() );
		$this->assertSame(
			[
				'2026_01_01_000001_first'  => 1,
				'2026_01_01_000002_second' => 1,
			],
			$migrator->applied()
		);
		$this->assertSame( [], $migrator->pending() );
		$this->assertFalse( $migrator->needs_run() );
	}

	public function test_a_second_run_does_nothing(): void {
		$migrator = $this->migrator( 'ok' );
		$migrator->run();

		$this->assertTrue( $migrator->run() );

		$this->assertCount( 2, $this->log() );
	}

	public function test_a_migration_that_needs_more_time_goes_on_in_the_next_run(): void {
		$migrator = $this->migrator( 'resumable' );

		$this->assertFalse( $migrator->run() );
		$this->assertTrue( $migrator->needs_run() );
		$this->assertFalse( $migrator->run() );
		$this->assertTrue( $migrator->run() );

		$this->assertSame( [ 'slice 1', 'slice 2', 'slice 3' ], $this->log() );
		$this->assertSame( [], $migrator->pending() );
	}

	public function test_a_failure_stops_the_run_and_is_kept_until_it_is_cleared(): void {
		$migrator = $this->migrator( 'failing' );

		$this->assertFalse( $migrator->run() );

		$failure = $migrator->failure();
		$this->assertSame( '2026_01_01_000002_failing', $failure['migration'] );
		$this->assertSame( 'The disk is full.', $failure['message'] );
		$this->assertSame( [ 'up 2026_01_01_000001_first' ], $this->log() );
		$this->assertSame( [ '2026_01_01_000001_first' ], array_keys( $migrator->applied() ) );

		$migrator->clear_failure();
		$this->assertNull( $migrator->failure() );
	}

	public function test_a_rollback_undoes_the_last_batch_newest_first(): void {
		$migrator = $this->migrator( 'ok' );
		$migrator->run();
		delete_option( 'rp4wp_test_migrations' );

		$this->assertSame( [ '2026_01_01_000002_second', '2026_01_01_000001_first' ], $migrator->rollback() );

		$this->assertSame( [ 'down 2026_01_01_000002_second', 'down 2026_01_01_000001_first' ], $this->log() );
		$this->assertSame( [], $migrator->applied() );
		$this->assertTrue( $migrator->needs_run() );
	}

	public function test_a_rollback_can_undo_a_number_of_migrations(): void {
		$migrator = $this->migrator( 'ok' );
		$migrator->run();

		$this->assertSame( [ '2026_01_01_000002_second' ], $migrator->rollback( 1 ) );
		$this->assertSame( [ '2026_01_01_000001_first' ], array_keys( $migrator->applied() ) );
	}

	public function test_migrations_of_a_later_run_get_the_next_batch(): void {
		$migrator = $this->migrator( 'ok' );
		$migrator->run();
		$migrator->rollback( 1 );

		$migrator->run();

		$this->assertSame(
			[
				'2026_01_01_000001_first'  => 1,
				'2026_01_01_000002_second' => 2,
			],
			$migrator->applied()
		);
	}

	public function test_another_request_that_holds_the_lock_stops_the_run(): void {
		add_option( 'rp4wp_migrate_lock', (string) ( time() + 60 ), '', false );

		$this->assertFalse( $this->migrator( 'ok' )->run() );
		$this->assertSame( [], $this->log() );
	}

	public function test_an_expired_lock_is_taken_over(): void {
		add_option( 'rp4wp_migrate_lock', (string) ( time() - 1 ), '', false );

		$this->assertTrue( $this->migrator( 'ok' )->run() );
	}

	public function test_the_status_lists_every_migration_with_its_batch(): void {
		$migrator = $this->migrator( 'failing' );
		$migrator->run();

		$this->assertSame(
			[
				[
					'migration'   => '2026_01_01_000001_first',
					'batch'       => 1,
					'description' => 'The first.',
				],
				[
					'migration'   => '2026_01_01_000002_failing',
					'batch'       => null,
					'description' => 'One that fails.',
				],
				[
					'migration'   => '2026_01_01_000003_after',
					'batch'       => null,
					'description' => 'One after the failure.',
				],
			],
			$migrator->status()
		);
	}

	public function test_the_free_plugins_migrations_ran_when_the_plugin_booted(): void {
		$migrator = new Migrator( 'free', dirname( __DIR__, 3 ) . '/migrations' );

		$this->assertSame( [], $migrator->pending() );
		$this->assertFalse( $migrator->needs_run() );
	}

	/**
	 * A migrator for a folder of fixture migrations, under its own plugin name.
	 *
	 * @param string $set The folder in tests/Fixtures/migrations.
	 *
	 * @return Migrator
	 */
	private function migrator( string $set ): Migrator {
		return new Migrator( 'test-' . $set, dirname( __DIR__, 2 ) . '/Fixtures/migrations/' . $set );
	}

	/**
	 * What the fixture migrations did.
	 *
	 * @return string[]
	 */
	private function log(): array {
		return array_values( (array) get_option( 'rp4wp_test_migrations', [] ) );
	}
}
