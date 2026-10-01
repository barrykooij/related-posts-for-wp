import { TextControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { ArgSchema } from '../api/types';

interface Props {
	schema: ArgSchema | undefined;
	value: number;
	onChange: ( value: number ) => void;
	help?: string;
}

/**
 * The number of related posts to link to each post, within what the installer accepts.
 *
 * @param props          The props.
 * @param props.schema   The JSON schema of `amount`.
 * @param props.value    The number.
 * @param props.onChange Called with a new number.
 * @param props.help     A line below the field.
 */
export function AmountField( { schema, value, onChange, help }: Props ) {
	const min = schema?.minimum ?? 1;
	const max = schema?.maximum ?? 50;
	// What is typed, which may be empty or out of range for a moment.
	const [ text, setText ] = useState( String( value ) );

	useEffect( () => setText( String( value ) ), [ value ] );

	const clamp = ( number: number ) =>
		Math.min( max, Math.max( min, number ) );

	return (
		<TextControl
			__nextHasNoMarginBottom
			__next40pxDefaultSize
			className="rp4wp-amount"
			label={ __( 'Related posts per post', 'related-posts-for-wp' ) }
			help={ help }
			type="number"
			min={ min }
			max={ max }
			value={ text }
			onChange={ ( next: string ) => {
				const number = Number.parseInt( next, 10 );

				setText( next );

				if ( ! Number.isNaN( number ) && number === clamp( number ) ) {
					onChange( number );
				}
			} }
			onBlur={ () => {
				const number = Number.parseInt( text, 10 );
				const valid = Number.isNaN( number ) ? value : clamp( number );

				setText( String( valid ) );
				onChange( valid );
			} }
		/>
	);
}

/**
 * The default number of related posts: the setting, as the installer reports it.
 *
 * @param schema The JSON schema of `amount`.
 * @return The number.
 */
export function defaultAmount( schema: ArgSchema | undefined ): number {
	return typeof schema?.default === 'number' ? schema.default : 3;
}
