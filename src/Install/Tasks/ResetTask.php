<?php
/**
 * The reset task class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Install\Tasks;

use LV2\WordPress\RelatedPostsForWP\Contracts\InstallTask;
use LV2\WordPress\RelatedPostsForWP\Install\Table;
use LV2\WordPress\RelatedPostsForWP\Links\LinkRepository;
use LV2\WordPress\RelatedPostsForWP\Links\PostState;
use LV2\WordPress\RelatedPostsForWP\Words\Statistics;

/**
 * Remove the automatic links, the marks of linked and cached posts, the word cache and its statistics, so an
 * installation starts over. Links added by hand stay (2.x removed them too).
 */
class ResetTask implements InstallTask {

	/**
	 * The task ID.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'reset';
	}

	/**
	 * The task name.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Removing the old links and words', 'related-posts-for-wp' );
	}

	/**
	 * Runs in one batch, so there is nothing to count.
	 *
	 * @return int
	 */
	public function remaining(): int {
		return 0;
	}

	/**
	 * Remove it all at once, like 2.x did.
	 *
	 * @return bool Always true.
	 */
	public function run_batch(): bool {
		global $wpdb;

		( new LinkRepository() )->delete_automatic();
		PostState::reset();

		$wpdb->query( 'DELETE FROM ' . Table::name() . ' WHERE 1=1' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- Our own table.
		$wpdb->query( 'DELETE FROM ' . Statistics::words_table() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- Our own table.
		update_option( Statistics::OPTION_POSTS, 0, true );

		return true;
	}
}
