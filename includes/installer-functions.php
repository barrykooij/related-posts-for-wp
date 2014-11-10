<?php

function rp4wp_activate_plugin() {
	global $wpdb;

	$sql = "CREATE TABLE IF NOT EXISTS `" . $wpdb->prefix . "rp4wp_cache` (
  `post_id` bigint(20) unsigned NOT NULL,
  `word` varchar(255) CHARACTER SET utf8 NOT NULL,
  `weight` float unsigned NOT NULL,
  `post_type` varchar(20) CHARACTER SET utf8 NOT NULL,
  PRIMARY KEY (`post_id`,`word`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

	$wpdb->query( $sql );

	// Redirect to installation wizard
	add_option( 'rp4wp_do_install', true );
}