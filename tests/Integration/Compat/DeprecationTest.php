<?php
/**
 * The deprecation helper test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Compat;

use LV2\WordPress\RelatedPostsForWP\Compat\Deprecation;
use LV2\WordPress\RelatedPostsForWP\Tests\Integration\TestCase;

/**
 * Deprecations are reported through WordPress core, with the version and the replacement.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Compat\Deprecation
 */
final class DeprecationTest extends TestCase {

	/**
	 * The arguments of the core actions that report a deprecation.
	 *
	 * @var array<int, array<int, mixed>>
	 */
	private array $reported = [];

	public function set_up(): void {
		parent::set_up();

		$this->reported = [];
		add_action( 'deprecated_function_run', [ $this, 'record' ], 10, 3 );
		add_action( 'deprecated_class_run', [ $this, 'record' ], 10, 3 );
	}

	public function test_a_method_is_reported_with_its_replacement(): void {
		$this->setExpectedDeprecated( 'RP4WP_Example::method' );

		Deprecation::method( 'RP4WP_Example::method', 'Example::method()' );

		$this->assertSame( [ [ 'RP4WP_Example::method', 'Example::method()', '3.0.0' ] ], $this->reported );
	}

	public function test_a_function_is_reported_with_its_replacement(): void {
		$this->setExpectedDeprecated( 'rp4wp_example' );

		Deprecation::function_used( 'rp4wp_example', 'example()' );

		$this->assertSame( [ [ 'rp4wp_example', 'example()', '3.0.0' ] ], $this->reported );
	}

	public function test_a_class_is_reported_with_its_replacement(): void {
		$this->setExpectedDeprecated( 'RP4WP_Example' );

		Deprecation::class_used( 'RP4WP_Example', 'Example' );

		$this->assertSame( [ [ 'RP4WP_Example', 'Example', '3.0.0' ] ], $this->reported );
	}

	/**
	 * Record a reported deprecation.
	 *
	 * @param mixed ...$args The arguments of the core action.
	 *
	 * @return void
	 */
	public function record( ...$args ): void {
		$this->reported[] = $args;
	}
}
