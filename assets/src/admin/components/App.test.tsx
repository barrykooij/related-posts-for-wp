import { act, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { registerRoute } from '../public-api';
import { installStore } from '../store/install';
import { settingsStore } from '../store/settings';
import { App } from './App';

const General = () => <p>General screen</p>;
const Styling = () => <p>Styling screen</p>;

describe( 'App', () => {
	beforeEach( () => {
		settingsStore.setState( {
			status: 'ready',
			pages: [],
			saved: {},
			edits: {},
		} );
		installStore.setState( {
			loaded: true,
			state: { installed: true, job: null, args: {} },
		} );
	} );

	afterEach( () => {
		window.location.hash = '';
	} );

	it( 'waits for the settings and the installer', () => {
		settingsStore.setState( { status: 'loading' } );

		render( <App /> );

		expect( screen.queryByRole( 'navigation' ) ).toBeNull();
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

	it( 'opens the first screen for #/setup, where activation sends admins', () => {
		registerRoute( {
			path: 'general',
			title: 'General',
			order: 10,
			component: General,
		} );
		window.location.hash = '#/setup';

		render( <App /> );

		expect( screen.getByText( 'General screen' ) ).toBeTruthy();
	} );

	it( 'shows the first-run card on a site that was not installed', () => {
		installStore.setState( {
			state: {
				installed: false,
				job: null,
				args: { amount: { default: 4, minimum: 1, maximum: 50 } },
			},
		} );

		render( <App /> );

		expect(
			screen.getByRole( 'heading', {
				name: "Let's link your related posts",
			} )
		).toBeTruthy();
		expect(
			screen.getByRole( 'spinbutton', { name: 'Related posts per post' } )
		).toHaveProperty( 'value', '4' );
	} );

	it( 'shows one tab for a group of screens, with a switcher between them', async () => {
		const Posts = () => <p>Posts screen</p>;
		const Books = () => <p>Books screen</p>;
		// Other tests registered a plain General tab in the same registry; this group has a title of its own.
		const group = { id: 'by-type', title: 'By post type' };

		registerRoute( {
			path: 'general_post',
			title: 'Posts',
			order: 1,
			component: Posts,
			group: { ...group, label: 'Posts' },
		} );
		registerRoute( {
			path: 'general_book',
			title: 'Books',
			order: 2,
			component: Books,
			group: { ...group, label: 'Books' },
		} );
		window.location.hash = '#/general_book';

		render( <App /> );

		const tabs = screen.getByRole( 'navigation' );
		expect(
			Array.from( tabs.querySelectorAll( 'a' ) ).filter(
				( tab ) => tab.textContent === 'By post type'
			)
		).toHaveLength( 1 );
		expect( screen.getByText( 'Books screen' ) ).toBeTruthy();
		expect(
			screen
				.getByRole( 'group', { name: 'By post type' } )
				.querySelector( '[aria-current="page"]' )?.textContent
		).toBe( 'Books' );
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
