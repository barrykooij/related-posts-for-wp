<?php
/**
 * The reindex words task class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Install\Tasks;

use LV2\WordPress\RelatedPostsForWP\Contracts\InstallTask;
use LV2\WordPress\RelatedPostsForWP\Links\PostState;
use LV2\WordPress\RelatedPostsForWP\PostTypes;
use LV2\WordPress\RelatedPostsForWP\Words\Cache;

/**
 * In the update of 3.0 (decision D55): cache the words of every published post of the supported post types again, with
 * the tokenizer of 3.0, newest first. A post whose words were cached since the update started is done, so the task goes
 * on where it stopped. Each post's old words are replaced in one transaction.
 */
class ReindexWordsTask implements InstallTask {

	/**
	 * When the update started, in milliseconds.
	 *
	 * @var int
	 */
	private int $generation;

	/**
	 * The word cache.
	 *
	 * @var Cache
	 */
	private Cache $cache;

	/**
	 * Set up the task.
	 *
	 * @param int        $generation When the update started, in milliseconds.
	 * @param Cache|null $cache      The word cache.
	 */
	public function __construct( int $generation, ?Cache $cache = null ) {
		$this->generation = $generation;
		$this->cache      = $cache ?? new Cache();
	}

	/**
	 * The task ID.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'reindex_words';
	}

	/**
	 * The task name.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Reading your posts again', 'related-posts-for-wp' );
	}

	/**
	 * The posts left.
	 *
	 * @return int
	 */
	public function remaining(): int {
		global $wpdb;

		return (int) $wpdb->get_var( $this->sql( 'COUNT(P.ID)' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- See sql().
	}

	/**
	 * Cache the words of the next posts.
	 *
	 * @return bool Whether every post is done.
	 */
	public function run_batch(): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- See sql(); an integer.
		foreach ( $wpdb->get_col( $this->sql( 'P.ID' ) . ' ORDER BY P.ID DESC LIMIT ' . BatchSize::of( $this->id(), 25 ) ) as $post_id ) {
			$this->cache->save_post( (int) $post_id );
		}

		return 0 === $this->remaining();
	}

	/**
	 * The query for the published posts of the supported post types whose words were not cached since the update
	 * started.
	 *
	 * @param string $select What to select.
	 *
	 * @return string
	 */
	private function sql( string $select ): string {
		global $wpdb;

		return "SELECT {$select} FROM {$wpdb->posts} P WHERE P.post_type IN ('" . implode( "','", array_map( 'esc_sql', PostTypes::supported() ) ) . "') AND P.post_status = 'publish' AND " . PostState::not_indexed_sql( 'P', $this->generation );
	}
}
