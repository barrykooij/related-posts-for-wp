<?php

if ( !function_exists( 'rp4wp_children' ) ) {
	/**
	 * Generate the Related Posts for WordPress children list
	 *
	 * @param bool $id
	 * @param bool $output
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return string
	 */
	function rp4wp_children( $id = false, $output = true ) {

		// Get the current ID if ID not set
		if ( false === $id ) {
			$id = get_the_ID();
		}

		// Post Link Manager
		$pl_manager = new RP4WP_Post_Link_Manager();

		// Generate the children list
		$content = $pl_manager->generate_children_list( $id );

		// Output or return the content
		if ( $output ) {
			echo $content;
		} else {
			return $content;
		}

	}
}

