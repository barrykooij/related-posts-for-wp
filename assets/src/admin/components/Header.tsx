import { __, _x, sprintf } from '@wordpress/i18n';

export function Header() {
	const data = window.rp4wpAdminData;
	const version = data?.version
		? sprintf(
				/** translators: %s: the plugin version */
				__( 'Version %s', 'related-posts-for-wp' ),
				data.version
			)
		: '';

	return (
		<header className="rp4wp-admin__header">
			<h1 className="rp4wp-admin__title">
				{
					/** translators: The title of the settings page: the name of the plugin, best left as it is. */
					_x( 'Related Posts', 'page title', 'related-posts-for-wp' )
				}
			</h1>
			{ 'premium' === data?.edition && (
				<span className="rp4wp-admin__badge">
					{ __( 'Premium', 'related-posts-for-wp' ) }
				</span>
			) }
			{ version && (
				<span className="rp4wp-admin__version">{ version }</span>
			) }
		</header>
	);
}
