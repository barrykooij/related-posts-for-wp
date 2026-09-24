<?php
/**
 * The deprecated plugin class test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Deprecated;

use LV2\WordPress\RelatedPostsForWP\Main;

/**
 * RP4WP and RP4WP().
 *
 * @covers \RP4WP
 * @covers ::RP4WP
 */
final class PluginTest extends ShimTestCase {

	public function set_up(): void {
		parent::set_up();

		// Start every test without a settings object, so creating one reports RP4WP_Settings each time.
		\RP4WP::instance()->settings = null;
	}

	public function test_the_function_returns_the_plugin_object(): void {
		$this->expect_deprecated( 'RP4WP' );

		$this->assertSame( \RP4WP::instance(), RP4WP() );
	}

	public function test_get_returns_the_plugin_object(): void {
		$this->expect_deprecated( 'RP4WP::get' );

		$this->assertSame( \RP4WP::instance(), \RP4WP::get() );
	}

	public function test_settings_is_a_settings_object_from_init_on(): void {
		$this->expect_deprecated( 'RP4WP_Settings' );

		$plugin = \RP4WP::instance();

		$this->assertTrue( isset( $plugin->settings ) );
		$this->assertInstanceOf( \RP4WP_Settings::class, $plugin->settings );
		$this->assertSame( $plugin->settings, $plugin->settings );
	}

	public function test_setup_settings_creates_a_new_settings_object(): void {
		$this->expect_deprecated( 'RP4WP::setup_settings', 'RP4WP_Settings' );

		\RP4WP::instance()->setup_settings();

		$this->assertInstanceOf( \RP4WP_Settings::class, \RP4WP::instance()->settings );
	}

	public function test_the_plugin_file_and_version_are_those_of_main(): void {
		$this->expect_deprecated( 'RP4WP::get_plugin_file' );

		$this->assertSame( Main::file(), \RP4WP::get_plugin_file() );
		$this->assertSame( Main::VERSION, \RP4WP::VERSION );
	}
}
