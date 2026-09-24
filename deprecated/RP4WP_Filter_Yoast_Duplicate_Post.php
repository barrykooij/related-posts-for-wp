<?php
/**
 * The deprecated RP4WP_Filter_Yoast_Duplicate_Post class file.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Compat\Deprecation;
use LV2\WordPress\RelatedPostsForWP\Integrations\YoastDuplicatePost;

/**
 * The 2.x filter that keeps Yoast Duplicate Post from copying the link meta.
 *
 * @deprecated 3.0.0 Use \LV2\WordPress\RelatedPostsForWP\Integrations\YoastDuplicatePost.
 */
class RP4WP_Filter_Yoast_Duplicate_Post extends RP4WP_Filter {

	/**
	 * The filter.
	 *
	 * @var string
	 */
	protected $tag = 'duplicate_post_excludelist_filter';

	/**
	 * Constructor. Adds run() to the filter, like 2.x did.
	 */
	public function __construct() {
		Deprecation::class_used( __CLASS__, YoastDuplicatePost::class );

		$this->attach_legacy_hook();
	}

	/**
	 * Add the plugin meta to the meta that is not copied.
	 *
	 * @deprecated 3.0.0
	 *
	 * @param array $meta_excludelist The meta keys.
	 *
	 * @return array
	 */
	public function run( $meta_excludelist ) {
		Deprecation::method( __METHOD__, YoastDuplicatePost::class . '::exclude_meta()' );

		return YoastDuplicatePost::exclude_meta( $meta_excludelist );
	}
}
