<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class RP4WP_Filter_Yoast_Duplicate_Post extends RP4WP_Filter {
	protected $tag = 'duplicate_post_excludelist_filter';
	protected $priority = 10;

	/** 
	 * Filters out custom fields from being duplicated in addition to the defaults.
	 *
	 * @param array $meta_excludelist The default exclusion list, based on the “Do not copy these fields” setting, plus some other field names.
	 *
	 * @return array The custom fields to exclude.
	 */
	public function run( $meta_excludelist  ) {
		return array_merge( $meta_excludelist, array( RP4WP_Constants::PM_POST_AUTO_LINKED ) );
	}
}