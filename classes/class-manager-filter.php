<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class RP4WP_Manager_Filter {

	private $filter_dir;
	private static $filters;

	public function __construct( $filter_dir ) {
		$this->filter_dir = $filter_dir;
	}

	/**
	 * Load on specific filter instead of all filters.
	 * This method should be used when the load_filters() isn't run yet, for example in the (de)activation process.
	 *
	 * @param $file_name
	 */
	public function load_filter( $file_name ) {
		$class = RP4WP_Class_Manager::format_class_name( $file_name );
		if ( 'RP4WP_Filter' != $class ) {
			self::$filters[$class] = new $class;
		}
	}

	/**
	 * Load and set hooks
	 *
	 * @access public
	 * @static
	 * @return void
	 */
	public function load_filters() {

		foreach ( new DirectoryIterator( $this->filter_dir ) as $file ) {
			$file_name = $file->getFileName();

			if ( ! $file->isDir() && ( strpos( $file->getFileName(), '.' ) !== 0 ) ) {

				$class = RP4WP_Class_Manager::format_class_name( $file->getFileName() );
				if ( 'RP4WP_Filter' != $class ) {
					self::$filters[$class] = new $class;
				}

			}
		}

	}

	/**
	 * Return instance of created hook
	 *
	 * @param $class_name
	 *
	 * @return Hook
	 */
	public function get_hook_instance( $class_name ) {
		if ( isset( self::$hooks[$class_name] ) ) {
			return self::$hooks[$class_name];
		}

		return null;
	}

}