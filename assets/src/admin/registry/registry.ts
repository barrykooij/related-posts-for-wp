/**
 * The registry the admin app is built from. The free plugin registers its own screens here, and the premium plugin
 * adds its screens and field types through the same functions (window.rp4wp.admin).
 */
import type { ComponentType } from 'react';

export interface Route {
	/** The part of the URL after `#/`, for example `general`. */
	path: string;
	/** The tab label. */
	title: string;
	/** Tabs are sorted by this, low to high. */
	order: number;
	/** The screen. */
	component: ComponentType;
}

export interface FieldProps< Value = unknown > {
	id: string;
	label: string;
	value: Value;
	onChange: ( value: Value ) => void;
}

export interface SetupStep {
	id: string;
	order: number;
	component: ComponentType;
}

export interface Snapshot {
	routes: Route[];
	fieldTypes: Readonly<
		Record< string, ComponentType< FieldProps< any > > >
	>;
	setupSteps: SetupStep[];
}

type Listener = () => void;

const byOrder = < Item extends { order: number } >(
	a: Item,
	b: Item
): number => a.order - b.order;

export function createRegistry() {
	let snapshot: Snapshot = { routes: [], fieldTypes: {}, setupSteps: [] };
	const listeners = new Set< Listener >();

	const update = ( next: Partial< Snapshot > ): void => {
		// A new object on every change, so React sees it (useSyncExternalStore compares by identity).
		snapshot = { ...snapshot, ...next };
		listeners.forEach( ( listener ) => listener() );
	};

	return {
		/**
		 * Add a screen, or replace the one with the same path.
		 *
		 * @param route The screen.
		 */
		registerRoute( route: Route ): void {
			const routes = snapshot.routes.filter(
				( existing ) => existing.path !== route.path
			);

			update( { routes: [ ...routes, route ].sort( byOrder ) } );
		},

		/**
		 * Add a field type, or replace the one with the same name.
		 *
		 * @param type      The type, as used in the settings schema.
		 * @param component The control.
		 */
		registerFieldType< Value >(
			type: string,
			component: ComponentType< FieldProps< Value > >
		): void {
			update( {
				fieldTypes: { ...snapshot.fieldTypes, [ type ]: component },
			} );
		},

		/**
		 * Add a part to the first-run card, or replace the one with the same ID.
		 *
		 * @param step The part.
		 */
		registerSetupStep( step: SetupStep ): void {
			const steps = snapshot.setupSteps.filter(
				( existing ) => existing.id !== step.id
			);

			update( { setupSteps: [ ...steps, step ].sort( byOrder ) } );
		},

		getSnapshot(): Snapshot {
			return snapshot;
		},

		subscribe( listener: Listener ): () => void {
			listeners.add( listener );

			return () => {
				listeners.delete( listener );
			};
		},
	};
}

export type Registry = ReturnType< typeof createRegistry >;

/** The registry of this page. */
export const registry = createRegistry();
