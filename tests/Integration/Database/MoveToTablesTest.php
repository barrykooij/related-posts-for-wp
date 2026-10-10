<?php
/**
 * The move to tables test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Database;

use LV2\WordPress\RelatedPostsForWP\Database\Migration;
use LV2\WordPress\RelatedPostsForWP\Database\Migrator;
use LV2\WordPress\RelatedPostsForWP\Database\Schema;
use LV2\WordPress\RelatedPostsForWP\Install\Table;
use LV2\WordPress\RelatedPostsForWP\Links\LinkPostType;
use LV2\WordPress\RelatedPostsForWP\Links\LinkRepository;
use LV2\WordPress\RelatedPostsForWP\Links\PostState;
use LV2\WordPress\RelatedPostsForWP\Tests\Integration\TestCase;

/**
 * The migrations that move the data of 2.x and the 3.0 release candidates into tables: the marks on posts into the post
 * state table, the link posts into the links table (with their IDs, and links without a mark sorted out by the rule of
 * decision D39), and then the link posts go. Their down() puts everything back.
 *
 * The data migrations only move rows, so the test undoes them inside its transaction, writes 2.x data, and runs them
 * again.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Database\Schema
 */
final class MoveToTablesTest extends TestCase {

	/**
	 * The data migrations, in order.
	 */
	private const MIGRATIONS = [
		'2026_10_10_000003_move_post_marks',
		'2026_10_10_000005_move_links',
		'2026_10_10_000006_remove_link_posts',
	];

	/**
	 * The posts of the scenario, by name.
	 *
	 * @var array<string, int>
	 */
	private array $posts = [];

	/**
	 * The link posts of the scenario, by name.
	 *
	 * @var array<string, int>
	 */
	private array $links = [];

	public function set_up(): void {
		parent::set_up();

		$this->down();
		$this->assertSame( 0, Schema::storage() );

		foreach ( [ 'wizard', 'premium', 'duplicate', 'broken', 'unlinked', 'ambiguous', 'empty', 'a', 'b', 'c', 'd', 'e' ] as $name ) {
			$this->posts[ $name ] = self::factory()->post->create(
				[
					'post_status' => 'draft',
					'post_title'  => $name,
				]
			);
		}

		$this->seed();
	}

	public function test_the_marks_move_to_the_post_state_table(): void {
		$this->up();

		$this->assertSame( 1, PostState::linked_at( $this->posts['wizard'] ) );
		$this->assertSame( 1700000001234, PostState::indexed_at( $this->posts['wizard'] ) );
		$this->assertSame( 1700000000000, PostState::linked_at( $this->posts['premium'] ) );
		$this->assertSame( 1, PostState::indexed_at( $this->posts['empty'] ) );
		$this->assertSame( 0, PostState::linked_at( $this->posts['empty'] ) );
		$this->assertSame( 1, PostState::indexed_at( $this->posts['a'] ) );
		$this->assertSame( 0, PostState::linked_at( $this->posts['unlinked'] ) );

		$this->assertSame( 0, $this->meta_count( [ 'rp4wp_auto_linked', 'rp4wp_relinked', 'rp4wp_words_cached', 'rp4wp_no_words', 'rp4wp_cached' ] ) );
	}

	public function test_the_links_move_to_the_links_table_with_their_ids(): void {
		$this->up();

		$this->assertSame( Schema::STORAGE_LINKS, Schema::storage() );
		$this->assertSame( 0, $this->link_post_count() );

		$repository = new LinkRepository();

		$this->assertSame(
			[
				$this->links['wizard-1'] => $this->posts['a'],
				$this->links['wizard-2'] => $this->posts['b'],
				$this->links['wizard-3'] => $this->posts['c'],
				$this->links['wizard-4'] => $this->posts['d'],
			],
			$repository->child_ids( $this->posts['wizard'] )
		);
		$this->assertSame( 5, $repository->find( $this->links['wizard-4'] )['position'] );
		$this->assertSame( [ $this->posts['b'] ], array_values( $repository->child_ids( $this->posts['duplicate'] ) ), 'Of two links between the same posts, the oldest stays.' );
		$this->assertSame( [], $repository->child_ids( $this->posts['broken'] ), 'A link without a related post is left out.' );

		$parents = array_map(
			static function ( $post ) {
				return $post->ID;
			},
			$repository->get_parents( $this->posts['a'] )
		);
		$this->assertSame( $this->posts['wizard'], $parents[ $this->links['wizard-1'] ] );
	}

	public function test_links_without_a_mark_are_sorted_out_by_the_way_they_were_written(): void {
		$this->up();

		$manual = $this->manual_by_link();

		// One insert of the wizard (same second) on an automatically linked post: automatic. The later one: by hand.
		$this->assertFalse( $manual[ $this->links['wizard-1'] ] );
		$this->assertFalse( $manual[ $this->links['wizard-2'] ] );
		$this->assertFalse( $manual[ $this->links['wizard-3'] ] );
		$this->assertTrue( $manual[ $this->links['wizard-4'] ] );

		// Premium marks links added by hand; a link it wrote without the mark is automatic.
		$this->assertFalse( $manual[ $this->links['premium-auto'] ] );
		$this->assertTrue( $manual[ $this->links['premium-manual'] ] );

		// A post that was not linked automatically, or with two groups: the links count as added by hand.
		$this->assertTrue( $manual[ $this->links['unlinked-1'] ] );
		$this->assertTrue( $manual[ $this->links['unlinked-2'] ] );
		foreach ( [ 'ambiguous-1', 'ambiguous-2', 'ambiguous-3', 'ambiguous-4' ] as $link ) {
			$this->assertTrue( $manual[ $this->links[ $link ] ], $link );
		}
	}

	public function test_a_filter_can_decide_about_links_without_a_mark(): void {
		add_filter( 'rp4wp_legacy_link_is_manual', '__return_false' );

		$this->up();

		$manual = $this->manual_by_link();
		$this->assertFalse( $manual[ $this->links['wizard-4'] ] );
		$this->assertFalse( $manual[ $this->links['unlinked-1'] ] );
		$this->assertTrue( $manual[ $this->links['premium-manual'] ], 'A link with the mark stays added by hand.' );
	}

	public function test_the_post_type_of_the_parent_comes_from_the_mark_or_the_post(): void {
		global $wpdb;

		$this->up();

		$table = Schema::table( Schema::LINKS );
		$types = $wpdb->get_results( "SELECT id, parent_type FROM `{$table}`", OBJECT_K ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Test assertion.

		$this->assertSame( 'post', $types[ $this->links['wizard-1'] ]->parent_type );
		$this->assertSame( 'book', $types[ $this->links['premium-auto'] ]->parent_type );
	}

	public function test_a_migration_that_runs_out_of_time_goes_on_where_it_stopped(): void {
		$links = $this->migration( '2026_10_10_000005_move_links' );

		$this->migration( '2026_10_10_000003_move_post_marks' )->up();

		$links->set_deadline( microtime( true ) - 1 );
		$this->assertFalse( $links->up() );
		$this->assertSame( Schema::STORAGE_POST_STATE, Schema::storage() );

		$links->set_deadline( 0.0 );
		$this->assertTrue( $links->up() );
		$this->assertSame( Schema::STORAGE_LINKS, Schema::storage() );
	}

	public function test_while_the_links_move_they_are_read_from_the_link_posts_and_not_written(): void {
		$this->migration( '2026_10_10_000003_move_post_marks' )->up();

		$repository = new LinkRepository();

		$this->assertFalse( $repository->can_write() );
		$this->assertSame( [ $this->posts['a'], $this->posts['b'], $this->posts['c'], $this->posts['d'] ], array_values( $repository->child_ids( $this->posts['wizard'] ) ) );
		$this->assertSame( 0, $repository->add( $this->posts['wizard'], $this->posts['e'] ) );
	}

	public function test_links_keep_their_order_when_the_positions_can_not_hold_it(): void {
		$p     = $this->posts;
		$first = $this->legacy_link( $p['e'], $p['a'], '2024-01-01 10:00:00' );
		$moved = $this->legacy_link( $p['e'], $p['b'], '2024-01-02 10:00:00', -1 );
		$last  = $this->legacy_link( $p['e'], $p['c'], '2024-01-03 10:00:00', 70000 );

		$this->up();

		$repository = new LinkRepository();
		$this->assertSame( [ $moved, $first, $last ], array_keys( $repository->child_ids( $p['e'] ) ), 'The order of 2.x: by menu_order, then by ID.' );
		$this->assertSame( [ 0, 1, 2 ], array_column( array_map( [ $repository, 'find' ], [ $moved, $first, $last ] ), 'position' ) );
		$this->assertSame( 5, $repository->find( $this->links['wizard-4'] )['position'], 'Positions that fit stay as they were.' );
	}

	public function test_down_puts_the_link_posts_and_the_marks_back(): void {
		$this->up();
		$before = $this->children_by_parent();

		$this->down();

		$this->assertSame( 0, Schema::storage() );
		$this->assertSame( 13, $this->link_post_count() );
		$this->assertSame( '1', get_post_meta( $this->posts['wizard'], 'rp4wp_auto_linked', true ) );
		$this->assertSame( '1700000001234', get_post_meta( $this->posts['wizard'], 'rp4wp_words_cached', true ) );
		$this->assertSame( '1700000000000', get_post_meta( $this->posts['premium'], 'rp4wp_relinked', true ) );
		$this->assertSame( '1', get_post_meta( $this->posts['empty'], 'rp4wp_no_words', true ) );

		// And up again gives the same links, added by hand or not.
		$this->up();
		$this->assertSame( $before, $this->children_by_parent() );
	}

	/**
	 * Write the data as 2.x and the 3.0 release candidates left it.
	 *
	 * @return void
	 */
	private function seed(): void {
		$p = $this->posts;

		// The wizard linked three posts in one insert; a fourth was linked by hand later and moved to place 5.
		$this->links['wizard-1'] = $this->legacy_link( $p['wizard'], $p['a'], '2024-01-01 10:00:00' );
		$this->links['wizard-2'] = $this->legacy_link( $p['wizard'], $p['b'], '2024-01-01 10:00:00' );
		$this->links['wizard-3'] = $this->legacy_link( $p['wizard'], $p['c'], '2024-01-01 10:00:01' );
		$this->links['wizard-4'] = $this->legacy_link( $p['wizard'], $p['d'], '2024-02-01 12:00:00', 5 );

		// Premium writes the post type of the parent first, and marks links added by hand.
		$this->links['premium-auto']   = $this->legacy_link( $p['premium'], $p['a'], '2024-01-01 10:00:00', 0, [ LinkPostType::META_PARENT_POST_TYPE => 'book' ], true );
		$this->links['premium-manual'] = $this->legacy_link( $p['premium'], $p['b'], '2024-01-02 10:00:00', 1, [ LinkPostType::META_MANUAL => '1' ] );

		$this->links['duplicate-1'] = $this->legacy_link( $p['duplicate'], $p['b'], '2024-01-01 10:00:00' );
		$this->links['duplicate-2'] = $this->legacy_link( $p['duplicate'], $p['b'], '2024-01-01 10:00:00' );
		$this->links['broken']      = $this->legacy_link( $p['broken'], null, '2024-01-01 10:00:00' );

		// Not linked automatically: links without a mark were added by hand.
		$this->links['unlinked-1'] = $this->legacy_link( $p['unlinked'], $p['a'], '2024-01-01 10:00:00' );
		$this->links['unlinked-2'] = $this->legacy_link( $p['unlinked'], $p['b'], '2024-01-01 10:00:00' );

		// Two groups that could each be the wizard's: not clear, so all count as added by hand.
		$this->links['ambiguous-1'] = $this->legacy_link( $p['ambiguous'], $p['a'], '2024-01-01 10:00:00' );
		$this->links['ambiguous-2'] = $this->legacy_link( $p['ambiguous'], $p['b'], '2024-01-01 10:00:00' );
		$this->links['ambiguous-3'] = $this->legacy_link( $p['ambiguous'], $p['c'], '2024-03-01 10:00:00' );
		$this->links['ambiguous-4'] = $this->legacy_link( $p['ambiguous'], $p['d'], '2024-03-01 10:00:00' );

		update_post_meta( $p['wizard'], 'rp4wp_auto_linked', '1' );
		update_post_meta( $p['wizard'], 'rp4wp_words_cached', '1700000001234' );
		update_post_meta( $p['premium'], 'rp4wp_auto_linked', '1' );
		update_post_meta( $p['premium'], 'rp4wp_relinked', '1700000000000' );
		update_post_meta( $p['ambiguous'], 'rp4wp_auto_linked', '1' );
		update_post_meta( $p['empty'], 'rp4wp_no_words', '1' );
		update_post_meta( $p['a'], 'rp4wp_cached', '1' );

		$this->cache_words( $p['a'] );
	}

	/**
	 * Write a link post the way 2.x did: one insert into the posts table, then the meta.
	 *
	 * @param int                   $parent     The post that shows the related post.
	 * @param int|null              $child      The related post; null for a broken link without one.
	 * @param string                $date       The date, in UTC.
	 * @param int                   $menu_order The place.
	 * @param array<string, string> $meta       More meta.
	 * @param bool                  $meta_first Whether the extra meta comes before the parent, as premium writes it.
	 *
	 * @return int The link post ID.
	 */
	private function legacy_link( int $parent, ?int $child, string $date, int $menu_order = 0, array $meta = [], bool $meta_first = false ): int {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- 2.x data, written the way 2.x wrote it.
		$wpdb->insert(
			$wpdb->posts,
			[
				'post_date'             => $date,
				'post_date_gmt'         => $date,
				'post_content'          => '',
				'post_title'            => LinkPostType::TITLE,
				'post_excerpt'          => '',
				'post_status'           => 'publish',
				'post_type'             => LinkPostType::POST_TYPE,
				'to_ping'               => '',
				'pinged'                => '',
				'post_content_filtered' => '',
				'menu_order'            => $menu_order,
			]
		);
		$link_id = (int) $wpdb->insert_id;

		$rows = [ LinkPostType::META_PARENT => (string) $parent ];
		if ( null !== $child ) {
			$rows[ LinkPostType::META_CHILD ] = (string) $child;
		}
		$rows = $meta_first ? $meta + $rows : $rows + $meta;

		foreach ( $rows as $key => $value ) {
			$wpdb->insert(
				$wpdb->postmeta,
				[
					'post_id'    => $link_id,
					'meta_key'   => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key -- Test data.
					'meta_value' => $value, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_value -- Test data.
				]
			);
		}
		// phpcs:enable

		return $link_id;
	}

	/**
	 * Store a word for a post, as the word cache does.
	 *
	 * @param int $post_id The post.
	 *
	 * @return void
	 */
	private function cache_words( int $post_id ): void {
		global $wpdb;

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test data.
			Table::name(),
			[
				'post_id'   => $post_id,
				'word'      => 'roses',
				'weight'    => 0.5,
				'post_type' => 'post',
			]
		);
	}

	/**
	 * Run the data migrations to the end.
	 *
	 * @return void
	 */
	private function up(): void {
		foreach ( self::MIGRATIONS as $name ) {
			$migration = $this->migration( $name );
			$migration->set_deadline( 0.0 );

			$this->assertTrue( $migration->up(), $name );
			$migration->clear_cursors();
		}

		wp_cache_flush();
	}

	/**
	 * Undo the data migrations, newest first.
	 *
	 * @return void
	 */
	private function down(): void {
		foreach ( array_reverse( self::MIGRATIONS ) as $name ) {
			$this->migration( $name )->down();
		}

		wp_cache_flush();
	}

	/**
	 * A migration of the free plugin.
	 *
	 * @param string $name The name.
	 *
	 * @return Migration
	 */
	private function migration( string $name ): Migration {
		return ( new Migrator( 'free', dirname( __DIR__, 3 ) . '/migrations' ) )->migration( $name );
	}

	/**
	 * Whether each link is added by hand, by link ID.
	 *
	 * @return array<int, bool>
	 */
	private function manual_by_link(): array {
		$repository = new LinkRepository();
		$manual     = [];

		foreach ( $this->posts as $post_id ) {
			foreach ( $repository->link_rows( $post_id ) as $link_id => $row ) {
				$manual[ $link_id ] = $row['manual'];
			}
		}

		return $manual;
	}

	/**
	 * The related posts of every post, with the mark, in order: the same before and after a round trip.
	 *
	 * @return array<int, array<int, array{int, bool}>>
	 */
	private function children_by_parent(): array {
		$repository = new LinkRepository();
		$children   = [];

		foreach ( $this->posts as $post_id ) {
			$rows = $repository->link_rows( $post_id );

			foreach ( $repository->child_ids( $post_id ) as $link_id => $child_id ) {
				$children[ $post_id ][] = [ $child_id, $rows[ $link_id ]['manual'] ];
			}
		}

		return $children;
	}

	/**
	 * How many link posts there are.
	 *
	 * @return int
	 */
	private function link_post_count(): int {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", LinkPostType::POST_TYPE ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test assertion.
	}

	/**
	 * How many rows of post meta have one of the keys.
	 *
	 * @param string[] $keys The meta keys.
	 *
	 * @return int
	 */
	private function meta_count( array $keys ): int {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key IN ('" . implode( "','", array_map( 'esc_sql', $keys ) ) . "')" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- Test assertion.
	}
}
