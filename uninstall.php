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

		// The link posts of 2.x and their meta, when the migration did not remove them yet.
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
		delete_option( 'widget_rp4wp_related_posts_widget' );
		delete_option( 'rp4wp_install_job' );
		delete_option( 'rp4wp_install_lock' );

		// What users chose: the review notice dismissed, and the links per page on the link screen.
		delete_metadata( 'user', 0, 'rp4wp_hide_nag', '', true );
		delete_metadata( 'user', 0, 'rp4wp_per_page', '', true );

		// The post meta on content posts, including the marks of premium's refresh, which belong to the links and words.
		$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE `meta_key` IN ( 'rp4wp_auto_linked', 'rp4wp_cached', 'rp4wp_no_words', 'rp4wp_relinked', 'rp4wp_words_cached' )" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One-off cleanup.

		// The tables: the word cache, the links, the post state and the record of the migrations.
		foreach ( [ 'rp4wp_cache', 'rp4wp_links', 'rp4wp_post_state', 'rp4wp_migrations' ] as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- One-off cleanup of our own tables.
		}

		// What the migrations kept: the storage level, their state and their cursors.
		delete_option( 'rp4wp_storage' );
		delete_option( 'rp4wp_db_state' );
		delete_option( 'rp4wp_deferred_links' );
		delete_option( 'rp4wp_migrate_lock' );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'rp4wp_migration_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One-off cleanup.

		// The actions of the background installer. The Action Scheduler tables stay: other plugins may use them.
		$actions = $wpdb->prefix . 'actionscheduler_actions';
		$groups  = $wpdb->prefix . 'actionscheduler_groups';
		$logs    = $wpdb->prefix . 'actionscheduler_logs';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- One-off cleanup; the table names are built from the prefix.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $actions ) ) ) === $actions ) {
			// The installer's group, and the group of the migrations that run in the background.
			foreach ( [ 'rp4wp', 'rp4wp-db' ] as $slug ) {
				$group_id = $wpdb->get_var( $wpdb->prepare( "SELECT group_id FROM {$groups} WHERE slug = %s", $slug ) );

				if ( null !== $group_id ) {
					$wpdb->query( $wpdb->prepare( "DELETE FROM {$logs} WHERE action_id IN ( SELECT action_id FROM {$actions} WHERE group_id = %d )", $group_id ) );
					$wpdb->query( $wpdb->prepare( "DELETE FROM {$actions} WHERE group_id = %d", $group_id ) );
					$wpdb->query( $wpdb->prepare( "DELETE FROM {$groups} WHERE group_id = %d", $group_id ) );
				}
			}
		}
		// phpcs:enable
	}
}

rp4wp_uninstall();
