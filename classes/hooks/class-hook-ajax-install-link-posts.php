<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class RP4WP_Hook_Ajax_Install_Link_Posts extends RP4WP_Hook {
	protected $tag = 'wp_ajax_rp4wp_install_link_posts';

	public function run() {

		// Get the PPR
		$ppr = isset( $_POST['ppr'] ) ? $_POST['ppr'] : 5;

		// Get the rel amount
		$rel_amount = isset( $_POST['rel_amount'] ) ? $_POST['rel_amount'] : 3;

		// Related Post Manager object
		$related_post_manager = new RP4WP_Related_Post_Manager();

		// Link posts
		$related_post_manager->link_related_posts( $rel_amount, $ppr );

		// Get uncached post count
		$uncached_post_count  = $related_post_manager->get_uncached_post_count();

		// Check if we're done
		if ( $uncached_post_count == 0 ) {
			// Save the wizard setting as the option
			$options                                  = RP4WP()->settings->get_options();
			$options['automatic_linking_post_amount'] = $rel_amount;
			update_option( 'rp4wp', $options );
		}

		// Echo the uncached posts
		echo $uncached_post_count;

		exit;
	}

}