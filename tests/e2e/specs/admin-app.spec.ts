/**
 * The admin app (assets/src/admin), on the page it has while it is being built.
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

		await admin.visitAdminPage( 'admin.php', 'page=rp4wp_app' );

		await expect(
			page.getByRole( 'heading', { name: 'Related Posts', level: 1 } )
		).toBeVisible();
		await expect(
			page.getByRole( 'heading', { name: 'General', level: 2 } )
		).toBeVisible();
		await expect( page ).toHaveURL( /#\/general$/ );
		expect(
			await page.evaluate( () => window.rp4wp?.admin?.apiVersion )
		).toBe( 1 );
		expect( errors ).toEqual( [] );
	} );
} );
