<?php
/**
 * The settings field schema class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Settings\Schema;

/**
 * Turns a field of the 2.x settings sections (as `rp4wp_settings_sections` filters them) into what the admin app and
 * the REST API need: its control, the JSON schema of its value, and the value as the app edits it.
 *
 * A field may say how the app shows it with `ui` (for example `number`), limits with `min` and `max`, and its JSON
 * schema with `schema`. Without them the 2.x `type` decides; a type the app does not know is a text field.
 */
class Field {

	/**
	 * The control for each 2.x field type.
	 */
	private const CONTROLS = [
		'checkbox'    => 'toggle',
		'text'        => 'text',
		'textarea'    => 'textarea',
		'select'      => 'select',
		'button_link' => 'link',
	];

	/**
	 * The control of a field in the admin app.
	 *
	 * @param array<string, mixed> $field The field.
	 *
	 * @return string
	 */
	public static function control( array $field ): string {
		if ( isset( $field['ui'] ) && is_string( $field['ui'] ) ) {
			return $field['ui'];
		}

		return self::CONTROLS[ $field['type'] ?? '' ] ?? 'text';
	}

	/**
	 * Whether the field holds a value that can be saved; a link does not.
	 *
	 * @param array<string, mixed> $field The field.
	 *
	 * @return bool
	 */
	public static function is_writable( array $field ): bool {
		return 'link' !== self::control( $field );
	}

	/**
	 * The field as the admin app gets it.
	 *
	 * @param array<string, mixed> $field    The field.
	 * @param bool                 $filtered Whether a filter decides its value.
	 *
	 * @return array<string, mixed>
	 */
	public static function describe( array $field, bool $filtered ): array {
		$control     = self::control( $field );
		$description = [
			'id'          => (string) $field['id'],
			'type'        => $control,
			'label'       => (string) ( $field['label'] ?? '' ),
			'description' => wp_kses_post( (string) ( $field['description'] ?? '' ) ),
			'default'     => self::from_storage( $field, $field['default'] ?? null ),
			'filtered'    => $filtered,
		];

		foreach ( [ 'min', 'max' ] as $limit ) {
			if ( isset( $field[ $limit ] ) ) {
				$description[ $limit ] = (int) $field[ $limit ];
			}
		}

		if ( isset( $field['options'] ) && is_array( $field['options'] ) ) {
			$description['options'] = [];

			foreach ( $field['options'] as $value => $label ) {
				$description['options'][] = [
					'value' => (string) $value,
					'label' => (string) $label,
				];
			}
		}

		if ( 'link' === $control ) {
			$description['href'] = esc_url_raw( (string) ( $field['href'] ?? '' ) );
		}

		return $description;
	}

	/**
	 * The JSON schema of the value of the field.
	 *
	 * @param array<string, mixed> $field The field.
	 *
	 * @return array<string, mixed>
	 */
	public static function json_schema( array $field ): array {
		if ( isset( $field['schema'] ) && is_array( $field['schema'] ) ) {
			return $field['schema'];
		}

		switch ( self::control( $field ) ) {
			case 'toggle':
				return [ 'type' => 'boolean' ];

			case 'number':
				$schema = [ 'type' => 'integer' ];

				foreach ( [
					'min' => 'minimum',
					'max' => 'maximum',
				] as $limit => $keyword ) {
					if ( isset( $field[ $limit ] ) ) {
						$schema[ $keyword ] = (int) $field[ $limit ];
					}
				}

				return $schema;

			case 'select':
				return [
					'type' => 'string',
					'enum' => array_map( 'strval', array_keys( (array) ( $field['options'] ?? [] ) ) ),
				];

			default:
				return [ 'type' => 'string' ];
		}
	}

	/**
	 * A stored value as the admin app edits it.
	 *
	 * @param array<string, mixed> $field The field.
	 * @param mixed                $value The stored value.
	 *
	 * @return mixed
	 */
	public static function from_storage( array $field, $value ) {
		switch ( self::control( $field ) ) {
			case 'toggle':
				return 1 === (int) $value;

			case 'number':
				return (int) $value;

			case 'text':
			case 'textarea':
			case 'code':
			case 'select':
				return is_scalar( $value ) ? (string) $value : '';

			default:
				return $value;
		}
	}

	/**
	 * A value from the admin app as it is stored. Checkboxes are stored as 1 and 0, like the 2.x settings screen did.
	 *
	 * @param array<string, mixed> $field The field.
	 * @param mixed                $value The value, valid for json_schema().
	 *
	 * @return mixed
	 */
	public static function to_storage( array $field, $value ) {
		switch ( self::control( $field ) ) {
			case 'toggle':
				return $value ? 1 : 0;

			case 'number':
				return (int) $value;

			default:
				return $value;
		}
	}
}
