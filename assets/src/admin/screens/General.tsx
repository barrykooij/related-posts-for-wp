import { Card, CardBody, CardHeader } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * The General tab. For now a placeholder: the settings follow in the next phase of the rebuild.
 */
export function General() {
	return (
		<Card>
			<CardHeader>
				<h2 className="rp4wp-admin__card-title">
					{ __( 'General', 'related-posts-for-wp' ) }
				</h2>
			</CardHeader>
			<CardBody>
				<p>
					{ __(
						'The new settings screen is being built.',
						'related-posts-for-wp'
					) }
				</p>
			</CardBody>
		</Card>
	);
}
