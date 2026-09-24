<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

use LV2\WordPress\RelatedPostsForWP\Install\Table;
use LV2\WordPress\RelatedPostsForWP\Words\Cache;
use LV2\WordPress\RelatedPostsForWP\Words\IgnoredWords;

/**
 * 2.x word manager. Delegates to the classes in src/Words while the rest of the 2.x code still uses it; it becomes a
 * deprecated shim once nothing in the plugin does.
 */
class RP4WP_Related_Word_Manager {

	const DB_TABLE = Table::NAME;

	/**
	 * The word cache, shared by the calls on this instance like the 2.x ignored words were.
	 *
	 * @var Cache|null
	 */
	private $cache = null;

	/**
	 * Get the database table
	 *
	 * @return string
	 */
	public static function get_database_table() {
		return Table::name();
	}

	/**
	 * Internal method that formats and outputs the $ignored_words array to screen
	 */
	public function dedupe_and_order_ignored_words( $lang ) {
		$output = 'return array(';

		$ignored_words = ( new IgnoredWords() )->get( $lang );

		$temp_words = array();
		foreach ( $ignored_words as $word ) {

			// Only add word if it's not already added
			if ( ! in_array( $word, $temp_words ) ) {
				if ( false !== strpos( $word, "Ã" ) ) {
					continue;
				}
				$temp_words[] = trim( str_ireplace( "'", "", $word ) );
			}

		}

		sort( $temp_words );


		foreach ( $temp_words as $word ) {
			$output .= " '{$word}',";
		}


		$output .= ");";

		echo $output;
		die();
	}

	/**
	 * Get the words of a post
	 *
	 * @param  int  $post_id
	 *
	 * @return    array  $words
	 */
	public function get_words_of_post( $post_id ) {
		return $this->cache()->extractor()->words_of_post( (int) $post_id );
	}

	/**
	 * Save words of given post
	 *
	 * @param $post_id
	 */
	public function save_words_of_post( $post_id ) {
		$this->cache()->save_post( (int) $post_id );
	}

	/**
	 * Get uncached posts
	 *
	 * @param  int  $limit
	 *
	 * @return array
	 */
	public function get_uncached_post_ids( $limit = - 1 ) {
		return $this->cache()->uncached_post_ids( (int) $limit );
	}

	/**
	 * Get the uncached post count
	 *
	 * @return mixed
	 * @since  1.6.0
	 * @access public
	 *
	 */
	public function get_uncached_post_count() {
		return (string) $this->cache()->uncached_post_count();
	}

	/**
	 * Save all words of posts
	 */
	public function save_all_words( $limit = - 1 ) {
		$this->cache()->save_all( (int) $limit );

		// Done
		return true;
	}

	/**
	 * Get the amount of words of a post
	 *
	 *
	 * @return int
	 */
	public function get_word_count() {
		return (string) $this->cache()->word_count();
	}

	/**
	 * Delete words by post ID
	 *
	 * @param $post_id
	 */
	public function delete_words( $post_id ) {
		$this->cache()->delete_post( (int) $post_id );
	}

	/**
	 * The word cache of this instance.
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
