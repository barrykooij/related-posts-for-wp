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
import type { SettingsPage } from './api/types';
import { loadSettings, settingsStore } from './store/settings';
import { registry } from './registry/registry';
import './style.scss';

window.rp4wp = { ...window.rp4wp, admin: api };

registerFreeFieldTypes();

api.registerRoute( {
	path: 'installer',
	title: __( 'Installer', 'related-posts-for-wp' ),
	order: 80,
	component: Installer,
} );

/** The settings pages that have a tab now, and their paths. */
let settingsPages: SettingsPage[] = [];
let settingsRoutes = new Set< string >();

/**
 * A tab for every settings page, in the order of the settings. Runs again when the settings are loaded again: an
 * installation may add or remove pages, such as the general settings of a post type.
 */
function registerSettingsPages(): void {
	const { pages } = settingsStore.getState();

	// Edits change the state too; only new pages change the tabs.
	if ( pages === settingsPages ) {
		return;
	}

	const paths = new Set( pages.map( ( page ) => page.id ) );

	settingsRoutes.forEach( ( path ) => {
		if ( ! paths.has( path ) ) {
			registry.unregisterRoute( path );
		}
	} );

	pages.forEach( ( page, index ) => {
		api.registerRoute( {
			path: page.id,
			title: page.title,
			order: 10 + index,
			component: settingsScreen( page.id ),
			group: page.group
				? {
						id: page.group,
						title: page.group_title ?? page.title,
						label: page.group_label ?? page.title,
					}
				: undefined,
		} );
	} );

	settingsPages = pages;
	settingsRoutes = paths;
}

/** The screen of each settings page, made once, so registering a page again does not remount its screen. */
const screens = new Map< string, () => JSX.Element >();

function settingsScreen( page: string ): () => JSX.Element {
	if ( ! screens.has( page ) ) {
		screens.set( page, () => <SettingsScreen page={ page } /> );
	}

	return screens.get( page ) as () => JSX.Element;
}

domReady( () => {
	const element = document.getElementById( 'rp4wp-admin' );

	if ( ! element ) {
		return;
	}

	createRoot( element ).render( <App /> );
	settingsStore.subscribe( registerSettingsPages );
	loadSettings();
	loadInstall();
} );
