import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it } from 'vitest';
import { registerRoute, registerSetupStep } from '../public-api';
import { installStore } from '../store/install';
import { SetupCard } from './SetupCard';

const Screen = () => <p>A screen</p>;

describe( 'SetupCard', () => {
	beforeEach( () => {
		installStore.setState( {
			loaded: true,
			state: {
				installed: false,
				job: null,
				args: { amount: { minimum: 1, maximum: 50, default: 5 } },
			},
		} );
	} );

	it( 'asks for the number of related posts, and sends the admin to the Installer tab', () => {
		registerRoute( {
			path: 'installer',
			title: 'Installer',
			order: 80,
			component: Screen,
			linking: true,
		} );

		render( <SetupCard /> );

		expect(
			(
				screen.getByRole( 'spinbutton', {
					name: 'Related posts per post',
				} ) as HTMLInputElement
			 ).value
		).toBe( '5' );
		expect(
			screen.getByText( /link them later from the Installer tab\./ )
		).toBeTruthy();
	} );

	it( 'leaves the number to a step that asks for it, and names the tab that took the place of the Installer tab', () => {
		registerRoute( {
			path: 'post-types',
			title: 'Post types',
			order: 70,
			component: Screen,
			linking: true,
		} );
		registerSetupStep( {
			id: 'amounts',
			order: 10,
			component: () => <p>Per post type</p>,
			providesAmount: true,
		} );

		render( <SetupCard /> );

		expect( screen.getByText( 'Per post type' ) ).toBeTruthy();
		expect(
			screen.queryByRole( 'spinbutton', {
				name: 'Related posts per post',
			} )
		).toBeNull();
		// The first route that says it is where posts are linked.
		expect(
			screen.getByText( /link them later from the Post types tab\./ )
		).toBeTruthy();
	} );
} );
