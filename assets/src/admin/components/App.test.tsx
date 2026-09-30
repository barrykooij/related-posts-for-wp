import { act, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import { registerRoute } from '../public-api';
import { App } from './App';

const General = () => <p>General screen</p>;
const Styling = () => <p>Styling screen</p>;

describe( 'App', () => {
	afterEach( () => {
		window.location.hash = '';
	} );

	it( 'opens the first screen, shows the tabs and follows the hash', async () => {
		registerRoute( {
			path: 'general',
			title: 'General',
			order: 10,
			component: General,
		} );
		registerRoute( {
			path: 'styling',
			title: 'Styling',
			order: 20,
			component: Styling,
		} );

		render( <App /> );

		expect( await screen.findByText( 'General screen' ) ).toBeTruthy();
		expect(
			screen
				.getByRole( 'link', { name: 'General' } )
				.getAttribute( 'aria-current' )
		).toBe( 'page' );

		await act( async () => {
			window.location.hash = '#/styling';
			window.dispatchEvent( new HashChangeEvent( 'hashchange' ) );
		} );

		expect( screen.getByText( 'Styling screen' ) ).toBeTruthy();
	} );

	it( 'shows a screen that is registered after the app mounted', async () => {
		window.location.hash = '#/late';
		render( <App /> );

		expect( screen.getByText( 'This page does not exist.' ) ).toBeTruthy();

		await act( async () => {
			registerRoute( {
				path: 'late',
				title: 'Late',
				order: 30,
				component: () => <p>Late screen</p>,
			} );
		} );

		expect( screen.getByText( 'Late screen' ) ).toBeTruthy();
	} );
} );
