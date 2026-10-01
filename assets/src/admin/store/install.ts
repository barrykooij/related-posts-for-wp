/**
 * The background installer: whether the site was installed, and the current or last job with its progress.
 */
import { __ } from '@wordpress/i18n';
import {
	cancelInstall,
	errorMessage,
	getInstall,
	retryInstall,
	startInstall,
	tickInstall,
} from '../api/client';
import type { InstallRequest, InstallState, Job } from '../api/types';
import { createStore, useStore } from './createStore';

export interface InstallStoreState {
	loaded: boolean;
	state: InstallState | null;
	/** A start, cancel or resume is on its way. */
	busy: boolean;
	error: string | null;
	/** No background request ran the job, so this page runs it, until the job ends. */
	helping: boolean;
}

export const installStore = createStore< InstallStoreState >( {
	loaded: false,
	state: null,
	busy: false,
	error: null,
	helping: false,
} );

export function useInstall(): InstallStoreState {
	return useStore( installStore );
}

function received( state: InstallState ): void {
	installStore.setState( { loaded: true, state, error: null } );
}

export async function loadInstall(): Promise< void > {
	try {
		received( await getInstall() );
	} catch ( error ) {
		installStore.setState( {
			loaded: true,
			error: errorMessage(
				error,
				__(
					'The installer could not be reached.',
					'related-posts-for-wp'
				)
			),
		} );
	}
}

/**
 * Run an action of the installer, with the busy flag on while it runs.
 *
 * @param action The action.
 * @return Resolves when done; rejects with the error.
 */
async function act( action: () => Promise< InstallState > ): Promise< void > {
	installStore.setState( { busy: true } );

	try {
		received( await action() );
	} finally {
		installStore.setState( { busy: false } );
	}
}

export const start = ( request: InstallRequest ) =>
	act( () => startInstall( request ) );
export const cancel = () => act( cancelInstall );
export const resume = () => act( retryInstall );

/**
 * Follow the running job: one request, either to read its progress, or, when no background request works on it,
 * to run its next part.
 *
 * @return Resolves with the job after the request.
 */
export async function poll(): Promise< Job | null > {
	const { state, helping } = installStore.getState();
	const job = state?.job ?? null;
	const tick = job?.status === 'running' && ( job.stalled || helping );

	try {
		const next = tick ? await tickInstall() : await getInstall();

		received( next );

		// Once the page had to run the job, it goes on doing so: waiting for it to stall again would make each
		// step wait. When a background request holds the job, the tick runs nothing, and the page lets it be.
		installStore.setState( {
			helping:
				next.job?.status === 'running' &&
				( tick ? Boolean( next.ran ) : helping ),
		} );
	} catch {
		// A request that fails now and then is no reason to stop following; the next one tries again.
	}

	return installStore.getState().state?.job ?? null;
}

/**
 * How far a job is, from 0 to 1, counting each step the same.
 *
 * @param job The job.
 * @return The fraction.
 */
export function progressOf( job: Job ): number {
	if ( job.steps.length === 0 ) {
		return job.status === 'done' ? 1 : 0;
	}

	const done = job.steps.reduce( ( sum, step ) => {
		if ( step.done ) {
			return sum + 1;
		}

		if ( step.total && step.remaining !== null ) {
			return (
				sum + Math.max( 0, step.total - step.remaining ) / step.total
			);
		}

		return sum;
	}, 0 );

	return Math.min( 1, done / job.steps.length );
}

/**
 * How many seconds a job will take still, from how fast it went so far.
 *
 * @param job The job.
 * @param now The current Unix time.
 * @return The seconds, or null when it can't tell yet.
 */
export function secondsLeft( job: Job, now: number ): number | null {
	const fraction = progressOf( job );
	const elapsed = now - job.started;

	if ( fraction < 0.05 || elapsed < 5 || fraction >= 1 ) {
		return null;
	}

	return Math.round( ( elapsed / fraction ) * ( 1 - fraction ) );
}
