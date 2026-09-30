<?php
/**
 * The cache words task class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Install\Tasks;

use LV2\WordPress\RelatedPostsForWP\Contracts\InstallTask;
use LV2\WordPress\RelatedPostsForWP\Words\Cache;

/**
 * Cache the words of the published posts that have none cached yet.
 */
class CacheWordsTask implements InstallTask {

	/**
	 * The word cache.
	 *
	 * @var Cache
	 */
	private Cache $cache;

	/**
	 * Set up the task.
	 *
	 * @param Cache|null $cache The word cache.
	 */
	public function __construct( ?Cache $cache = null ) {
		$this->cache = $cache ?? new Cache();
	}

	/**
	 * The task ID.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'cache_words';
	}

	/**
	 * The task name.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Caching posts', 'related-posts-for-wp' );
	}

	/**
	 * The posts without cached words. Posts that have no words to cache are marked and not counted (P15).
	 *
	 * @return int
	 */
	public function remaining(): int {
		return $this->cache->uncached_post_count();
	}

	/**
	 * Cache the words of the next posts.
	 *
	 * @return bool Whether every post is cached.
	 */
	public function run_batch(): bool {
		$this->cache->save_all( BatchSize::of( $this->id(), 25 ) );

		return 0 === $this->remaining();
	}
}
