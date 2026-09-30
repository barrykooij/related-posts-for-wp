import { cleanup } from '@testing-library/react';
import { afterEach } from 'vitest';

// Testing Library only cleans up by itself when Vitest runs with globals.
afterEach( () => {
	cleanup();
} );

// jsdom has no matchMedia, which responsive WordPress components (Card, Flex) use.
if ( ! window.matchMedia ) {
	window.matchMedia = ( query: string ): MediaQueryList => ( {
		matches: false,
		media: query,
		onchange: null,
		addListener: () => {},
		removeListener: () => {},
		addEventListener: () => {},
		removeEventListener: () => {},
		dispatchEvent: () => false,
	} );
}
