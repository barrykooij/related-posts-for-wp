<?php
/**
 * The deprecation helper class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Compat;

/**
 * Reports use of the 2.x API through WordPress core, so the notices show with WP_DEBUG and in tools like Query Monitor.
 */
class Deprecation {

	/**
	 * The version that deprecated the 2.x API.
	 */
	public const SINCE = '3.0.0';

	/**
	 * Report a call to a deprecated method.
	 *
	 * @param string $method      The method, as __METHOD__ gives it.
	 * @param string $replacement What to use instead; empty when there is nothing to use instead.
	 *
	 * @return void
	 */
	public static function method( string $method, string $replacement = '' ): void {
		_deprecated_function( esc_html( $method ), esc_html( self::SINCE ), esc_html( $replacement ) );
	}

	/**
	 * Report a call to a deprecated function.
	 *
	 * @param string $function_name The function, as __FUNCTION__ gives it.
	 * @param string $replacement   What to use instead; empty when there is nothing to use instead.
	 *
	 * @return void
	 */
	public static function function_used( string $function_name, string $replacement = '' ): void {
		_deprecated_function( esc_html( $function_name ), esc_html( self::SINCE ), esc_html( $replacement ) );
	}

	/**
	 * Report that a deprecated class was instantiated or extended.
	 *
	 * @param string $class_name  The class, as __CLASS__ gives it.
	 * @param string $replacement What to use instead; empty when there is nothing to use instead.
	 *
	 * @return void
	 */
	public static function class_used( string $class_name, string $replacement = '' ): void {
		_deprecated_class( esc_html( $class_name ), esc_html( self::SINCE ), esc_html( $replacement ) );
	}
}
