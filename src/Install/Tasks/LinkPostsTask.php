<?php
/**
 * The link posts task class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Install\Tasks;

use LV2\WordPress\RelatedPostsForWP\Contracts\InstallTask;
use LV2\WordPress\RelatedPostsForWP\Related\Finder;
use LV2\WordPress\RelatedPostsForWP\Related\Linker;

/**
 * Link the related posts of the published posts that were not linked automatically yet.
 */
class LinkPostsTask implements InstallTask {

	/**
	 * The number of related posts per post.
	 *
	 * @var int
	 */
	private int $amount;

	/**
	 * The linker.
	 *
	 * @var Linker
	 */
	private Linker $linker;

	/**
	 * The finder, which counts the posts left.
	 *
	 * @var Finder
	 */
	private Finder $finder;

	/**
	 * Set up the task.
	 *
	 * @param int         $amount The number of related posts per post.
	 * @param Linker|null $linker The linker.
	 * @param Finder|null $finder The finder.
	 */
	public function __construct( int $amount, ?Linker $linker = null, ?Finder $finder = null ) {
		$this->amount = $amount;
		$this->finder = $finder ?? new Finder();
		$this->linker = $linker ?? new Linker( $this->finder );
	}

	/**
	 * The task ID.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'link_posts';
	}

	/**
	 * The task name.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Linking posts', 'related-posts-for-wp' );
	}

	/**
	 * The posts not linked yet. A post without related posts counts as linked once it was tried.
	 *
	 * @return int
	 */
	public function remaining(): int {
		return $this->finder->unlinked_post_count();
	}

	/**
	 * Link the next posts.
	 *
	 * @return bool Whether every post is linked.
	 */
	public function run_batch(): bool {
		$this->linker->link_all( $this->amount, BatchSize::of( $this->id(), 5 ) );

		return 0 === $this->remaining();
	}
}
