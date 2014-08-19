<?php

// What is happening?
if ( !defined( 'ABSPATH' ) || !defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

// Should we clean?
if ( 1 == RP4WP::get()->settings->get_option( 'clean_on_uninstall' ) ) {

	/**
	 * Once upon a time I was relating posts
	 * But now I'm only cleaning them up
	 * There's nothing I can do
	 * A total eclipse of the heart
	 */

	// Delete all related posts links
	$post_manager = new RP4WP_Post_Link_Manager();
	$post_manager->delete_all_links();


	exit;

	// Delete the options
	delete_option( 'rp4wp' );
	delete_option( RP4WP_Constants::OPTION_DO_INSTALL );
	delete_option( RP4WP_Constants::OPTION_INSTALL_DATE );
	delete_option( RP4WP_Constants::OPTION_ADMIN_NOTICE_KEY );

	// Remove the post meta we attached to posts
	$wpdb->query( "DELETE FROM $wpdb->post_meta WHERE `meta_key` = 'rp4wp_auto_linked' OR `meta_key` = 'rp4wp_cached' " );

}