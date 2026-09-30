<?php
/**
 * The reset task class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Install\Tasks;

use LV2\WordPress\RelatedPostsForWP\Contracts\InstallTask;
use LV2\WordPress\RelatedPostsForWP\Install\Table;
use LV2\WordPress\RelatedPostsForWP\Links\LinkPostType;
use LV2\WordPress\RelatedPostsForWP\Words\Cache;

/**
 * Remove every link, the "linked automatically" flags and the word cache, so an installation starts over.
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

		$link_ids = get_posts(
			[
				'post_type'      => LinkPostType::POST_TYPE,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => -1,
			]
		);

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Bulk removal, like 2.x; the placeholders are built from the ID count.
		if ( count( $link_ids ) > 0 ) {
			$placeholders = implode( ',', array_fill( 0, count( $link_ids ), '%d' ) );

			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->posts} WHERE `ID` IN ({$placeholders})", $link_ids ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE `post_id` IN ({$placeholders})", $link_ids ) );
		}

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE `meta_key` IN ( %s, 'rp4wp_cached', %s )", LinkPostType::META_AUTO_LINKED, Cache::META_NO_WORDS ) );
		$wpdb->query( 'DELETE FROM ' . Table::name() . ' WHERE 1=1' );
		// phpcs:enable

		return true;
	}
}
