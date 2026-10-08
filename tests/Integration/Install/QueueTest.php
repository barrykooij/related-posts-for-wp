<?php
/**
 * The install queue test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Install;

use LV2\WordPress\RelatedPostsForWP\Contracts\InstallPlanner;
use LV2\WordPress\RelatedPostsForWP\Contracts\InstallTask;
use LV2\WordPress\RelatedPostsForWP\Install\Jobs\Job;
use LV2\WordPress\RelatedPostsForWP\Install\Jobs\JobStore;
use LV2\WordPress\RelatedPostsForWP\Install\Jobs\Queue;
use LV2\WordPress\RelatedPostsForWP\Install\Planner;
use LV2\WordPress\RelatedPostsForWP\Links\LinkRepository;
use LV2\WordPress\RelatedPostsForWP\Main;
use LV2\WordPress\RelatedPostsForWP\Related\Finder;
use LV2\WordPress\RelatedPostsForWP\Tests\Integration\TestCase;
use LV2\WordPress\RelatedPostsForWP\Words\Cache;

/**
 * The background installer: starting, running in batches over several requests, cancelling, failing and resuming.
 *
 * The background actions run by calling their hook, like Action Scheduler does.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Install\Jobs\Queue
 * @covers \LV2\WordPress\RelatedPostsForWP\Install\Jobs\Job
 * @covers \LV2\WordPress\RelatedPostsForWP\Install\Jobs\JobStore
 * @covers \LV2\WordPress\RelatedPostsForWP\Install\Jobs\Runner
 * @covers \LV2\WordPress\RelatedPostsForWP\Install\Planner
 * @covers \LV2\WordPress\RelatedPostsForWP\Install\Tasks\CacheWordsTask
 * @covers \LV2\WordPress\RelatedPostsForWP\Install\Tasks\LinkPostsTask
 * @covers \LV2\WordPress\RelatedPostsForWP\Install\Tasks\ResetTask
 * @covers \LV2\WordPress\RelatedPostsForWP\Install\Tasks\SaveAmountTask
 */
final class QueueTest extends TestCase {

	/**
	 * The queue under test.
	 *
	 * @var Queue
	 */
	private Queue $queue;

	public function set_up(): void {
		parent::set_up();

		// One batch of two posts per background request, so a small site takes several requests.
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
		( new JobStore() )->unlock();
	}

	public function tear_down(): void {
		$this->queue->unschedule();
		( new JobStore() )->unlock();

		parent::tear_down();
	}

	public function test_starting_plans_the_steps_and_queues_a_background_action(): void {
		$job = $this->queue->start( [ 'amount' => 2 ] );

		$this->assertInstanceOf( Job::class, $job );
		$this->assertSame( [ 'cache_words', 'save_amount', 'link_posts' ], array_column( $job->steps, 'id' ) );
		$this->assertSame( Job::RUNNING, $job->status );
		$this->assertSame( [ $job->id ], $this->pending_action_args() );
		$this->assertSame( '1', (string) get_option( 'rp4wp_is_installing' ) );
	}

	public function test_a_job_that_is_not_an_installation_leaves_the_installing_flag_alone(): void {
		$filter = static function ( bool $is_install, Job $job ): bool {
			return empty( $job->request['other'] );
		};
		add_filter( 'rp4wp_job_is_install', $filter, 10, 2 );

		$job = $this->queue->start( [ 'other' => true ] );

		$this->assertFalse( get_option( 'rp4wp_is_installing' ) );

		update_option( 'rp4wp_is_installing', 1 );
		$this->queue->cancel();

		$this->assertSame( '1', (string) get_option( 'rp4wp_is_installing' ) );
		$this->assertFalse( $job->is_install() );

		remove_filter( 'rp4wp_job_is_install', $filter );
		delete_option( 'rp4wp_is_installing' );
	}

	public function test_the_status_says_whether_the_job_is_an_installation_and_brings_its_labels(): void {
		$this->queue->start( [] );

		$status = $this->queue->status();
		$this->assertTrue( $status['install'] );
		$this->assertEquals( (object) [], $status['labels'] );

		$filter = static function ( array $labels, Job $job ): array {
			return [
				'running' => 'Refreshing',
				'ignored' => [ 'not', 'a', 'string' ],
			];
		};
		add_filter( 'rp4wp_job_is_install', '__return_false' );
		add_filter( 'rp4wp_job_labels', $filter, 10, 2 );

		$status = $this->queue->status();

		remove_filter( 'rp4wp_job_labels', $filter, 10 );
		remove_filter( 'rp4wp_job_is_install', '__return_false' );
		$this->assertFalse( $status['install'] );
		$this->assertEquals( (object) [ 'running' => 'Refreshing' ], $status['labels'] );
	}

	public function test_a_second_installation_can_not_start_while_one_runs(): void {
		$this->queue->start( [] );

		$second = $this->queue->start( [] );

		$this->assertWPError( $second );
		$this->assertSame( 'rp4wp_install_running', $second->get_error_code() );
		$this->assertSame( 409, $second->get_error_data()['status'] );
	}

	public function test_the_planner_follows_the_request(): void {
		$planner = new Planner();

		$this->assertSame(
			[ 'reset', 'cache_words', 'save_amount' ],
			$this->ids(
				$planner->plan(
					[
						'rebuild' => true,
						'skip_linking' => true,
					]
				)
			)
		);

		add_filter(
			'rp4wp_install_max_amount',
			static function () {
				return 4;
			}
		);
		$this->assertSame( 4, $planner->args()['amount']['maximum'] );
	}

	public function test_the_job_caches_and_links_every_post_over_several_requests_and_saves_the_amount(): void {
		$posts = $this->corpus();

		// The settings screen's sanitize callback, which treats a missing checkbox as off, runs on every save.
		add_filter( 'sanitize_option_rp4wp', [ Main::get()->settings(), 'sanitize' ] );
		delete_option( 'rp4wp' );

		$done = 0;
		add_action(
			'rp4wp_install_done',
			static function () use ( &$done ) {
				++$done;
			}
		);

		$job      = $this->queue->start( [ 'amount' => 2 ] );
		$requests = $this->run_until_done( $job->id );

		$this->assertGreaterThan( 3, $requests, 'Six posts in batches of two take several requests.' );
		$this->assertSame( Job::DONE, $this->queue->job()->status );
		$this->assertSame( 1, $done );
		$this->assertSame( 0, ( new Cache() )->uncached_post_count() );
		$this->assertSame( 0, ( new Finder() )->unlinked_post_count() );
		$this->assertCount( 2, ( new LinkRepository() )->get_children( $posts[0] ) );
		$this->assertFalse( get_option( 'rp4wp_is_installing' ) );
		$this->assertSame( [], $this->pending_action_args() );

		$options = get_option( 'rp4wp' );
		$this->assertSame( 2, $options['automatic_linking_post_amount'] );
		$this->assertSame( 1, $options['automatic_linking'], 'Automatic linking stays on.' );
	}

	public function test_the_progress_counts_down_while_the_job_runs(): void {
		$this->corpus();
		$job = $this->queue->start( [ 'amount' => 2 ] );

		do_action( Queue::HOOK, $job->id );
		$status = $this->queue->status();

		$this->assertSame( 'cache_words', $status['steps'][0]['id'] );
		$this->assertSame( 6, $status['steps'][0]['total'] );
		$this->assertSame( 4, $status['steps'][0]['remaining'] );
		$this->assertTrue( $status['steps'][0]['current'] );
		$this->assertNull( $status['steps'][2]['total'], 'A step that did not start has no total yet.' );
		$this->assertFalse( $status['stalled'] );
	}

	public function test_a_post_without_words_does_not_keep_the_job_waiting(): void {
		$post_id = self::factory()->post->create(
			[
				'post_title'   => 'The',
				'post_content' => '',
			]
		);
		delete_post_meta( $post_id, Cache::META_NO_WORDS );

		$job = $this->queue->start( [ 'skip_linking' => true ] );
		$this->run_until_done( $job->id );

		$this->assertSame( Job::DONE, $this->queue->job()->status );
		$this->assertSame( '1', get_post_meta( $post_id, Cache::META_NO_WORDS, true ) );
	}

	public function test_rebuilding_removes_the_old_links_and_words_first(): void {
		$posts   = $this->corpus();
		$old_one = ( new LinkRepository() )->add( $posts[0], $posts[5] );

		$job = $this->queue->start( [ 'rebuild' => true ] );
		$this->run_until_done( $job->id );

		$this->assertSame( Job::DONE, $this->queue->job()->status );
		$this->assertNull( get_post( $old_one ) );
		$this->assertSame( 0, ( new Finder() )->unlinked_post_count() );
	}

	public function test_cancelling_stops_the_job_and_removes_its_actions(): void {
		$this->corpus();
		$job = $this->queue->start( [] );

		$cancelled = $this->queue->cancel();

		$this->assertSame( Job::CANCELLED, $cancelled->status );
		$this->assertSame( [], $this->pending_action_args() );
		$this->assertFalse( get_option( 'rp4wp_is_installing' ) );

		// An action that was running already does nothing more.
		do_action( Queue::HOOK, $job->id );
		$this->assertSame( 6, ( new Cache() )->uncached_post_count() );
	}

	public function test_an_action_of_an_older_job_does_nothing(): void {
		$this->corpus();
		$this->queue->start( [] );

		do_action( Queue::HOOK, 'an-older-job' );

		$this->assertSame( 6, ( new Cache() )->uncached_post_count() );
	}

	public function test_while_another_request_holds_the_lock_the_action_waits_for_it(): void {
		$this->corpus();
		$job = $this->queue->start( [] );
		$this->queue->unschedule();
		( new JobStore() )->lock( 60 );

		do_action( Queue::HOOK, $job->id );

		$this->assertSame( 6, ( new Cache() )->uncached_post_count() );
		$pending = as_get_scheduled_actions(
			[
				'hook'   => Queue::HOOK,
				'status' => \ActionScheduler_Store::STATUS_PENDING,
			]
		);
		$this->assertCount( 1, $pending );
		$this->assertGreaterThan( time() + 60, reset( $pending )->get_schedule()->get_date()->getTimestamp(), 'It runs after the lock expired.' );
	}

	public function test_a_failing_task_fails_the_job_and_retrying_resumes_it(): void {
		$task  = new FailingOnceTask();
		$queue = new Queue( new FixedPlanner( [ $task ] ) );

		$job = $queue->start( [] );
		$queue->run( $job->id );

		$this->assertSame( Job::FAILED, $queue->job()->status );
		$this->assertSame( 'The database went away.', $queue->job()->error );
		$this->assertFalse( get_option( 'rp4wp_is_installing' ) );

		$resumed = $queue->retry();
		$this->assertSame( Job::RUNNING, $resumed->status );
		$this->assertSame( [ $job->id ], $this->pending_action_args() );

		$queue->run( $job->id );
		$this->assertSame( Job::DONE, $queue->job()->status );
	}

	public function test_a_finished_job_can_not_be_retried(): void {
		$job = $this->queue->start( [ 'skip_linking' => true ] );
		$this->run_until_done( $job->id );

		$this->assertWPError( $this->queue->retry() );
	}

	public function test_a_tick_runs_the_next_action_in_this_request(): void {
		$this->corpus();
		$this->queue->start( [] );

		$this->assertTrue( $this->queue->tick() );

		$this->assertSame( 4, ( new Cache() )->uncached_post_count() );
		$this->assertCount( 1, $this->pending_action_args(), 'The next action is queued.' );
	}

	public function test_a_job_without_progress_for_a_minute_is_stalled(): void {
		$job = $this->queue->start( [] );
		$job = $this->queue->job();

		$job->last_progress = time() - Queue::STALLED_AFTER - 5;
		( new JobStore() )->save( $job );

		$this->assertTrue( $this->queue->status()['stalled'] );

		( new JobStore() )->lock( 60 );
		$this->assertFalse( $this->queue->status()['stalled'], 'A request works on it.' );
	}

	public function test_deactivating_the_plugin_cancels_the_job(): void {
		$this->queue->start( [] );

		rp4wp_deactivate_plugin();

		$this->assertSame( Job::CANCELLED, $this->queue->job()->status );
		$this->assertSame( [], $this->pending_action_args() );
	}

	/**
	 * Six published posts in two topics.
	 *
	 * @return int[]
	 */
	private function corpus(): array {
		$posts = [];

		// Publishing a post caches its words and links it; the installer is to do that here.
		update_option( 'rp4wp', [ 'automatic_linking' => 0 ] );

		foreach ( [ 'sourdough bread oven flour', 'bread flour yeast oven', 'oven bread crust flour', 'garden tomato soil water', 'tomato garden seeds water', 'soil water garden plants' ] as $words ) {
			$post_id = self::factory()->post->create(
				[
					'post_title'   => ucfirst( $words ),
					'post_content' => str_repeat( $words . ' ', 5 ),
				]
			);
			( new Cache() )->delete_post( $post_id );
			delete_post_meta( $post_id, 'rp4wp_auto_linked' );
			$posts[] = $post_id;
		}

		return $posts;
	}

	/**
	 * Run the background action until the job is done, like Action Scheduler would.
	 *
	 * @param string $job_id The job.
	 *
	 * @return int The number of requests it took.
	 */
	private function run_until_done( string $job_id ): int {
		for ( $requests = 1; $requests <= 50; $requests++ ) {
			do_action( Queue::HOOK, $job_id );

			if ( ! $this->queue->job()->is_running() ) {
				return $requests;
			}
		}

		$this->fail( 'The job did not finish.' );
	}

	/**
	 * The arguments of the pending background actions.
	 *
	 * @return array<int, mixed>
	 */
	private function pending_action_args(): array {
		$actions = as_get_scheduled_actions(
			[
				'hook'   => Queue::HOOK,
				'group'  => Queue::GROUP,
				'status' => \ActionScheduler_Store::STATUS_PENDING,
			]
		);

		return array_merge(
			...array_values(
				array_map(
					static function ( $action ) {
						return $action->get_args();
					},
					$actions
				)
			)
		);
	}

	/**
	 * The IDs of tasks.
	 *
	 * @param InstallTask[] $tasks The tasks.
	 *
	 * @return string[]
	 */
	private function ids( array $tasks ): array {
		return array_map(
			static function ( InstallTask $task ) {
				return $task->id();
			},
			$tasks
		);
	}
}

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- Test doubles that belong to this test.

/**
 * A planner with fixed tasks.
 */
final class FixedPlanner implements InstallPlanner {

	/**
	 * The tasks.
	 *
	 * @var InstallTask[]
	 */
	private array $tasks;

	/**
	 * Set up the planner.
	 *
	 * @param InstallTask[] $tasks The tasks.
	 */
	public function __construct( array $tasks ) {
		$this->tasks = $tasks;
	}

	/**
	 * No arguments.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function args(): array {
		return [];
	}

	/**
	 * Never installed.
	 *
	 * @return bool
	 */
	public function is_installed(): bool {
		return false;
	}

	/**
	 * The tasks.
	 *
	 * @param array<string, mixed> $request Ignored.
	 *
	 * @return InstallTask[]
	 */
	public function plan( array $request ): array {
		return $this->tasks;
	}
}

/**
 * A task that fails the first time it runs.
 */
final class FailingOnceTask implements InstallTask {

	/**
	 * Whether it ran before.
	 *
	 * @var bool
	 */
	private bool $ran = false;

	/**
	 * The ID.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'failing_once';
	}

	/**
	 * The name.
	 *
	 * @return string
	 */
	public function label(): string {
		return 'Failing once';
	}

	/**
	 * Nothing to count.
	 *
	 * @return int
	 */
	public function remaining(): int {
		return 0;
	}

	/**
	 * Fail the first time.
	 *
	 * @throws \RuntimeException The first time.
	 *
	 * @return bool
	 */
	public function run_batch(): bool {
		if ( ! $this->ran ) {
			$this->ran = true;

			throw new \RuntimeException( 'The database went away.' );
		}

		return true;
	}
}
