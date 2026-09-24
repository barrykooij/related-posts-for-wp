<?php
/**
 * The plugin loader test file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Unit\Bootstrap;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use LV2\WordPress\RelatedPostsForWP\Main;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * The main plugin file: requirements guard, dormant mode next to premium 2.x, and a normal boot.
 *
 * Each test runs in its own process, because the main file defines functions and constants.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @coversNothing
 */
final class LoaderTest extends TestCase {

	protected function set_up(): void {
		parent::set_up();

		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
	}

	public function test_registers_the_loader_on_plugins_loaded_at_priority_20(): void {
		Actions\expectAdded( 'plugins_loaded' )->once()->with( 'rp4wp_load_plugin', 20 );

		$this->include_main_file();
	}

	public function test_does_not_boot_on_an_old_wordpress_version(): void {
		Functions\when( 'get_bloginfo' )->justReturn( '6.5.5' );
		Actions\expectAdded( 'admin_notices' )->once()->with( 'rp4wp_requirements_notice' );

		$this->include_main_file();
		rp4wp_load_plugin();

		$this->assertFalse( defined( 'RP4WP_PLUGIN_FILE' ) );
		$this->assertFalse( Main::get()->is_set_up() );
	}

	public function test_stays_dormant_next_to_premium_2x(): void {
		Functions\when( 'get_bloginfo' )->justReturn( '7.1' );
		define( 'RP4WP_PLUGIN_FILE', '/wp-content/plugins/related-posts-for-wp-premium/related-posts-for-wp-premium.php' );
		Functions\expect( 'deactivate_plugins' )->never();

		$this->include_main_file();
		rp4wp_load_plugin();

		$this->assertFalse( Main::get()->is_set_up() );
	}

	public function test_boots_when_the_requirements_are_met(): void {
		Functions\when( 'get_bloginfo' )->justReturn( '7.1' );
		Filters\expectApplied( 'rp4wp_modules' )->once()->andReturn( [] );

		$this->include_main_file();
		rp4wp_load_plugin();

		$this->assertSame( dirname( __DIR__, 3 ) . '/related-posts-for-wp.php', RP4WP_PLUGIN_FILE );
		$this->assertTrue( Main::get()->is_set_up() );
		$this->assertTrue( function_exists( 'RP4WP' ) );
	}

	/**
	 * Load the main plugin file.
	 *
	 * @return void
	 */
	private function include_main_file(): void {
		require dirname( __DIR__, 3 ) . '/related-posts-for-wp.php';
	}
}
