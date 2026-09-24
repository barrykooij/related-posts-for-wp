<?php
/**
 * The deprecated RP4WP_Class_Manager class file.
 *
 * @package RelatedPostsForWP
 */

use LV2\WordPress\RelatedPostsForWP\Compat\Deprecation;

/**
 * A 2.x helper that the plugin no longer uses.
 *
 * @deprecated 3.0.0 No replacement.
 */
class RP4WP_Class_Manager {

	/**
	 * Uppercase the character after an underscore; the callback of format_class_name().
	 *
	 * @deprecated 3.0.0
	 *
	 * @param array $part The match.
	 *
	 * @return string
	 */
	public static function capitalize_part( $part ) {
		Deprecation::method( __METHOD__ );

		return '_' . strtoupper( substr( $part[0], 1, 1 ) );
	}

	/**
	 * The 2.x class name of a 2.x class file name.
	 *
	 * @deprecated 3.0.0
	 *
	 * @param string $file_name The file name.
	 *
	 * @return string
	 */
	public static function format_class_name( $file_name ) {
		Deprecation::method( __METHOD__ );

		return preg_replace_callback(
			'/(_[a-z])/',
			static function ( $part ) {
				return '_' . strtoupper( substr( $part[0], 1, 1 ) );
			},
			'RP4WP_' . str_ireplace( '-', '_', str_ireplace( array( 'class-', '.php' ), '', $file_name ) )
		);
	}
}
