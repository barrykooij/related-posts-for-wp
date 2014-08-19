<?php

// What is happening?
if ( !defined( 'ABSPATH' ) || !defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

// Should we clean?
$options = get_option( 'rp4wp', array() );
if ( isset( $options['clean_on_uninstall'] ) && 1 == $options['clean_on_uninstall'] ) {

	global $wpdb;

	/**
	 * Once upon a time I was relating posts
	 * But now I'm only cleaning them up
	 * There's nothing I can do
	 * A total eclipse of the heart
	 */

	// Get ID's of post links
	$link_ids = get_posts(
		array(
			'post_type'      => 'rp4wp_link',
			'fields'         => 'ids',
			'posts_per_page' => - 1
		)
	);

	// Delete all link posts
	$wpdb->query( "DELETE FROM $wpdb->posts WHERE `ID` IN (" . implode( ",", $link_ids ) . ");" );

	// Delete all link post meta
	$wpdb->query( "DELETE FROM $wpdb->postmeta WHERE `post_id` IN (" . implode( ",", $link_ids ) . ");" );


	// Delete the options
	delete_option( 'rp4wp' );
	delete_option( 'rp4wp_do_install' );
	delete_option( 'rp4wp_install_date' );
	delete_option( 'rp4wp_hide_nag' );

	// Remove the post meta we attached to posts
	$wpdb->query( "DELETE FROM $wpdb->postmeta WHERE `meta_key` = 'rp4wp_auto_linked' OR `meta_key` = 'rp4wp_cached' " );

}