<?php
/**
 * The legacy API contract test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Contract;

use LV2\WordPress\RelatedPostsForWP\Tests\Integration\TestCase;
use LV2\WordPress\RelatedPostsForWP\Tests\Support\ApiSnapshot;
use LV2\WordPress\RelatedPostsForWP\Tests\Support\Golden;
use LV2\WordPress\RelatedPostsForWP\Tests\Support\HookScanner;

/**
 * The public PHP API of 2.x must stay available: every class, method, property, constant, function and hook.
 *
 * The snapshots in tests/Fixtures/legacy-api/ were recorded from the 2.3.1 code with `npm run test:integration:record`.
 *
 * @group golden
 * @coversNothing
 */
final class LegacyApiTest extends TestCase {

	/**
	 * Global functions that are part of the 2.x API.
	 */
	private const FUNCTIONS = [ 'RP4WP', 'rp4wp_children', 'rp4wp_load_plugin', 'rp4wp_activate_plugin' ];

	/**
	 * Legacy hooks the golden master scenarios do not fire yet, with the reason. Keep this list short.
	 */
	private const HOOKS_NOT_EXERCISED = [
		'rp4wp_manual_link_post_statuses' => 'Admin link screen list table; covered by the E2E suite.',
		'rp4wp_settings_sections'         => 'Settings are built on init, before a test can attach a recorder; covered by the E2E settings spec.',
	];

	public function test_legacy_api_is_still_compatible(): void {
		// The list table class extends WP_List_Table, which WordPress only loads in the admin.
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

		$file = $this->fixture( 'free-2.x.json' );

		if ( Golden::is_recording() ) {
			$this->write( $file, ApiSnapshot::build( $this->legacy_classes(), self::FUNCTIONS ) );
			$this->assertFileExists( $file );

			return;
		}

		$expected = $this->read( $file );
		$actual   = ApiSnapshot::build( array_keys( $expected['classes'] ), array_keys( $expected['functions'] ) );

		$this->assertSame( [], ApiSnapshot::compare( $expected, $actual ), 'The 2.x API is no longer fully available.' );
	}

	public function test_every_legacy_hook_is_exercised_by_the_golden_master(): void {
		$file = $this->fixture( 'free-2.x-hooks.json' );

		if ( Golden::is_recording() ) {
			$root = dirname( __DIR__, 3 );
			$this->write( $file, HookScanner::scan( [ $root . '/classes', $root . '/includes', $root . '/related-posts-for-wp.php' ] ) );
			$this->assertFileExists( $file );

			return;
		}

		$fired     = array_keys( $this->read( dirname( __DIR__, 2 ) . '/Fixtures/golden/free-2.x/hooks-fired.json' ) );
		$uncovered = [];

		foreach ( array_keys( $this->read( $file ) ) as $hook ) {
			if ( isset( self::HOOKS_NOT_EXERCISED[ $hook ] ) || in_array( $hook, $fired, true ) ) {
				continue;
			}

			// A dynamic hook counts as exercised when any recorded hook matches its pattern.
			if ( false !== strpos( $hook, '{' ) ) {
				$pattern = '/^' . preg_replace( '/\\\\\{.*?\\\\\}/', '.+', preg_quote( $hook, '/' ) ) . '$/';
				if ( preg_grep( $pattern, $fired ) ) {
					continue;
				}
			}

			$uncovered[] = $hook;
		}

		$this->assertSame( [], $uncovered, 'These 2.x hooks are not fired by any golden master scenario.' );
	}

	/**
	 * The legacy classes, taken from the Composer classmap.
	 *
	 * @return string[]
	 */
	private function legacy_classes(): array {
		$classmap = require dirname( __DIR__, 3 ) . '/vendor/composer/autoload_classmap.php';

		return array_keys(
			array_filter(
				$classmap,
				static function ( $path ) {
					return false !== strpos( $path, '/classes/' );
				}
			)
		);
	}

	/**
	 * The path of a legacy API fixture.
	 *
	 * @param string $name The file name.
	 *
	 * @return string
	 */
	private function fixture( string $name ): string {
		return dirname( __DIR__, 2 ) . '/Fixtures/legacy-api/' . $name;
	}

	/**
	 * Write a fixture as JSON.
	 *
	 * @param string $file The path.
	 * @param mixed  $data The data.
	 *
	 * @return void
	 */
	private function write( string $file, $data ): void {
		if ( ! is_dir( dirname( $file ) ) ) {
			mkdir( dirname( $file ), 0777, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Test fixture files.
		}

		file_put_contents( $file, wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture files.
	}

	/**
	 * Read a JSON fixture.
	 *
	 * @param string $file The path.
	 *
	 * @return array<string, mixed>
	 */
	private function read( string $file ): array {
		$this->assertFileExists( $file, 'Record the legacy API snapshots against 2.x with `npm run test:integration:record`.' );

		return json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local fixture file.
	}
}
