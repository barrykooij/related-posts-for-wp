<?php
/**
 * The admin app test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Admin;

use LV2\WordPress\RelatedPostsForWP\Admin\App\Assets;
use LV2\WordPress\RelatedPostsForWP\Main;
use LV2\WordPress\RelatedPostsForWP\Tests\Integration\TestCase;
use LV2\WordPress\RelatedPostsForWP\Tests\Support\AdminAppBuild;

/**
 * How the build of the admin app is enqueued.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Admin\App\Assets
 */
final class AppTest extends TestCase {

	use AdminAppBuild;

	/**
	 * The asset file of the build.
	 *
	 * @var string
	 */
	private string $asset_file;

	public function set_up(): void {
		parent::set_up();

		$this->asset_file = $this->stand_in_for_admin_app_build();

		wp_deregister_script( Assets::SCRIPT );
		wp_deregister_style( Assets::STYLE );
	}

	public function tear_down(): void {
		$this->remove_admin_app_build_stand_in();

		wp_deregister_script( Assets::SCRIPT );
		wp_deregister_style( Assets::STYLE );
		remove_all_filters( 'rp4wp_admin_data' );
		remove_all_actions( 'rp4wp_admin_enqueue_scripts' );

		parent::tear_down();
	}

	public function test_the_app_is_enqueued_with_the_dependencies_of_its_build(): void {
		$asset = require $this->asset_file;

		Assets::enqueue();

		$script = wp_scripts()->query( Assets::SCRIPT );
		$this->assertInstanceOf( \_WP_Dependency::class, $script );
		$this->assertTrue( wp_script_is( Assets::SCRIPT, 'enqueued' ) );
		// wp_set_script_translations() adds wp-i18n, which a real build lists already.
		$this->assertSame( [], array_diff( $asset['dependencies'], $script->deps ) );
		$this->assertContains( 'wp-i18n', $script->deps );
		$this->assertSame( $asset['version'], $script->ver );
		$this->assertStringEndsWith( '/assets/build/admin/index.js', (string) $script->src );
		$this->assertSame( 1, $script->extra['group'] ?? null, 'The app loads in the footer.' );

		$style = wp_styles()->query( Assets::STYLE );
		$this->assertInstanceOf( \_WP_Dependency::class, $style );
		$this->assertSame( [ 'wp-components' ], $style->deps );
	}

	public function test_the_app_gets_its_data_and_the_filters_can_add_to_it(): void {
		add_filter(
			'rp4wp_admin_data',
			static function ( array $data ): array {
				$data['edition'] = 'premium';

				return $data;
			}
		);

		Assets::enqueue();

		$before = implode( '', (array) wp_scripts()->get_data( Assets::SCRIPT, 'before' ) );
		$data   = [
			'version' => Main::VERSION,
			'edition' => 'premium',
		];
		$this->assertStringContainsString( 'window.rp4wpAdminData = ' . wp_json_encode( $data ) . ';', $before );
	}

	public function test_scripts_that_extend_the_app_are_enqueued_after_it(): void {
		$enqueued_before = null;
		add_action(
			'rp4wp_admin_enqueue_scripts',
			static function () use ( &$enqueued_before ): void {
				$enqueued_before = wp_script_is( Assets::SCRIPT, 'enqueued' );
			}
		);

		Assets::enqueue();

		$this->assertTrue( $enqueued_before );
	}

	public function test_without_a_build_admins_get_a_notice_and_nothing_is_enqueued(): void {
		$moved = $this->asset_file . '.moved';
		rename( $this->asset_file, $moved ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Test fixture.

		try {
			Assets::enqueue();
		} finally {
			rename( $moved, $this->asset_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Test fixture.
		}

		$this->assertFalse( wp_script_is( Assets::SCRIPT, 'registered' ) );
		$this->assertNotFalse( has_action( 'admin_notices', [ Assets::class, 'missing_build_notice' ] ) );

		remove_action( 'admin_notices', [ Assets::class, 'missing_build_notice' ] );
	}
}
