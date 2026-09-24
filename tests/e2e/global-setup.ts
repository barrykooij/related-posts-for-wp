/**
 * Logs in once and stores the admin session for all specs.
 */
import { request, type FullConfig } from '@playwright/test';
import { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

export default async function globalSetup(
	config: FullConfig
): Promise< void > {
	const { storageState, baseURL } = config.projects[ 0 ].use;
	const storageStatePath =
		typeof storageState === 'string' ? storageState : undefined;

	const requestContext = await request.newContext( { baseURL } );
	const requestUtils = new RequestUtils( requestContext, {
		storageStatePath,
	} );

	await requestUtils.setupRest();
	await requestContext.dispose();
}
