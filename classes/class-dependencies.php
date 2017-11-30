<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class RP4WP_Dependencies {

	/**
	 * Check for dependencies
	 */
	public function check() {

		// check if mbstring is loaded
		if ( ! extension_loaded( "mbstring" ) ) {
			add_action( 'admin_notices', array( $this, 'display_mbstring_error' ) );
		}

	}

	/**
	 * Display error if mbstring is not loaded
	 */
	public function display_mbstring_error() {
		?>
        <div class="notice notice-error">
            <p><strong><?php _e( "Error:", "related-posts-for-wp"); ?></strong> <?php _e( sprintf( "The %s extension needs to be installed and activated for Related Posts for WP to work!", "<strong>mbstring</strong>" ), 'related-posts-for-wp' ); ?></p>
            <p><?php _e( sprintf( "Please contact your host and ask them to install the %s PHP extension.", "<strong>mbstring</strong>" ), 'related-posts-for-wp' ); ?></p>
        </div>
		<?php
	}

}