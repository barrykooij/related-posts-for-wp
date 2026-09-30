/**
 * The admin app of Related Posts for WordPress.
 *
 * The API is on window.rp4wp.admin as soon as this script runs. Scripts that depend on it (the premium plugin) run
 * after it and register their screens, and the app mounts once the DOM is ready, when they all have run.
 */
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { App } from './components/App';
import * as api from './public-api';
import { General } from './screens/General';
import './style.scss';

window.rp4wp = { ...window.rp4wp, admin: api };

api.registerRoute( {
	path: 'general',
	title: __( 'General', 'related-posts-for-wp' ),
	order: 10,
	component: General,
} );

domReady( () => {
	const element = document.getElementById( 'rp4wp-admin' );

	if ( element ) {
		createRoot( element ).render( <App /> );
	}
} );
