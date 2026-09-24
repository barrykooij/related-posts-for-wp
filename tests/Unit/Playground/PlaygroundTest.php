<?php
/**
 * The playground detection test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Unit\Playground;

use Brain\Monkey\Actions;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * WordPress Playground detection.
 *
 * @covers \RP4WP_Playground
 */
final class PlaygroundTest extends TestCase {

	/**
	 * The HTTP host before the test.
	 *
	 * @var string|null
	 */
	private ?string $host = null;

	protected function set_up(): void {
		parent::set_up();

		$this->host = $_SERVER['HTTP_HOST'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Saved as is, to restore it after the test.
	}

	protected function tear_down(): void {
		if ( null === $this->host ) {
			unset( $_SERVER['HTTP_HOST'] );
		} else {
			$_SERVER['HTTP_HOST'] = $this->host;
		}

		parent::tear_down();
	}

	public function test_detects_playground_host(): void {
		$_SERVER['HTTP_HOST'] = 'playground.wordpress.net';

		$this->assertTrue( \RP4WP_Playground::is_playground() );
	}

	public function test_ignores_other_hosts(): void {
		$_SERVER['HTTP_HOST'] = 'example.org';

		$this->assertFalse( \RP4WP_Playground::is_playground() );
	}

	public function test_ignores_missing_host(): void {
		unset( $_SERVER['HTTP_HOST'] );

		$this->assertFalse( \RP4WP_Playground::is_playground() );
	}

	public function test_admin_notice_is_hooked(): void {
		Actions\expectAdded( 'admin_notices' )->once();

		\RP4WP_Playground::add_admin_notice();
	}
}
