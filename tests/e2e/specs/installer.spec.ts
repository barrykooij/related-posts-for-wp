/**
 * The installer on the settings screen: activation opens it, it runs in the background, the screen follows it.
 */
import type { Page } from '@playwright/test';
import { test, expect } from '../fixtures';

/**
 * Open the settings screen at a route of the app.
 *
 * @param page  The page.
 * @param route The route, for example `installer`.
 */
async function openSettings( page: Page, route = '' ): Promise< void > {
	await page.goto(
		`/wp-admin/options-general.php?page=rp4wp${ route ? `#/${ route }` : '' }`
	);
}

test.describe( 'Installer', () => {
	// Background requests, and the screen that waits for a stalled one, take their time.
	test.describe.configure( { timeout: 120_000 } );

	test.beforeEach( async ( { rp4wp } ) => {
		await rp4wp.reset();
	} );

	test( 'activating the plugin opens the first-run card, and starting it links every post', async ( {
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

		// The plugin sends the admin to the installer on the first admin page after activation.
		await expect( page ).toHaveURL( /page=rp4wp#\/setup$/ );
		await expect(
			page.getByRole( 'heading', {
				name: "Let's link your related posts",
			} )
		).toBeVisible();

		await page
			.getByRole( 'spinbutton', { name: 'Related posts per post' } )
			.fill( '2' );
		await page
			.getByRole( 'button', { name: 'Start', exact: true } )
			.click();

		await expect(
			page.getByRole( 'heading', {
				name: 'All set! Your posts are linked.',
			} )
		).toBeVisible( { timeout: 60_000 } );

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

		// The chosen number is the setting for posts published later, and the card is gone once closed.
		await openSettings( page );
		await expect(
			page.getByRole( 'spinbutton', { name: 'Amount of Posts' } )
		).toHaveValue( '2' );
		await page.getByRole( 'button', { name: 'Close' } ).click();
		await page.reload();
		await expect(
			page.getByRole( 'heading', { name: 'General', level: 2 } )
		).toBeVisible();
		await expect(
			page.getByRole( 'heading', {
				name: 'All set! Your posts are linked.',
			} )
		).toHaveCount( 0 );
	} );

	test( 'linking can be left for later', async ( { page, rp4wp } ) => {
		const posts = await rp4wp.createCorpus();
		await rp4wp.install();

		await openSettings( page, 'setup' );
		await page.getByLabel( 'Link the posts right away' ).uncheck();
		await page
			.getByRole( 'button', { name: 'Start', exact: true } )
			.click();

		await expect(
			page.getByRole( 'heading', {
				name: 'All set! Your posts are read.',
			} )
		).toBeVisible( { timeout: 60_000 } );

		await page.goto( posts[ 'sourdough-bread' ].link );
		await expect( page.locator( '.rp4wp-related-posts' ) ).toHaveCount( 0 );
	} );

	test( 'the installation goes on after the admin leaves the screen', async ( {
		page,
		rp4wp,
	} ) => {
		const posts = await rp4wp.createCorpus();
		await rp4wp.install();

		await openSettings( page, 'setup' );
		const started = page.waitForResponse(
			( response ) =>
				response.url().includes( 'rp4wp/v1/install' ) &&
				response.request().method() === 'POST'
		);
		await page
			.getByRole( 'button', { name: 'Start', exact: true } )
			.click();
		expect( ( await started ).status() ).toBe( 201 );

		// Away from the screen right away; nothing on the page drives the installation any more.
		await page.goto( '/wp-admin/index.php' );

		await expect
			.poll(
				async () => {
					await page.goto( posts[ 'sourdough-bread' ].link );

					return page.locator( '.rp4wp-related-posts li' ).count();
				},
				{ timeout: 60_000, intervals: [ 2_000 ] }
			)
			.toBe( 3 );
	} );

	test( 'when background requests do not run, the screen runs the installation itself', async ( {
		page,
		rp4wp,
	} ) => {
		await rp4wp.createCorpus();
		await rp4wp.install();
		await rp4wp.installerMode( { background: false } );

		await openSettings( page, 'setup' );
		await page
			.getByRole( 'button', { name: 'Start', exact: true } )
			.click();

		await expect(
			page
				.locator( '.rp4wp-progress' )
				.getByText( 'Background processing is slow on this site' )
		).toBeVisible( { timeout: 60_000 } );
		await expect(
			page.getByRole( 'heading', {
				name: 'All set! Your posts are linked.',
			} )
		).toBeVisible( { timeout: 60_000 } );
	} );

	test( 'an installation can be cancelled and resumed', async ( {
		page,
		admin,
		rp4wp,
	} ) => {
		await rp4wp.createCorpus();
		await rp4wp.install();
		await rp4wp.installerMode( { slow: true } );

		await openSettings( page, 'setup' );
		await page
			.getByRole( 'button', { name: 'Start', exact: true } )
			.click();
		await expect(
			page.getByRole( 'heading', { name: 'Linking your related posts' } )
		).toBeVisible();

		// Other admin screens say it runs.
		await admin.visitAdminPage( 'index.php' );
		await expect(
			page.getByText(
				/Related Posts for WordPress is linking your posts in the background/
			)
		).toBeVisible();
		await page.getByRole( 'link', { name: 'View progress' } ).click();

		await page
			.getByRole( 'button', { name: 'Cancel', exact: true } )
			.click();
		await page
			.getByRole( 'dialog' )
			.getByRole( 'button', { name: 'Cancel installation' } )
			.click();
		await expect(
			page.getByRole( 'heading', {
				name: 'The installation was cancelled',
			} )
		).toBeVisible();

		await page.getByRole( 'button', { name: 'Resume' } ).click();
		await expect(
			page.getByRole( 'heading', { name: 'Linking your related posts' } )
		).toBeVisible();
	} );

	test( 'rebuilding asks first, then removes the links and links every post again', async ( {
		page,
		rp4wp,
	} ) => {
		const posts = await rp4wp.createCorpus();
		await rp4wp.install();
		await rp4wp.link();

		// The "Rebuild" link of the 2.x settings screen opens the installer, and removes nothing by itself.
		await page.goto(
			'/wp-admin/?page=rp4wp_install&reinstall=1&rp4wp_nonce=from-2x'
		);
		await expect( page ).toHaveURL( /page=rp4wp#\/installer$/ );

		await page
			.getByRole( 'button', { name: 'Rebuild everything' } )
			.click();
		const dialog = page.getByRole( 'dialog' );
		await expect( dialog ).toContainText( 'This can not be undone.' );
		await dialog.getByRole( 'button', { name: 'Keep my links' } ).click();
		await expect( dialog ).toHaveCount( 0 );

		await page
			.getByRole( 'button', { name: 'Rebuild everything' } )
			.click();
		await page
			.getByRole( 'dialog' )
			.getByRole( 'button', { name: 'Remove links and rebuild' } )
			.click();

		await expect(
			page.getByRole( 'heading', {
				name: 'All set! Your posts are linked.',
			} )
		).toBeVisible( { timeout: 60_000 } );

		await page.goto( posts[ 'sourdough-bread' ].link );
		await expect( page.locator( '.rp4wp-related-posts li' ) ).toHaveCount(
			3
		);
	} );
} );
