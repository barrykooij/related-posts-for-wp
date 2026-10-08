<?php
/**
 * The install job class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Install\Jobs;

/**
 * An installation that runs in the background: what was asked for, its steps and how far it got.
 */
final class Job {

	public const RUNNING   = 'running';
	public const DONE      = 'done';
	public const CANCELLED = 'cancelled';
	public const FAILED    = 'failed';

	/**
	 * A random ID; the background actions carry it, so an action of an older job does nothing.
	 *
	 * @var string
	 */
	public string $id;

	/**
	 * One of the status constants.
	 *
	 * @var string
	 */
	public string $status = self::RUNNING;

	/**
	 * What the admin asked for, as the planner reads it.
	 *
	 * @var array<string, mixed>
	 */
	public array $request = [];

	/**
	 * The steps, in order: `id`, `label`, `total` (the items when the step started, null before) and `done`.
	 *
	 * @var array<int, array{id: string, label: string, total: int|null, done: bool}>
	 */
	public array $steps = [];

	/**
	 * The index of the step that runs now; the number of steps when all are done.
	 *
	 * @var int
	 */
	public int $current = 0;

	/**
	 * When the job started, as a Unix timestamp.
	 *
	 * @var int
	 */
	public int $started = 0;

	/**
	 * When a batch last finished, as a Unix timestamp.
	 *
	 * @var int
	 */
	public int $last_progress = 0;

	/**
	 * When the job ended, as a Unix timestamp, or 0.
	 *
	 * @var int
	 */
	public int $ended = 0;

	/**
	 * Why the job failed, or null.
	 *
	 * @var string|null
	 */
	public ?string $error = null;

	/**
	 * Start a job.
	 *
	 * @param array<string, mixed>                                     $request What the admin asked for.
	 * @param \LV2\WordPress\RelatedPostsForWP\Contracts\InstallTask[] $tasks   Its tasks.
	 *
	 * @return self
	 */
	public static function start( array $request, array $tasks ): self {
		$job                = new self();
		$job->id            = wp_generate_uuid4();
		$job->request       = $request;
		$job->started       = time();
		$job->last_progress = $job->started;

		foreach ( $tasks as $task ) {
			$job->steps[] = [
				'id'    => $task->id(),
				'label' => $task->label(),
				'total' => null,
				'done'  => false,
			];
		}

		return $job;
	}

	/**
	 * A job from what to_array() stored.
	 *
	 * @param mixed $data The stored data.
	 *
	 * @return self|null Null when the data is not a job.
	 */
	public static function from_array( $data ): ?self {
		if ( ! is_array( $data ) || ! isset( $data['id'], $data['status'], $data['steps'] ) || ! is_array( $data['steps'] ) ) {
			return null;
		}

		$job                = new self();
		$job->id            = (string) $data['id'];
		$job->status        = (string) $data['status'];
		$job->request       = is_array( $data['request'] ?? null ) ? $data['request'] : [];
		$job->current       = (int) ( $data['current'] ?? 0 );
		$job->started       = (int) ( $data['started'] ?? 0 );
		$job->last_progress = (int) ( $data['last_progress'] ?? 0 );
		$job->ended         = (int) ( $data['ended'] ?? 0 );
		$job->error         = isset( $data['error'] ) ? (string) $data['error'] : null;

		foreach ( $data['steps'] as $step ) {
			$job->steps[] = [
				'id'    => (string) ( $step['id'] ?? '' ),
				'label' => (string) ( $step['label'] ?? '' ),
				'total' => isset( $step['total'] ) ? (int) $step['total'] : null,
				'done'  => ! empty( $step['done'] ),
			];
		}

		return $job;
	}

	/**
	 * The job as an array, to store.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return [
			'id'            => $this->id,
			'status'        => $this->status,
			'request'       => $this->request,
			'steps'         => $this->steps,
			'current'       => $this->current,
			'started'       => $this->started,
			'last_progress' => $this->last_progress,
			'ended'         => $this->ended,
			'error'         => $this->error,
		];
	}

	/**
	 * Whether the job still has work to do.
	 *
	 * @return bool
	 */
	public function is_running(): bool {
		return self::RUNNING === $this->status;
	}

	/**
	 * Whether the job installs the plugin, as opposed to other background work on the links, such as premium's refresh.
	 * Only an installation sets `rp4wp_is_installing` and shows the installation notices.
	 *
	 * @return bool
	 */
	public function is_install(): bool {
		/**
		 * Filters whether a background job is an installation. Only an installation sets `rp4wp_is_installing` and
		 * shows the notices about the installation on other admin screens.
		 *
		 * @since 3.0.0
		 *
		 * @param bool $is_install Whether the job is an installation. Default true.
		 * @param Job  $job        The job.
		 */
		return (bool) apply_filters( 'rp4wp_job_is_install', true, $this );
	}

	/**
	 * Whether every step is done.
	 *
	 * @return bool
	 */
	public function is_complete(): bool {
		return $this->current >= count( $this->steps );
	}

	/**
	 * End the job.
	 *
	 * @param string      $status The status it ends with.
	 * @param string|null $error  Why it failed, for FAILED.
	 *
	 * @return void
	 */
	public function end( string $status, ?string $error = null ): void {
		$this->status = $status;
		$this->error  = $error;
		$this->ended  = time();
	}
}
