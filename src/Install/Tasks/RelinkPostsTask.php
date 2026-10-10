<?php
/**
 * The relink posts task class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Install\Tasks;

use LV2\WordPress\RelatedPostsForWP\Contracts\InstallTask;
use LV2\WordPress\RelatedPostsForWP\Related\Finder;
use LV2\WordPress\RelatedPostsForWP\Related\Linker;

/**
 * In the update of 3.0 (decision D55): link every post that was linked automatically before again, by difference
 * (see Linker::relink_post()), newest first. Links added by hand keep their place, automatic links that are still
 * found stay, and no post shows fewer related posts at any moment. Posts that were never linked automatically stay as
 * they are.
 */
class RelinkPostsTask implements InstallTask {

	/**
	 * The number of related posts per post.
	 *
	 * @var int
	 */
	private int $amount;

	/**
	 * When the update started, in milliseconds.
	 *
	 * @var int
	 */
	private int $generation;

	/**
	 * Set up the task.
	 *
	 * @param int $amount     The number of related posts per post.
	 * @param int $generation When the update started, in milliseconds.
	 */
	public function __construct( int $amount, int $generation ) {
		$this->amount     = $amount;
		$this->generation = $generation;
	}

	/**
	 * The task ID.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'relink_posts';
	}

	/**
	 * The task name.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Linking your posts again', 'related-posts-for-wp' );
	}

	/**
	 * The posts left.
	 *
	 * @return int
	 */
	public function remaining(): int {
		return ( new Finder() )->linked_before_post_count( $this->generation );
	}

	/**
	 * Link the next posts again.
	 *
	 * @return bool Whether every post is done.
	 */
	public function run_batch(): bool {
		$linker = new Linker();

		foreach ( ( new Finder() )->linked_before_post_ids( $this->generation, BatchSize::of( $this->id(), 5 ) ) as $post_id ) {
			$linker->relink_post( $post_id, $this->amount );
		}

		return 0 === $this->remaining();
	}
}
