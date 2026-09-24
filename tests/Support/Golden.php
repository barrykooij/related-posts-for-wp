<?php
/**
 * The golden file helper class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Support;

use PHPUnit\Framework\Assert;

/**
 * Compares output with committed golden files.
 *
 * Golden files are recorded once against the 2.x code and must keep matching through the whole rework. To record or
 * re-record them, run `npm run test:integration:record`, which sets RP4WP_UPDATE_GOLDEN=1. Only do that against 2.x
 * code, or when a deviation from 2.x is intended and listed in the modernization plan.
 */
final class Golden {

	/**
	 * Environment variable that switches from comparing to recording.
	 */
	public const UPDATE_ENV = 'RP4WP_UPDATE_GOLDEN';

	/**
	 * Assert that a string matches its golden file, or record it.
	 *
	 * @param string $set    The golden set, for example "free-2.x".
	 * @param string $name   The file name inside the set.
	 * @param string $actual The actual output.
	 *
	 * @return void
	 */
	public static function assert_matches( string $set, string $name, string $actual ): void {
		$file = self::path( $set, $name );

		if ( self::is_recording() ) {
			if ( ! is_dir( dirname( $file ) ) ) {
				mkdir( dirname( $file ), 0777, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Test fixture files.
			}

			file_put_contents( $file, $actual ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture files.
			Assert::assertFileExists( $file );

			return;
		}

		Assert::assertFileExists( $file, "Golden file {$set}/{$name} is missing. Record it against the 2.x code with `npm run test:integration:record`." );
		Assert::assertSame( file_get_contents( $file ), $actual, "Output differs from golden file {$set}/{$name}." ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local fixture file.
	}

	/**
	 * Assert that data matches its golden JSON file, or record it.
	 *
	 * @param string $set    The golden set.
	 * @param string $name   The file name inside the set, ending in .json.
	 * @param mixed  $actual The actual data.
	 *
	 * @return void
	 */
	public static function assert_json_matches( string $set, string $name, $actual ): void {
		self::assert_matches( $set, $name, wp_json_encode( $actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
	}

	/**
	 * Whether this run records golden files instead of comparing.
	 *
	 * @return bool
	 */
	public static function is_recording(): bool {
		return '1' === getenv( self::UPDATE_ENV );
	}

	/**
	 * The path of a golden file.
	 *
	 * @param string $set  The golden set.
	 * @param string $name The file name.
	 *
	 * @return string
	 */
	private static function path( string $set, string $name ): string {
		return dirname( __DIR__ ) . '/Fixtures/golden/' . $set . '/' . $name;
	}
}
