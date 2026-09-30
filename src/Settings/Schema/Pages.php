<?php
/**
 * The settings pages class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Settings\Schema;

use LV2\WordPress\RelatedPostsForWP\Contracts\SettingsSchema;
use LV2\WordPress\RelatedPostsForWP\Settings\Settings;

/**
 * The settings pages of the free plugin: one page per settings section, all saved in the `rp4wp` option.
 */
class Pages implements SettingsSchema {

	/**
	 * The settings service.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Set up the pages.
	 *
	 * @param Settings $settings The settings service.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * The settings pages, in order.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function pages(): array {
		$pages = [];

		foreach ( $this->settings->sections() as $section ) {
			$fields = [];

			foreach ( $this->fields_of( $section ) as $field ) {
				$fields[] = Field::describe( $field, $this->is_filtered( $field ) );
			}

			$pages[] = [
				'id'          => (string) $section['id'],
				'title'       => (string) ( $section['label'] ?? '' ),
				'description' => '',
				'option'      => Settings::OPTION,
				'sections'    => [
					[
						'id'          => (string) $section['id'],
						'title'       => (string) ( $section['label'] ?? '' ),
						'description' => wp_kses_post( (string) ( $section['description'] ?? '' ) ),
						'fields'      => $fields,
					],
				],
			];
		}

		return $pages;
	}

	/**
	 * The current values of the fields of a page.
	 *
	 * @param string $page The page ID.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function values( string $page ) {
		$fields = $this->writable_fields( $page );

		if ( null === $fields ) {
			return self::unknown_page( $page );
		}

		$values = [];

		foreach ( $fields as $field ) {
			$values[ $field['id'] ] = Field::from_storage( $field, $this->settings->get( $field['id'] ) );
		}

		return $values;
	}

	/**
	 * Save the values of fields of a page. Fields that a filter decides are left alone.
	 *
	 * @param string               $page   The page ID.
	 * @param array<string, mixed> $values The values, by field ID.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function save( string $page, array $values ) {
		$fields = $this->writable_fields( $page );

		if ( null === $fields ) {
			return self::unknown_page( $page );
		}

		$schema = self::object_schema( $fields );
		$valid  = rest_validate_value_from_schema( $values, $schema, 'values' );

		if ( is_wp_error( $valid ) ) {
			$valid->add_data( [ 'status' => 400 ] );

			return $valid;
		}

		$values = (array) rest_sanitize_value_from_schema( $values, $schema, 'values' );

		// The whole option is saved, like the settings screen does, so its sanitize callback sees every field.
		$stored  = get_option( Settings::OPTION, [] );
		$options = array_merge( $this->settings->defaults(), is_array( $stored ) ? $stored : [] );

		foreach ( $fields as $field ) {
			if ( array_key_exists( $field['id'], $values ) && ! $this->is_filtered( $field ) ) {
				$options[ $field['id'] ] = Field::to_storage( $field, $values[ $field['id'] ] );
			}
		}

		update_option( Settings::OPTION, $options );

		return $this->values( $page );
	}

	/**
	 * The JSON schema of the values of some fields: an object with a property per field, and nothing else.
	 *
	 * @param array<int, array<string, mixed>> $fields The fields.
	 *
	 * @return array<string, mixed>
	 */
	public static function object_schema( array $fields ): array {
		$properties = [];

		foreach ( $fields as $field ) {
			$properties[ $field['id'] ] = Field::json_schema( $field );
		}

		return [
			'type'                 => 'object',
			'properties'           => $properties,
			'additionalProperties' => false,
		];
	}

	/**
	 * The error for a page that does not exist.
	 *
	 * @param string $page The page ID.
	 *
	 * @return \WP_Error
	 */
	public static function unknown_page( string $page ): \WP_Error {
		return new \WP_Error(
			'rp4wp_unknown_settings_page',
			/* translators: %s: a settings page ID */
			sprintf( __( 'There is no settings page "%s".', 'related-posts-for-wp' ), $page ),
			[ 'status' => 404 ]
		);
	}

	/**
	 * The fields of a page that hold a value, or null when the page does not exist.
	 *
	 * @param string $page The page ID.
	 *
	 * @return array<int, array<string, mixed>>|null
	 */
	private function writable_fields( string $page ): ?array {
		foreach ( $this->settings->sections() as $section ) {
			if ( (string) $section['id'] === $page ) {
				return array_values( array_filter( $this->fields_of( $section ), [ Field::class, 'is_writable' ] ) );
			}
		}

		return null;
	}

	/**
	 * The fields of a section that have an ID.
	 *
	 * @param array<string, mixed> $section The section.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function fields_of( array $section ): array {
		$fields = [];

		foreach ( (array) ( $section['fields'] ?? [] ) as $field ) {
			if ( is_array( $field ) && isset( $field['id'] ) && '' !== (string) $field['id'] ) {
				$fields[] = $field;
			}
		}

		return $fields;
	}

	/**
	 * Whether a filter decides the value of a field, as the 2.x settings screen told admins.
	 *
	 * @param array<string, mixed> $field The field.
	 *
	 * @return bool
	 */
	private function is_filtered( array $field ): bool {
		return false !== has_filter( 'rp4wp_' . $field['id'] );
	}
}
