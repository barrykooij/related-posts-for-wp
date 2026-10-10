<?php
/**
 * Migration: the link posts go, now that the links are in the links table.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Database\Migration;
use LV2\WordPress\RelatedPostsForWP\Database\Schema;
use LV2\WordPress\RelatedPostsForWP\Links\LinkPostType;

return new class() extends Migration {

	/**
	 * Link posts removed per query.
	 */
	private const BATCH = 2000;

	/**
	 * What the migration does.
	 *
	 * @return string
	 */
	public function description(): string {
		return 'Remove the link posts and their meta, which the links table replaced. Undoing it writes link posts back from the table, with new IDs.';
	}

	/**
	 * Remove the link posts in slices.
	 *
	 * @return bool
	 */
	public function up(): bool {
		$posts = $this->db->posts;
		$meta  = $this->db->postmeta;

		while ( $this->time_left() ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- A migration.
			$ids = $this->db->get_col( $this->db->prepare( "SELECT ID FROM {$posts} WHERE post_type = %s ORDER BY ID LIMIT %d", LinkPostType::POST_TYPE, self::BATCH ) );

			if ( count( $ids ) > 0 ) {
				$in = implode( ',', array_map( 'intval', $ids ) );

				// The meta first, so a link post is never read without its meta.
				$this->query( "DELETE FROM {$meta} WHERE post_id IN ({$in})" );
				$this->query( "DELETE FROM {$posts} WHERE ID IN ({$in})" );
			}

			if ( count( $ids ) < self::BATCH ) {
				$this->flush_cache_group( 'posts' );
				$this->flush_cache_group( 'post_meta' );

				return true;
			}
		}

		return false;
	}

	/**
	 * Write a link post for every link, the way the plugin wrote them: premium's marks, so the links are sorted out
	 * the same when the migrations run again.
	 *
	 * @return void
	 */
	public function down(): void {
		$table = $this->table( Schema::LINKS );
		if ( ! $this->has_table( $table ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- A migration.
		$rows = $this->db->get_results( "SELECT parent_id, child_id, position, is_manual, parent_type, created FROM `{$table}` ORDER BY id" );

		foreach ( (array) $rows as $row ) {
			$local = get_date_from_gmt( (string) $row->created );

			$this->query(
				$this->db->prepare(
					"INSERT INTO {$this->db->posts} ( post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt, post_status, comment_status, ping_status, post_name, to_ping, pinged, post_modified, post_modified_gmt, post_content_filtered, menu_order, post_type )
					VALUES ( 0, %s, %s, '', %s, '', 'publish', 'closed', 'closed', '', '', '', %s, %s, '', %d, %s )",
					$local,
					(string) $row->created,
					LinkPostType::TITLE,
					$local,
					(string) $row->created,
					(int) $row->position,
					LinkPostType::POST_TYPE
				)
			);

			$link_id = (int) $this->db->insert_id;
			$meta    = [];

			// Premium writes the post type of the parent first; a link without the mark is then automatic.
			if ( 1 !== (int) $row->is_manual ) {
				$meta[] = [ LinkPostType::META_PARENT_POST_TYPE, (string) $row->parent_type ];
			}

			$meta[] = [ LinkPostType::META_PARENT, (string) $row->parent_id ];
			$meta[] = [ LinkPostType::META_CHILD, (string) $row->child_id ];

			if ( 1 === (int) $row->is_manual ) {
				$meta[] = [ LinkPostType::META_PARENT_POST_TYPE, (string) $row->parent_type ];
				$meta[] = [ LinkPostType::META_MANUAL, '1' ];
			}

			$values = [];
			foreach ( $meta as [ $key, $value ] ) {
				$values[] = $this->db->prepare( '(%d, %s, %s)', $link_id, $key, $value );
			}

			$this->query( "INSERT INTO {$this->db->postmeta} ( post_id, meta_key, meta_value ) VALUES " . implode( ',', $values ) );
		}
	}
};
