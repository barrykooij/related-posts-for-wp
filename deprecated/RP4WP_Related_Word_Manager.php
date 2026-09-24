<?php
/**
 * The deprecated RP4WP_Related_Word_Manager class file.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Compat\Deprecation;
use LV2\WordPress\RelatedPostsForWP\Install\Table;
use LV2\WordPress\RelatedPostsForWP\Words\Cache;
use LV2\WordPress\RelatedPostsForWP\Words\Extractor;
use LV2\WordPress\RelatedPostsForWP\Words\IgnoredWords;

/**
 * The 2.x word manager.
 *
 * @deprecated 3.0.0 Use \LV2\WordPress\RelatedPostsForWP\Words\Cache.
 */
class RP4WP_Related_Word_Manager {

	/**
	 * The word cache table, without the database prefix.
	 */
	public const DB_TABLE = Table::NAME;

	/**
	 * The word cache, shared by the calls on this object like the 2.x ignored words were.
	 *
	 * @var Cache|null
	 */
	private $cache = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		Deprecation::class_used( __CLASS__, Cache::class );
	}

	/**
	 * The word cache table, with the database prefix.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return string
	 */
	public static function get_database_table() {
		Deprecation::method( __METHOD__, Table::class . '::name()' );

		return Table::name();
	}

	/**
	 * Print the ignored words of a language as a sorted PHP array without duplicates, then stop. A developer helper for
	 * maintaining the word lists.
	 *
	 * @deprecated 3.0.0
	 *
	 * @param string $lang The locale.
	 *
	 * @return void
	 */
	public function dedupe_and_order_ignored_words( $lang ) {
		Deprecation::method( __METHOD__ );

		$temp_words = array();
		foreach ( ( new IgnoredWords() )->get( (string) $lang ) as $word ) {
			// Only add a word once, and skip words with broken encoding.
			if ( ! in_array( $word, $temp_words ) && false === strpos( $word, 'Ã' ) ) { // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict -- Same as 2.x.
				$temp_words[] = trim( str_ireplace( "'", '', $word ) );
			}
		}

		sort( $temp_words );

		$output = 'return array(';
		foreach ( $temp_words as $word ) {
			$output .= " '{$word}',";
		}
		$output .= ');';

		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- PHP source for a developer, as in 2.x.
		die();
	}

	/**
	 * The words of a post with their weight.
	 *
	 * @deprecated 3.0.0
	 *
	 * @param int $post_id The post.
	 *
	 * @return array
	 */
	public function get_words_of_post( $post_id ) {
		Deprecation::method( __METHOD__, Extractor::class . '::words_of_post()' );

		return $this->cache()->extractor()->words_of_post( (int) $post_id );
	}

	/**
	 * Save the words of a post in the cache.
	 *
	 * @deprecated 3.0.0
	 *
	 * @param int $post_id The post.
	 *
	 * @return void
	 */
	public function save_words_of_post( $post_id ) {
		Deprecation::method( __METHOD__, Cache::class . '::save_post()' );

		$this->cache()->save_post( (int) $post_id );
	}

	/**
	 * The IDs of posts whose words are not cached.
	 *
	 * @deprecated 3.0.0
	 *
	 * @param int $limit The maximum number of IDs; -1 for all.
	 *
	 * @return array
	 */
	public function get_uncached_post_ids( $limit = -1 ) {
		Deprecation::method( __METHOD__, Cache::class . '::uncached_post_ids()' );

		return $this->cache()->uncached_post_ids( (int) $limit );
	}

	/**
	 * The number of posts whose words are not cached.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return mixed The count as the database returned it in 2.x: a string.
	 */
	public function get_uncached_post_count() {
		Deprecation::method( __METHOD__, Cache::class . '::uncached_post_count()' );

		return (string) $this->cache()->uncached_post_count();
	}

	/**
	 * Cache the words of posts whose words are not cached.
	 *
	 * @deprecated 3.0.0
	 *
	 * @param int $limit The maximum number of posts; -1 for all.
	 *
	 * @return bool
	 */
	public function save_all_words( $limit = -1 ) {
		Deprecation::method( __METHOD__, Cache::class . '::save_all()' );

		$this->cache()->save_all( (int) $limit );

		return true;
	}

	/**
	 * The number of cached words.
	 *
	 * @deprecated 3.0.0
	 *
	 * @return mixed The count as the database returned it in 2.x: a string.
	 */
	public function get_word_count() {
		Deprecation::method( __METHOD__, Cache::class . '::word_count()' );

		return (string) $this->cache()->word_count();
	}

	/**
	 * Delete the cached words of a post.
	 *
	 * @deprecated 3.0.0
	 *
	 * @param int $post_id The post.
	 *
	 * @return void
	 */
	public function delete_words( $post_id ) {
		Deprecation::method( __METHOD__, Cache::class . '::delete_post()' );

		$this->cache()->delete_post( (int) $post_id );
	}

	/**
	 * The word cache of this object.
	 *
	 * @return Cache
	 */
	private function cache() {
		if ( null === $this->cache ) {
			$this->cache = new Cache();
		}

		return $this->cache;
	}
}
