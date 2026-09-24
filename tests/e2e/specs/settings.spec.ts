/**
 * The settings screen.
 */
import { test, expect } from '../fixtures';

test.describe( 'Settings', () => {
	test.beforeEach( async ( { rp4wp } ) => {
		await rp4wp.reset();
	} );

	test( 'settings are saved and change the related posts on the front end', async ( {
		page,
		admin,
		rp4wp,
	} ) => {
		const posts = await rp4wp.createCorpus();
		await rp4wp.install();
		await rp4wp.link();

		await admin.visitAdminPage( 'options-general.php', 'page=rp4wp' );
		await expect(
			page.getByRole( 'heading', {
				name: 'Related Posts for WordPress',
				exact: true,
			} )
		).toBeVisible();

		await page.locator( '#heading_text' ).fill( 'You might also like' );
		await page.locator( '#excerpt_length' ).fill( '0' );
		await page.getByRole( 'button', { name: 'Save Changes' } ).click();

		await expect( page.getByText( 'Settings saved.' ) ).toBeVisible();
		await expect( page.locator( '#heading_text' ) ).toHaveValue(
			'You might also like'
		);

		await page.goto( posts[ 'banana-bread' ].link );
		const block = page.locator( '.rp4wp-related-posts' );
		await expect( block.locator( 'h3' ) ).toHaveText(
			'You might also like'
		);
		await expect( block.locator( 'li p' ) ).toHaveCount( 0 );
	} );

	test( 'the rebuild button restarts the wizard', async ( {
		page,
		admin,
		rp4wp,
	} ) => {
		await rp4wp.createCorpus();
		await rp4wp.install();
		await rp4wp.link();

		await admin.visitAdminPage( 'options-general.php', 'page=rp4wp' );
		await page.getByRole( 'link', { name: 'Misc', exact: true } ).click();
		await page
			.getByRole( 'link', { name: 'Rebuild', exact: true } )
			.click();

		// The reinstall removes all links and starts over at step 1, which moves on to step 2 by itself.
		await expect( page ).toHaveURL( /page=rp4wp_install/ );
		await expect( page ).toHaveURL( /step=2/, { timeout: 30_000 } );
	} );
} );
