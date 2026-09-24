<?php
/**
 * Removes the plugin's data when the plugin is deleted and "Remove data on uninstall" is on.
 *
 * Runs without the plugin loaded, so it uses no plugin classes.
 *
 * @package RelatedPostsForWP
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

if ( ! function_exists( 'rp4wp_uninstall' ) ) {
	/**
	 * Remove the plugin's data, if the user asked for it.
	 *
	 * @return void
	 */
	function rp4wp_uninstall() {
		global $wpdb;

		$options = get_option( 'rp4wp', [] );
		if ( ! isset( $options['clean_on_uninstall'] ) || 1 !== (int) $options['clean_on_uninstall'] ) {
			return;
		}

		// The link posts and their meta.
		$link_ids = get_posts(
			[
				'post_type'      => 'rp4wp_link',
				'fields'         => 'ids',
				'posts_per_page' => -1,
			]
		);

		if ( count( $link_ids ) > 0 ) {
			$placeholders = implode( ',', array_fill( 0, count( $link_ids ), '%d' ) );

			// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- One-off cleanup; the placeholders are built from the ID count.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->posts} WHERE `ID` IN ({$placeholders})", $link_ids ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE `post_id` IN ({$placeholders})", $link_ids ) );
			// phpcs:enable
		}

		// The options.
		delete_option( 'rp4wp' );
		delete_option( 'rp4wp_do_install' );
		delete_option( 'rp4wp_is_installing' );
		delete_option( 'rp4wp_install_date' );
		delete_option( 'rp4wp_hide_nag' );

		// What users chose: the review notice dismissed, and the links per page on the link screen.
		delete_metadata( 'user', 0, 'rp4wp_hide_nag', '', true );
		delete_metadata( 'user', 0, 'rp4wp_per_page', '', true );

		// The post meta on content posts.
		$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE `meta_key` IN ( 'rp4wp_auto_linked', 'rp4wp_cached', 'rp4wp_no_words' )" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One-off cleanup.

		// The word cache table.
		$wpdb->query( "DROP TABLE {$wpdb->prefix}rp4wp_cache" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One-off cleanup of our own table.
	}
}

rp4wp_uninstall();
