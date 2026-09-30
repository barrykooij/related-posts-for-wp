import { useCallback, useSyncExternalStore } from '@wordpress/element';

const subscribe = ( listener: () => void ): ( () => void ) => {
	window.addEventListener( 'hashchange', listener );

	return () => window.removeEventListener( 'hashchange', listener );
};

/**
 * The path in the URL hash: `general` for `#/general`, or an empty string.
 *
 * @param hash The hash, with its `#`.
 * @return The path.
 */
export function pathOf( hash: string ): string {
	return hash.replace( /^#\/?/, '' ).replace( /\/+$/, '' );
}

/**
 * The current path, and a function that goes to another one.
 *
 * @return The path and the function.
 */
export function useHashPath(): [ string, ( path: string ) => void ] {
	const path = useSyncExternalStore( subscribe, () =>
		pathOf( window.location.hash )
	);
	const navigate = useCallback( ( next: string ) => {
		window.location.hash = `#/${ next }`;
	}, [] );

	return [ path, navigate ];
}
