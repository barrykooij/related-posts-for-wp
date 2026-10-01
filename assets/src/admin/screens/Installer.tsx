import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Notice,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { errorMessage } from '../api/client';
import { ConfirmModal } from '../components/ConfirmModal';
import { notify } from '../components/Snackbars';
import { AmountField, defaultAmount } from '../install/AmountField';
import { start, useInstall } from '../store/install';

/**
 * The Installer tab: link what is not linked yet, or rebuild everything.
 */
export function Installer() {
	const { state, busy } = useInstall();
	const args = state?.args ?? {};
	const running = state?.job?.status === 'running';
	const [ amount, setAmount ] = useState( () =>
		defaultAmount( args.amount )
	);
	const [ confirming, setConfirming ] = useState( false );

	const run = async ( rebuild: boolean ) => {
		try {
			await start( { amount, rebuild } );
			window.scrollTo?.( { top: 0, behavior: 'smooth' } );
		} catch ( error ) {
			notify(
				errorMessage(
					error,
					__(
						'The installation could not start.',
						'related-posts-for-wp'
					)
				),
				'error'
			);
		}
	};

	return (
		<div className="rp4wp-page">
			{ running && (
				<Notice status="info" isDismissible={ false }>
					{ __(
						'An installation is running. You can start another one when it is done.',
						'related-posts-for-wp'
					) }
				</Notice>
			) }

			<Card className="rp4wp-card">
				<CardHeader>
					<div>
						<h2 className="rp4wp-card__title">
							{ __( 'Link new posts', 'related-posts-for-wp' ) }
						</h2>
						<p className="rp4wp-card__description">
							{ __(
								'Read and link the posts that are not linked yet, for example posts from before automatic linking was on. Links that exist stay as they are.',
								'related-posts-for-wp'
							) }
						</p>
					</div>
				</CardHeader>
				<CardBody className="rp4wp-installer">
					<AmountField
						schema={ args.amount }
						value={ amount }
						onChange={ setAmount }
					/>
					<Button
						variant="primary"
						onClick={ () => run( false ) }
						disabled={ running || busy }
						isBusy={ busy }
						__next40pxDefaultSize
					>
						{ __( 'Link new posts', 'related-posts-for-wp' ) }
					</Button>
				</CardBody>
			</Card>

			<Card className="rp4wp-card rp4wp-card--danger">
				<CardHeader>
					<div>
						<h2 className="rp4wp-card__title">
							{ __(
								'Rebuild everything',
								'related-posts-for-wp'
							) }
						</h2>
						<p className="rp4wp-card__description">
							{ __(
								'Start over: remove every related post link, also the links you added by hand, read all posts again and link them. Your posts themselves are never touched.',
								'related-posts-for-wp'
							) }
						</p>
					</div>
				</CardHeader>
				<CardBody className="rp4wp-installer">
					<Button
						variant="secondary"
						isDestructive
						onClick={ () => setConfirming( true ) }
						disabled={ running || busy }
						__next40pxDefaultSize
					>
						{ __( 'Rebuild everything', 'related-posts-for-wp' ) }
					</Button>
				</CardBody>
			</Card>

			{ confirming && (
				<ConfirmModal
					title={ __(
						'Rebuild everything?',
						'related-posts-for-wp'
					) }
					confirmLabel={ __(
						'Remove links and rebuild',
						'related-posts-for-wp'
					) }
					cancelLabel={ __(
						'Keep my links',
						'related-posts-for-wp'
					) }
					isDestructive
					onCancel={ () => setConfirming( false ) }
					onConfirm={ () => {
						setConfirming( false );
						run( true );
					} }
				>
					<p>
						{ __(
							'Every related post link is removed, including the links you added by hand. This can not be undone.',
							'related-posts-for-wp'
						) }
					</p>
				</ConfirmModal>
			) }
		</div>
	);
}
