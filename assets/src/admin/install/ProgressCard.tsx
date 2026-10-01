import { Button, Card, CardBody, Notice, Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Icon, check } from '@wordpress/icons';
import { errorMessage } from '../api/client';
import type { InstallStep, Job } from '../api/types';
import { ConfirmModal } from '../components/ConfirmModal';
import { notify } from '../components/Snackbars';
import {
	cancel,
	progressOf,
	resume,
	secondsLeft,
	useInstall,
} from '../store/install';

const number = ( value: number ) => value.toLocaleString();

/**
 * The time a job needs still, in words.
 *
 * @param seconds The seconds.
 * @return The text.
 */
function timeLeft( seconds: number ): string {
	if ( seconds < 60 ) {
		return __( 'Less than a minute left', 'related-posts-for-wp' );
	}

	const minutes = Math.ceil( seconds / 60 );

	return sprintf(
		/** translators: %s: a number of minutes */
		_n(
			'About %s minute left',
			'About %s minutes left',
			minutes,
			'related-posts-for-wp'
		),
		number( minutes )
	);
}

function StepRow( { step }: { step: InstallStep } ) {
	const done =
		step.total !== null && step.remaining !== null
			? Math.max( 0, step.total - step.remaining )
			: null;
	let state = 'pending';

	if ( step.done ) {
		state = 'done';
	} else if ( step.current ) {
		state = 'current';
	}

	return (
		<li className={ `rp4wp-step rp4wp-step--${ state }` }>
			<span className="rp4wp-step__icon" aria-hidden="true">
				{ step.done && <Icon icon={ check } size={ 20 } /> }
				{ step.current && ! step.done && <Spinner /> }
			</span>
			<span className="rp4wp-step__label">{ step.label }</span>
			{ step.current && done !== null && step.total ? (
				<span className="rp4wp-step__count">
					{ sprintf(
						/** translators: 1: posts done, 2: all posts */
						__( '%1$s of %2$s posts', 'related-posts-for-wp' ),
						number( done ),
						number( step.total )
					) }
				</span>
			) : null }
		</li>
	);
}

function Title( { job }: { job: Job } ) {
	switch ( job.status ) {
		case 'failed':
			return (
				<>
					{ __( 'The installation stopped', 'related-posts-for-wp' ) }
				</>
			);
		case 'cancelled':
			return (
				<>
					{ __(
						'The installation was cancelled',
						'related-posts-for-wp'
					) }
				</>
			);
		default:
			return (
				<>
					{ __(
						'Linking your related posts',
						'related-posts-for-wp'
					) }
				</>
			);
	}
}

/**
 * The progress of a running job, or what to do about a job that stopped.
 *
 * @param props     The props.
 * @param props.job The job.
 */
export function ProgressCard( { job }: { job: Job } ) {
	const { busy, helping } = useInstall();
	const [ confirming, setConfirming ] = useState( false );
	const [ now, setNow ] = useState( () => Math.floor( Date.now() / 1000 ) );
	const running = job.status === 'running';
	const percent = Math.floor( progressOf( job ) * 100 );
	const left = running ? secondsLeft( job, now ) : null;

	useEffect( () => {
		if ( ! running ) {
			return undefined;
		}

		const timer = window.setInterval(
			() => setNow( Math.floor( Date.now() / 1000 ) ),
			5000
		);

		return () => window.clearInterval( timer );
	}, [ running ] );

	const run = async ( action: () => Promise< void >, failed: string ) => {
		try {
			await action();
		} catch ( error ) {
			notify( errorMessage( error, failed ), 'error' );
		}
	};

	return (
		<Card
			className={ `rp4wp-card rp4wp-progress rp4wp-progress--${ job.status }` }
		>
			<CardBody className="rp4wp-progress__body">
				<div className="rp4wp-progress__head">
					<div>
						<h2 className="rp4wp-progress__title">
							<Title job={ job } />
						</h2>
						{ running && ! job.stalled && ! helping && (
							<p className="rp4wp-progress__text">
								{ __(
									'This runs in the background. You can leave this page; it goes on without you.',
									'related-posts-for-wp'
								) }
							</p>
						) }
					</div>
					<span
						className="rp4wp-progress__percent"
						aria-hidden="true"
					>
						{ sprintf(
							/** translators: %d: how far the installation is, in percent */
							__( '%d%%', 'related-posts-for-wp' ),
							percent
						) }
					</span>
				</div>

				<div
					className="rp4wp-bar"
					role="progressbar"
					aria-label={ __(
						'Installation progress',
						'related-posts-for-wp'
					) }
					aria-valuemin={ 0 }
					aria-valuemax={ 100 }
					aria-valuenow={ percent }
				>
					<span
						className="rp4wp-bar__fill"
						style={ { width: `${ percent }%` } }
					/>
				</div>

				{ left !== null && (
					<p className="rp4wp-progress__eta">{ timeLeft( left ) }</p>
				) }

				<ol className="rp4wp-steps">
					{ job.steps.map( ( step ) => (
						<StepRow key={ step.id } step={ step } />
					) ) }
				</ol>

				{ running && ( job.stalled || helping ) && (
					<Notice status="warning" isDismissible={ false }>
						{ __(
							'Background processing is slow on this site, so this page is doing the work now. Keep it open to finish sooner, or come back later.',
							'related-posts-for-wp'
						) }
					</Notice>
				) }

				{ job.status === 'failed' && (
					<Notice status="error" isDismissible={ false }>
						{ job.error ??
							__(
								'Something went wrong.',
								'related-posts-for-wp'
							) }
					</Notice>
				) }

				{ job.status === 'cancelled' && (
					<p className="rp4wp-progress__text">
						{ __(
							'What was done so far is kept. Resume to finish the rest.',
							'related-posts-for-wp'
						) }
					</p>
				) }

				<div className="rp4wp-progress__actions">
					{ running ? (
						<Button
							variant="tertiary"
							isDestructive
							onClick={ () => setConfirming( true ) }
							disabled={ busy }
							__next40pxDefaultSize
						>
							{ __( 'Cancel', 'related-posts-for-wp' ) }
						</Button>
					) : (
						<Button
							variant="primary"
							onClick={ () =>
								run(
									resume,
									__(
										'The installation could not resume.',
										'related-posts-for-wp'
									)
								)
							}
							isBusy={ busy }
							disabled={ busy }
							__next40pxDefaultSize
						>
							{ __( 'Resume', 'related-posts-for-wp' ) }
						</Button>
					) }
				</div>
			</CardBody>

			{ confirming && (
				<ConfirmModal
					title={ __(
						'Cancel the installation?',
						'related-posts-for-wp'
					) }
					confirmLabel={ __(
						'Cancel installation',
						'related-posts-for-wp'
					) }
					cancelLabel={ __( 'Keep running', 'related-posts-for-wp' ) }
					isDestructive
					onCancel={ () => setConfirming( false ) }
					onConfirm={ () => {
						setConfirming( false );
						run(
							cancel,
							__(
								'The installation could not be cancelled.',
								'related-posts-for-wp'
							)
						);
					} }
				>
					<p>
						{ __(
							'What was done so far is kept, and you can resume later.',
							'related-posts-for-wp'
						) }
					</p>
				</ConfirmModal>
			) }
		</Card>
	);
}
