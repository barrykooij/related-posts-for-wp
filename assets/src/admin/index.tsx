/**
 * The admin app of Related Posts for WordPress: the settings, and the installer that runs in the background.
 *
 * The API is on window.rp4wp.admin as soon as this script runs. Scripts that depend on it (the premium plugin) run
 * after it and register their screens and field types, and the app mounts once the DOM is ready, when they all have
 * run.
 */
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { App } from './components/App';
import { registerFreeFieldTypes } from './fields';
import * as api from './public-api';
import { Installer } from './screens/Installer';
import { SettingsScreen } from './screens/SettingsScreen';
import { loadInstall } from './store/install';
import { loadSettings, settingsStore } from './store/settings';
import './style.scss';

window.rp4wp = { ...window.rp4wp, admin: api };

registerFreeFieldTypes();

api.registerRoute( {
	path: 'installer',
	title: __( 'Installer', 'related-posts-for-wp' ),
	order: 80,
	component: Installer,
} );

/**
 * A tab for every settings page, in the order of the settings.
 */
function registerSettingsPages(): void {
	settingsStore.getState().pages.forEach( ( page, index ) => {
		api.registerRoute( {
			path: page.id,
			title: page.title,
			order: 10 + index,
			component: () => <SettingsScreen page={ page.id } />,
		} );
	} );
}

domReady( () => {
	const element = document.getElementById( 'rp4wp-admin' );

	if ( ! element ) {
		return;
	}

	createRoot( element ).render( <App /> );
	loadSettings().then( registerSettingsPages );
	loadInstall();
} );
