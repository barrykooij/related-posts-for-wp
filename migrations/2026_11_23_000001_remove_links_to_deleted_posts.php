<?php
/**
 * Migration: remove the links to and from posts that were deleted.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Database\Migration;
use LV2\WordPress\RelatedPostsForWP\Database\Schema;
use LV2\WordPress\RelatedPostsForWP\Install\Update;
use LV2\WordPress\RelatedPostsForWP\Links\LinkRepository;

return new class() extends Migration {

	/**
	 * How many links are removed per slice.
	 */
	private const BATCH = 500;

	/**
	 * What the migration does.
	 *
	 * @return string
	 */
	public function description(): string {
		return 'Remove the links to and from posts that were deleted, which 2.x and 3.0 kept (known issue K2), and relink the posts that showed a deleted post, without a notice. The way back does nothing: the links pointed at nothing.';
	}

	/**
	 * Remove the links in slices, then ask for a relink of the posts that lost a related post.
	 *
	 * @return bool
	 */
	public function up(): bool {
		$table = $this->table( Schema::LINKS );
		if ( ! $this->has_table( $table ) ) {
			return true;
		}

		$links   = new LinkRepository();
		$parents = array_map( 'intval', (array) $this->cursor( 'parents', [] ) );

		while ( $this->time_left() ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- A migration.
			$rows = (array) $this->db->get_results(
				$this->db->prepare(
					'SELECT L.id, L.parent_id, P.ID AS parent_exists FROM %i L
					LEFT JOIN %i P ON P.ID = L.parent_id
					LEFT JOIN %i C ON C.ID = L.child_id
					WHERE P.ID IS NULL OR C.ID IS NULL
					ORDER BY L.id LIMIT %d',
					$table,
					$this->db->posts,
					$this->db->posts,
					self::BATCH
				)
			);

			if ( [] === $rows ) {
				if ( [] !== $parents ) {
					Update::request( 'relink', '3.1.0', $parents, false );
				}

				return true;
			}

			$ids = [];
			foreach ( $rows as $row ) {
				$ids[] = (int) $row->id;

				// A post that still exists lost a related post.
				if ( null !== $row->parent_exists ) {
					$parents[] = (int) $row->parent_id;
				}
			}

			$links->delete_many( $ids );
			$parents = array_values( array_unique( $parents ) );
			$this->save_cursor( 'parents', $parents );
		}

		return false;
	}

	/**
	 * Nothing to undo: the removed links pointed at posts that no longer exist.
	 *
	 * @return void
	 */
	public function down(): void {
	}
};
