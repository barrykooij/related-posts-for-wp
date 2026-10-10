<?php
/**
 * Migration: the marks on posts move from post meta to the post state table.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Database\Migration;
use LV2\WordPress\RelatedPostsForWP\Database\Schema;

return new class() extends Migration {

	/**
	 * The post meta of 2.x and the 3.0 release candidates that becomes the post state.
	 */
	private const KEYS = [ 'rp4wp_auto_linked', 'rp4wp_relinked', 'rp4wp_words_cached', 'rp4wp_no_words', 'rp4wp_cached' ];

	/**
	 * Rows of meta deleted per query.
	 */
	private const BATCH = 5000;

	/**
	 * What the migration does.
	 *
	 * @return string
	 */
	public function description(): string {
		return 'Move the marks on posts (linked, words cached, no words) from post meta to the post state table.';
	}

	/**
	 * Copy the marks in one go, switch to the table, then remove the meta in slices.
	 *
	 * @return bool
	 */
	public function up(): bool {
		if ( ! $this->cursor( 'copied', false ) ) {
			$this->copy();
			$this->save_cursor( 'copied', true );

			if ( Schema::storage() < Schema::STORAGE_POST_STATE ) {
				Schema::set_storage( Schema::STORAGE_POST_STATE );
			}
		}

		$keys = "'" . implode( "','", self::KEYS ) . "'";

		while ( $this->time_left() ) {
			if ( $this->query( "DELETE FROM {$this->db->postmeta} WHERE meta_key IN ( {$keys} ) LIMIT " . self::BATCH ) < self::BATCH ) {
				$this->flush_cache_group( 'post_meta' );

				return true;
			}
		}

		return false;
	}

	/**
	 * Write the marks back as post meta, and go back to them.
	 *
	 * @return void
	 */
	public function down(): void {
		$state = $this->table( Schema::POST_STATE );
		$cache = $this->table( Schema::CACHE );
		$keys  = "'" . implode( "','", self::KEYS ) . "'";
		$meta  = $this->db->postmeta;

		$this->query( "DELETE FROM {$meta} WHERE meta_key IN ( {$keys} )" );

		if ( $this->has_table( $state ) ) {
			$this->query( "INSERT INTO {$meta} ( post_id, meta_key, meta_value ) SELECT post_id, 'rp4wp_auto_linked', '1' FROM `{$state}` WHERE linked_at > 0" );
			$this->query( "INSERT INTO {$meta} ( post_id, meta_key, meta_value ) SELECT post_id, 'rp4wp_relinked', linked_at FROM `{$state}` WHERE linked_at > 1" );
			$this->query( "INSERT INTO {$meta} ( post_id, meta_key, meta_value ) SELECT post_id, 'rp4wp_words_cached', indexed_at FROM `{$state}` WHERE indexed_at > 1" );

			$no_words = $this->has_table( $cache ) ? " AND NOT EXISTS ( SELECT 1 FROM `{$cache}` C WHERE C.post_id = S.post_id )" : '';
			$this->query( "INSERT INTO {$meta} ( post_id, meta_key, meta_value ) SELECT post_id, 'rp4wp_no_words', '1' FROM `{$state}` S WHERE indexed_at > 0{$no_words}" );

			$this->query( "DELETE FROM `{$state}`" );
		}

		$this->flush_cache_group( 'post_meta' );
		Schema::set_storage( 0 );
	}

	/**
	 * Copy the marks into the post state table. Every statement adds or raises, so running it again is safe.
	 *
	 * @return void
	 */
	private function copy(): void {
		$state = $this->table( Schema::POST_STATE );
		$cache = $this->table( Schema::CACHE );
		$posts = $this->db->posts;
		$meta  = $this->db->postmeta;

		// A post with words in the cache was cached at a time that is not known: 1.
		if ( $this->has_table( $cache ) ) {
			$this->query(
				"INSERT INTO `{$state}` ( post_id, post_type, indexed_at, version )
				SELECT C.post_id, P.post_type, 1, 1 FROM `{$cache}` C INNER JOIN {$posts} P ON P.ID = C.post_id GROUP BY C.post_id, P.post_type
				ON DUPLICATE KEY UPDATE indexed_at = GREATEST( indexed_at, 1 ), version = 1"
			);
		}

		// A post that yields no words, or whose words could not be stored, counts as cached too (known issue P15).
		$this->query(
			"INSERT INTO `{$state}` ( post_id, post_type, indexed_at, version )
			SELECT M.post_id, P.post_type, 1, 1 FROM {$meta} M INNER JOIN {$posts} P ON P.ID = M.post_id WHERE M.meta_key = 'rp4wp_no_words' GROUP BY M.post_id, P.post_type
			ON DUPLICATE KEY UPDATE indexed_at = GREATEST( indexed_at, 1 ), version = 1"
		);

		// When premium's refresh cached the words, in milliseconds.
		$this->query(
			"INSERT INTO `{$state}` ( post_id, post_type, indexed_at, version )
			SELECT M.post_id, P.post_type, MAX( CAST( M.meta_value AS UNSIGNED ) ), 1 FROM {$meta} M INNER JOIN {$posts} P ON P.ID = M.post_id WHERE M.meta_key = 'rp4wp_words_cached' GROUP BY M.post_id, P.post_type
			ON DUPLICATE KEY UPDATE indexed_at = GREATEST( indexed_at, VALUES( indexed_at ) ), version = 1"
		);

		// A linked post was linked at a time that is not known: 1.
		$this->query(
			"INSERT INTO `{$state}` ( post_id, post_type, linked_at )
			SELECT M.post_id, P.post_type, 1 FROM {$meta} M INNER JOIN {$posts} P ON P.ID = M.post_id WHERE M.meta_key = 'rp4wp_auto_linked' AND CAST( M.meta_value AS UNSIGNED ) = 1 GROUP BY M.post_id, P.post_type
			ON DUPLICATE KEY UPDATE linked_at = GREATEST( linked_at, 1 )"
		);

		// When premium's refresh linked it again, in milliseconds; only for a post that counts as linked.
		$this->query(
			"UPDATE `{$state}` S INNER JOIN ( SELECT post_id, MAX( CAST( meta_value AS UNSIGNED ) ) AS relinked FROM {$meta} WHERE meta_key = 'rp4wp_relinked' GROUP BY post_id ) R ON R.post_id = S.post_id
			SET S.linked_at = GREATEST( S.linked_at, R.relinked ) WHERE S.linked_at > 0"
		);
	}
};
