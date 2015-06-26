<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class RP4WP_Hook_Shortcode extends RP4WP_Hook {
	protected $tag = 'init';

	public function run() {
		add_shortcode( 'rp4wp', array( $this, 'output' ) );
	}

	/**
	 * Output the shortcode
	 *
	 * @since  1.3.0
	 * @access public
	 *
	 */
	public function output( $atts ) {

		$atts = shortcode_atts( array(
			'id'    => get_the_ID(),
			'limit' => -1
		), $atts );

		// Post Link Manager
		$pl_manager = new RP4WP_Post_Link_Manager();

		// Generate the children list
		return $pl_manager->generate_children_list( $atts['id'], $atts['limit'] );
	}
}