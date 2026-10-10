<?php
/**
 * The word cache class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Words;

use LV2\WordPress\RelatedPostsForWP\Database\Transaction;
use LV2\WordPress\RelatedPostsForWP\Install\Table;
use LV2\WordPress\RelatedPostsForWP\Links\PostState;
use LV2\WordPress\RelatedPostsForWP\PostTypes;

/**
 * Stores the most important words of each post in the word cache table.
 */
class Cache {

	/**
	 * Post meta of 2.x and the 3.0 release candidates on a post that yields no words, or whose words could not be
	 * stored. Such a post has no rows in the table, so without a mark it would count as a post still to cache forever,
	 * and the installation would wait for it forever (known issue P15). Since 3.0 the mark is in the post state table
	 * (see PostState); the migration moves it there.
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
	 * The old words go and the new words come in one transaction, so finding the related posts of another post never
	 * sees this post without words; when the new words can't be stored, the old ones stay. A post without words keeps
	 * its previous words, like in 2.x. Either way the post is marked as cached, with its language.
	 *
	 * @param int         $post_id   The post ID.
	 * @param string|null $post_type The post type the words are stored for; the post's own by default.
	 *
	 * @return void
	 */
	public function save_post( int $post_id, ?string $post_type = null ): void {
		global $wpdb;

		$words = $this->extractor->post_words( $post_id );
		if ( null === $words || count( $words->weights ) < 1 ) {
			PostState::mark_indexed( $post_id, false, null, null === $words ? '' : $words->language, null === $words ? 0 : $words->tokens );

			return;
		}

		$post_type = $post_type ?? (string) get_post_type( $post_id );
		$params    = [];
		foreach ( $words->weights as $word => $weight ) {
			array_push( $params, $post_id, (string) $word, $weight, $post_type, $words->counts[ $word ] ?? 0, Tokenizer::VERSION );
		}

		$values = rtrim( str_repeat( '( %d, %s, %f, %s, %d, %d ),', count( $words->weights ) ), ',' );

		try {
			Transaction::run(
				function () use ( $wpdb, $post_id, $values, $params ) {
					Transaction::query( $wpdb->prepare( 'DELETE FROM ' . Table::name() . ' WHERE post_id = %d', $post_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Our own table.

					// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Our own table; the VALUES placeholders are built from the word count.
					Transaction::query( $wpdb->prepare( 'INSERT INTO ' . Table::name() . " (post_id, word, weight, post_type, tf, version) VALUES {$values}", $params ) );
				}
			);
		} catch ( \RuntimeException $error ) {
			// Marked as cached all the same, so an installation does not wait for it (known issue P15).
			PostState::mark_indexed( $post_id, false, null, $words->language, $words->tokens );

			return;
		}

		PostState::mark_indexed( $post_id, true, null, $words->language, $words->tokens );
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
		PostState::unmark_indexed( $post_id );
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
			. ' AND ' . PostState::not_indexed_sql( 'p' );
	}
}
