<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! class_exists( 'RP4WP_Manager_Filter' ) ) {

	class RP4WP_Manager_Filter {

		private $filter_names;
		private static $filters;

		/**
		 * @param array $filter_names
		 */
		public function __construct( array $filter_names ) {
			$this->filter_names = $filter_names;
		}

		/**
		 * Load on specific filter instead of all filters.
		 * This method should be used when the load_filters() isn't run yet, for example in the (de)activation process.
		 *
		 * @param string $filter_name
		 */
		public function load_filter( $filter_name ) {
			$class_name = "RP4WP_Filter_" . str_replace( ' ', '_', ucwords( str_replace( '_', ' ', $filter_name ) ) );
			self::$filters[$class_name] = new $class_name;
		}

		/**
		 * Load and set hooks
		 *
		 * @access public
		 * @return void
		 */
		public function load_filters() {

			foreach( $this->filter_names as $filter_name ) {
				$this->load_filter( $filter_name );
			}

		}

		/**
		 * Return instance of created hook
		 *
		 * @param $class_name
		 *
		 * @return RP4WP_Filter
		 */
		public static function get_filter_object( $class_name ) {
			if ( isset( self::$filters[$class_name] ) ) {
				return self::$filters[$class_name];
			}

			return null;
		}

	}

}

