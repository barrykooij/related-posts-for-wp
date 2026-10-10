<?php
/**
 * The update test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Install;

use LV2\WordPress\RelatedPostsForWP\Admin\Settings\Page as SettingsPage;
use LV2\WordPress\RelatedPostsForWP\Database\Migrations;
use LV2\WordPress\RelatedPostsForWP\Database\Migrator;
use LV2\WordPress\RelatedPostsForWP\Database\Schema;
use LV2\WordPress\RelatedPostsForWP\Install\Jobs\Job;
use LV2\WordPress\RelatedPostsForWP\Install\Jobs\JobStore;
use LV2\WordPress\RelatedPostsForWP\Install\Jobs\Queue;
use LV2\WordPress\RelatedPostsForWP\Install\Planner;
use LV2\WordPress\RelatedPostsForWP\Install\Update;
use LV2\WordPress\RelatedPostsForWP\Links\Deferred;
use LV2\WordPress\RelatedPostsForWP\Links\LinkRepository;
use LV2\WordPress\RelatedPostsForWP\Links\PostLifecycle;
use LV2\WordPress\RelatedPostsForWP\Links\PostState;
use LV2\WordPress\RelatedPostsForWP\Related\Linker;
use LV2\WordPress\RelatedPostsForWP\Tests\Integration\Security\RedirectException;
use LV2\WordPress\RelatedPostsForWP\Tests\Integration\TestCase;

/**
 * The update to 3.0: a migration asks for it on a site with data, the next request starts its job, which reads every
 * post again, weighs the words and relinks the posts that were linked automatically, by difference. Automatic linking
 * waits while it runs.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Install\Update
 * @covers \LV2\WordPress\RelatedPostsForWP\Install\Planner
 * @covers \LV2\WordPress\RelatedPostsForWP\Install\Tasks\ReindexWordsTask
 * @covers \LV2\WordPress\RelatedPostsForWP\Install\Tasks\RelinkPostsTask
 * @covers \LV2\WordPress\RelatedPostsForWP\Links\PostLifecycle
 */
final class UpdateTest extends TestCase {

	/**
	 * The queue.
	 *
	 * @var Queue
	 */
	private Queue $queue;

	/**
	 * The posts, by name.
	 *
	 * @var array<string, int>
	 */
	private array $posts = [];

	public function set_up(): void {
		parent::set_up();

		// One batch of two posts per background request, so the update takes several requests.
		add_filter( 'rp4wp_install_time_budget', '__return_zero' );
		add_filter(
			'rp4wp_install_batch_size',
			static function () {
				return 2;
			}
		);

		$this->queue = new Queue( new Planner() );
		$this->queue->unschedule();
		delete_option( JobStore::OPTION );
		delete_option( Update::OPTION );
		( new JobStore() )->unlock();
		Deferred::take();

		// Publishing caches the words of a post; the test links them.
		update_option(
			'rp4wp',
			[
				'automatic_linking'             => 0,
				'automatic_linking_post_amount' => 2,
			]
		);

		foreach ( [
			'bread'     => 'sourdough bread oven flour',
			'rolls'     => 'bread flour yeast oven',
			'crust'     => 'oven bread crust flour',
			'garden'    => 'garden tomato soil water',
			'seeds'     => 'tomato garden seeds water',
			'unlinked'  => 'soil water garden plants',
		] as $name => $words ) {
			$this->posts[ $name ] = self::factory()->post->create(
				[
					'post_title'   => ucfirst( $words ),
					'post_content' => str_repeat( $words . ' ', 5 ),
				]
			);
		}

		$linker = new Linker();
		foreach ( $this->posts as $name => $post_id ) {
			if ( 'unlinked' !== $name ) {
				$linker->link_post( $post_id, 2 );
			}
		}

		// The marks are in milliseconds: an update that starts now must come after the posts were linked.
		usleep( 2000 );
	}

	public function tear_down(): void {
		$this->queue->unschedule();
		( new JobStore() )->unlock();
		Deferred::take();

		parent::tear_down();
	}

	public function test_the_migration_asks_for_the_update_on_a_site_with_data_only(): void {
		$migration = ( new Migrator( 'free', dirname( __DIR__, 3 ) . '/migrations' ) )->migration( '2026_10_11_000009_queue_update' );

		$this->assertTrue( $migration->up() );
		$this->assertSame(
			[
				'pending' => true,
				'notice'  => '',
			],
			get_option( Update::OPTION )
		);

		// No words, no links and no post linked automatically. DELETE, not TRUNCATE, which would end the transaction of
		// the test.
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Test setup of our own tables.
		$wpdb->query( 'DELETE FROM ' . Schema::table( Schema::CACHE ) );
		$wpdb->query( 'DELETE FROM ' . Schema::table( Schema::LINKS ) );
		$wpdb->query( 'UPDATE ' . Schema::table( Schema::POST_STATE ) . ' SET linked_at = 0' );
		// phpcs:enable

		$this->assertTrue( $migration->up() );
		$this->assertFalse( $this->state()['pending'], 'A site without words or links installs with the wizard.' );

		$migration->down();
		$this->assertFalse( get_option( Update::OPTION ) );
	}

	public function test_the_next_request_starts_the_job_which_is_no_installation(): void {
		update_option( Update::OPTION, [ 'pending' => true ] );

		Update::maybe_start();

		$job = $this->queue->job();
		$this->assertInstanceOf( Job::class, $job );
		$this->assertTrue( $job->request['update'] );
		$this->assertSame( [ 'reindex_words', 'weigh_words', 'relink_posts' ], array_column( $job->steps, 'id' ) );
		$this->assertFalse( $job->is_install() );
		$this->assertFalse( get_option( 'rp4wp_is_installing' ) );
		$this->assertSame(
			[
				'pending' => false,
				'notice'  => 'running',
			],
			get_option( Update::OPTION )
		);

		$status = $this->queue->status();
		$this->assertFalse( $status['install'] );
		$this->assertSame( 'Updating your related posts', $status['labels']->running );
		$this->assertSame( 'Cancel update', $status['labels']->cancel_button );
	}

	public function test_the_job_waits_for_a_job_that_runs(): void {
		$this->queue->start( [ 'amount' => 2 ] );
		update_option( Update::OPTION, [ 'pending' => true ] );

		Update::maybe_start();

		$this->assertEmpty( $this->queue->job()->request['update'] ?? null );
		$this->assertTrue( $this->state()['pending'] );
	}

	public function test_the_job_waits_for_the_migrations_left_for_the_background(): void {
		update_option( Update::OPTION, [ 'pending' => true ] );
		$unfinished = new \ReflectionProperty( Migrations::class, 'unfinished' );
		$unfinished->setAccessible( true );
		$unfinished->setValue( null, true );

		try {
			Update::maybe_start();
		} finally {
			$unfinished->setValue( null, false );
		}

		$this->assertNull( $this->queue->job() );
		$this->assertTrue( $this->state()['pending'] );
	}

	public function test_nothing_starts_without_the_request_of_the_migration(): void {
		Update::maybe_start();

		$this->assertNull( $this->queue->job() );
	}

	public function test_every_post_is_read_again_and_the_linked_posts_are_relinked_by_difference(): void {
		$links  = new LinkRepository();
		$manual = $links->add( $this->posts['bread'], $this->posts['garden'] );
		$before = $this->link_counts();

		// The amount counts the links added by hand: with three, bread keeps both automatic links next to its own.
		update_option(
			'rp4wp',
			[
				'automatic_linking'             => 0,
				'automatic_linking_post_amount' => 3,
			]
		);

		update_option( Update::OPTION, [ 'pending' => true ] );
		Update::maybe_start();
		$job        = $this->queue->job();
		$generation = (int) $job->request['generation'];

		for ( $i = 0; $i < 50 && $this->queue->job()->is_running(); $i++ ) {
			do_action( Queue::HOOK, $job->id );

			foreach ( $this->link_counts() as $post_id => $count ) {
				$this->assertGreaterThanOrEqual( $before[ $post_id ], $count, "Post {$post_id} showed fewer related posts during the update." );
			}
		}

		$this->assertSame( Job::DONE, $this->queue->job()->status );

		foreach ( $this->posts as $name => $post_id ) {
			$this->assertGreaterThanOrEqual( $generation, PostState::indexed_at( $post_id ), "The words of {$name} were read again." );
		}

		foreach ( $this->posts as $name => $post_id ) {
			if ( 'unlinked' === $name ) {
				$this->assertFalse( PostState::is_linked( $post_id ), 'A post that was never linked automatically stays that way.' );
				$this->assertSame( [], $links->child_ids( $post_id ) );
			} else {
				$this->assertGreaterThanOrEqual( $generation, PostState::linked_at( $post_id ), "{$name} was linked again." );
			}
		}

		$this->assertNotNull( $links->find( $manual ), 'The link added by hand stays.' );
		$this->assertTrue( $links->find( $manual )['manual'] );
		$this->assertSame( 'done', $this->state()['notice'] );
	}

	public function test_a_post_published_while_a_job_runs_is_linked_when_it_is_done(): void {
		update_option(
			'rp4wp',
			[
				'automatic_linking'             => 1,
				'automatic_linking_post_amount' => 2,
			]
		);
		update_option( Update::OPTION, [ 'pending' => true ] );
		Update::maybe_start();
		$job = $this->queue->job();

		$newer = self::factory()->post->create(
			[
				'post_title'   => 'Rye bread',
				'post_content' => str_repeat( 'rye bread flour oven sourdough ', 5 ),
			]
		);

		$this->assertFalse( PostState::is_linked( $newer ), 'Linking waits while the job runs.' );
		$this->assertTrue( Deferred::has() );

		PostLifecycle::run_deferred();
		$this->assertFalse( PostState::is_linked( $newer ), 'The posts that wait are not linked while the job runs.' );

		for ( $i = 0; $i < 50 && $this->queue->job()->is_running(); $i++ ) {
			do_action( Queue::HOOK, $job->id );
		}

		PostLifecycle::run_deferred();

		$this->assertTrue( PostState::is_linked( $newer ) );
		$this->assertContains( $this->posts['bread'], ( new LinkRepository() )->child_ids( $newer ) );
		$this->assertFalse( Deferred::has() );
	}

	public function test_the_notice_shows_the_progress_outside_the_settings_screen(): void {
		$this->act_as( 'administrator' );
		update_option( Update::OPTION, [ 'pending' => true ] );
		Update::maybe_start();

		$html = $this->notice();
		$this->assertStringContainsString( 'notice-info', $html );
		$this->assertStringContainsString( 'updating your related posts in the background (0%)', $html );
		$this->assertStringContainsString( esc_url( SettingsPage::url( 'setup' ) ), $html );

		$_GET['page'] = SettingsPage::SLUG;
		$this->assertSame( '', $this->notice(), 'The settings screen shows the progress itself.' );
		unset( $_GET['page'] );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$this->assertSame( '', $this->notice(), 'Only admins see it.' );
	}

	public function test_a_stopped_update_can_be_resumed(): void {
		$this->act_as( 'administrator' );
		update_option( Update::OPTION, [ 'pending' => true ] );
		Update::maybe_start();
		$this->queue->cancel();

		$html = $this->notice();
		$this->assertStringContainsString( 'notice-warning', $html );
		$this->assertStringContainsString( 'action=' . Update::RESUME, $html );

		$_REQUEST['_wpnonce'] = wp_create_nonce( Update::RESUME );
		add_filter( 'wp_redirect', [ self::class, 'throw_on_redirect' ] );

		try {
			Update::resume();
			$this->fail( 'Resuming did not redirect.' );
		} catch ( RedirectException $e ) {
			$this->assertSame( Job::RUNNING, $this->queue->job()->status );
		} finally {
			remove_filter( 'wp_redirect', [ self::class, 'throw_on_redirect' ] );
			unset( $_REQUEST['_wpnonce'] );
		}
	}

	public function test_once_done_the_notice_says_what_changed_until_it_is_dismissed(): void {
		$this->act_as( 'administrator' );
		update_option( Update::OPTION, [ 'pending' => true ] );
		Update::maybe_start();
		$job = $this->queue->job();
		for ( $i = 0; $i < 50 && $this->queue->job()->is_running(); $i++ ) {
			do_action( Queue::HOOK, $job->id );
		}

		add_filter(
			'rp4wp_update_notes_url',
			static function () {
				return 'https://example.org/notes';
			}
		);

		$html = $this->notice();
		$this->assertStringContainsString( 'notice-success', $html );
		$this->assertStringContainsString( 'https://example.org/notes', $html );
		$this->assertStringContainsString( 'action=' . Update::DISMISS, $html );

		$_REQUEST['_wpnonce'] = wp_create_nonce( Update::DISMISS );
		add_filter( 'wp_redirect', [ self::class, 'throw_on_redirect' ] );

		try {
			Update::dismiss();
			$this->fail( 'Dismissing did not redirect.' );
		} catch ( RedirectException $e ) {
			$this->assertSame( '', $this->notice() );
		} finally {
			remove_filter( 'wp_redirect', [ self::class, 'throw_on_redirect' ] );
			unset( $_REQUEST['_wpnonce'] );
		}
	}

	public function test_another_job_keeps_its_own_labels(): void {
		$this->queue->start( [ 'amount' => 2 ] );

		$this->assertSame( [ 'running' => 'Installing' ], Update::labels( [ 'running' => 'Installing' ], $this->queue->job() ) );
		$this->assertTrue( Update::is_install( true, $this->queue->job() ) );
	}

	// phpcs:disable Squiz.Commenting.FunctionComment.InvalidNoReturn -- A filter callback must declare a return type, but this one always throws.
	/**
	 * The wp_redirect filter callback that stops a handler before its exit().
	 *
	 * @param string $location The redirect target.
	 *
	 * @return string Never returns: it always throws.
	 *
	 * @throws RedirectException Always.
	 */
	public static function throw_on_redirect( $location ): string {
		throw new RedirectException( $location ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test-only exception.
	}
	// phpcs:enable

	/**
	 * The state of the update, as stored now.
	 *
	 * @phpstan-impure
	 *
	 * @return array<string, mixed>
	 */
	private function state(): array {
		return (array) get_option( Update::OPTION, [] );
	}

	/**
	 * The notice about the update.
	 *
	 * @return string
	 */
	private function notice(): string {
		ob_start();
		Update::notice();

		return (string) ob_get_clean();
	}

	/**
	 * How many related posts each post has.
	 *
	 * @return array<int, int>
	 */
	private function link_counts(): array {
		$counts = [];
		foreach ( $this->posts as $post_id ) {
			$counts[ $post_id ] = count( ( new LinkRepository() )->child_ids( $post_id ) );
		}

		return $counts;
	}
}
