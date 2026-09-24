<?php
/**
 * The settings fields class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Admin\Settings;

use LV2\WordPress\RelatedPostsForWP\Main;
use LV2\WordPress\RelatedPostsForWP\Module;
use LV2\WordPress\RelatedPostsForWP\Settings\Settings;

/**
 * Registers the setting sections and fields with the Settings API, and renders them.
 */
class Fields implements Module {

	/**
	 * Register the settings in the admin.
	 *
	 * @return void
	 */
	public static function setup(): void {
		add_action( 'admin_init', [ self::class, 'register' ] );
	}

	/**
	 * Register every section, its fields and the option that holds them.
	 *
	 * @return void
	 */
	public static function register(): void {
		$settings = Main::get()->settings();
		$sections = $settings->sections();

		if ( count( $sections ) < 1 ) {
			return;
		}

		foreach ( $sections as $section ) {
			add_settings_section( $section['id'], $section['label'], [ self::class, 'section_intro' ], Page::SLUG );

			foreach ( $section['fields'] as $field ) {
				add_settings_field( $field['id'], $field['label'], [ self::class, 'render_field' ], Page::SLUG, $section['id'], $field );
			}
		}

		register_setting( Page::SLUG, Settings::OPTION, [ 'sanitize_callback' => [ $settings, 'sanitize' ] ] );
	}

	/**
	 * The description below a section title.
	 *
	 * @param array<string, mixed> $section The section, as passed by do_settings_sections().
	 *
	 * @return void
	 */
	public static function section_intro( array $section ): void {
		$sections = Main::get()->settings()->sections();

		echo '<p>' . wp_kses_post( $sections[ $section['id'] ]['description'] ?? '' ) . '</p>' . PHP_EOL;
	}

	/**
	 * A field, its description, and a note when a filter overrides its value.
	 *
	 * @param array<string, mixed> $field The field, as registered.
	 *
	 * @return void
	 */
	public static function render_field( array $field ): void {
		$settings = Main::get()->settings();
		$name     = Settings::OPTION . '[' . $field['id'] . ']';

		switch ( $field['type'] ) {
			case 'checkbox':
				echo '<input type="checkbox" name="' . esc_attr( $name ) . '" id="' . esc_attr( $field['id'] ) . '" value="1" ' . checked( 1, $settings->get( $field['id'] ), false ) . ' />';
				break;
			case 'text':
				echo '<input type="text" name="' . esc_attr( $name ) . '" id="' . esc_attr( $field['id'] ) . '" value="' . esc_attr( (string) $settings->get( $field['id'] ) ) . '" class="rp4wp-input-text" />';
				break;
			case 'textarea':
				echo '<textarea name="' . esc_attr( $name ) . '" id="' . esc_attr( $field['id'] ) . '">' . esc_html( (string) $settings->get( $field['id'] ) ) . '</textarea>';
				break;
			case 'button_link':
				echo '<a href="' . esc_url( $field['href'] ) . '" class="button">' . esc_html( $field['default'] ) . '</a>';
				break;
		}

		if ( isset( $field['description'] ) && '' != $field['description'] ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- Filtered fields may use other types; 2.x compares loosely.
			echo '<label class="rp4wp-description" for="' . esc_attr( $field['id'] ) . '">' . wp_kses_post( $field['description'] ) . '</label>';
		}

		if ( has_filter( 'rp4wp_' . $field['id'] ) ) {
			echo '<small>This option is overwritten by a filter.</small>';
		}

		echo PHP_EOL;
	}
}
