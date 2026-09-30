import { __ } from '@wordpress/i18n';
import type { Route } from '../registry/registry';

interface Props {
	routes: Route[];
	current?: string;
}

export function Navigation( { routes, current }: Props ) {
	if ( routes.length < 2 ) {
		return null;
	}

	return (
		<nav
			className="rp4wp-admin__nav"
			aria-label={ __(
				'Related Posts settings',
				'related-posts-for-wp'
			) }
		>
			{ routes.map( ( route ) => (
				<a
					key={ route.path }
					href={ `#/${ route.path }` }
					className="rp4wp-admin__tab"
					aria-current={ route.path === current ? 'page' : undefined }
				>
					{ route.title }
				</a>
			) ) }
		</nav>
	);
}
