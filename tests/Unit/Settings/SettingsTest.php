<?php
/**
 * The settings test file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Unit\Settings;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use LV2\WordPress\RelatedPostsForWP\Settings\Settings;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Defaults, stored values, filters and sanitising of the settings.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Settings\Settings
 */
final class SettingsTest extends TestCase {

	protected function set_up(): void {
		parent::set_up();

		$this->stubTranslationFunctions();
		Functions\when( 'admin_url' )->returnArg();
		Functions\when( 'wp_create_nonce' )->justReturn( 'nonce' );
		Functions\when( 'is_rtl' )->justReturn( false );
		Functions\when( 'wp_parse_args' )->alias(
			static function ( $args, $defaults ) {
				return array_merge( $defaults, (array) $args );
			}
		);
	}

	public function test_defaults_match_2x(): void {
		$defaults = ( new Settings() )->defaults();

		$this->assertSame( 1, $defaults['automatic_linking'] );
		$this->assertSame( '3', $defaults['automatic_linking_post_amount'] );
		$this->assertSame( 'Related Posts', $defaults['heading_text'] );
		$this->assertSame( '15', $defaults['excerpt_length'] );
		$this->assertSame( 0, $defaults['display_image'] );
		$this->assertSame( 0, $defaults['clean_on_uninstall'] );
		$this->assertSame( 0, $defaults['show_love'] );
		$this->assertStringContainsString( 'float:left', $defaults['css'] );
	}

	public function test_right_to_left_sites_get_mirrored_css(): void {
		Functions\when( 'is_rtl' )->justReturn( true );

		$this->assertStringContainsString( 'float:right', ( new Settings() )->defaults()['css'] );
	}

	public function test_stored_values_override_defaults_and_pass_the_filters(): void {
		Functions\when( 'get_option' )->justReturn( [ 'heading_text' => 'Read more' ] );
		Filters\expectApplied( 'rp4wp_options' )->once()->andReturnFirstArg();
		Filters\expectApplied( 'rp4wp_heading_text' )->once()->with( 'Read more' )->andReturn( 'Filtered' );

		$this->assertSame( 'Filtered', ( new Settings() )->get( 'heading_text' ) );
	}

	public function test_unknown_settings_are_false(): void {
		Functions\when( 'get_option' )->justReturn( [] );

		$this->assertFalse( ( new Settings() )->get( 'does_not_exist' ) );
	}

	public function test_sections_can_be_filtered_and_their_defaults_count(): void {
		Filters\expectApplied( 'rp4wp_settings_sections' )->once()->andReturnUsing(
			static function ( $sections ) {
				$sections['general']['fields']['extra'] = [
					'id'      => 'extra',
					'default' => 'yes',
				];

				return $sections;
			}
		);

		$this->assertSame( 'yes', ( new Settings() )->defaults()['extra'] );
	}

	public function test_sanitize_turns_unchecked_boxes_off_and_numbers_into_integers(): void {
		$clean = ( new Settings() )->sanitize(
			[
				'automatic_linking_post_amount' => '5 posts',
				'excerpt_length'                => '20',
				'heading_text'                  => 'Kept as is',
			]
		);

		$this->assertSame( 0, $clean['automatic_linking'] );
		$this->assertSame( 0, $clean['display_image'] );
		$this->assertSame( 5, $clean['automatic_linking_post_amount'] );
		$this->assertSame( 20, $clean['excerpt_length'] );
		$this->assertSame( 'Kept as is', $clean['heading_text'] );
	}
}
