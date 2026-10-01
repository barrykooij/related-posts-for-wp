import { Button, Card, CardBody } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { Icon, check } from '@wordpress/icons';
import type { Job } from '../api/types';
import { useLinkingTab } from '../registry/useRegistry';

interface Props {
	job: Job;
	onClose: () => void;
}

/**
 * The installation is done.
 *
 * @param props         The props.
 * @param props.job     The job.
 * @param props.onClose Hides the card.
 */
export function DoneCard( { job, onClose }: Props ) {
	const linked = ! job.request.skip_linking;
	const linkingTab = useLinkingTab();

	return (
		<Card className="rp4wp-card rp4wp-done">
			<CardBody className="rp4wp-done__body">
				<span className="rp4wp-done__icon" aria-hidden="true">
					<Icon icon={ check } size={ 32 } />
				</span>
				<div className="rp4wp-done__text">
					<h2 className="rp4wp-done__title">
						{ linked
							? __(
									'All set! Your posts are linked.',
									'related-posts-for-wp'
								)
							: __(
									'All set! Your posts are read.',
									'related-posts-for-wp'
								) }
					</h2>
					<p>
						{ linked
							? __(
									'Every post shows its related posts now, and new posts are linked when you publish them.',
									'related-posts-for-wp'
								)
							: sprintf(
									/** translators: %s: the name of a tab of the settings screen, for example Installer */
									__(
										'Link them from the %s tab when you are ready.',
										'related-posts-for-wp'
									),
									linkingTab
								) }
					</p>
				</div>
				<Button
					variant="secondary"
					onClick={ onClose }
					__next40pxDefaultSize
				>
					{ __( 'Close', 'related-posts-for-wp' ) }
				</Button>
			</CardBody>
		</Card>
	);
}
