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
		Functions\when( 'wp_get_custom_css' )->justReturn( '' );
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
	}

	public function test_the_default_css_is_modern_and_the_same_in_both_directions(): void {
		$css = ( new Settings() )->defaults()['css'];

		$this->assertStringContainsString( 'display:flex', $css );
		$this->assertStringContainsString( 'var(--rp4wp-gap,', $css );
		$this->assertStringContainsString( 'var(--rp4wp-image-width,', $css );
		$this->assertStringNotContainsString( 'float', $css );
		$this->assertStringNotContainsString( '!important', $css );

		Functions\when( 'is_rtl' )->justReturn( true );

		$this->assertSame( $css, ( new Settings() )->defaults()['css'] );
	}

	/**
	 * The 2.x defaults as sites stored them: as is, with the line endings browsers post, and with stray whitespace.
	 *
	 * @return array<string, array{string}>
	 */
	public static function stored_legacy_defaults(): array {
		return [
			'left to right'             => [ Settings::LEGACY_DEFAULT_CSS['ltr'] ],
			'right to left'             => [ Settings::LEGACY_DEFAULT_CSS['rtl'] ],
			'posted from a textarea'    => [ str_replace( "\n", "\r\n", Settings::LEGACY_DEFAULT_CSS['ltr'] ) ],
			'with trailing whitespace'  => [ "\n" . Settings::LEGACY_DEFAULT_CSS['rtl'] . "\n\n" ],
		];
	}

	/**
	 * @dataProvider stored_legacy_defaults
	 *
	 * @param string $stored The stored CSS.
	 */
	public function test_a_stored_2x_default_is_served_as_the_new_default( string $stored ): void {
		Functions\when( 'get_option' )->justReturn( [ 'css' => $stored ] );

		$settings = new Settings();

		$this->assertSame( $settings->defaults()['css'], $settings->get( 'css' ) );
	}

	public function test_a_stored_2x_default_stays_when_the_additional_css_styles_the_related_posts(): void {
		Functions\when( 'get_option' )->justReturn( [ 'css' => Settings::LEGACY_DEFAULT_CSS['ltr'] ] );
		Functions\when( 'wp_get_custom_css' )->justReturn( '.RP4WP-related-posts h3 { color: red; }' );

		$this->assertSame( Settings::LEGACY_DEFAULT_CSS['ltr'], ( new Settings() )->get( 'css' ) );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function stored_custom_css(): array {
		return [
			'emptied, which turns the CSS off' => [ '' ],
			'a changed 2.x default'            => [ str_replace( '35%', '40%', Settings::LEGACY_DEFAULT_CSS['ltr'] ) ],
			'the 2.x default with more rules'  => [ Settings::LEGACY_DEFAULT_CSS['ltr'] . "\n.rp4wp-related-posts h3{color:red;}" ],
			'something else'                   => [ '.rp4wp-related-posts{display:none}' ],
		];
	}

	/**
	 * @dataProvider stored_custom_css
	 *
	 * @param string $stored The stored CSS.
	 */
	public function test_customised_css_is_served_as_stored( string $stored ): void {
		Functions\when( 'get_option' )->justReturn( [ 'css' => $stored ] );

		$this->assertSame( $stored, ( new Settings() )->get( 'css' ) );
	}

	public function test_the_filters_see_the_resolved_css(): void {
		Functions\when( 'get_option' )->justReturn( [ 'css' => Settings::LEGACY_DEFAULT_CSS['ltr'] ] );
		$settings = new Settings();
		$default  = $settings->defaults()['css'];

		Filters\expectApplied( 'rp4wp_options' )->once()->andReturnUsing(
			function ( $options ) use ( $default ) {
				$this->assertSame( $default, $options['css'] );

				return $options;
			}
		);

		$settings->get( 'css' );
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

	public function test_sanitize_leaves_out_numbers_that_were_not_saved(): void {
		$clean = ( new Settings() )->sanitize( [ 'heading_text' => 'Only this' ] );

		$this->assertArrayNotHasKey( 'automatic_linking_post_amount', $clean );
		$this->assertArrayNotHasKey( 'excerpt_length', $clean );
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
