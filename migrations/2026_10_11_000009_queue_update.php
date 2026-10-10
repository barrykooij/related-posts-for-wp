<?php
/**
 * Migration: ask for the update of every post.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Database\Migration;
use LV2\WordPress\RelatedPostsForWP\Database\Schema;

return new class() extends Migration {

	/**
	 * What the migration does.
	 *
	 * @return string
	 */
	public function description(): string {
		return 'Ask for the update of 3.0 on a site with words or links: a background job reads every post again, weighs the words and links the posts again.';
	}

	/**
	 * Ask for the job when the site has data; a new site installs with the wizard instead.
	 *
	 * @return bool
	 */
	public function up(): bool {
		$cache = $this->table( Schema::CACHE );
		$links = $this->table( Schema::LINKS );
		$state = $this->table( Schema::POST_STATE );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The plugin's own tables.
		$has_data = (bool) $this->db->get_var( "SELECT EXISTS ( SELECT 1 FROM `{$cache}` ) OR EXISTS ( SELECT 1 FROM `{$links}` ) OR EXISTS ( SELECT 1 FROM `{$state}` WHERE linked_at > 0 )" );

		// The update module starts the job on the next request (see Install\Update).
		update_option(
			'rp4wp_update',
			[
				'pending' => $has_data,
				'notice'  => '',
			],
			true
		);

		return true;
	}

	/**
	 * Forget the update.
	 *
	 * @return void
	 */
	public function down(): void {
		delete_option( 'rp4wp_update' );
	}
};
