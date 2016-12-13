<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class RP4WP_Hook_Related_Update_Link extends RP4WP_Hook {
	protected $tag = 'transition_post_status';
	protected $args = 3;
	protected $priority = 11;

	public function run( $new_status, $old_status, $post ) {

		// verify this is not an auto save routine.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Only count on supported post types
		if ( ! in_array( $post->post_type, RP4WP_Related_Post_Manager::get_supported_post_types() ) ) {
			return;
		}

		// Old status must be publish and new status can't be publish (meaning we're moving 'away' from a publish)
		if ( 'publish' != $old_status || 'publish' == $new_status ) {
			return;
		}

		// Is automatic linking enabled?
		if ( 1 != RP4WP::get()->settings->get_option( 'automatic_linking' ) ) {
			return;
		}

		// Check if the current post is auto linked
		if ( 1 == get_post_meta( $post->ID, RP4WP_Constants::PM_POST_AUTO_LINKED, true ) ) {

			// create objects for PLM and RPM
			$plm = new RP4WP_Post_Link_Manager();
			$rpm = new RP4WP_Related_Post_Manager();

			// get parents
			$parents = $plm->get_parents( $post->ID );

			// delete links to given post
			$plm->delete_links_related_to( $post->ID );

			// find new related post for parents
			if ( ! ( empty( $parents ) ) ) {
				foreach ( $parents as $parent ) {
					$rpm->link_related_post( $parent->ID, 1 );
				}
			}

			// Set the auto linked meta
			delete_post_meta( $post->ID, RP4WP_Constants::PM_POST_AUTO_LINKED );
		}

	}
}