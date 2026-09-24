/**
 * The classic related posts widget, in a classic theme with widget areas.
 */
import { test, expect } from '../fixtures';

test.describe( 'Related posts widget', () => {
	let previousTheme = '';

	test.beforeEach( async ( { rp4wp, requestUtils } ) => {
		await rp4wp.reset();
		previousTheme = await rp4wp.activeTheme();
		await requestUtils.activateTheme( 'twentytwentyone' );
	} );

	test.afterEach( async ( { rp4wp, requestUtils } ) => {
		await rp4wp.reset();
		await requestUtils.activateTheme( previousTheme );
	} );

	test( 'shows related posts on a single post, not on the blog index', async ( {
		page,
		rp4wp,
	} ) => {
		const posts = await rp4wp.createCorpus();
		await rp4wp.install();
		await rp4wp.link();
		await rp4wp.addWidget( 'sidebar-1' );

		await page.goto( posts[ 'rose-feeding' ].link );
		const widget = page.locator( '.widget-area .rp4wp-related-posts' );
		await expect( widget ).toHaveCount( 1 );
		await expect( widget.locator( 'li' ) ).toHaveCount( 3 );
		await expect( widget ).toContainText( /roses/i );

		// The widget skips the blog index.
		await page.goto( '/' );
		await expect(
			page.locator( '.widget-area .rp4wp-related-posts' )
		).toHaveCount( 0 );
	} );
} );
