<?php

if ( !defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class RP4WP_Cap_Manager {

	/**
	 * Get custom post type capabilities
	 *
	 * @param $post_id
	 *
	 * @return string
	 */
	public static function get_capability( $post_id ) {
		$post_type     = ( isset( $post_id ) ) ? get_post_type( $post_id ) : 'post';
		$post_type_obj = get_post_type_object( $post_type );

		return ( ( null != $post_type_obj ) ? $post_type_obj->cap->edit_posts : 'edit_posts' );
	}

}