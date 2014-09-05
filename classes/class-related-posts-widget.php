<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class RP4WP_Related_Posts_Widget extends WP_Widget {

	public function __construct() {
		// Parent construct
		parent::__construct(
			'rp4wp_related_posts_widget',
			__( 'Related Posts for WordPress', 'related-posts-for-wp' ),
			array( 'description' => __( 'Display related posts.', 'related-posts-for-wp' ) )
		);
	}

	public function widget( $args, $instance ) {

		// Get the current ID
		$id = get_the_ID();

		// Post Link Manager
		$pl_manager = new RP4WP_Post_Link_Manager();

		// Output the widget
		echo $args['before_widget'];
		echo $pl_manager->generate_children_list( $id );
		echo $args['after_widget'];
	}
}