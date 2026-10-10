<?php
/**
 * The weigh words task class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Install\Tasks;

use LV2\WordPress\RelatedPostsForWP\Contracts\InstallTask;
use LV2\WordPress\RelatedPostsForWP\Database\Schema;
use LV2\WordPress\RelatedPostsForWP\Words\Statistics;
use LV2\WordPress\RelatedPostsForWP\Words\Tokenizer;

/**
 * Pass two over every post (decision D54): count the document frequencies again from the word cache, then weigh the
 * words of every post with them, in batches. It runs after the words of every post were cached and before posts are
 * linked, in installations of both plugins and in premium's refresh.
 *
 * The job's generation keys the progress, which an option keeps between batches, so a new job starts over.
 */
class WeighWordsTask implements InstallTask {

	/**
	 * The option with the progress: the generation of the job, and the last post weighed.
	 */
	public const OPTION = 'rp4wp_weigh_cursor';

	/**
	 * The generation of the job, in milliseconds.
	 *
	 * @var int
	 */
	private int $generation;

	/**
	 * The word statistics.
	 *
	 * @var Statistics
	 */
	private Statistics $statistics;

	/**
	 * Set up the task.
	 *
	 * @param int             $generation The generation of the job (see Queue::start()).
	 * @param Statistics|null $statistics The word statistics.
	 */
	public function __construct( int $generation, ?Statistics $statistics = null ) {
		$this->generation = $generation;
		$this->statistics = $statistics ?? new Statistics();
	}

	/**
	 * The task ID.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'weigh_words';
	}

	/**
	 * The task name.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Weighing the words', 'related-posts-for-wp' );
	}

	/**
	 * The posts left to weigh, and the count of the words when it did not happen yet.
	 *
	 * @return int
	 */
	public function remaining(): int {
		$cursor = $this->cursor();

		return null === $cursor ? 1 + $this->posts_after( 0 ) : $this->posts_after( $cursor );
	}

	/**
	 * Count the words again on the first batch; weigh the next posts on the others.
	 *
	 * @return bool Whether every post is weighed.
	 */
	public function run_batch(): bool {
		// Without a generation there is no progress to keep: all of it at once.
		if ( $this->generation < 1 ) {
			$this->statistics->recount();

			$post_id = $this->weigh_after( 0 );
			while ( null !== $post_id ) {
				$post_id = $this->weigh_after( $post_id );
			}

			return true;
		}

		$cursor = $this->cursor();
		if ( null === $cursor ) {
			$this->statistics->recount();
			$this->save_cursor( 0 );

			return 0 === $this->posts_after( 0 );
		}

		$last = $this->weigh_after( $cursor );
		if ( null === $last ) {
			return true;
		}

		$this->save_cursor( $last );

		return 0 === $this->posts_after( $last );
	}

	/**
	 * Weigh the next batch of posts.
	 *
	 * @param int $post_id The last post weighed.
	 *
	 * @return int|null The last post of the batch, or null when no post was left.
	 */
	private function weigh_after( int $post_id ): ?int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- The plugin's own table.
		$post_ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT post_id FROM ' . $this->table() . ' WHERE version = %d AND indexed_at > 0 AND post_id > %d ORDER BY post_id LIMIT %d', Tokenizer::VERSION, $post_id, BatchSize::of( $this->id(), 200 ) ) ) );
		if ( count( $post_ids ) < 1 ) {
			return null;
		}

		$this->statistics->weigh( $post_ids );

		return (int) max( $post_ids );
	}

	/**
	 * The posts with words after a post, by ID.
	 *
	 * @param int $post_id The post.
	 *
	 * @return int
	 */
	private function posts_after( int $post_id ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- The plugin's own table.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $this->table() . ' WHERE version = %d AND indexed_at > 0 AND post_id > %d', Tokenizer::VERSION, $post_id ) );
	}

	/**
	 * The last post weighed in this job's generation, or null when the words were not counted in it yet.
	 *
	 * @return int|null
	 */
	private function cursor(): ?int {
		$cursor = get_option( self::OPTION, [] );

		return is_array( $cursor ) && isset( $cursor['generation'], $cursor['post'] ) && (int) $cursor['generation'] === $this->generation ? (int) $cursor['post'] : null;
	}

	/**
	 * Keep the progress.
	 *
	 * @param int $post_id The last post weighed.
	 *
	 * @return void
	 */
	private function save_cursor( int $post_id ): void {
		update_option(
			self::OPTION,
			[
				'generation' => $this->generation,
				'post'       => $post_id,
			],
			false
		);
	}

	/**
	 * The post state table, which lists the posts whose words were cached.
	 *
	 * @return string
	 */
	private function table(): string {
		return Schema::table( Schema::POST_STATE );
	}
}
