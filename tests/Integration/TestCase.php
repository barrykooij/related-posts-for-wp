<?php
/**
 * The integration test case class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration;

/**
 * Base class for integration tests. Runs inside WordPress with the plugin booted.
 */
abstract class TestCase extends \WP_UnitTestCase {

	/**
	 * Start every test class with an empty word cache. Rows written by committed fixtures would otherwise leak between
	 * classes, because the WordPress test suite only cleans up its own tables.
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

		$wpdb->query( 'TRUNCATE TABLE ' . \RP4WP_Related_Word_Manager::get_database_table() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- Test cleanup of our own table.
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
	 * Get the post IDs of all link posts that belong to a parent post, in menu order.
	 *
	 * Links created by the wizard or automatic linking all have menu_order 0, and 2.x orders by menu_order only, so the
	 * database decides the order of ties. In practice that is insertion order (most related first), so ties are broken
	 * by ID here to keep snapshots stable.
	 *
	 * @param int $parent_id The parent post ID.
	 *
	 * @return int[]
	 */
	protected function get_link_ids( int $parent_id ): array {
		return array_map(
			'intval',
			get_posts(
				[
					'post_type'      => 'rp4wp_link',
					'fields'         => 'ids',
					'posts_per_page' => -1,
					'orderby'        => [
						'menu_order' => 'ASC',
						'ID'         => 'ASC',
					],
					'meta_key'       => 'rp4wp_parent', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key -- Test helper.
					'meta_value'     => (string) $parent_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_value -- Test helper.
				]
			)
		);
	}
}
