import { useSyncExternalStore } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { registry, type Snapshot } from './registry';

/**
 * What is registered, updated when something is registered later.
 *
 * @return The registered screens, field types and setup steps.
 */
export function useRegistry(): Snapshot {
	return useSyncExternalStore( registry.subscribe, registry.getSnapshot );
}

/**
 * The title of the tab where posts are linked after the first run: the Installer tab, or the tab that took its place.
 *
 * @return The title.
 */
export function useLinkingTab(): string {
	const { routes } = useRegistry();

	return (
		routes.find( ( route ) => route.linking )?.title ??
		__( 'Installer', 'related-posts-for-wp' )
	);
}
