<?php
/**
 * The deprecated shims test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Unit\Compat;

use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Every 2.x class in deprecated/ is marked deprecated, and every public or protected method reports its use before it
 * does anything else. The integration tests in Deprecated/ check what the methods do.
 *
 * @coversNothing
 */
final class DeprecatedShimsTest extends TestCase {

	/**
	 * Methods that do not report themselves, with the reason.
	 */
	private const SILENT = [
		'RP4WP::instance'                      => 'RP4WP() reports itself and then uses it.',
		'RP4WP::__get'                         => 'Reading RP4WP()->settings; RP4WP_Settings reports itself when it is created.',
		'RP4WP::__set'                         => 'Replacing RP4WP()->settings.',
		'RP4WP::__isset'                       => 'Checking RP4WP()->settings.',
		'RP4WP_Hook::attach_legacy_hook'       => 'Called by the constructor and register(), which report themselves.',
		'RP4WP_Filter::attach_legacy_hook'     => 'Called by the constructor and register(), which report themselves.',
	];

	/**
	 * The 2.x class files.
	 *
	 * @return array<string, array{string}>
	 */
	public static function shims(): array {
		$data = [];
		foreach ( glob( dirname( __DIR__, 3 ) . '/deprecated/RP4WP*.php' ) as $file ) {
			$data[ basename( $file, '.php' ) ] = [ $file ];
		}

		return $data;
	}

	/**
	 * The class is marked deprecated, and each method reports itself first.
	 *
	 * @dataProvider shims
	 *
	 * @param string $file The class file.
	 */
	public function test_every_method_reports_its_use( string $file ): void {
		$class_name = basename( $file, '.php' );
		$source     = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local source file.

		$this->assertMatchesRegularExpression( '/\* @deprecated \d+\.\d+\.\d+ .+\n(?:\s\*.*\n)*\s\*\/\n(?:abstract\s+)?class\s+' . $class_name . '\b/', $source, "{$class_name} is not marked @deprecated." );

		// The first statement of every public or protected method; parameters may contain one level of parentheses, and
		// a comment may follow the opening brace.
		preg_match_all( '/(?:public|protected)\s+(?:static\s+)?function\s+(\w+)\s*\((?:[^()]|\([^()]*\))*\)\s*\{(?:[ \t]*\/\/[^\n]*)?\s*([^;{}]*)/', $source, $methods, PREG_SET_ORDER );

		foreach ( $methods as [ , $method, $first ] ) {
			if ( isset( self::SILENT[ "{$class_name}::{$method}" ] ) ) {
				continue;
			}

			$expected = '__construct' === $method ? 'Deprecation::class_used( __CLASS__' : 'Deprecation::method( __METHOD__';
			$this->assertStringStartsWith( $expected, trim( $first ), "{$class_name}::{$method}() does not report its use first." );
		}
	}

	public function test_the_deprecated_functions_report_their_use(): void {
		$source = (string) file_get_contents( dirname( __DIR__, 3 ) . '/deprecated/functions.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local source file.

		preg_match_all( '/^function\s+(\w+)\s*\([^)]*\)\s*\{[^\n]*\n\s*([^;]*)/m', $source, $functions, PREG_SET_ORDER );

		$this->assertNotEmpty( $functions );
		foreach ( $functions as [ , $function, $first ] ) {
			$this->assertStringStartsWith( 'Deprecation::function_used( __FUNCTION__', trim( $first ), "{$function}() does not report its use first." );
		}
	}
}
