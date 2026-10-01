import { useEffect, useRef, useState } from '@wordpress/element';
import { speak } from '@wordpress/a11y';
import { __ } from '@wordpress/i18n';
import { isDirty, loadSettings, settingsStore } from '../store/settings';
import { poll, useInstall } from '../store/install';
import { DoneCard } from './DoneCard';
import { ProgressCard } from './ProgressCard';
import { SetupCard } from './SetupCard';

const DISMISSED = 'rp4wp-dismissed-job';

function dismissedJob(): string | null {
	try {
		return window.localStorage.getItem( DISMISSED );
	} catch {
		return null;
	}
}

function dismissJob( id: string ): void {
	try {
		window.localStorage.setItem( DISMISSED, id );
	} catch {
		// Without storage the card comes back on the next visit; nothing else is lost.
	}
}

/**
 * Follow a running job: a request every few seconds, less often while the tab is in the background.
 *
 * @param running Whether a job runs.
 */
function usePolling( running: boolean ): void {
	useEffect( () => {
		if ( ! running ) {
			return undefined;
		}

		let stopped = false;
		let timer: number | undefined;

		const next = async () => {
			const job = await poll();

			if ( stopped ) {
				return;
			}

			if ( job?.status === 'running' ) {
				timer = window.setTimeout(
					next,
					document.hidden ? 10000 : 2500
				);
			}
		};

		timer = window.setTimeout( next, 1500 );

		return () => {
			stopped = true;
			window.clearTimeout( timer );
		};
	}, [ running ] );
}

/**
 * The installer at the top of the settings screen: the first-run card, the progress of a job, or that it is done.
 * Nothing once the site is installed and the last job was seen.
 */
export function InstallPanel() {
	const { loaded, state } = useInstall();
	const job = state?.job ?? null;
	const running = job?.status === 'running';
	const [ dismissed, setDismissed ] = useState( dismissedJob );
	const previous = useRef( job?.status );
	const panel = useRef< HTMLDivElement >( null );

	usePolling( running );

	// Tell screen readers when a job ends, and show the settings it saved.
	useEffect( () => {
		const status = job?.status;

		if (
			previous.current === 'running' &&
			status &&
			status !== 'running'
		) {
			if ( status === 'done' ) {
				speak(
					__( 'The installation is done.', 'related-posts-for-wp' )
				);

				if ( ! isDirty( settingsStore.getState() ) ) {
					loadSettings();
				}
			} else if ( status === 'failed' ) {
				speak(
					__( 'The installation stopped.', 'related-posts-for-wp' ),
					'assertive'
				);
			}
		}

		if ( previous.current !== 'running' && status === 'running' ) {
			panel.current?.scrollIntoView?.( {
				behavior: 'smooth',
				block: 'nearest',
			} );
		}

		previous.current = status;
	}, [ job?.status ] );

	if ( ! loaded || ! state ) {
		return null;
	}

	let content = null;

	if ( job && job.status !== 'done' ) {
		content = <ProgressCard job={ job } />;
	} else if ( job && job.status === 'done' && dismissed !== job.id ) {
		content = (
			<DoneCard
				job={ job }
				onClose={ () => {
					dismissJob( job.id );
					setDismissed( job.id );
				} }
			/>
		);
	} else if ( ! state.installed ) {
		content = <SetupCard />;
	}

	return (
		<div className="rp4wp-install" ref={ panel }>
			{ content }
		</div>
	);
}
