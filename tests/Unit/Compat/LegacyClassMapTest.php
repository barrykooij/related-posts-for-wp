<?php
/**
 * The legacy class map test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Unit\Compat;

use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * The map LegacyClassLoader reads must list exactly the classes in deprecated/, each in the file named after it.
 *
 * @coversNothing
 */
final class LegacyClassMapTest extends TestCase {

	public function test_the_map_lists_every_class_in_its_own_file(): void {
		$directory = dirname( __DIR__, 3 ) . '/deprecated';

		$expected = [];
		foreach ( glob( $directory . '/RP4WP*.php' ) as $file ) {
			$class_name              = basename( $file, '.php' );
			$expected[ $class_name ] = $file;

			$this->assertMatchesRegularExpression(
				'/^(?:abstract\s+)?class\s+' . preg_quote( $class_name, '/' ) . '\b/m',
				(string) file_get_contents( $file ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local source file.
				"{$file} does not declare {$class_name}."
			);
		}

		$map = require $directory . '/classmap.php';
		ksort( $expected );
		ksort( $map );

		$this->assertSame( $expected, $map, 'deprecated/classmap.php is out of date; run `composer legacy:classmap`.' );
	}
}
