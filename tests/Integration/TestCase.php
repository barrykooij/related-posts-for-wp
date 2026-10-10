<?php
/**
 * The integration test case class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration;

use LV2\WordPress\RelatedPostsForWP\Database\Schema;
use LV2\WordPress\RelatedPostsForWP\Install\Table;
use LV2\WordPress\RelatedPostsForWP\Links\LinkRepository;

/**
 * Base class for integration tests. Runs inside WordPress with the plugin booted.
 */
abstract class TestCase extends \WP_UnitTestCase {

	/**
	 * Start every test class with an empty word cache, links table and post state table. Rows written by committed
	 * fixtures would otherwise leak between classes, because the WordPress test suite only cleans up its own tables.
	 *
	 * @return void
	 */
	public static function set_up_before_class() {
		self::truncate_cache();
		parent::set_up_before_class();
	}

	/**
	 * Clean up the word cache after the class, see set_up_before_class().
	 *
	 * @return void
	 */
	public static function tear_down_after_class() {
		parent::tear_down_after_class();
		self::truncate_cache();
	}

	/**
	 * Empty the plugin's word cache table.
	 *
	 * @return void
	 */
	protected static function truncate_cache(): void {
		global $wpdb;

		foreach ( [ Table::name(), Schema::table( Schema::LINKS ), Schema::table( Schema::POST_STATE ) ] as $table ) {
			$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Test cleanup of our own tables.
		}
	}

	/**
	 * Create a user with the given role and make them the current user.
	 *
	 * @param string $role The role.
	 *
	 * @return int The user ID.
	 */
	protected function act_as( string $role ): int {
		$user_id = self::factory()->user->create( [ 'role' => $role ] );
		wp_set_current_user( $user_id );

		return $user_id;
	}

	/**
	 * The link IDs of a parent post, in their order: by position, then by ID.
	 *
	 * @param int $parent_id The parent post ID.
	 *
	 * @return int[]
	 */
	protected function get_link_ids( int $parent_id ): array {
		return array_keys( ( new LinkRepository() )->child_ids( $parent_id ) );
	}
}
