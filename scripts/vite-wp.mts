/**
 * Vite plugin for scripts that WordPress loads with wp_enqueue_script().
 *
 * - React and the `@wordpress` packages are not bundled: they map to the globals WordPress already loads (`React`,
 *   `wp.components`, ...), so every screen shares one copy.
 * - Writes `<entry>.asset.php` next to the build, with the script handles the bundle needs and a content hash, the same
 *   file `@wordpress/scripts` writes. PHP reads it to enqueue the bundle with the right dependencies.
 *
 * The premium plugin has a copy of this file; keep the two the same.
 */
import { createHash } from 'node:crypto';
import type { Plugin } from 'vite';

export interface External {
	/** The global the module is on, for example `wp.components`. */
	global: string;
	/** The handle of the script that sets that global, for example `wp-components`. */
	handle: string;
}

export interface WpScriptsOptions {
	/** More externals, by module ID. The premium plugin maps `@rp4wp/admin` to the free plugin's app. */
	externals?: Record< string, External >;
}

const REACT: Record< string, External > = {
	react: { global: 'React', handle: 'react' },
	'react-dom': { global: 'ReactDOM', handle: 'react-dom' },
	// WordPress 6.6 and newer register this handle.
	'react/jsx-runtime': {
		global: 'ReactJSXRuntime',
		handle: 'react-jsx-runtime',
	},
};

// `@wordpress` packages that WordPress registers no script for, so they are bundled.
const BUNDLED = new Set( [ '@wordpress/icons' ] );

const camelCase = ( name: string ): string =>
	name.replace( /-([a-z])/g, ( _match, letter: string ) =>
		letter.toUpperCase()
	);

/**
 * The global and handle of a module ID, or null when the module is bundled.
 *
 * @param id    The module ID, as written in an import.
 * @param extra More externals.
 * @return The external, or null.
 */
export function externalOf(
	id: string,
	extra: Record< string, External > = {}
): External | null {
	if ( extra[ id ] ) {
		return extra[ id ];
	}

	if ( REACT[ id ] ) {
		return REACT[ id ];
	}

	const match = /^@wordpress\/([a-z0-9-]+)$/.exec( id );

	if ( ! match || BUNDLED.has( id ) ) {
		return null;
	}

	return {
		global: `wp.${ camelCase( match[ 1 ] ) }`,
		handle: `wp-${ match[ 1 ] }`,
	};
}

/**
 * The contents of an asset file.
 *
 * @param handles The script handles.
 * @param version The version.
 * @return PHP code.
 */
export function assetFile( handles: string[], version: string ): string {
	const list = handles.map( ( handle ) => `'${ handle }'` ).join( ', ' );

	return `<?php return array( 'dependencies' => array( ${ list } ), 'version' => '${ version }' );\n`;
}

export function wpScripts( options: WpScriptsOptions = {} ): Plugin {
	const extra = options.externals ?? {};

	return {
		name: 'rp4wp:wp-scripts',
		apply: 'build',

		config() {
			return {
				build: {
					rolldownOptions: {
						external: ( id: string ) => {
							// Sub paths such as react-dom/client have no global of their own.
							if (
								/^(react|react-dom)\/(?!jsx-runtime$)/.test(
									id
								) ||
								/^@wordpress\/[^/]+\//.test( id )
							) {
								throw new Error(
									`Import "${ id }" from its package root, WordPress has no global for it.`
								);
							}

							return null !== externalOf( id, extra );
						},
						output: {
							globals: ( id: string ) =>
								externalOf( id, extra )?.global ?? id,
						},
					},
				},
			};
		},

		// After the other plugins, so the styles are in the bundle when the version is made.
		generateBundle: {
			order: 'post',
			handler( _options, bundle ) {
				for ( const chunk of Object.values( bundle ) ) {
					if ( 'chunk' !== chunk.type || ! chunk.isEntry ) {
						continue;
					}

					const handles = chunk.imports
						.map( ( id ) => externalOf( id, extra )?.handle )
						.filter(
							( handle ): handle is string => undefined !== handle
						)
						.sort();

					// The version changes with the script and with its styles.
					const hash = createHash( 'sha256' ).update( chunk.code );

					for ( const asset of Object.values( bundle ) ) {
						if (
							'asset' === asset.type &&
							asset.fileName.endsWith( '.css' )
						) {
							hash.update( asset.source );
						}
					}

					this.emitFile( {
						type: 'asset',
						fileName: chunk.fileName.replace(
							/\.js$/,
							'.asset.php'
						),
						source: assetFile(
							[ ...new Set( handles ) ],
							hash.digest( 'hex' ).slice( 0, 20 )
						),
					} );
				}
			},
		},
	};
}
