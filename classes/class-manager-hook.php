<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! class_exists( 'RP4WP_Manager_Hook' ) ) {

	class RP4WP_Manager_Hook {

		private $action_names = array();
		private static $hooks;

		/**
		 * @param array $action_names
		 */
		public function __construct( array $action_names ) {
			$this->action_names = $action_names;
		}

		/**
		 * @param string $action_name
		 */
		public function load_hook( $action_name ) {
			$class_name = "RP4WP_Hook_" . str_replace( ' ', '_', ucwords( str_replace( '_', ' ', $action_name ) ) );
			self::$hooks[$class_name] = new $class_name;
		}

		/**
		 * Load and set hooks
		 *
		 * @access public
		 * @return void
		 */
		public function load_hooks() {

			foreach( $this->action_names as $action_name ) {
				$this->load_hook( $action_name );
			}

		}

		/**
		 * Return instance of created hook
		 *
		 * @param $class_name
		 *
		 * @return RP4WP_Hook
		 */
		public static function get_hook_object( $class_name ) {
			if ( isset( self::$hooks[$class_name] ) ) {
				return self::$hooks[$class_name];
			}

			return null;
		}

	}

}