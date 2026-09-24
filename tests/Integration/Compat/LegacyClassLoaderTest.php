<?php
/**
 * The legacy class loader test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Compat;

use LV2\WordPress\RelatedPostsForWP\Compat\LegacyClassLoader;
use LV2\WordPress\RelatedPostsForWP\Tests\Integration\TestCase;

/**
 * The 2.x classes load from deprecated/, through a map the premium add-on can change.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Compat\LegacyClassLoader
 */
final class LegacyClassLoaderTest extends TestCase {

	public function test_the_loader_is_registered_when_the_plugin_boots(): void {
		$this->assertContains( [ LegacyClassLoader::class, 'load' ], spl_autoload_functions() );
	}

	public function test_a_class_loads_from_the_filtered_map(): void {
		add_filter(
			'rp4wp_legacy_class_map',
			static function ( $map ) {
				$map['RP4WP_Loader_Fixture'] = dirname( __DIR__, 2 ) . '/Fixtures/legacy/RP4WP_Loader_Fixture.php';

				return $map;
			}
		);

		$this->assertTrue( class_exists( 'RP4WP_Loader_Fixture' ) );
	}

	public function test_an_unknown_2x_class_is_not_loaded(): void {
		$this->assertFalse( class_exists( 'RP4WP_Does_Not_Exist' ) );
	}

	public function test_other_classes_are_left_to_the_other_loaders(): void {
		$read = false;
		add_filter(
			'rp4wp_legacy_class_map',
			static function ( $map ) use ( &$read ) {
				$read = true;

				return $map;
			}
		);

		LegacyClassLoader::load( 'Some_Other_Class' );

		$this->assertFalse( $read );
	}
}
