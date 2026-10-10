<?php
/**
 * Stands in for the WP-CLI utilities the plugin's commands use, for static analysis and tests.
 *
 * @package RelatedPostsForWP
 */

namespace WP_CLI\Utils;

if ( ! function_exists( __NAMESPACE__ . '\\format_items' ) ) {
	/**
	 * Print items as a table, or in another format.
	 *
	 * @param string                           $format The format.
	 * @param array<int, array<string, mixed>> $items  The items.
	 * @param string[]                         $fields The fields to print.
	 *
	 * @return void
	 */
	function format_items( $format, $items, $fields ) {
		foreach ( $items as $item ) {
			\WP_CLI::line( $format . ': ' . implode( ' | ', array_intersect_key( array_map( 'strval', $item ), array_flip( $fields ) ) ) );
		}
	}
}
