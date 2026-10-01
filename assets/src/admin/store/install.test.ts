import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { InstallState, Job } from '../api/types';
import { installStore, poll, progressOf, secondsLeft } from './install';

vi.mock( '../api/client', () => ( {
	getInstall: vi.fn(),
	tickInstall: vi.fn(),
	startInstall: vi.fn(),
	cancelInstall: vi.fn(),
	retryInstall: vi.fn(),
	errorMessage: ( _error: unknown, fallback: string ) => fallback,
} ) );

const client = await import( '../api/client' );

function job( changes: Partial< Job > = {} ): Job {
	return {
		id: 'job',
		status: 'running',
		request: {},
		steps: [
			{
				id: 'cache_words',
				label: 'Caching posts',
				total: 100,
				remaining: 50,
				done: false,
				current: true,
			},
			{
				id: 'save_amount',
				label: 'Saving',
				total: null,
				remaining: null,
				done: false,
				current: false,
			},
		],
		started: 1000,
		last_progress: 1000,
		ended: 0,
		error: null,
		stalled: false,
		...changes,
	};
}

function state(
	changes: Partial< Job > = {},
	extra: Partial< InstallState > = {}
): InstallState {
	return { installed: false, job: job( changes ), args: {}, ...extra };
}

describe( 'progress', () => {
	it( 'counts each step the same', () => {
		expect( progressOf( job() ) ).toBe( 0.25 );
		expect( progressOf( job( { steps: [], status: 'done' } ) ) ).toBe( 1 );
	} );

	it( 'estimates the time left from how fast it went', () => {
		// A quarter in 100 seconds: three quarters take 300 more.
		expect( secondsLeft( job(), 1100 ) ).toBe( 300 );
		expect( secondsLeft( job(), 1002 ), 'Too early to tell.' ).toBeNull();
	} );
} );

describe( 'polling', () => {
	beforeEach( () => {
		vi.mocked( client.getInstall ).mockReset();
		vi.mocked( client.tickInstall ).mockReset();
		installStore.setState( {
			loaded: true,
			state: state(),
			helping: false,
		} );
	} );

	it( 'reads the progress while background requests run the job', async () => {
		vi.mocked( client.getInstall ).mockResolvedValue( state() );

		await poll();

		expect( client.getInstall ).toHaveBeenCalledTimes( 1 );
		expect( client.tickInstall ).not.toHaveBeenCalled();
	} );

	it( 'runs a stalled job from the page, and keeps doing so until it ends', async () => {
		installStore.setState( { state: state( { stalled: true } ) } );
		vi.mocked( client.tickInstall ).mockResolvedValue( {
			...state(),
			ran: true,
		} );

		await poll();
		expect( installStore.getState().helping ).toBe( true );

		// Not stalled any more, because the page made progress: the page goes on.
		await poll();
		expect( client.tickInstall ).toHaveBeenCalledTimes( 2 );
		expect( client.getInstall ).not.toHaveBeenCalled();

		vi.mocked( client.tickInstall ).mockResolvedValue( {
			...state( { status: 'done' } ),
			ran: true,
		} );
		await poll();
		expect( installStore.getState().helping ).toBe( false );
	} );

	it( 'leaves the job to a background request that works on it', async () => {
		installStore.setState( {
			state: state( { stalled: true } ),
			helping: true,
		} );
		vi.mocked( client.tickInstall ).mockResolvedValue( {
			...state(),
			ran: false,
		} );

		await poll();

		expect( installStore.getState().helping ).toBe( false );
	} );

	it( 'keeps following after a request fails', async () => {
		vi.mocked( client.getInstall ).mockRejectedValue(
			new Error( 'Offline' )
		);

		await expect( poll() ).resolves.toMatchObject( { id: 'job' } );
	} );
} );
