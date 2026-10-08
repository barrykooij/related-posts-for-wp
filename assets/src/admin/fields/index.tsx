/**
 * The field types of the free plugin's settings. The premium plugin registers its own (select, image, ...) with
 * registerFieldType().
 */
import {
	Button,
	TextControl,
	TextareaControl,
	ToggleControl,
	VisuallyHidden,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { FieldProps } from '../registry/registry';
import { registry } from '../registry/registry';

// No id: ToggleControl would put it on the input but not on its label.
function Toggle( { field, value, onChange, disabled }: FieldProps< boolean > ) {
	return (
		<ToggleControl
			__nextHasNoMarginBottom
			// The row shows the label; the control keeps it for screen readers.
			label={ <VisuallyHidden>{ field.label }</VisuallyHidden> }
			checked={ Boolean( value ) }
			onChange={ onChange }
			disabled={ disabled }
		/>
	);
}

function NumberField( {
	field,
	id,
	value,
	onChange,
	disabled,
}: FieldProps< number > ) {
	return (
		<TextControl
			__nextHasNoMarginBottom
			__next40pxDefaultSize
			id={ id }
			label={ field.label }
			hideLabelFromVision
			type="number"
			min={ field.min }
			max={ field.max }
			value={ String( value ?? '' ) }
			onChange={ ( next: string ) =>
				onChange( Number.parseInt( next, 10 ) || 0 )
			}
			disabled={ disabled }
			className="rp4wp-field__number"
		/>
	);
}

function Text( {
	field,
	id,
	value,
	onChange,
	disabled,
}: FieldProps< string > ) {
	return (
		<TextControl
			__nextHasNoMarginBottom
			__next40pxDefaultSize
			id={ id }
			label={ field.label }
			hideLabelFromVision
			value={ String( value ?? '' ) }
			onChange={ onChange }
			disabled={ disabled }
		/>
	);
}

// No id: TextareaControl would put it on the textarea but not on its label.
function Textarea( {
	field,
	value,
	onChange,
	disabled,
}: FieldProps< string > ) {
	return (
		<TextareaControl
			__nextHasNoMarginBottom
			label={ field.label }
			hideLabelFromVision
			value={ String( value ?? '' ) }
			onChange={ onChange }
			disabled={ disabled }
			rows={ 4 }
		/>
	);
}

// The default can be restored when it is not empty: an empty default means "none", which clearing the field does.
function Code( { field, value, onChange, disabled }: FieldProps< string > ) {
	const fallback = typeof field.default === 'string' ? field.default : '';

	return (
		<>
			<TextareaControl
				__nextHasNoMarginBottom
				label={ field.label }
				hideLabelFromVision
				value={ String( value ?? '' ) }
				onChange={ onChange }
				disabled={ disabled }
				rows={ 10 }
				spellCheck={ false }
				className="rp4wp-field__code"
			/>
			{ fallback !== '' && value !== fallback && ! disabled && (
				<Button
					variant="secondary"
					size="compact"
					onClick={ () => onChange( fallback ) }
					className="rp4wp-field__restore"
				>
					{ __( 'Restore default', 'related-posts-for-wp' ) }
				</Button>
			) }
		</>
	);
}

function Link( { field }: FieldProps ) {
	return (
		<Button variant="secondary" href={ field.href } __next40pxDefaultSize>
			{ String( field.default ?? field.label ) }
		</Button>
	);
}

export function registerFreeFieldTypes(): void {
	registry.registerFieldType( 'toggle', Toggle );
	registry.registerFieldType( 'number', NumberField );
	registry.registerFieldType( 'text', Text );
	registry.registerFieldType( 'textarea', Textarea );
	registry.registerFieldType( 'code', Code );
	registry.registerFieldType( 'link', Link );
}
