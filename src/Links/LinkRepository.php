<?php
/**
 * The link repository class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Links;

/**
 * Stores and reads links between posts.
 */
class LinkRepository {

	/**
	 * Link a related post to a post.
	 *
	 * @param int $parent_id The post that shows the related post.
	 * @param int $child_id  The related post.
	 *
	 * @return int The link ID.
	 */
	public function add( int $parent_id, int $child_id ): int {
		global $wpdb;

		$data = $this->insert_data( $parent_id, $child_id );

		// A direct insert, like 2.x: link posts never run the save_post hooks of other plugins.
		$wpdb->query( "INSERT INTO `{$wpdb->posts}` (`post_date`,`post_date_gmt`,`post_content`,`post_title`,`post_type`,`post_status`) VALUES {$data['post']}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Values are built from integers, constants and dates.

		$link_id = (int) $wpdb->insert_id;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- The values hold %d placeholders for the link ID.
		$wpdb->query( $wpdb->prepare( "INSERT INTO `{$wpdb->postmeta}` (`post_id`,`meta_key`,`meta_value`) VALUES {$data['meta'][0]}, {$data['meta'][1]}", $link_id, $link_id ) );

		/**
		 * Fires after a link is added.
		 *
		 * @since 1.0.0
		 *
		 * @param int $link_id The link ID.
		 */
		do_action( 'rp4wp_after_link_add', $link_id );

		return $link_id;
	}

	/**
	 * The SQL values to insert a link, for inserting many links at once.
	 *
	 * The meta values contain a %d placeholder for the link ID.
	 *
	 * @param int $parent_id The post that shows the related post.
	 * @param int $child_id  The related post.
	 *
	 * @return array{post: string, meta: string[]}
	 */
	public function insert_data( int $parent_id, int $child_id ): array {
		return [
			'post' => "('" . current_time( 'mysql', false ) . "', '" . current_time( 'mysql', true ) . "','','" . LinkPostType::TITLE . "','" . LinkPostType::POST_TYPE . "','publish')",
			'meta' => [
				"(%d, '" . LinkPostType::META_PARENT . "', '{$parent_id}')",
				"(%d, '" . LinkPostType::META_CHILD . "', '{$child_id}')",
			],
		];
	}

	/**
	 * Remove a link.
	 *
	 * @param int $link_id The link ID.
	 *
	 * @return void
	 */
	public function delete( int $link_id ): void {
		/**
		 * Fires before a link is removed.
		 *
		 * @since 1.0.0
		 *
		 * @param int $link_id The link ID.
		 */
		do_action( 'rp4wp_before_link_delete', $link_id );

		wp_delete_post( $link_id, true );

		/**
		 * Fires after a link is removed.
		 *
		 * @since 1.0.0
		 *
		 * @param int $link_id The link ID.
		 */
		do_action( 'rp4wp_after_link_delete', $link_id );
	}

	/**
	 * The related posts of a post, in their order.
	 *
	 * Without extra arguments the result is keyed by link ID. With extra arguments the children are queried again
	 * with them and keyed by post ID. posts_per_page, offset and order apply to the links; the rest to the children.
	 *
	 * @param int                  $parent_id  The post.
	 * @param array<string, mixed> $extra_args Extra WP_Query arguments.
	 *
	 * @return array<int, \WP_Post|null>
	 */
	public function get_children( int $parent_id, array $extra_args = [] ): array {
		$link_args = $this->link_args( LinkPostType::META_PARENT, $parent_id );

		// These arguments belong to the link query, not the child query.
		if ( isset( $extra_args['posts_per_page'] ) ) {
			$link_args['posts_per_page'] = $extra_args['posts_per_page'];
			unset( $extra_args['posts_per_page'] );
		}

		if ( isset( $extra_args['order'] ) && ! isset( $extra_args['orderby'] ) ) {
			$link_args['order'] = $extra_args['order'];
			unset( $extra_args['order'] );
		}

		if ( isset( $extra_args['offset'] ) ) {
			$link_args['offset'] = $extra_args['offset'];
			unset( $extra_args['offset'] );
		}

		/**
		 * Filters the query arguments for the links of a post.
		 *
		 * @since 1.0.0
		 *
		 * @param array $link_args The WP_Query arguments.
		 * @param int   $parent_id The post.
		 */
		$link_args = $this->with_tie_break( apply_filters( 'rp4wp_get_children_link_args', $link_args, $parent_id ) );

		$child_ids = [];
		foreach ( ( new \WP_Query() )->query( $link_args ) as $link ) {
			$child_ids[ $link->ID ] = get_post_meta( $link->ID, LinkPostType::META_CHILD, true );
		}

		// Without extra arguments, the children themselves in link order. 2.x returns null for links to deleted posts.
		if ( count( $extra_args ) < 1 ) {
			return array_map( 'get_post', $child_ids );
		}

		$children = [];
		if ( count( $child_ids ) < 1 ) {
			return $children;
		}

		$child_args = array_merge_recursive(
			[
				'post_type'           => 'post',
				'posts_per_page'      => -1,
				'ignore_sticky_posts' => 1,
				'post__in'            => $child_ids,
			],
			$extra_args
		);

		/**
		 * Filters the query arguments for the related posts of a post.
		 *
		 * @since 1.0.0
		 *
		 * @param array $child_args The WP_Query arguments.
		 * @param int   $parent_id  The post.
		 */
		$child_args = apply_filters( 'rp4wp_get_children_child_args', $child_args, $parent_id );

		foreach ( ( new \WP_Query() )->query( $child_args ) as $child ) {
			$children[ $child->ID ] = $child;
		}

		// Keep the link order, unless the caller asked for another order.
		if ( ! isset( $extra_args['orderby'] ) ) {
			$order = array_values( $child_ids );
			uasort(
				$children,
				static function ( $a, $b ) use ( $order ) {
					return array_search( $a->ID, $order ) - array_search( $b->ID, $order ); // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict -- Meta values are strings, post IDs are integers.
				}
			);
		}

		return $children;
	}

	/**
	 * The posts that show a post as related, keyed by link ID.
	 *
	 * @param int $child_id The related post.
	 *
	 * @return array<int, \WP_Post|null>
	 */
	public function get_parents( int $child_id ): array {
		$link_args           = $this->link_args( LinkPostType::META_CHILD, $child_id );
		$link_args['fields'] = 'ids';

		/**
		 * Filters the query arguments for the links that point to a post.
		 *
		 * @since 1.0.0
		 *
		 * @param array $link_args The WP_Query arguments.
		 * @param int   $child_id  The related post.
		 */
		$link_args = $this->with_tie_break( apply_filters( 'rp4wp_get_parents_link_args', $link_args, $child_id ) );

		$parents = [];
		foreach ( ( new \WP_Query() )->query( $link_args ) as $link_id ) {
			$parents[ $link_id ] = get_post( get_post_meta( $link_id, LinkPostType::META_PARENT, true ) );
		}

		return $parents;
	}

	/**
	 * Remove every link from and to a post.
	 *
	 * @param int $post_id The post.
	 *
	 * @return void
	 */
	public function delete_links_related_to( int $post_id ): void {
		$links = ( new \WP_Query() )->query(
			[
				'post_type'      => LinkPostType::POST_TYPE,
				'posts_per_page' => -1,
				'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_query -- Links are found by their meta.
					'relation' => 'OR',
					[
						'key'     => LinkPostType::META_PARENT,
						'value'   => $post_id,
						'compare' => '=',
					],
					[
						'key'     => LinkPostType::META_CHILD,
						'value'   => $post_id,
						'compare' => '=',
					],
				],
			]
		);

		foreach ( $links as $link ) {
			wp_delete_post( $link->ID, true );
		}
	}

	/**
	 * The query arguments for links with the given meta value.
	 *
	 * @param string $meta_key The meta key.
	 * @param int    $post_id  The post ID.
	 *
	 * @return array<string, mixed>
	 */
	private function link_args( string $meta_key, int $post_id ): array {
		return [
			'post_type'      => LinkPostType::POST_TYPE,
			'posts_per_page' => -1,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_query -- Links are found by their meta.
				[
					'key'     => $meta_key,
					'value'   => $post_id,
					'compare' => '=',
				],
			],
		];
	}

	/**
	 * Order links by menu_order, then by ID, in the requested direction.
	 *
	 * Links created together (by the wizard or automatic linking) all have menu_order 0; their IDs follow the order of
	 * relevance. 2.x ordered by menu_order only, which left ties to the database (known issue K1). The filters above
	 * still get the 2.x arguments, so this only applies while the order is still plain menu_order.
	 *
	 * @param mixed $args The (filtered) WP_Query arguments.
	 *
	 * @return array<string, mixed>
	 */
	private function with_tie_break( $args ): array {
		$args = (array) $args;

		if ( isset( $args['orderby'] ) && 'menu_order' === $args['orderby'] ) {
			$direction       = $args['order'] ?? 'ASC';
			$args['orderby'] = [
				'menu_order' => $direction,
				'ID'         => $direction,
			];
		}

		return $args;
	}
}
