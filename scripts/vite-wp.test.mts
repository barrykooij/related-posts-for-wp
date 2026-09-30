import { describe, expect, it } from 'vitest';
import { assetFile, externalOf } from './vite-wp.mts';

describe( 'externalOf', () => {
	it( 'maps React and the @wordpress packages to their globals and handles', () => {
		expect( externalOf( 'react/jsx-runtime' ) ).toEqual( {
			global: 'ReactJSXRuntime',
			handle: 'react-jsx-runtime',
		} );
		expect( externalOf( '@wordpress/components' ) ).toEqual( {
			global: 'wp.components',
			handle: 'wp-components',
		} );
		expect( externalOf( '@wordpress/api-fetch' ) ).toEqual( {
			global: 'wp.apiFetch',
			handle: 'wp-api-fetch',
		} );
		expect( externalOf( '@wordpress/dom-ready' ) ).toEqual( {
			global: 'wp.domReady',
			handle: 'wp-dom-ready',
		} );
	} );

	it( 'bundles packages that WordPress has no script for', () => {
		expect( externalOf( '@wordpress/icons' ) ).toBeNull();
		expect( externalOf( './components/App' ) ).toBeNull();
		expect( externalOf( 'react-grid-layout' ) ).toBeNull();
	} );

	it( 'uses extra externals first', () => {
		const extra = {
			'@rp4wp/admin': {
				global: 'rp4wp.admin',
				handle: 'rp4wp_settings_js',
			},
		};

		expect( externalOf( '@rp4wp/admin', extra ) ).toEqual(
			extra[ '@rp4wp/admin' ]
		);
		expect( externalOf( '@rp4wp/admin' ) ).toBeNull();
	} );
} );

describe( 'assetFile', () => {
	it( 'writes the PHP array that wp_enqueue_script() needs', () => {
		expect(
			assetFile( [ 'react-jsx-runtime', 'wp-components' ], 'abc' )
		).toBe(
			"<?php return array( 'dependencies' => array( 'react-jsx-runtime', 'wp-components' ), 'version' => 'abc' );\n"
		);
	} );
} );
