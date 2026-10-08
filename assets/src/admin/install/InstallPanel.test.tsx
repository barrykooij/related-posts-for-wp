import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it } from 'vitest';
import type { Job } from '../api/types';
import { installStore } from '../store/install';
import { InstallPanel } from './InstallPanel';

function job( overrides: Partial< Job > ): Job {
	return {
		id: 'job-1',
		status: 'running',
		request: {},
		steps: [
			{
				id: 'relink_posts:post',
				label: 'Refreshing the related posts of Posts',
				total: 10,
				remaining: 5,
				done: false,
				current: true,
			},
		],
		started: 1,
		last_progress: 1,
		ended: 0,
		error: null,
		stalled: false,
		...overrides,
	};
}

function show( next: Job ): void {
	installStore.setState( {
		loaded: true,
		state: { installed: true, job: next, args: {} },
	} );
}

describe( 'InstallPanel', () => {
	beforeEach( () => {
		window.localStorage.clear();
	} );

	it( 'calls an installation an installation', () => {
		show( job( {} ) );

		render( <InstallPanel /> );

		expect(
			screen.getByRole( 'heading', {
				name: 'Linking your related posts',
			} )
		).toBeTruthy();
	} );

	it( 'uses the words a job that is not an installation brings', () => {
		show(
			job( {
				install: false,
				labels: {
					running: 'Refreshing your related posts',
					cancel: 'Cancel the refresh?',
					cancel_button: 'Cancel refresh',
				},
			} )
		);

		render( <InstallPanel /> );

		expect(
			screen.getByRole( 'heading', {
				name: 'Refreshing your related posts',
			} )
		).toBeTruthy();

		fireEvent.click( screen.getByRole( 'button', { name: 'Cancel' } ) );

		expect( screen.getByText( 'Cancel the refresh?' ) ).toBeTruthy();
		expect(
			screen.getByRole( 'button', { name: 'Cancel refresh' } )
		).toBeTruthy();
	} );

	it( 'shows that a job stopped in its own words', () => {
		show(
			job( {
				status: 'failed',
				install: false,
				labels: { failed: 'The refresh stopped' },
			} )
		);

		render( <InstallPanel /> );

		expect(
			screen.getByRole( 'heading', { name: 'The refresh stopped' } )
		).toBeTruthy();
	} );

	it( 'shows the done card only for an installation', () => {
		show( job( { status: 'done', install: false } ) );

		const { unmount } = render( <InstallPanel /> );

		expect( screen.queryByText( /All set!/ ) ).toBeNull();
		unmount();

		show( job( { id: 'job-2', status: 'done' } ) );
		render( <InstallPanel /> );

		expect( screen.getByText( /All set!/ ) ).toBeTruthy();
	} );
} );
