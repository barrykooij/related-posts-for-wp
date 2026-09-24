<?php
/**
 * The ignored words class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Words;

/**
 * Common words per locale that say nothing about what a post is about ("the", "and", ...).
 */
class IgnoredWords {

	/**
	 * The words, once loaded.
	 *
	 * @var string[]|null
	 */
	private ?array $words = null;

	/**
	 * The ignored words for a locale, filterable through `rp4wp_ignored_words`.
	 *
	 * Kept from 2.x: the first list loaded is reused for later calls on the same instance, whatever locale they ask
	 * for, unless it is empty. A locale without a word list gives an empty list, and then the filter is not applied.
	 *
	 * @param string $locale The locale; defaults to the site locale.
	 *
	 * @return string[]
	 */
	public function get( string $locale = '' ): array {
		if ( ! empty( $this->words ) ) {
			return $this->words;
		}

		if ( '' === $locale ) {
			$locale = get_locale();
		}

		$relative_path = '/ignored-words/' . $locale . '.php';

		// Prevent path traversal through the locale.
		if ( 0 !== validate_file( $relative_path ) ) {
			return [];
		}

		$file = dirname( __DIR__, 2 ) . '/resources' . $relative_path;
		if ( ! file_exists( $file ) ) {
			return [];
		}

		$words = require $file;
		if ( ! is_array( $words ) ) {
			return [];
		}

		/**
		 * Filters the words that are left out when caching the words of a post.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $words The ignored words.
		 */
		$this->words = (array) apply_filters( 'rp4wp_ignored_words', $words );

		return $this->words;
	}
}
