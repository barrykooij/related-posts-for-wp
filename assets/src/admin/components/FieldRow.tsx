import { RawHTML, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { Field, FieldValue } from '../api/types';
import { useRegistry } from '../registry/useRegistry';

interface Props {
	page: string;
	field: Field;
	value: FieldValue;
	onChange: ( value: FieldValue ) => void;
}

const warned = new Set< string >();

/**
 * One setting: its label and description on the left, its control on the right (stacked on small screens).
 *
 * @param props          The props.
 * @param props.page     The settings page.
 * @param props.field    The field.
 * @param props.value    Its value.
 * @param props.onChange Called with a new value.
 */
export function FieldRow( { page, field, value, onChange }: Props ) {
	const { fieldTypes } = useRegistry();
	const Control = fieldTypes[ field.type ] ?? fieldTypes.text;
	const id = `rp4wp-field-${ field.id }`;

	useEffect( () => {
		if ( ! fieldTypes[ field.type ] && ! warned.has( field.type ) ) {
			warned.add( field.type );
			// eslint-disable-next-line no-console -- Tells developers why their field looks like a text field.
			console.warn(
				`Related Posts: no field type "${ field.type }" is registered; "${ field.id }" is shown as a text field.`
			);
		}
	}, [ fieldTypes, field.type, field.id ] );

	return (
		<div
			className={ `rp4wp-field${ field.wide ? ' rp4wp-field--wide' : '' }` }
		>
			<div className="rp4wp-field__about">
				<span className="rp4wp-field__label">{ field.label }</span>
				{ field.description && (
					<RawHTML className="rp4wp-field__description">
						{ field.description }
					</RawHTML>
				) }
				{ field.filtered && (
					<p className="rp4wp-field__filtered">
						{ __(
							'This option is overwritten by a filter.',
							'related-posts-for-wp'
						) }
					</p>
				) }
			</div>
			<div className="rp4wp-field__control">
				{ Control && (
					<Control
						field={ field }
						page={ page }
						id={ id }
						value={ value }
						onChange={ onChange }
						disabled={ field.filtered }
					/>
				) }
			</div>
		</div>
	);
}
