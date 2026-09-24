<?php
/**
 * The word cache class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Words;

use LV2\WordPress\RelatedPostsForWP\Install\Table;
use LV2\WordPress\RelatedPostsForWP\PostTypes;

/**
 * Stores the most important words of each post in the word cache table.
 */
class Cache {

	/**
	 * Post meta of a post that yields no words, or whose words could not be stored. Such a post has no rows in the
	 * table, so without the mark it would count as a post still to cache forever, and the installation wizard would
	 * wait for it forever (known issue P15).
	 */
	public const META_NO_WORDS = 'rp4wp_no_words';

	/**
	 * The word extractor.
	 *
	 * @var Extractor
	 */
	private Extractor $extractor;

	/**
	 * Constructor.
	 *
	 * @param Extractor|null $extractor The word extractor; a new one when null.
	 */
	public function __construct( ?Extractor $extractor = null ) {
		$this->extractor = $extractor ?? new Extractor();
	}

	/**
	 * The word extractor.
	 *
	 * @return Extractor
	 */
	public function extractor(): Extractor {
		return $this->extractor;
	}

	/**
	 * Store the words of a post, replacing the words stored before.
	 *
	 * A post without words keeps its previous words, like in 2.x, and is marked as done.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return void
	 */
	public function save_post( int $post_id ): void {
		global $wpdb;

		$words = $this->extractor->words_of_post( $post_id );
		if ( count( $words ) < 1 ) {
			update_post_meta( $post_id, self::META_NO_WORDS, 1 );

			return;
		}

		$post_type = get_post_type( $post_id );

		$this->delete_post( $post_id );

		$params = [];
		foreach ( $words as $word => $weight ) {
			$params[] = $post_id;
			$params[] = $word;
			$params[] = $weight;
			$params[] = $post_type;
		}

		$values = rtrim( str_repeat( '( %d, %s, %f, %s ),', count( $words ) ), ',' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Our own table; the VALUES placeholders are built from the word count.
		if ( false === $wpdb->query( $wpdb->prepare( 'INSERT INTO ' . Table::name() . " (post_id, word, weight, post_type ) VALUES {$values}", $params ) ) ) {
			update_post_meta( $post_id, self::META_NO_WORDS, 1 );
		}
	}

	/**
	 * Store the words of every published post that has none yet.
	 *
	 * @param int $limit The maximum number of posts; -1 for all.
	 *
	 * @return void
	 */
	public function save_all( int $limit = -1 ): void {
		foreach ( $this->uncached_post_ids( $limit ) as $post_id ) {
			$this->save_post( (int) $post_id );
		}
	}

	/**
	 * The published posts of the supported post types without stored words.
	 *
	 * @param int $limit The maximum number of posts; -1 for all.
	 *
	 * @return string[] Post IDs, as the database returns them.
	 */
	public function uncached_post_ids( int $limit = -1 ): array {
		global $wpdb;

		$sql = $this->uncached_posts_sql( 'p.ID' );

		if ( $limit > 0 ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Built from our table name and escaped post types.
			$sql = $wpdb->prepare( $sql . ' LIMIT %d', $limit );
		}

		return $wpdb->get_col( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- See above.
	}

	/**
	 * The number of published posts of the supported post types without stored words.
	 *
	 * @return int
	 */
	public function uncached_post_count(): int {
		global $wpdb;

		return (int) $wpdb->get_var( $this->uncached_posts_sql( 'COUNT(p.ID)' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- Built from our table name and escaped post types.
	}

	/**
	 * The number of stored words for the supported post types.
	 *
	 * @return int
	 */
	public function word_count(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- Our own table and escaped post types.
		return (int) $wpdb->get_var( 'SELECT COUNT(word) FROM `' . Table::name() . "` WHERE `post_type` IN ('" . implode( "','", PostTypes::supported() ) . "') " );
	}

	/**
	 * Remove the stored words of a post.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return void
	 */
	public function delete_post( int $post_id ): void {
		global $wpdb;

		$wpdb->delete( Table::name(), [ 'post_id' => $post_id ], [ '%d' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Our own table.
		delete_post_meta( $post_id, self::META_NO_WORDS );
	}

	/**
	 * The query for published posts of the supported post types without stored words, which are not marked as done
	 * either.
	 *
	 * @param string $select What to select.
	 *
	 * @return string
	 */
	private function uncached_posts_sql( string $select ): string {
		global $wpdb;

		return "SELECT {$select} FROM {$wpdb->posts} p"
			. ' LEFT JOIN ' . Table::name() . ' w ON w.post_id = p.ID'
			. " WHERE p.post_type IN ('" . implode( "','", PostTypes::supported() ) . "') AND p.post_status = 'publish'"
			. ' AND w.post_id IS NULL'
			. " AND NOT EXISTS ( SELECT 1 FROM {$wpdb->postmeta} m WHERE m.post_id = p.ID AND m.meta_key = '" . self::META_NO_WORDS . "' )";
	}
}
