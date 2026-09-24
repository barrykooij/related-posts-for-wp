<?php
/**
 * The legacy class loader class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Compat;

/**
 * Loads a 2.x class from deprecated/ when code first uses it.
 *
 * The 2.x classes are not in the Composer autoloader, so the map of class to file can be filtered: the premium add-on
 * loads its own variant of a 2.x class whose API differed between the editions.
 */
class LegacyClassLoader {

	/**
	 * Register the loader.
	 *
	 * @return void
	 */
	public static function register(): void {
		spl_autoload_register( [ self::class, 'load' ] );
	}

	/**
	 * Load a 2.x class, if it is one.
	 *
	 * @param string $class_name The class.
	 *
	 * @return void
	 */
	public static function load( string $class_name ): void {
		// Every 2.x class starts with RP4WP; leave all other classes to the other loaders quickly.
		if ( 0 !== strncmp( $class_name, 'RP4WP', 5 ) ) {
			return;
		}

		$map = self::map();

		if ( isset( $map[ $class_name ] ) && is_readable( $map[ $class_name ] ) ) {
			require $map[ $class_name ];
		}
	}

	/**
	 * The file of every 2.x class, filterable through `rp4wp_legacy_class_map`.
	 *
	 * @return array<string, string> Class name => absolute file path.
	 */
	public static function map(): array {
		$map = require dirname( __DIR__, 2 ) . '/deprecated/classmap.php';

		/**
		 * Filters the files the 2.x classes load from.
		 *
		 * @since 3.0.0
		 *
		 * @param array<string, string> $map Class name => absolute file path.
		 */
		return (array) apply_filters( 'rp4wp_legacy_class_map', $map );
	}
}
