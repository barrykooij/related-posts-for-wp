<?php
/**
 * The hook recorder class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Support;

/**
 * Records which rp4wp_* actions and filters fire, and with how many arguments.
 */
final class HookRecorder {

	/**
	 * Recorded hooks: name => list of argument counts seen.
	 *
	 * @var array<string, int[]>
	 */
	private array $hooks = [];

	/**
	 * Start recording.
	 *
	 * @return void
	 */
	public function start(): void {
		add_action( 'all', [ $this, 'record' ], 10, 99 );
	}

	/**
	 * Stop recording.
	 *
	 * @return void
	 */
	public function stop(): void {
		remove_action( 'all', [ $this, 'record' ], 10 );
	}

	/**
	 * The "all" hook callback.
	 *
	 * @param string $hook The hook name.
	 * @param mixed  ...$args The hook arguments.
	 *
	 * @return void
	 */
	public function record( $hook, ...$args ): void {
		if ( ! is_string( $hook ) || 0 !== strpos( $hook, 'rp4wp_' ) ) {
			return;
		}

		$count = count( $args );
		if ( ! in_array( $count, $this->hooks[ $hook ] ?? [], true ) ) {
			$this->hooks[ $hook ][] = $count;
			sort( $this->hooks[ $hook ] );
		}
	}

	/**
	 * The recorded hooks, sorted by name.
	 *
	 * @return array<string, int[]>
	 */
	public function hooks(): array {
		$hooks = $this->hooks;
		ksort( $hooks );

		return $hooks;
	}
}
