<?php
/**
 * The exit guard class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Support;

use PHPUnit\Runner\AfterLastTestHook;
use PHPUnit\Runner\BeforeTestHook;

/**
 * Fails the run when code under test calls exit() or die().
 *
 * The legacy AJAX and redirect handlers end with a bare exit(). If a test reaches one, PHP stops in the middle of the
 * suite with exit code 0 and the run looks green. This extension remembers which test is running and whether the
 * suite finished; the shutdown check turns an early stop into a failure that names the test.
 */
final class ExitGuard implements BeforeTestHook, AfterLastTestHook {

	/**
	 * Whether PHPUnit got to the end of the suite.
	 *
	 * @var bool
	 */
	private static bool $finished = false;

	/**
	 * The test that was running last.
	 *
	 * @var string
	 */
	private static string $current_test = '';

	/**
	 * Register the shutdown check. Call this once from the bootstrap.
	 *
	 * @return void
	 */
	public static function register(): void {
		register_shutdown_function( [ self::class, 'check' ] );
	}

	/**
	 * Remember the test that is about to run.
	 *
	 * @param string $test The test name.
	 *
	 * @return void
	 */
	public function executeBeforeTest( string $test ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- PHPUnit interface.
		self::$current_test = $test;
	}

	/**
	 * Mark the suite as finished.
	 *
	 * @return void
	 */
	public function executeAfterLastTest(): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- PHPUnit interface.
		self::$finished = true;
	}

	/**
	 * Shutdown check: fail loudly when the suite did not finish.
	 *
	 * @return void
	 */
	public static function check(): void {
		if ( self::$finished || '' === self::$current_test ) {
			return;
		}

		fwrite( STDERR, PHP_EOL . 'PHPUnit stopped early: code under test called exit() or die() during ' . self::$current_test . PHP_EOL ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite,WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
		exit( 1 ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_exit -- Sets the process exit code.
	}
}
