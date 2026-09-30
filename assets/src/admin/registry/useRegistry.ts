import { useSyncExternalStore } from '@wordpress/element';
import { registry, type Snapshot } from './registry';

/**
 * What is registered, updated when something is registered later.
 *
 * @return The registered screens, field types and setup steps.
 */
export function useRegistry(): Snapshot {
	return useSyncExternalStore( registry.subscribe, registry.getSnapshot );
}
