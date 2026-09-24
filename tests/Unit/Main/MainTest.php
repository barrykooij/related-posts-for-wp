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

	public function test_the_legacy_bootstrap_runs_first(): void {
		Filters\expectApplied( 'rp4wp_modules' )->once()->andReturnFirstArg();

		$this->assertSame( \LV2\WordPress\RelatedPostsForWP\Legacy\Bootstrap::class, ( new Main() )->modules()[0] );
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
