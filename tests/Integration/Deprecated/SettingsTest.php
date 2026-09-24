<?php
/**
 * The deprecated settings class test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Deprecated;

use LV2\WordPress\RelatedPostsForWP\Admin\Settings\Fields;
use LV2\WordPress\RelatedPostsForWP\Main;

/**
 * RP4WP_Settings.
 *
 * @covers \RP4WP_Settings
 */
final class SettingsTest extends ShimTestCase {

	/**
	 * The 2.x settings object.
	 *
	 * @var \RP4WP_Settings
	 */
	private \RP4WP_Settings $settings;

	public function set_up(): void {
		parent::set_up();

		$this->expect_deprecated( 'RP4WP_Settings' );
		$this->settings = new \RP4WP_Settings();
	}

	public function test_get_option_reads_one_setting(): void {
		$this->expect_deprecated( 'RP4WP_Settings::get_option' );

		$this->assertSame( 'Related Posts', $this->settings->get_option( 'heading_text' ) );
	}

	public function test_get_options_reads_all_settings(): void {
		$this->expect_deprecated( 'RP4WP_Settings::get_options' );

		$this->assertSame( Main::get()->settings()->get_options(), $this->settings->get_options() );
	}

	public function test_sanitize_option_sanitizes_like_the_settings_page(): void {
		$this->expect_deprecated( 'RP4WP_Settings::sanitize_option' );

		$this->assertSame(
			[
				'automatic_linking_post_amount' => 4,
				'excerpt_length'                => 10,
				'automatic_linking'             => 0,
				'display_image'                 => 0,
			],
			$this->settings->sanitize_option(
				[
					'automatic_linking_post_amount' => '4',
					'excerpt_length'                => '10',
				]
			)
		);
	}

	public function test_setup_registers_the_settings(): void {
		global $wp_settings_sections, $wp_settings_fields, $wp_registered_settings, $new_allowed_options;

		require_once ABSPATH . 'wp-admin/includes/template.php';
		$backup = [ $wp_settings_sections, $wp_settings_fields, $wp_registered_settings, $new_allowed_options ];

		$this->expect_deprecated( 'RP4WP_Settings::setup' );
		$this->settings->setup();
		$registered = isset( $wp_settings_sections['rp4wp']['general'], $wp_registered_settings['rp4wp'] );

		[ $wp_settings_sections, $wp_settings_fields, $wp_registered_settings, $new_allowed_options ] = $backup;

		$this->assertTrue( $registered );
	}

	public function test_sections_and_fields_render_like_the_settings_page(): void {
		$this->expect_deprecated( 'RP4WP_Settings::section_intro', 'RP4WP_Settings::do_field' );

		$section = [ 'id' => 'general' ];
		$field   = Main::get()->settings()->sections()['general']['fields']['heading_text'];

		$this->assertSame( $this->output( [ Fields::class, 'section_intro' ], $section ), $this->output( [ $this->settings, 'section_intro' ], $section ) );
		$this->assertSame( $this->output( [ Fields::class, 'render_field' ], $field ), $this->output( [ $this->settings, 'do_field' ], $field ) );
	}

	public function test_the_constants_keep_their_values(): void {
		$this->assertSame( 'rp4wp_', \RP4WP_Settings::PREFIX );
		$this->assertSame( 'rp4wp', \RP4WP_Settings::PAGE );
	}
}
