/**
 * The admin app (assets/src/admin) on Settings > Related Posts: React and the WordPress packages of the site, the API
 * for other scripts, and the REST API it talks to.
 */
import { test, expect } from '../fixtures';

test.describe( 'Admin app', () => {
	test.beforeEach( async ( { rp4wp } ) => {
		await rp4wp.reset();
		await rp4wp.install();
	} );

	test( 'mounts with React and the WordPress components of the site', async ( {
		page,
		admin,
	} ) => {
		const errors: string[] = [];
		page.on( 'pageerror', ( error ) => errors.push( error.message ) );

		await admin.visitAdminPage( 'options-general.php', 'page=rp4wp' );

		await expect(
			page.getByRole( 'heading', { name: 'Related Posts', level: 1 } )
		).toBeVisible();
		await expect(
			page.getByRole( 'heading', { name: 'General', level: 2 } )
		).toBeVisible();
		// The 2.x screen and its scripts are gone.
		await expect( page.locator( '#rp4wp-settings-form' ) ).toHaveCount( 0 );
		await expect(
			page.locator( 'script[src*="assets/js/settings"]' )
		).toHaveCount( 0 );
		await expect( page ).toHaveURL( /#\/general$/ );
		// The first REST responses come with the page.
		const preloaded = await page
			.locator( 'script#wp-api-fetch-js-after' )
			.textContent();
		expect( preloaded ).toMatch( /rp4wp\\?\/v1\\?\/settings/ );
		expect( preloaded ).toMatch( /rp4wp\\?\/v1\\?\/install/ );
		expect(
			await page.evaluate( () => window.rp4wp?.admin?.apiVersion )
		).toBe( 1 );
		expect( errors ).toEqual( [] );
	} );

	test( 'the REST API needs the nonce of the page, not only the login cookie', async ( {
		page,
		admin,
	} ) => {
		await admin.visitAdminPage( 'options-general.php', 'page=rp4wp' );

		// The same browser, with the admin's login cookie, but without the nonce: a forged request from another site.
		const forged = await page.request.get( '/wp-json/rp4wp/v1/settings' );
		expect( forged.status() ).toBe( 401 );

		const nonce = await page.evaluate(
			// @ts-expect-error -- wp.apiFetch is a WordPress global.
			() => window.wp.apiFetch.nonceMiddleware.nonce as string
		);
		const allowed = await page.request.get( '/wp-json/rp4wp/v1/settings', {
			headers: { 'X-WP-Nonce': nonce },
		} );
		expect( allowed.status() ).toBe( 200 );
		expect(
			( await allowed.json() ).pages.map(
				( item: { id: string } ) => item.id
			)
		).toEqual( [ 'general', 'styling', 'misc' ] );
	} );
} );
