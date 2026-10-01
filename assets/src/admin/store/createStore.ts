import { useSyncExternalStore } from '@wordpress/element';

export interface Store< State > {
	getState: () => State;
	setState: ( next: Partial< State > ) => void;
	subscribe: ( listener: () => void ) => () => void;
}

/**
 * A small store: state that React components follow with useStore(). Each change makes a new state object.
 *
 * @param initial The first state.
 * @return The store.
 */
export function createStore< State extends object >(
	initial: State
): Store< State > {
	let state = initial;
	const listeners = new Set< () => void >();

	return {
		getState: () => state,
		setState( next ) {
			state = { ...state, ...next };
			listeners.forEach( ( listener ) => listener() );
		},
		subscribe( listener ) {
			listeners.add( listener );

			return () => {
				listeners.delete( listener );
			};
		},
	};
}

/**
 * The state of a store, updated when it changes.
 *
 * @param store The store.
 * @return The state.
 */
export function useStore< State extends object >(
	store: Store< State >
): State {
	return useSyncExternalStore( store.subscribe, store.getState );
}
