<?php
/**
 * Migration: weigh the words again, now that their frequency saturates.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Database\Migration;
use LV2\WordPress\RelatedPostsForWP\Database\Schema;
use LV2\WordPress\RelatedPostsForWP\Install\Update;

return new class() extends Migration {

	/**
	 * What the migration does.
	 *
	 * @return string
	 */
	public function description(): string {
		return 'Ask for the update of 3.1 on a site with words: a background job weighs the words again, now that how often a word is in a post saturates (decision D66), and links the posts again, by difference. The way back does nothing: the weights of 3.0 come back with the next weighing of 3.0.';
	}

	/**
	 * Ask for the job when the site has words; a new site weighs them when it installs.
	 *
	 * @return bool
	 */
	public function up(): bool {
		$cache = $this->table( Schema::CACHE );
		if ( ! $this->has_table( $cache ) ) {
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The plugin's own table.
		if ( (bool) $this->db->get_var( $this->db->prepare( 'SELECT EXISTS ( SELECT 1 FROM %i )', $cache ) ) ) {
			Update::request( 'weigh', '3.1.0' );
		}

		return true;
	}

	/**
	 * Nothing to undo here.
	 *
	 * @return void
	 */
	public function down(): void {
	}
};
