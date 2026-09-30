<?php
/**
 * The install task contract file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Contracts;

/**
 * One step of an installation, such as caching the words of the posts or linking them. The background installer runs
 * a task in batches, in as many requests as it takes, so a batch must be safe to run again: it works on what is not
 * done yet, never on an offset.
 */
interface InstallTask {

	/**
	 * The ID of the task, the same every time the planner plans it, for example `cache_words`.
	 *
	 * @return string
	 */
	public function id(): string;

	/**
	 * The name of the task, for the progress screen.
	 *
	 * @return string
	 */
	public function label(): string;

	/**
	 * How many items are left, counted from the site as it is now. 0 for a task that is done, or that has no items to
	 * count (it runs in one batch).
	 *
	 * @return int
	 */
	public function remaining(): int;

	/**
	 * Do the next batch of work.
	 *
	 * @return bool Whether the task is done.
	 */
	public function run_batch(): bool;
}
