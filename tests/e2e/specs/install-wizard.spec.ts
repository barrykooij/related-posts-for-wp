/**
 * The installation wizard: activation starts it, it caches and links every post.
 */
import { test, expect } from '../fixtures';

test.describe( 'Installation wizard', () => {
	test.beforeEach( async ( { rp4wp } ) => {
		await rp4wp.reset();
	} );

	test( 'activating the plugin runs the wizard, which caches and links every post', async ( {
		page,
		admin,
		rp4wp,
	} ) => {
		// Content exists before the plugin, like on a real site.
		const posts = await rp4wp.createCorpus();

		await admin.visitAdminPage( 'plugins.php' );
		await page
			.getByRole( 'link', {
				name: 'Activate Related Posts for WordPress',
				exact: true,
			} )
			.click();

		// The plugin sends the admin to its wizard on the first admin page after activation.
		await expect( page ).toHaveURL( /page=rp4wp_install/ );
		await expect(
			page.getByRole( 'heading', {
				name: 'Related Posts for WordPress Installation',
			} )
		).toBeVisible();

		// Step 1 caches the words of every post and moves on by itself.
		await expect( page ).toHaveURL( /step=2/, { timeout: 30_000 } );
		await expect(
			page.getByText( 'Great! All your posts were successfully cached!' )
		).toBeVisible();

		// Step 2 links the posts.
		await page.locator( '#rp4wp_related_posts_amount' ).fill( '2' );
		await page.getByRole( 'link', { name: 'Link now' } ).click();
		await expect( page ).toHaveURL( /step=3/, { timeout: 30_000 } );
		await expect(
			page.getByText( "That's it, you're good to go!" )
		).toBeVisible();

		// Every post now shows two related posts from its own topic.
		await page.goto( posts[ 'sourdough-bread' ].link );
		const related = page.locator( '.rp4wp-related-posts li' );
		await expect( related ).toHaveCount( 2 );
		for ( const item of await related.all() ) {
			await expect( item ).toContainText( /bread/i );
		}

		await page.goto( posts[ 'pruning-roses' ].link );
		await expect( page.locator( '.rp4wp-related-posts li' ) ).toHaveCount(
			2
		);
		await expect( page.locator( '.rp4wp-related-posts' ) ).toContainText(
			/roses/i
		);
	} );

	test( 'the linking step can be skipped', async ( {
		page,
		admin,
		rp4wp,
	} ) => {
		const posts = await rp4wp.createCorpus();

		await admin.visitAdminPage( 'plugins.php' );
		await page
			.getByRole( 'link', {
				name: 'Activate Related Posts for WordPress',
				exact: true,
			} )
			.click();
		await expect( page ).toHaveURL( /step=2/, { timeout: 30_000 } );

		await page.getByRole( 'link', { name: 'Skip linking' } ).click();
		await expect(
			page.getByText( "That's it, you're good to go!" )
		).toBeVisible();

		await page.goto( posts[ 'sourdough-bread' ].link );
		await expect( page.locator( '.rp4wp-related-posts' ) ).toHaveCount( 0 );
	} );
} );
