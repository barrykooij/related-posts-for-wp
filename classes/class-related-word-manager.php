<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class RP4WP_Related_Word_Manager {

	const DB_TABLE = 'rp4wp_cache';

	private $ignored_words = null;

	/**
	 * Get the database table
	 *
	 * @return string
	 */
	public static function get_database_table() {
		global $wpdb;

		return $wpdb->prefix . self::DB_TABLE;
	}

	/**
	 * Internal method that formats and outputs the $ignored_words array to screen
	 */
	public function dedupe_and_order_ignored_words( $lang ) {
		$output = 'return array(';

		$ignored_words = $this->get_ignored_words( $lang );

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
	 * Get the ignored words
	 *
	 * @param string $lang
	 *
	 * @return array
	 */
	private function get_ignored_words( $lang = '' ) {

		if ( null == $this->ignored_words ) {
			// Set the language
			if ( '' == $lang ) {
				$lang = get_locale();
			}

			// Require the lang file
			$relative_path = '/ignored-words/' . $lang . '.php';

			// Validate the file path to prevent traversal attacks
			if ( 0 !== validate_file( $relative_path ) ) {
				return array();
			}

			$filename = dirname( __FILE__ ) . $relative_path;

			// Check if file exists
			if ( ! file_exists( $filename ) ) {
				return array();
			}

			// Require the file
			$ignored_words = require( $filename );

			// Check if the the $ignored_words are set
			if ( is_null( $ignored_words ) || ! is_array( $ignored_words ) ) {
				return array();
			}

			// Words to ignore
			$this->ignored_words = apply_filters( 'rp4wp_ignored_words', $ignored_words );
		}

		return $this->ignored_words;
	}

	/**
	 * Get the words from the post content
	 *
	 * @param $post
	 *
	 * @return array $words
	 */
	private function get_content_words( $post ) {

		$content = $post->post_content;

		// Remove all line break
		$content = trim( preg_replace( '/\s+/', ' ', $content ) );

		// Array to store the linked words
		$linked_words = array();

		// Find all links in the content
		if ( true == preg_match_all( '`<a[^>]*href="([^"]+)">[^<]*</a>`iS', $content, $matches ) ) {
			if ( count( $matches[1] ) > 0 ) {

				// Loop
				foreach ( $matches[1] as $url ) {

					// Get the post Id
					$link_post_id = url_to_postid( $url );

					if ( 0 == $link_post_id ) {
						continue;
					}

					// Get the post
					$link_post = get_post( $link_post_id );

					// Check if we found a linked post
					if ( $link_post != null ) {

						// convert characters in title
						$post_title = $this->convert_characters( $link_post->post_title );

						// Get words of title
						$title_words = explode( ' ', $post_title );

						// Check, Loop
						if ( is_array( $title_words ) && count( $title_words ) > 0 ) {
							$linked_words = $this->add_words_from_array( $linked_words, $title_words, 20 );
						}
					}

				}

			}
		}

		// Remove all html tags
		$content = strip_tags( $content );

		// Remove all shortcodes
		$content = strip_shortcodes( $content );

		// Remove the <!--more--> tag
		$content = str_ireplace( '<!--more-->', '', $content );

		// UTF8 fix content
		$content = $this->convert_characters( $content );

		// Split string into words
		$words = explode( ' ', $content );

		// Add the $linked_words
		$words = array_merge( $words, $linked_words );

		// Return the $words
		return $words;
	}

	/**
	 * Add words from an array to the "base" words array, multiplied by their weight
	 *
	 * Note: Make sure `$words` is UTF-8 encoded at this point.
	 *
	 * @param array $base_words
	 * @param array $words
	 * @param int   $weight
	 *
	 * @return array
	 */
	private function add_words_from_array( array $base_words, $words, $weight = 1 ) {

		// Check if weight > 0 and if $words is array
		if ( $weight > 0 && is_array( $words ) ) {
			foreach ( $words as $word ) {
				$word_multiplied_by_weight = array_fill( 0, $weight, $word );
				$base_words                = array_merge( $base_words, $word_multiplied_by_weight );
			}
		}

		return $base_words;
	}

	/**
	 * Convert UTF-8 characters correctly
	 *
	 * @param string $string
	 *
	 * @return string
	 */
	private function convert_characters( $string ) {

		// Detect encoding, only encode if string isn't encoded already
		if ( 'UTF-8' !== mb_detect_encoding( $string, 'UTF-8', true ) ) {
			$string = utf8_encode( $string );
		}

		// Replace all 'special characters' with normal ones
		if ( strpos( $string = htmlentities( $string, ENT_QUOTES, 'UTF-8' ), '&' ) !== false ) {
			$string = html_entity_decode( preg_replace( '~&([a-z]{1,2})(?:acute|cedil|circ|grave|lig|orn|ring|slash|tilde|uml);~iS', '$1', $string ), ENT_QUOTES, 'UTF-8' );
		}

		// Remove characters that are on our 'blacklist'
		$string = preg_replace( '/[;:\'\"\[\]\-\_=\+\.,\/\\<>`~\(\)\!@#$%\^&\*\?\|]+/i', ' ', $string );

		// Return string
		return $string;
	}

	/**
	 * Get the words of a post
	 *
	 * @param     int $post_id
	 *
	 * @return    array  $words
	 */
	public function get_words_of_post( $post_id ) {

		setlocale( LC_CTYPE, 'en_US.UTF8' );

		$post = get_post( $post_id );

		$title_weight = apply_filters( 'rp4wp_weight_title', 80 );
		$tag_weight   = apply_filters( 'rp4wp_weight_tag', 10 );
		$cat_weight   = apply_filters( 'rp4wp_weight_cat', 20 );

		// Get raw words
		$raw_words = $this->get_content_words( $post );

		// Get words from title
		$post_title = $this->convert_characters( $post->post_title );
		$title_words = explode( ' ', $post_title );
		$raw_words   = $this->add_words_from_array( $raw_words, $title_words, $title_weight );

		// Get tags and add them to list
		$tags = wp_get_post_tags( $post->ID, array( 'fields' => 'names' ) );

		if ( is_array( $tags ) && count( $tags ) > 0 ) {
			foreach ( $tags as $tag ) {
				$tag = $this->convert_characters( $tag );
				$tag_words = explode( ' ', $tag );
				$raw_words = $this->add_words_from_array( $raw_words, $tag_words, $tag_weight );
			}
		}

		// Get categories and add them to list
		$categories = wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) );
		if ( is_array( $categories ) && count( $categories ) > 0 ) {
			foreach ( $categories as $category ) {
				$category = $this->convert_characters( $category );
				$cat_words = explode( ' ', $category );
				$raw_words = $this->add_words_from_array( $raw_words, $cat_words, $cat_weight );
			}
		}

		// Count words and store them in array
		$words = array();

		if ( is_array( $raw_words ) && count( $raw_words ) > 0 ) {

			$ignored_words = $this->get_ignored_words();

			foreach ( $raw_words as $word ) {

				// Trim word
				$word = mb_strtolower( trim( $word ) );

				// Only use words longer than 1 character
				if ( strlen( $word ) < 2 ) {
					continue;
				}

				// Skip ignored words
				if ( in_array( $word, $ignored_words ) ) {
					continue;
				}

				// Add word
				if ( isset( $words[$word] ) ) {
					$words[$word] += 1;
				} else {
					$words[$word] = 1;
				}

			}
		}

		// reverse sort, most important words at top
		arsort( $words );

		// store new words
		$new_words = array();

		// count total 'raw' words
		$total_raw_words = count( $raw_words );

		// count words added
		$words_added = 0;

		$cache_word_amount = apply_filters( 'rp4wp_cache_word_amount', 6 );

		foreach ( $words as $word => $amount ) {

			// add word to new words with relative weight
			$new_words[ $word ] = ( $amount / $total_raw_words );

			// we only add 6 most important words, filterable via 'rp4wp_cache_word_amount'
			$words_added ++;
			if ( $words_added >= $cache_word_amount ) {
				break;
			}
		}

		return $new_words;
	}

	/**
	 * Save words of given post
	 *
	 * @param $post_id
	 */
	public function save_words_of_post( $post_id ) {
		global $wpdb;

		// Get words
		$words = $this->get_words_of_post( $post_id );

		// Check words
		if ( is_array( $words ) && count( $words ) > 0 ) {

			// get post type of given post
			$post_type = get_post_type( $post_id );

			// Delete all currents words of post
			$this->delete_words( $post_id );

			// build SQL string for batch INSERT
			$sql = 'INSERT INTO '. self::get_database_table() . ' (post_id, word, weight, post_type )';
			$sql .= ' VALUES';
			$params = array(  );

			// add params for each VALUES pair
			foreach ( $words as $word => $amount ) {
				$params[] = $post_id;
				$params[] = $word;
				$params[] = $amount;
				$params[] = $post_type;
			}

			// add VALUES pairs
			$sql .= ' ' . str_repeat( '( %d, %s, %f, %s ),', count( $words ) );
			$sql = rtrim( $sql, ',');

			// prep & execute!
			$query = $wpdb->prepare( $sql, $params );
			$wpdb->query( $query );
		}
	}

	/**
	 * Get uncached posts
	 *
	 * @param int $limit
	 *
	 * @return array
	 */
	public function get_uncached_post_ids( $limit = -1 ) {

		global $wpdb;
		$words_table = self::get_database_table();

		$sql = "SELECT p.ID FROM {$wpdb->posts} p";
		$sql .= " LEFT JOIN {$words_table} w ON w.post_id = p.ID";
		$sql .= " WHERE p.post_type IN ('" . implode( "','", RP4WP_Related_Post_Manager::get_supported_post_types() ) . "') AND p.post_status = 'publish'";

		// limit result to post rows WITHOUT joined rows
		$sql .= ' AND w.post_id IS NULL';

		if( $limit > 0 ) {
			$sql .= ' LIMIT %d';
			$sql = $wpdb->prepare( $sql, $limit );
		}

		return $wpdb->get_col( $sql );
	}

	/**
	 * Get the uncached post count
	 *
	 * @since  1.6.0
	 * @access public
	 *
	 * @return mixed
	 */
	public function get_uncached_post_count() {
		global $wpdb;
		$words_table = self::get_database_table();

		$sql = "SELECT COUNT(p.ID) FROM {$wpdb->posts} p";
		$sql .= " LEFT JOIN {$words_table} w ON w.post_id = p.ID";
		$sql .= " WHERE p.post_type IN ('" . implode( "','", RP4WP_Related_Post_Manager::get_supported_post_types() ) . "') AND p.post_status = 'publish'";

		// limit result to post rows WITHOUT joined rows
		$sql .= ' AND w.post_id IS NULL';

		return $wpdb->get_var( $sql );
	}

	/**
	 * Save all words of posts
	 */
	public function save_all_words( $limit = - 1 ) {
		global $wpdb;

		// Get uncached posts
		$post_ids = $this->get_uncached_post_ids( $limit );

		// Check & Loop
		if ( count( $post_ids ) > 0 ) {
			foreach ( $post_ids as $post_id ) {
				$this->save_words_of_post( $post_id );
			}
		}

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
		global $wpdb;

		return $wpdb->get_var( "SELECT COUNT(word) FROM `" . self::get_database_table() . "` WHERE `post_type` IN ('" . implode( "','", RP4WP_Related_Post_Manager::get_supported_post_types() ) . "') " );
	}

	/**
	 * Delete words by post ID
	 *
	 * @param $post_id
	 */
	public function delete_words( $post_id ) {
		global $wpdb;

		$wpdb->delete( self::get_database_table(), array( 'post_id' => $post_id ), array( '%d' ) );
	}

}