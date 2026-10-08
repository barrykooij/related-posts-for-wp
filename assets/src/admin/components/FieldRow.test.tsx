import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import type { Field } from '../api/types';
import { registerFreeFieldTypes } from '../fields';
import { FieldRow } from './FieldRow';

registerFreeFieldTypes();

function field( changes: Partial< Field > = {} ): Field {
	return {
		id: 'heading_text',
		type: 'text',
		label: 'Heading text',
		description: 'Shown <strong>above</strong> the related posts.',
		default: 'Related Posts',
		filtered: false,
		...changes,
	};
}

describe( 'FieldRow', () => {
	it( 'shows the label, the description with its markup, and the control', () => {
		render(
			<FieldRow
				page="general"
				field={ field() }
				value="Related Posts"
				onChange={ () => {} }
			/>
		);

		expect( screen.getByText( 'above' ).tagName ).toBe( 'STRONG' );
		expect(
			screen.getByRole( 'textbox', { name: 'Heading text' } )
		).toHaveProperty( 'value', 'Related Posts' );
	} );

	it( 'locks a field that a filter decides', () => {
		render(
			<FieldRow
				page="general"
				field={ field( { filtered: true } ) }
				value="From a filter"
				onChange={ () => {} }
			/>
		);

		expect(
			screen.getByText( 'This option is overwritten by a filter.' )
		).toBeTruthy();
		expect(
			screen.getByRole( 'textbox', { name: 'Heading text' } )
		).toHaveProperty( 'disabled', true );
	} );

	it( 'shows a field of a type nobody registered as a text field, and says so', () => {
		const warn = vi.spyOn( console, 'warn' ).mockImplementation( () => {} );

		render(
			<FieldRow
				page="general"
				field={ field( {
					id: 'accent',
					type: 'colorpicker',
					label: 'Accent',
				} ) }
				value="#000"
				onChange={ () => {} }
			/>
		);

		expect(
			screen.getByRole( 'textbox', { name: 'Accent' } )
		).toBeTruthy();
		expect( warn ).toHaveBeenCalledWith(
			expect.stringContaining( 'colorpicker' )
		);
		warn.mockRestore();
	} );

	it( 'shows a number field with its number', () => {
		render(
			<FieldRow
				page="general"
				field={ field( {
					id: 'excerpt_length',
					type: 'number',
					label: 'Excerpt length',
					min: 0,
				} ) }
				value={ 15 }
				onChange={ () => {} }
			/>
		);

		const input = screen.getByRole( 'spinbutton', {
			name: 'Excerpt length',
		} );
		expect( input ).toHaveProperty( 'value', '15' );
	} );

	it( 'restores the default of a code field that was changed', () => {
		const onChange = vi.fn();
		const css = field( {
			id: 'css',
			type: 'code',
			label: 'CSS',
			default: '.rp4wp-related-posts ul{display:grid;}',
		} );
		const { rerender } = render(
			<FieldRow
				page="styling"
				field={ css }
				value=".mine{}"
				onChange={ onChange }
			/>
		);

		fireEvent.click(
			screen.getByRole( 'button', { name: 'Restore default' } )
		);
		expect( onChange ).toHaveBeenCalledWith(
			'.rp4wp-related-posts ul{display:grid;}'
		);

		rerender(
			<FieldRow
				page="styling"
				field={ css }
				value=".rp4wp-related-posts ul{display:grid;}"
				onChange={ onChange }
			/>
		);
		expect(
			screen.queryByRole( 'button', { name: 'Restore default' } )
		).toBeNull();
	} );

	it( 'offers no restore for a code field with an empty default', () => {
		render(
			<FieldRow
				page="styling"
				field={ field( {
					id: 'css',
					type: 'code',
					label: 'Custom CSS',
					default: '',
				} ) }
				value=".mine{}"
				onChange={ () => {} }
			/>
		);

		expect(
			screen.queryByRole( 'button', { name: 'Restore default' } )
		).toBeNull();
	} );
} );
