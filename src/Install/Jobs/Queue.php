<?php
/**
 * The install queue class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Install\Jobs;

use LV2\WordPress\RelatedPostsForWP\Contracts\InstallPlanner;
use LV2\WordPress\RelatedPostsForWP\Main;

/**
 * Runs installations in the background with Action Scheduler: one job at a time, in batches, in as many requests as
 * it takes. The admin screen only starts, follows and cancels it; closing the screen does not stop it.
 *
 * Each background action works on the job until its time budget is used, then queues the next action. When no
 * action runs for a while (WP-Cron is off, or loopback requests fail), the admin screen calls tick() to work on the
 * job in its own request.
 */
class Queue {

	/**
	 * The Action Scheduler hook of the background action.
	 */
	public const HOOK = 'rp4wp_install_run';

	/**
	 * The Action Scheduler group of the plugin's actions.
	 */
	public const GROUP = 'rp4wp';

	/**
	 * After this many seconds without progress, a running job counts as stalled. Background requests start within
	 * seconds when the site can make them, and a request that works on the job holds the lock.
	 */
	public const STALLED_AFTER = 30;

	/**
	 * How long the lock lasts when the request that holds it dies.
	 */
	private const LOCK_SECONDS = 120;

	/**
	 * The planner.
	 *
	 * @var InstallPlanner
	 */
	private InstallPlanner $planner;

	/**
	 * The job store.
	 *
	 * @var JobStore
	 */
	private JobStore $store;

	/**
	 * Set up the queue.
	 *
	 * @param InstallPlanner|null $planner The planner; the one of the plugin by default.
	 * @param JobStore|null       $store   The job store.
	 */
	public function __construct( ?InstallPlanner $planner = null, ?JobStore $store = null ) {
		$this->planner = $planner ?? Main::get()->install_planner();
		$this->store   = $store ?? new JobStore();
	}

	/**
	 * The current or last job.
	 *
	 * @return Job|null
	 */
	public function job(): ?Job {
		return $this->store->get();
	}

	/**
	 * Start an installation.
	 *
	 * @param array<string, mixed> $request What the admin asked for, validated against the planner's args().
	 *
	 * @return Job|\WP_Error The job, or an error when one runs already.
	 */
	public function start( array $request ) {
		$current = $this->store->get();

		if ( null !== $current && $current->is_running() ) {
			return new \WP_Error( 'rp4wp_install_running', __( 'An installation is running already.', 'related-posts-for-wp' ), [ 'status' => 409 ] );
		}

		$job = Job::start( $request, $this->planner->plan( $request ) );
		$this->store->save( $job );
		$this->enqueue( $job );
		$this->dispatch();

		/**
		 * Fires when an installation starts.
		 *
		 * @since 3.0.0
		 *
		 * @param Job $job The job.
		 */
		do_action( 'rp4wp_install_started', $job );

		return $job;
	}

	/**
	 * Cancel the running installation. What it did so far stays: starting again goes on from there.
	 *
	 * @return Job|null The cancelled job, or null when none runs.
	 */
	public function cancel(): ?Job {
		$job = $this->store->get();

		$this->unschedule();

		if ( null === $job || ! $job->is_running() ) {
			return null;
		}

		$job->end( Job::CANCELLED );
		$this->store->save( $job );

		return $job;
	}

	/**
	 * Run a failed or cancelled installation again, from the step it stopped at.
	 *
	 * @return Job|\WP_Error
	 */
	public function retry() {
		$job = $this->store->get();

		if ( null === $job || $job->is_running() || Job::DONE === $job->status ) {
			return new \WP_Error( 'rp4wp_install_not_resumable', __( 'There is no stopped installation to resume.', 'related-posts-for-wp' ), [ 'status' => 409 ] );
		}

		$job->status        = Job::RUNNING;
		$job->error         = null;
		$job->ended         = 0;
		$job->last_progress = time();
		$this->store->save( $job );
		$this->enqueue( $job );
		$this->dispatch();

		return $job;
	}

	/**
	 * Work on the job: the callback of the background action, and of tick(). Runs batches until the job is done or
	 * the time budget is used, then queues the next action.
	 *
	 * @param string $job_id The job the action was queued for.
	 *
	 * @return bool Whether this request worked on the job.
	 */
	public function run( string $job_id ): bool {
		$job = $this->store->get();

		if ( null === $job || $job->id !== $job_id || ! $job->is_running() ) {
			return false;
		}

		// Another request works on it; if that one dies, this action makes sure the job goes on after the lock expires.
		if ( ! $this->store->lock( self::LOCK_SECONDS ) ) {
			$this->enqueue( $job, self::LOCK_SECONDS );

			return false;
		}

		try {
			$this->work( $job );
		} finally {
			$this->store->unlock();
		}

		return true;
	}

	/**
	 * Work on the job in this request. For when no background request runs it: the admin screen calls this when the
	 * job stalls.
	 *
	 * It runs the job itself, under the same lock as the background action, and leaves the queued action where it is.
	 * Claiming that action through Action Scheduler would be neater, but on a new site Action Scheduler keeps its
	 * actions in posts for its first minutes, and that store fails on a claim by group.
	 *
	 * @return bool Whether this request worked on the job.
	 */
	public function tick(): bool {
		$job = $this->store->get();

		if ( null === $job || ! $job->is_running() || $this->store->is_locked() ) {
			return false;
		}

		return $this->run( $job->id );
	}

	/**
	 * The job with its progress, for the admin screen.
	 *
	 * @return array<string, mixed>|null Null when there never was a job.
	 */
	public function status(): ?array {
		$job = $this->store->get();

		if ( null === $job ) {
			return null;
		}

		$tasks = $job->is_running() ? $this->planner->plan( $job->request ) : [];
		$steps = [];

		foreach ( $job->steps as $index => $step ) {
			$remaining = null;

			if ( $step['done'] ) {
				$remaining = 0;
			} elseif ( $index === $job->current && isset( $tasks[ $index ] ) && null !== $step['total'] ) {
				$remaining = min( $step['total'], $tasks[ $index ]->remaining() );
			}

			$steps[] = [
				'id'        => $step['id'],
				'label'     => $step['label'],
				'total'     => $step['total'],
				'remaining' => $remaining,
				'done'      => $step['done'],
				'current'   => $job->is_running() && $index === $job->current,
			];
		}

		return [
			'id'            => $job->id,
			'status'        => $job->status,
			'request'       => $job->request,
			'steps'         => $steps,
			'started'       => $job->started,
			'last_progress' => $job->last_progress,
			'ended'         => $job->ended,
			'error'         => $job->error,
			'stalled'       => $this->is_stalled( $job ),
			'install'       => $job->is_install(),
			'labels'        => (object) $this->labels( $job ),
		];
	}

	/**
	 * What the admin screen calls a job that is not an installation, such as premium's refresh: by key, `running`,
	 * `done`, `failed`, `cancelled`, `cancel` (the question before cancelling) and `cancel_button`. Keys that are left
	 * out get the installation's words.
	 *
	 * @param Job $job The job.
	 *
	 * @return array<string, string>
	 */
	private function labels( Job $job ): array {
		/**
		 * Filters what the admin screen calls a background job, by key: `running`, `done`, `failed`, `cancelled`,
		 * `cancel` and `cancel_button`. Keys that are left out get the words of an installation.
		 *
		 * @since 3.0.0
		 *
		 * @param array<string, string> $labels The labels. Default none.
		 * @param Job                   $job    The job.
		 */
		$labels = apply_filters( 'rp4wp_job_labels', [], $job );

		return array_map( 'strval', array_filter( (array) $labels, 'is_scalar' ) );
	}

	/**
	 * Remove the plugin's background actions that did not run yet.
	 *
	 * @return void
	 */
	public function unschedule(): void {
		// By group: with a hook, Action Scheduler only matches actions with exactly the arguments given.
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( '', [], self::GROUP );
		}
	}

	/**
	 * Run batches until the job is done, fails, is cancelled or the time budget is used.
	 *
	 * @param Job $job The job; this request holds the lock.
	 *
	 * @return void
	 */
	private function work( Job $job ): void {
		$deadline = microtime( true ) + $this->time_budget();

		try {
			$tasks = $this->planner->plan( $job->request );

			while ( ! $job->is_complete() ) {
				$task = $tasks[ $job->current ] ?? null;

				if ( null === $task || $task->id() !== $job->steps[ $job->current ]['id'] ) {
					$job->end( Job::FAILED, __( 'The steps of the installation changed while it ran. Please start it again.', 'related-posts-for-wp' ) );
					$this->store->save( $job );
					$this->unschedule();

					return;
				}

				if ( null === $job->steps[ $job->current ]['total'] ) {
					$job->steps[ $job->current ]['total'] = $task->remaining();
				}

				$done               = $task->run_batch();
				$job->last_progress = time();

				if ( $done ) {
					$job->steps[ $job->current ]['done'] = true;
					++$job->current;
				}

				// Someone cancelled it, or started another one, while this batch ran: stop, and keep what they saved.
				if ( ! $this->still_running( $job ) ) {
					return;
				}

				if ( $job->is_complete() ) {
					break;
				}

				$this->store->save( $job );

				if ( microtime( true ) >= $deadline ) {
					$this->enqueue( $job );

					return;
				}
			}

			$job->end( Job::DONE );
			$this->store->save( $job );
			$this->unschedule();

			/**
			 * Fires when an installation is done.
			 *
			 * @since 3.0.0
			 *
			 * @param Job $job The job.
			 */
			do_action( 'rp4wp_install_done', $job );
		} catch ( \Throwable $error ) {
			if ( $this->still_running( $job ) ) {
				$job->end( Job::FAILED, $error->getMessage() );
				$this->store->save( $job );
				$this->unschedule();
			}
		}
	}

	/**
	 * Whether the stored job is still this one, and still running.
	 *
	 * @param Job $job The job this request works on.
	 *
	 * @return bool
	 */
	private function still_running( Job $job ): bool {
		$stored = $this->store->get();

		return null !== $stored && $stored->id === $job->id && $stored->is_running();
	}

	/**
	 * Queue the next background action of the job, unless one is waiting already.
	 *
	 * @param Job $job   The job.
	 * @param int $delay Seconds to wait before it runs.
	 *
	 * @return void
	 */
	private function enqueue( Job $job, int $delay = 0 ): void {
		$pending = as_get_scheduled_actions(
			[
				'hook'     => self::HOOK,
				'group'    => self::GROUP,
				'status'   => \ActionScheduler_Store::STATUS_PENDING,
				'per_page' => 1,
			],
			'ids'
		);

		if ( count( $pending ) > 0 ) {
			return;
		}

		if ( $delay > 0 ) {
			as_schedule_single_action( time() + $delay, self::HOOK, [ $job->id ], self::GROUP );
		} else {
			as_enqueue_async_action( self::HOOK, [ $job->id ], self::GROUP );
		}
	}

	/**
	 * Ask Action Scheduler to run its queue in a background request now, instead of on the next WP-Cron run. Action
	 * Scheduler only does this by itself on admin page loads, not on REST requests.
	 *
	 * @return void
	 */
	private function dispatch(): void {
		if ( wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) || ! class_exists( \ActionScheduler_AsyncRequest_QueueRunner::class ) ) {
			return;
		}

		( new \ActionScheduler_AsyncRequest_QueueRunner( \ActionScheduler::store() ) )->maybe_dispatch();
	}

	/**
	 * Whether a running job made no progress for a while, and no request works on it.
	 *
	 * @param Job $job The job.
	 *
	 * @return bool
	 */
	private function is_stalled( Job $job ): bool {
		return $job->is_running() && time() - $job->last_progress > self::STALLED_AFTER && ! $this->store->is_locked();
	}

	/**
	 * How many seconds one request works on the job.
	 *
	 * @return int
	 */
	private function time_budget(): int {
		$budget = 20;
		$limit  = (int) ini_get( 'max_execution_time' );

		if ( $limit > 0 ) {
			$budget = min( $budget, max( 1, intdiv( $limit, 2 ) ) );
		}

		/**
		 * Filters how many seconds one background request works on an installation, before it queues the next one.
		 *
		 * @since 3.0.0
		 *
		 * @param int $budget The seconds. Default 20, or half of max_execution_time when that is lower.
		 */
		return max( 0, (int) apply_filters( 'rp4wp_install_time_budget', $budget ) );
	}
}
