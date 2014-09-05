<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! class_exists( 'RP4WP_Javascript_Strings' ) ) {
	class RP4WP_Javascript_Strings {

		private static $value = null;

		private static function fill() {
			self::$value = array(
				'confirm_delete_related_post' => __( 'Are you sure you want to delete this related post?', 'related-posts-for-wp' ),
			);
		}

		public static function get() {
			if ( self::$value === null ) {
				self::fill();
			}

			return self::$value;
		}

	}
}