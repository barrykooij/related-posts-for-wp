import {
	Button,
	Card,
	CardBody,
	Notice,
	ToggleControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { errorMessage } from '../api/client';
import type { InstallRequest } from '../api/types';
import { useLinkingTab, useRegistry } from '../registry/useRegistry';
import { start, useInstall } from '../store/install';
import { AmountField, defaultAmount } from './AmountField';

/**
 * The first-run card: what the installer will do, how many related posts, and Start.
 */
export function SetupCard() {
	const { state, busy } = useInstall();
	const { setupSteps } = useRegistry();
	const linkingTab = useLinkingTab();
	const args = state?.args ?? {};
	// A step that asks for the number of related posts itself, for example per post type, replaces the card's field.
	const ownAmount = ! setupSteps.some( ( step ) => step.providesAmount );
	const [ request, setRequestState ] = useState< InstallRequest >( () =>
		ownAmount
			? { amount: defaultAmount( args.amount ), skip_linking: false }
			: { skip_linking: false }
	);
	const [ error, setError ] = useState< string | null >( null );

	const setRequest = ( changes: InstallRequest ) =>
		setRequestState( ( current ) => ( { ...current, ...changes } ) );

	const onStart = async () => {
		setError( null );

		try {
			await start( request );
		} catch ( caught ) {
			setError(
				errorMessage(
					caught,
					__(
						'The installation could not start.',
						'related-posts-for-wp'
					)
				)
			);
		}
	};

	return (
		<Card className="rp4wp-card rp4wp-setup">
			<CardBody className="rp4wp-setup__body">
				<div className="rp4wp-setup__intro">
					<span className="rp4wp-setup__eyebrow">
						{ __( 'Get started', 'related-posts-for-wp' ) }
					</span>
					<h2 className="rp4wp-setup__title">
						{ __(
							"Let's link your related posts",
							'related-posts-for-wp'
						) }
					</h2>
					<p className="rp4wp-setup__text">
						{ __(
							'Related Posts reads your posts once to learn what each one is about, then links every post to the posts that are most related to it.',
							'related-posts-for-wp'
						) }
					</p>
					<p className="rp4wp-setup__text">
						{ __(
							'It runs in the background, so you can leave this page while it works.',
							'related-posts-for-wp'
						) }
					</p>
				</div>
				<div className="rp4wp-setup__form">
					{ ownAmount && (
						<AmountField
							schema={ args.amount }
							value={ Number( request.amount ) }
							onChange={ ( amount ) => setRequest( { amount } ) }
							help={ __(
								'You can change this later, and link again at any time.',
								'related-posts-for-wp'
							) }
						/>
					) }
					{ setupSteps.map( ( { id, component: Step } ) => (
						<Step
							key={ id }
							request={ request }
							setRequest={ setRequest }
							args={ args }
						/>
					) ) }
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Link the posts right away',
							'related-posts-for-wp'
						) }
						help={ sprintf(
							/** translators: %s: the name of a tab of the settings screen, for example Installer */
							__(
								'Turn this off to only read the posts now, and link them later from the %s tab.',
								'related-posts-for-wp'
							),
							linkingTab
						) }
						checked={ ! request.skip_linking }
						onChange={ ( checked: boolean ) =>
							setRequest( { skip_linking: ! checked } )
						}
					/>
					{ error && (
						<Notice status="error" isDismissible={ false }>
							{ error }
						</Notice>
					) }
					<Button
						variant="primary"
						className="rp4wp-setup__start"
						onClick={ onStart }
						isBusy={ busy }
						disabled={ busy }
						__next40pxDefaultSize
					>
						{ __( 'Start', 'related-posts-for-wp' ) }
					</Button>
				</div>
			</CardBody>
		</Card>
	);
}
