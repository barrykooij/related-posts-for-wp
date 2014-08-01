<?php

if ( !defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class SRP_Autoloader {

	private $path;

	/**
	 * The Constructor, sets the path of the class directory.
	 *
	 * @param $path
	 */
	public function __construct( $path ) {
		$this->path = $path;
	}


	/**
	 * Autoloader load method. Load the class.
	 *
	 * @param $class_name
	 */
	public function load( $class_name ) {

		// Only autoload WooCommerce Sales Report Email classes
		if ( 0 === strpos( $class_name, 'SRP_' ) ) {

			// String to lower
			$class_name = strtolower( $class_name );

			// Format file name
			$file_name = 'class-' . str_ireplace( '_', '-', str_ireplace( 'SRP_', '', $class_name ) ) . '.php';

			// Setup the file path
			$file_path = $this->path;

			// Check if we need to extend the class path
			if ( strpos( $class_name, 'srp_hook' ) === 0 ) {
				$file_path .= 'hooks/';
			} elseif ( strpos( $class_name, 'srp_filter' ) === 0 ) {
				$file_path .= 'filters/';
			} elseif ( strpos( $class_name, 'srp_meta_box' ) === 0 ) {
				$file_path .= 'meta-boxes/';
			}

			// Append file name to clas path
			$file_path .= $file_name;

			// Check & load file
			if ( file_exists( $file_path ) ) {
				require_once( $file_path );
			}

		}

	}

}