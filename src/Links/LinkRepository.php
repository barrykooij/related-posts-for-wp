<?php
/**
 * The link repository class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Links;

use LV2\WordPress\RelatedPostsForWP\Database\Schema;
use LV2\WordPress\RelatedPostsForWP\Database\Transaction;

/**
 * Stores and reads links between posts: "post A shows post B as related", at a position, added by hand or not.
 *
 * Since 3.0 the links are rows of the links table; a link ID is the ID of its row. The migration that moves the link
 * posts of 2.x into the table keeps their IDs. Until it is done, the links are read from the link posts, and they
 * can't be written: can_write() says so, writers wait (see Deferred), and the admin says to try again in a moment.
 *
 * Links are ordered by position, then by ID: links created together have consecutive positions, and links of 2.x
 * that share position 0 keep the order they were created in (known issue K1).
 */
class LinkRepository {

	/**
	 * Whether links can be written now: they are in the links table.
	 *
	 * @return bool
	 */
	public function can_write(): bool {
		return Schema::has_links();
	}

	/**
	 * Link a related post to a post, by hand, at the end of its related posts. A link that exists already stays as it
	 * is.
	 *
	 * @param int $parent_id The post that shows the related post.
	 * @param int $child_id  The related post.
	 *
	 * @return int The link ID; 0 when links can't be written now.
	 */
	public function add( int $parent_id, int $child_id ): int {
		return $this->link( $parent_id, $child_id, true );
	}

	/**
	 * Link a related post to a post, at the end of its related posts. A link that exists already stays as it is.
	 *
	 * @param int         $parent_id   The post that shows the related post.
	 * @param int         $child_id    The related post.
	 * @param bool        $manual      Whether the link is added by hand.
	 * @param string|null $parent_type The post type of the parent; read from the post by default.
	 *
	 * @return int The link ID; 0 when links can't be written now.
	 */
	public function link( int $parent_id, int $child_id, bool $manual, ?string $parent_type = null ): int {
		if ( ! $this->can_write() ) {
			return 0;
		}

		$existing = array_search( $child_id, $this->child_ids( $parent_id ), true );
		if ( false !== $existing ) {
			return (int) $existing;
		}

		$positions = array_column( $this->link_rows( $parent_id ), 'position' );
		$position  = count( $positions ) > 0 ? max( $positions ) + 1 : 0;

		$added = $this->insert( $parent_id, [ $child_id => $position ], $manual, $parent_type );

		return $added[ $child_id ] ?? 0;
	}

	/**
	 * Add links from a post to related posts, at the given positions. Related posts it links to already are skipped.
	 * Fires `rp4wp_after_link_add` for each new link.
	 *
	 * @param int             $parent_id   The post that shows the related posts.
	 * @param array<int, int> $children    Related post => position.
	 * @param bool            $manual      Whether the links are added by hand.
	 * @param string|null     $parent_type The post type of the parent; read from the post by default.
	 *
	 * @return array<int, int> Related post => link ID, for the new links.
	 */
	public function insert( int $parent_id, array $children, bool $manual = false, ?string $parent_type = null ): array {
		global $wpdb;

		if ( ! $this->can_write() || count( $children ) < 1 ) {
			return [];
		}

		$children = array_diff_key( $children, array_flip( $this->child_ids( $parent_id ) ) );
		if ( count( $children ) < 1 ) {
			return [];
		}

		$parent_type = sanitize_key( $parent_type ?? (string) get_post_type( $parent_id ) );
		$created     = current_time( 'mysql', true );
		$values      = [];

		foreach ( $children as $child_id => $position ) {
			$values[] = $wpdb->prepare( '(%d, %d, %d, %d, %s, %s)', $parent_id, $child_id, max( 0, (int) $position ), $manual ? 1 : 0, $parent_type, $created );
		}

		$this->write( 'INSERT IGNORE INTO ' . $this->table() . ' (parent_id, child_id, position, is_manual, parent_type, created) VALUES ' . implode( ',', $values ) );

		// The IDs by related post: auto-increment steps can be larger than 1 (known issue P23).
		$ids   = implode( ',', array_map( 'intval', array_keys( $children ) ) );
		$added = [];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- The plugin's own table; integers.
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT id, child_id FROM ' . $this->table() . " WHERE parent_id = %d AND child_id IN ({$ids})", $parent_id ) ) as $row ) {
			$added[ (int) $row->child_id ] = (int) $row->id;
		}

		foreach ( $added as $link_id ) {
			/**
			 * Fires after a link is added.
			 *
			 * @since 1.0.0
			 *
			 * @param int $link_id The link ID.
			 */
			do_action( 'rp4wp_after_link_add', $link_id );
		}

		return $added;
	}

	/**
	 * Remove a link.
	 *
	 * @param int $link_id The link ID.
	 *
	 * @return void
	 */
	public function delete( int $link_id ): void {
		$this->delete_many( [ $link_id ] );
	}

	/**
	 * Remove links, firing `rp4wp_before_link_delete` and `rp4wp_after_link_delete` for each.
	 *
	 * @param int[] $link_ids The link IDs.
	 *
	 * @return void
	 */
	public function delete_many( array $link_ids ): void {
		$link_ids = array_values( array_unique( array_filter( array_map( 'intval', $link_ids ) ) ) );
		if ( ! $this->can_write() || count( $link_ids ) < 1 ) {
			return;
		}

		foreach ( $link_ids as $link_id ) {
			/**
			 * Fires before a link is removed.
			 *
			 * @since 1.0.0
			 *
			 * @param int $link_id The link ID.
			 */
			do_action( 'rp4wp_before_link_delete', $link_id );
		}

		foreach ( array_chunk( $link_ids, 1000 ) as $chunk ) {
			$this->write( 'DELETE FROM ' . $this->table() . ' WHERE id IN (' . implode( ',', $chunk ) . ')' );
		}

		foreach ( $link_ids as $link_id ) {
			/**
			 * Fires after a link is removed.
			 *
			 * @since 1.0.0
			 *
			 * @param int $link_id The link ID.
			 */
			do_action( 'rp4wp_after_link_delete', $link_id );
		}
	}

	/**
	 * Remove every link from and to a post.
	 *
	 * @param int $post_id The post.
	 *
	 * @return void
	 */
	public function delete_links_related_to( int $post_id ): void {
		global $wpdb;

		if ( ! $this->can_write() ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- The plugin's own table.
		$this->delete_many( $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . $this->table() . ' WHERE parent_id = %d OR child_id = %d', $post_id, $post_id ) ) );
	}

	/**
	 * Remove the links that were not added by hand: all of them, or those of posts of some post types.
	 *
	 * @param string[]|null $parent_types The post types of the posts whose links go; null for all.
	 *
	 * @return void
	 */
	public function delete_automatic( ?array $parent_types = null ): void {
		global $wpdb;

		if ( ! $this->can_write() ) {
			return;
		}

		$where = null === $parent_types ? '' : " AND parent_type IN ('" . implode( "','", array_map( 'esc_sql', $parent_types ) ) . "')";

		$this->delete_many( $wpdb->get_col( 'SELECT id FROM ' . $this->table() . " WHERE is_manual = 0{$where}" ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- The plugin's own table; escaped post types.
	}

	/**
	 * Move links to other positions.
	 *
	 * @param array<int, int> $positions Link ID => position.
	 *
	 * @return void
	 */
	public function set_positions( array $positions ): void {
		if ( ! $this->can_write() || count( $positions ) < 1 ) {
			return;
		}

		$cases = '';
		foreach ( $positions as $link_id => $position ) {
			$cases .= sprintf( ' WHEN %d THEN %d', $link_id, max( 0, (int) $position ) );
		}

		$this->write( 'UPDATE ' . $this->table() . " SET position = CASE id{$cases} END WHERE id IN (" . implode( ',', array_map( 'intval', array_keys( $positions ) ) ) . ')' );
	}

	/**
	 * Mark links as added by hand.
	 *
	 * @param int[] $link_ids The link IDs.
	 *
	 * @return void
	 */
	public function mark_manual( array $link_ids ): void {
		$link_ids = array_filter( array_map( 'intval', $link_ids ) );
		if ( ! $this->can_write() || count( $link_ids ) < 1 ) {
			return;
		}

		$this->write( 'UPDATE ' . $this->table() . ' SET is_manual = 1 WHERE id IN (' . implode( ',', $link_ids ) . ')' );
	}

	/**
	 * A link, or null when there is none.
	 *
	 * @param int $link_id The link ID.
	 *
	 * @return array{parent: int, child: int, position: int, manual: bool}|null
	 */
	public function find( int $link_id ): ?array {
		global $wpdb;

		if ( ! $this->can_write() ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- The plugin's own table.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT parent_id, child_id, position, is_manual FROM ' . $this->table() . ' WHERE id = %d', $link_id ) );

		return null === $row ? null : [
			'parent'   => (int) $row->parent_id,
			'child'    => (int) $row->child_id,
			'position' => (int) $row->position,
			'manual'   => 1 === (int) $row->is_manual,
		];
	}

	/**
	 * The related posts of a post, by link ID, in their order.
	 *
	 * @param int                  $parent_id  The post.
	 * @param array<string, mixed> $extra_args `posts_per_page` (-1 for all), `offset` and `order` (ASC or DESC, without
	 *                                         `orderby`) apply to the links; other arguments are ignored here.
	 *
	 * @return array<int, int> Link ID => related post ID.
	 */
	public function child_ids( int $parent_id, array $extra_args = [] ): array {
		return $this->query( 'children', $parent_id, $extra_args );
	}

	/**
	 * The related posts of a post, in their order.
	 *
	 * Without extra arguments the result is keyed by link ID, with null for a link to a post that is gone (known issue
	 * K2). With extra arguments, `posts_per_page`, `offset` and `order` apply to the links, and the related posts are
	 * queried again with the rest and keyed by post ID; they keep the link order unless an `orderby` is given.
	 *
	 * @param int                  $parent_id  The post.
	 * @param array<string, mixed> $extra_args Extra WP_Query arguments.
	 *
	 * @return array<int, \WP_Post|null>
	 */
	public function get_children( int $parent_id, array $extra_args = [] ): array {
		$child_ids = $this->child_ids( $parent_id, $extra_args );

		// Without extra arguments, the related posts themselves in link order. 2.x returns null for links to deleted posts.
		if ( count( $extra_args ) < 1 ) {
			return array_map( 'get_post', $child_ids );
		}

		$children = [];
		if ( count( $child_ids ) < 1 ) {
			return $children;
		}

		/**
		 * Filters the query arguments for the related posts of a post.
		 *
		 * @since 1.0.0
		 *
		 * @param array $child_args The WP_Query arguments.
		 * @param int   $parent_id  The post.
		 */
		$child_args = apply_filters( 'rp4wp_get_children_child_args', $this->child_args( array_values( $child_ids ), $extra_args ), $parent_id );

		foreach ( ( new \WP_Query() )->query( $child_args ) as $child ) {
			$children[ $child->ID ] = $child;
		}

		// Keep the link order, unless the caller asked for another order.
		if ( ! isset( $extra_args['orderby'] ) ) {
			$order = array_flip( array_values( $child_ids ) );
			uasort(
				$children,
				static function ( $a, $b ) use ( $order ) {
					return ( $order[ $a->ID ] ?? PHP_INT_MAX ) <=> ( $order[ $b->ID ] ?? PHP_INT_MAX );
				}
			);
		}

		return $children;
	}

	/**
	 * The posts that show a post as related, by link ID.
	 *
	 * @param int $child_id The related post.
	 *
	 * @return array<int, \WP_Post|null>
	 */
	public function get_parents( int $child_id ): array {
		return array_map( 'get_post', $this->query( 'parents', $child_id, [] ) );
	}

	/**
	 * How many related posts a post has.
	 *
	 * @param int $parent_id The post.
	 *
	 * @return int
	 */
	public function children_count( int $parent_id ): int {
		return count( $this->child_ids( $parent_id ) );
	}

	/**
	 * The links of a post as they are stored, for a relink: by link ID, oldest first.
	 *
	 * @param int $parent_id The post.
	 *
	 * @return array<int, array{child: int, position: int, created: string, manual: bool}>
	 */
	public function link_rows( int $parent_id ): array {
		global $wpdb;

		if ( ! $this->can_write() ) {
			return [];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- The plugin's own table.
		$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT id, child_id, position, is_manual, created FROM ' . $this->table() . ' WHERE parent_id = %d ORDER BY id', $parent_id ) );
		$links = [];

		foreach ( (array) $rows as $row ) {
			$links[ (int) $row->id ] = [
				'child'    => (int) $row->child_id,
				'position' => (int) $row->position,
				'created'  => (string) $row->created,
				'manual'   => 1 === (int) $row->is_manual,
			];
		}

		return $links;
	}

	/**
	 * The query arguments for the related posts, when get_children() is called with extra arguments.
	 *
	 * @param int[]                $child_ids  The related posts, in link order.
	 * @param array<string, mixed> $extra_args The extra arguments.
	 *
	 * @return array<string, mixed>
	 */
	protected function child_args( array $child_ids, array $extra_args ): array {
		// These arguments belong to the links, not the related posts.
		unset( $extra_args['posts_per_page'], $extra_args['offset'] );
		if ( ! isset( $extra_args['orderby'] ) ) {
			unset( $extra_args['order'] );
		}

		return array_merge_recursive(
			[
				'post_type'           => 'post',
				'posts_per_page'      => -1,
				'ignore_sticky_posts' => 1,
				'post__in'            => $child_ids,
			],
			$extra_args
		);
	}

	/**
	 * The links of a post in one direction, ordered by position and ID.
	 *
	 * @param string               $direction  `children`: the related posts of the post; `parents`: the posts that show it.
	 * @param int                  $post_id    The post.
	 * @param array<string, mixed> $extra_args `posts_per_page`, `offset` and `order` (without `orderby`).
	 *
	 * @return array<int, int> Link ID => the post at the other end.
	 */
	private function query( string $direction, int $post_id, array $extra_args ): array {
		global $wpdb;

		$query = [
			'limit'  => isset( $extra_args['posts_per_page'] ) ? (int) $extra_args['posts_per_page'] : -1,
			'offset' => isset( $extra_args['offset'] ) ? max( 0, (int) $extra_args['offset'] ) : 0,
			'order'  => isset( $extra_args['order'] ) && ! isset( $extra_args['orderby'] ) && 'DESC' === strtoupper( (string) $extra_args['order'] ) ? 'DESC' : 'ASC',
		];

		/**
		 * Filters which links of a post are read, and in which order.
		 *
		 * Replaces `rp4wp_get_children_link_args` and `rp4wp_get_parents_link_args`, which got WP_Query arguments for
		 * the link posts of 2.x.
		 *
		 * @since 3.0.0
		 *
		 * @param array  $query     `limit` (-1 for all), `offset` and `order` (ASC or DESC, by position then ID).
		 * @param int    $post_id   The post.
		 * @param string $direction `children`: the related posts of the post; `parents`: the posts that show it.
		 */
		$query = (array) apply_filters( 'rp4wp_links_query', $query, $post_id, $direction );

		$order  = 'DESC' === strtoupper( (string) ( $query['order'] ?? 'ASC' ) ) ? 'DESC' : 'ASC';
		$limit  = (int) ( $query['limit'] ?? -1 );
		$offset = max( 0, (int) ( $query['offset'] ?? 0 ) );
		// Like the WP_Query of 2.x, an offset only counts with a limit.
		$page = $limit > 0 ? $wpdb->prepare( ' LIMIT %d, %d', $offset, $limit ) : '';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- The plugin's tables; the values are prepared.
		if ( Schema::has_links() ) {
			[ $from, $to ] = 'children' === $direction ? [ 'parent_id', 'child_id' ] : [ 'child_id', 'parent_id' ];

			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, {$to} AS other FROM " . $this->table() . " WHERE {$from} = %d ORDER BY position {$order}, id {$order}", $post_id ) . $page );
		} else {
			// The link posts of 2.x, while the migration moves them into the links table.
			[ $from, $to ] = 'children' === $direction ? [ LinkPostType::META_PARENT, LinkPostType::META_CHILD ] : [ LinkPostType::META_CHILD, LinkPostType::META_PARENT ];

			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT L.ID AS id, MIN( O.meta_value ) AS other FROM {$wpdb->posts} L
					INNER JOIN {$wpdb->postmeta} F ON F.post_id = L.ID AND F.meta_key = %s AND F.meta_value = %s
					LEFT JOIN {$wpdb->postmeta} O ON O.post_id = L.ID AND O.meta_key = %s
					WHERE L.post_type = %s AND L.post_status = 'publish'
					GROUP BY L.ID, L.menu_order ORDER BY L.menu_order {$order}, L.ID {$order}",
					$from,
					(string) $post_id,
					$to,
					LinkPostType::POST_TYPE
				) . $page
			);
		}
		// phpcs:enable

		$links = [];
		foreach ( (array) $rows as $row ) {
			$links[ (int) $row->id ] = (int) $row->other;
		}

		return $links;
	}

	/**
	 * Run a write to the links table. Inside Transaction::run() a write that fails throws, so the writes after it do
	 * not happen and the transaction rolls back; otherwise it fails quietly, like the writes of WordPress.
	 *
	 * @param string $sql The query, prepared.
	 *
	 * @return bool Whether it worked.
	 *
	 * @throws \RuntimeException When it fails inside Transaction::run().
	 */
	protected function write( string $sql ): bool {
		global $wpdb;

		if ( Transaction::running() ) {
			Transaction::query( $sql );

			return true;
		}

		return false !== $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- The plugin's own table; the callers prepare it.
	}

	/**
	 * The links table.
	 *
	 * @return string
	 */
	protected function table(): string {
		return Schema::table( Schema::LINKS );
	}
}
