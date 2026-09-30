import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useRegistry } from '../registry/useRegistry';
import { useHashPath } from '../router/useHashPath';
import { Header } from './Header';
import { Navigation } from './Navigation';

export function App() {
	const { routes } = useRegistry();
	const [ path, navigate ] = useHashPath();
	const route = routes.find( ( item ) => item.path === path );

	// Without a known path in the URL, open the first screen.
	useEffect( () => {
		if ( ! route && routes.length > 0 && '' === path ) {
			navigate( routes[ 0 ].path );
		}
	}, [ route, routes, path, navigate ] );

	const Screen = route?.component;

	return (
		<div className="rp4wp-admin">
			<Header />
			<Navigation routes={ routes } current={ route?.path } />
			<main className="rp4wp-admin__main">
				{ Screen ? (
					<Screen />
				) : (
					'' !== path && (
						<p>
							{ __(
								'This page does not exist.',
								'related-posts-for-wp'
							) }
						</p>
					)
				) }
			</main>
		</div>
	);
}
