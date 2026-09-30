import { describe, expect, it, vi } from 'vitest';
import { createRegistry } from './registry';

const Screen = () => null;

describe( 'registry', () => {
	it( 'sorts routes by order and replaces a route with the same path', () => {
		const registry = createRegistry();

		registry.registerRoute( {
			path: 'styling',
			title: 'Styling',
			order: 20,
			component: Screen,
		} );
		registry.registerRoute( {
			path: 'general',
			title: 'General',
			order: 10,
			component: Screen,
		} );
		registry.registerRoute( {
			path: 'styling',
			title: 'Layout',
			order: 5,
			component: Screen,
		} );

		expect(
			registry.getSnapshot().routes.map( ( route ) => route.title )
		).toEqual( [ 'Layout', 'General' ] );
	} );

	it( 'tells subscribers about every change, with a new snapshot', () => {
		const registry = createRegistry();
		const listener = vi.fn();
		const before = registry.getSnapshot();
		const unsubscribe = registry.subscribe( listener );

		registry.registerFieldType( 'range', Screen );

		expect( listener ).toHaveBeenCalledTimes( 1 );
		expect( registry.getSnapshot() ).not.toBe( before );
		expect( registry.getSnapshot().fieldTypes.range ).toBe( Screen );

		unsubscribe();
		registry.registerSetupStep( {
			id: 'post-types',
			order: 10,
			component: Screen,
		} );

		expect( listener ).toHaveBeenCalledTimes( 1 );
		expect( registry.getSnapshot().setupSteps ).toHaveLength( 1 );
	} );
} );
