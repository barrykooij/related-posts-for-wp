<?php
/**
 * The shim test case class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Deprecated;

use LV2\WordPress\RelatedPostsForWP\Tests\Integration\TestCase;

/**
 * Base class for the tests of the 2.x classes in deprecated/: each call must report itself and still do what 2.x did.
 */
abstract class ShimTestCase extends TestCase {

	public function set_up(): void {
		parent::set_up();

		// Posts the tests create are not linked automatically, so each test sees only the links it makes.
		add_filter( 'rp4wp_automatic_linking', '__return_zero' );
	}

	/**
	 * Expect a deprecation notice for each of these 2.x classes, methods (Class::method) and functions.
	 *
	 * @param string ...$names The names.
	 *
	 * @return void
	 */
	protected function expect_deprecated( string ...$names ): void {
		foreach ( $names as $name ) {
			$this->setExpectedDeprecated( $name );
		}
	}

	/**
	 * What a callback prints.
	 *
	 * @param callable $callback The callback.
	 * @param mixed    ...$args  Its arguments.
	 *
	 * @return string
	 */
	protected function output( callable $callback, ...$args ): string {
		ob_start();
		call_user_func_array( $callback, $args );

		return (string) ob_get_clean();
	}
}
