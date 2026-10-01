import { Button, Card, CardBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { Icon, check } from '@wordpress/icons';
import type { Job } from '../api/types';

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
							: __(
									'Link them from the Installer tab when you are ready.',
									'related-posts-for-wp'
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
