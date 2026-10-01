/**
 * The REST API of the plugin. apiFetch sends the nonce of the page, and answers the first requests from what the page
 * preloaded.
 */
import apiFetch from '@wordpress/api-fetch';
import type {
	InstallRequest,
	InstallState,
	SettingsPage,
	Values,
} from './types';

const BASE = '/rp4wp/v1';

export function getSettings(): Promise< { pages: SettingsPage[] } > {
	return apiFetch( { path: `${ BASE }/settings` } );
}

export function savePage(
	page: string,
	values: Values
): Promise< { page: string; values: Values } > {
	return apiFetch( {
		path: `${ BASE }/settings/${ encodeURIComponent( page ) }`,
		method: 'PUT',
		data: { values },
	} );
}

export function getInstall(): Promise< InstallState > {
	return apiFetch( { path: `${ BASE }/install` } );
}

export function startInstall(
	request: InstallRequest
): Promise< InstallState > {
	return apiFetch( {
		path: `${ BASE }/install`,
		method: 'POST',
		data: request,
	} );
}

export function cancelInstall(): Promise< InstallState > {
	return apiFetch( { path: `${ BASE }/install`, method: 'DELETE' } );
}

export function retryInstall(): Promise< InstallState > {
	return apiFetch( { path: `${ BASE }/install/retry`, method: 'POST' } );
}

export function tickInstall(): Promise< InstallState > {
	return apiFetch( { path: `${ BASE }/install/tick`, method: 'POST' } );
}

/**
 * The message of an error that apiFetch threw.
 *
 * @param error    What was thrown.
 * @param fallback The message when it has none.
 * @return The message.
 */
export function errorMessage( error: unknown, fallback: string ): string {
	if (
		error &&
		typeof error === 'object' &&
		'message' in error &&
		typeof ( error as { message: unknown } ).message === 'string'
	) {
		return ( error as { message: string } ).message;
	}

	return fallback;
}
