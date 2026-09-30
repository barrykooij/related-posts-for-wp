import { describe, expect, it } from 'vitest';
import { pathOf } from './useHashPath';

describe( 'pathOf', () => {
	it.each( [
		[ '', '' ],
		[ '#', '' ],
		[ '#/', '' ],
		[ '#/general', 'general' ],
		[ '#general', 'general' ],
		[ '#/general/post/', 'general/post' ],
	] )( 'reads %j as %j', ( hash, path ) => {
		expect( pathOf( hash ) ).toBe( path );
	} );
} );
