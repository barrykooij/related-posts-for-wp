<?php
/**
 * The batch size class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Install\Tasks;

/**
 * How many posts one batch of a task works on.
 */
class BatchSize {

	/**
	 * The batch size of a task.
	 *
	 * @param string $task_id The task.
	 * @param int    $size    The default size.
	 *
	 * @return int At least 1.
	 */
	public static function of( string $task_id, int $size ): int {
		/**
		 * Filters how many posts one batch of an installation task works on. A lower number makes each background
		 * request shorter; the installer runs as many batches as fit in its time budget.
		 *
		 * @since 3.0.0
		 *
		 * @param int    $size    The number of posts. Default 25 for caching words, 5 for linking.
		 * @param string $task_id The task, for example `cache_words` or `link_posts`.
		 */
		return max( 1, (int) apply_filters( 'rp4wp_install_batch_size', $size, $task_id ) );
	}
}
