<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class RP4WP_Filter_After_Post extends RP4WP_Filter {
	protected $tag = 'the_content';
	protected $priority = 99;

	/**
	 * the_content filter that will add linked posts to the bottom of the main post content
	 *
	 * @param $content
	 *
	 * @return string
	 */
	public function run( $content ) {
		/**
		 * Wow, what's going on here?! Well, setup_postdata() sets a lot of variables but does not change the $post variable.
		 * All checks return the main queried ID but we want to check if this specific filter call is the for the 'main' content.
		 * The method setup_postdata() does global and set the $id variable, so we're checking that.
		 */
		global $id;

		// Only run on single
		if ( ! is_singular() || ! is_main_query() || $id != get_queried_object_id() ) {
			return $content;
		}

		// Allow disabling content filter
		if ( false === apply_filters( 'rp4wp_append_content', true ) ) {
			return $content;
		}

		// Post Link Manager
		$pl_manager = new RP4WP_Post_Link_Manager();

		// Generate the content
		$content .= $pl_manager->generate_children_list( $id );

		// Return content
		return $content;
	}
}