import { Notice, Spinner } from '@wordpress/components';
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { InstallPanel } from '../install/InstallPanel';
import { useRegistry } from '../registry/useRegistry';
import { useHashPath } from '../router/useHashPath';
import { useInstall } from '../store/install';
import { useSettings } from '../store/settings';
import { Header } from './Header';
import { GroupSwitcher, Navigation } from './Navigation';
import { SaveBar } from './SaveBar';
import { Snackbars } from './Snackbars';

/**
 * Paths that open the first screen: `setup` is where activation and the notices send admins, to the installer at the
 * top.
 */
const FIRST_SCREEN = new Set( [ '', 'setup' ] );

export function App() {
	const { routes } = useRegistry();
	const settings = useSettings();
	const install = useInstall();
	const [ path, navigate ] = useHashPath();
	const route =
		routes.find( ( item ) => item.path === path ) ??
		( FIRST_SCREEN.has( path ) ? routes[ 0 ] : undefined );
	const loading = settings.status === 'loading' || ! install.loaded;

	// Without a path in the URL, show the first screen's path, so its tab is the current one.
	useEffect( () => {
		if ( ! loading && '' === path && routes.length > 0 ) {
			navigate( routes[ 0 ].path );
		}
	}, [ loading, routes, path, navigate ] );

	const Screen = route?.component;

	return (
		<div className="rp4wp-admin">
			<Header />

			{ loading && (
				<div className="rp4wp-admin__loading">
					<Spinner />
				</div>
			) }

			{ ! loading && settings.status === 'error' && (
				<Notice status="error" isDismissible={ false }>
					{ settings.error }
				</Notice>
			) }

			{ ! loading && install.error && (
				<Notice status="error" isDismissible={ false }>
					{ install.error }
				</Notice>
			) }

			{ ! loading && (
				<>
					<InstallPanel />
					<Navigation routes={ routes } current={ route } />
					<main className="rp4wp-admin__main">
						<GroupSwitcher routes={ routes } current={ route } />
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
					<SaveBar />
				</>
			) }

			<Snackbars />
		</div>
	);
}
