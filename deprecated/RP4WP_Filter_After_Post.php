<?php
/**
 * The deprecated RP4WP_Filter_After_Post class file.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Compat\Deprecation;
use LV2\WordPress\RelatedPostsForWP\Frontend\ContentFilter;

/**
 * The 2.x filter that adds the related posts after the post content.
 *
 * @deprecated 3.0.0 Use \LV2\WordPress\RelatedPostsForWP\Frontend\ContentFilter.
 */
class RP4WP_Filter_After_Post extends RP4WP_Filter {

	/**
	 * The filter.
	 *
	 * @var string
	 */
	protected $tag = 'the_content';

	/**
	 * The priority.
	 *
	 * @var int
	 */
	protected $priority = 99;

	/**
	 * Constructor. Adds run() to the filter, like 2.x did.
	 */
	public function __construct() {
		Deprecation::class_used( __CLASS__, ContentFilter::class );

		$this->attach_legacy_hook();
	}

	/**
	 * Add the related posts after the content of a single post.
	 *
	 * @deprecated 3.0.0
	 *
	 * @param string $content The post content.
	 *
	 * @return string
	 */
	public function run( $content ) {
		Deprecation::method( __METHOD__, ContentFilter::class . '::append()' );

		return ContentFilter::append( $content );
	}
}
