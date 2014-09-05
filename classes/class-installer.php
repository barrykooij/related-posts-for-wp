<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class RP4WP_Installer {

	/**
	 * Create the related cache database table
	 */
	public function create_db_table_related() {
		global $wpdb;

		$sql = "CREATE TABLE IF NOT EXISTS `" . RP4WP_Related_Word_Manager::get_database_table() . "` (
  `post_id` bigint(20) unsigned NOT NULL,
  `word` varchar(255) CHARACTER SET utf8 NOT NULL,
  `weight` float unsigned NOT NULL,
  `post_type` varchar(20) CHARACTER SET utf8 NOT NULL,
  PRIMARY KEY (`post_id`,`word`) );";

		$wpdb->query( $sql );
	}

	/**
	 * Install method, do all the install work
	 */
	public function install() {
		$this->create_db_table_related();
	}

}