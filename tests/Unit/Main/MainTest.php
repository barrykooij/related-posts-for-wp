<?php
/**
 * The main class test file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Unit\Main;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use LV2\WordPress\RelatedPostsForWP\Main;
use LV2\WordPress\RelatedPostsForWP\Module;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Boot order: services hook, modules in order, loaded hook.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Main
 */
final class MainTest extends TestCase {

	protected function set_up(): void {
		parent::set_up();

		RecordingModule::$calls = [];
		unset( $_SERVER['HTTP_HOST'] );

		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'is_network_admin' )->justReturn( false );
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'wp_unslash' )->returnArg();
	}

	protected function tear_down(): void {
		unset( $_SERVER['HTTP_HOST'] );

		parent::tear_down();
	}

	public function test_does_not_run_inside_wordpress_playground(): void {
		$_SERVER['HTTP_HOST'] = 'playground.wordpress.net';
		Filters\expectApplied( 'rp4wp_modules' )->never();
		Actions\expectDone( 'rp4wp_loaded' )->never();
		Actions\expectAdded( 'admin_notices' )->once();

		( new Main() )->setup();

		$this->assertSame( [], RecordingModule::$calls );
	}

	public function test_only_shows_a_notice_in_a_multisite_admin(): void {
		Functions\when( 'is_multisite' )->justReturn( true );
		Functions\when( 'is_admin' )->justReturn( true );
		Filters\expectApplied( 'rp4wp_modules' )->never();
		Actions\expectDone( 'rp4wp_loaded' )->never();
		Actions\expectAdded( 'admin_notices' )->once();
		Actions\expectAdded( 'network_admin_notices' )->once();
		Actions\expectAdded( 'init' )->once(); // The text domain still loads, like 2.x.

		$main = new Main();
		$main->setup();

		$this->assertFalse( $main->should_run() );
	}

	public function test_runs_in_a_multisite_admin_when_the_premium_add_on_supports_it(): void {
		Functions\when( 'is_multisite' )->justReturn( true );
		Functions\when( 'is_admin' )->justReturn( true );
		Filters\expectApplied( 'rp4wp_supports_multisite' )->andReturn( true );
		Filters\expectApplied( 'rp4wp_modules' )->once()->andReturn( [ FirstModule::class ] );

		$main = new Main();
		$main->setup();

		$this->assertTrue( $main->should_run() );
		$this->assertSame( [ FirstModule::class ], RecordingModule::$calls );
	}

	public function test_runs_on_the_front_end_of_a_multisite(): void {
		Functions\when( 'is_multisite' )->justReturn( true );
		Filters\expectApplied( 'rp4wp_modules' )->once()->andReturn( [ FirstModule::class ] );

		( new Main() )->setup();

		$this->assertSame( [ FirstModule::class ], RecordingModule::$calls );
	}

	public function test_setup_runs_services_hook_then_modules_then_loaded_hook(): void {
		$main  = new Main();
		$calls = [];

		Actions\expectDone( 'rp4wp_register_services' )->once()->with( $main )->whenHappen(
			static function () use ( &$calls ) {
				$calls[] = 'rp4wp_register_services';
			}
		);
		Filters\expectApplied( 'rp4wp_modules' )->once()->andReturn( [ FirstModule::class, SecondModule::class ] );
		Actions\expectDone( 'rp4wp_loaded' )->once()->with( $main )->whenHappen(
			static function () use ( &$calls ) {
				$calls[] = 'rp4wp_loaded';
			}
		);

		$main->setup();

		$this->assertSame( [ 'rp4wp_register_services', 'rp4wp_loaded' ], $calls );
		$this->assertSame( [ FirstModule::class, SecondModule::class ], RecordingModule::$calls );
		$this->assertTrue( $main->is_set_up() );
	}

	public function test_setup_runs_only_once(): void {
		$main = new Main();
		Filters\expectApplied( 'rp4wp_modules' )->once()->andReturn( [ FirstModule::class ] );

		$main->setup();
		$main->setup();

		$this->assertSame( [ FirstModule::class ], RecordingModule::$calls );
	}

	public function test_setup_skips_classes_that_are_not_modules(): void {
		$main = new Main();
		Filters\expectApplied( 'rp4wp_modules' )->once()->andReturn( [ \stdClass::class, FirstModule::class, 42 ] );
		Functions\expect( '_doing_it_wrong' )->twice();
		$this->stubEscapeFunctions();

		$main->setup();

		$this->assertSame( [ FirstModule::class ], RecordingModule::$calls );
	}

	public function test_the_wizard_redirect_runs_first(): void {
		Filters\expectApplied( 'rp4wp_modules' )->once()->andReturnFirstArg();

		// It may redirect and stop the request, so nothing else should be set up before it.
		$this->assertSame( \LV2\WordPress\RelatedPostsForWP\Admin\Wizard\Redirect::class, ( new Main() )->modules()[0] );
	}

	public function test_get_returns_one_shared_instance(): void {
		$this->assertSame( Main::get(), Main::get() );
	}
}

/**
 * Records the order modules are set up in.
 */
abstract class RecordingModule implements Module {

	/**
	 * Module classes in the order setup() was called.
	 *
	 * @var string[]
	 */
	public static array $calls = [];

	public static function setup(): void {
		self::$calls[] = static::class;
	}
}

/**
 * A first test module.
 */
final class FirstModule extends RecordingModule {
}

/**
 * A second test module.
 */
final class SecondModule extends RecordingModule {
}
