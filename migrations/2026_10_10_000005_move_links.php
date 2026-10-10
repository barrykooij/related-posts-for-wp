<?php
/**
 * Migration: the links move from link posts to the links table.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Database\Migration;
use LV2\WordPress\RelatedPostsForWP\Database\Schema;
use LV2\WordPress\RelatedPostsForWP\Links\LinkPostType;
use LV2\WordPress\RelatedPostsForWP\Links\PostState;

return new class() extends Migration {

	/**
	 * Link posts copied per query.
	 */
	private const BATCH = 2000;

	/**
	 * Posts whose links are sorted out per query.
	 */
	private const PARENTS = 200;

	/**
	 * A link copied without knowing yet whether it was added by hand.
	 */
	private const UNDECIDED = 2;

	/**
	 * Links whose dates are at most this many seconds apart can come from one insert.
	 */
	private const SAME_INSERT_SECONDS = 2;

	/**
	 * What the migration does.
	 *
	 * @return string
	 */
	public function description(): string {
		return 'Copy the link posts into the links table, with their IDs, and tell the links added by hand from the automatic ones. The link posts stay until the next migration.';
	}

	/**
	 * Copy the links in slices, sort out the links without a mark, then switch to the table.
	 *
	 * While this runs, the links are read from the link posts, and nothing writes links (see LinkRepository).
	 *
	 * @return bool
	 */
	public function up(): bool {
		if ( Schema::has_links() ) {
			return true;
		}

		while ( $this->time_left() ) {
			if ( 'copy' === $this->cursor( 'stage', 'copy' ) ) {
				if ( $this->copy() ) {
					$this->save_cursor( 'stage', 'sort' );
				}

				continue;
			}

			if ( $this->sort_out() ) {
				Schema::set_storage( Schema::STORAGE_LINKS );

				return true;
			}
		}

		return false;
	}

	/**
	 * Go back to the link posts; the next migration's down() has put them back.
	 *
	 * @return void
	 */
	public function down(): void {
		$table = $this->table( Schema::LINKS );

		if ( $this->has_table( $table ) ) {
			$this->query( "DELETE FROM `{$table}`" );
		}

		Schema::set_storage( Schema::STORAGE_POST_STATE );
	}

	/**
	 * Copy the next link posts. A link keeps the ID of its link post. Of two links between the same posts, the oldest
	 * stays. A link is added by hand when it has the mark; premium always marked those, so a link premium wrote
	 * without the mark is automatic (premium writes the post type of the parent before the parent); the rest is sorted
	 * out afterwards.
	 *
	 * @return bool Whether every link post is copied.
	 */
	private function copy(): bool {
		$last = (int) $this->cursor( 'last', 0 );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- A migration; the values are prepared.
		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT L.ID AS id, L.menu_order, L.post_date, L.post_date_gmt,
					MIN( CASE WHEN M.meta_key = %s THEN M.meta_value END ) AS parent,
					MIN( CASE WHEN M.meta_key = %s THEN M.meta_id END ) AS parent_meta,
					MIN( CASE WHEN M.meta_key = %s THEN M.meta_value END ) AS child,
					MIN( CASE WHEN M.meta_key = %s THEN M.meta_value END ) AS manual,
					MIN( CASE WHEN M.meta_key = %s THEN M.meta_value END ) AS parent_type,
					MIN( CASE WHEN M.meta_key = %s THEN M.meta_id END ) AS type_meta
				FROM {$this->db->posts} L
				LEFT JOIN {$this->db->postmeta} M ON M.post_id = L.ID AND M.meta_key IN ( %s, %s, %s, %s )
				WHERE L.post_type = %s AND L.post_status = 'publish' AND L.ID > %d
				GROUP BY L.ID, L.menu_order, L.post_date, L.post_date_gmt
				ORDER BY L.ID
				LIMIT %d",
				LinkPostType::META_PARENT,
				LinkPostType::META_PARENT,
				LinkPostType::META_CHILD,
				LinkPostType::META_MANUAL,
				LinkPostType::META_PARENT_POST_TYPE,
				LinkPostType::META_PARENT_POST_TYPE,
				LinkPostType::META_PARENT,
				LinkPostType::META_CHILD,
				LinkPostType::META_MANUAL,
				LinkPostType::META_PARENT_POST_TYPE,
				LinkPostType::POST_TYPE,
				$last,
				self::BATCH
			)
		);

		if ( ! is_array( $rows ) || count( $rows ) < 1 ) {
			return true;
		}

		$types  = $this->post_types( array_column( $rows, 'parent' ) );
		$values = [];

		foreach ( $rows as $row ) {
			$parent = (int) $row->parent;
			$child  = (int) $row->child;

			if ( $parent > 0 && $child > 0 ) {
				if ( '1' === (string) $row->manual ) {
					$manual = 1;
				} elseif ( null !== $row->type_meta && (int) $row->type_meta < (int) $row->parent_meta ) {
					$manual = 0;
				} else {
					$manual = self::UNDECIDED;
				}

				$values[] = $this->db->prepare(
					'(%d, %d, %d, %d, %d, %s, %s)',
					$row->id,
					$parent,
					$child,
					min( 65535, max( 0, (int) $row->menu_order ) ),
					$manual,
					substr( sanitize_key( null !== $row->parent_type ? (string) $row->parent_type : ( $types[ $parent ] ?? 'post' ) ), 0, 20 ),
					'0000-00-00 00:00:00' === (string) $row->post_date_gmt ? (string) $row->post_date : (string) $row->post_date_gmt
				);
			}
		}

		if ( count( $values ) > 0 ) {
			$this->query( 'INSERT IGNORE INTO `' . $this->table( Schema::LINKS ) . '` ( id, parent_id, child_id, position, is_manual, parent_type, created ) VALUES ' . implode( ',', $values ) );
		}

		$this->save_cursor( 'last', (int) end( $rows )->id );

		return count( $rows ) < self::BATCH;
	}

	/**
	 * Sort out the next posts with links without a mark, the way the first relink of premium 3.0 did (decision D39):
	 * the free plugin's links of an automatically linked post are automatic when exactly one group of two or more of
	 * them came from one insert, with dates at most two seconds apart; that group. Any other link without a mark counts
	 * as added by hand, so a relink keeps it. When it is not clear, a link counts as added by hand.
	 *
	 * @return bool Whether every link is sorted out.
	 */
	private function sort_out(): bool {
		$table = $this->table( Schema::LINKS );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- A migration; integers.
		$parents = $this->db->get_col( $this->db->prepare( "SELECT DISTINCT parent_id FROM `{$table}` WHERE is_manual = %d LIMIT %d", self::UNDECIDED, self::PARENTS ) );

		foreach ( $parents as $parent_id ) {
			$parent_id = (int) $parent_id;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- A migration.
			$rows = $this->db->get_results( $this->db->prepare( "SELECT id, is_manual, created FROM `{$table}` WHERE parent_id = %d ORDER BY id", $parent_id ) );

			$automatic = $this->automatic_group( $parent_id, (array) $rows );
			$set       = [
				0 => [],
				1 => [],
			];

			foreach ( (array) $rows as $row ) {
				if ( self::UNDECIDED !== (int) $row->is_manual ) {
					continue;
				}

				/**
				 * Filters whether a link without a mark, written before links added by hand were marked, counts as added
				 * by hand. A link added by hand stays when premium links the post again; an automatic link is replaced.
				 *
				 * @since 3.0.0
				 *
				 * @param bool $manual  Whether it counts as added by hand.
				 * @param int  $link_id The link.
				 * @param int  $post_id The post that shows the related post.
				 */
				$manual = (bool) apply_filters( 'rp4wp_legacy_link_is_manual', ! in_array( (int) $row->id, $automatic, true ), (int) $row->id, $parent_id );

				$set[ $manual ? 1 : 0 ][] = (int) $row->id;
			}

			foreach ( $set as $value => $ids ) {
				if ( count( $ids ) > 0 ) {
					$this->query( "UPDATE `{$table}` SET is_manual = {$value} WHERE id IN (" . implode( ',', $ids ) . ')' );
				}
			}
		}

		return count( $parents ) < self::PARENTS;
	}

	/**
	 * The links of a post that came from one automatic insert, if that is clear.
	 *
	 * @param int                $parent_id The post.
	 * @param array<int, object> $rows      Its links, oldest first: id, is_manual and created.
	 *
	 * @return int[] Link IDs.
	 */
	private function automatic_group( int $parent_id, array $rows ): array {
		if ( ! PostState::is_linked( $parent_id ) ) {
			return [];
		}

		$groups  = [];
		$current = [];
		$last    = null;

		foreach ( $rows as $row ) {
			$time = strtotime( $row->created . ' UTC' );

			// Only links without a mark can be in a group; any other link ends it.
			if ( self::UNDECIDED !== (int) $row->is_manual || false === $time ) {
				$groups[] = $current;
				$current  = [];
				$last     = null;
				continue;
			}

			if ( null !== $last && abs( $time - $last ) > self::SAME_INSERT_SECONDS ) {
				$groups[] = $current;
				$current  = [];
			}

			$current[] = (int) $row->id;
			$last      = $time;
		}

		$groups[] = $current;

		$groups = array_values(
			array_filter(
				$groups,
				static function ( array $group ): bool {
					return count( $group ) > 1;
				}
			)
		);

		return 1 === count( $groups ) ? $groups[0] : [];
	}

	/**
	 * The post types of posts.
	 *
	 * @param array<int, string|null> $post_ids The posts.
	 *
	 * @return array<int, string> Post ID => post type.
	 */
	private function post_types( array $post_ids ): array {
		$post_ids = array_filter( array_map( 'intval', $post_ids ) );
		if ( count( $post_ids ) < 1 ) {
			return [];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- A migration; integers.
		$rows  = $this->db->get_results( "SELECT ID, post_type FROM {$this->db->posts} WHERE ID IN (" . implode( ',', array_unique( $post_ids ) ) . ')' );
		$types = [];

		foreach ( (array) $rows as $row ) {
			$types[ (int) $row->ID ] = (string) $row->post_type;
		}

		return $types;
	}
};
