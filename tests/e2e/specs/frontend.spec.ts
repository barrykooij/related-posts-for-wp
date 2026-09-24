/**
 * Related posts on the front end: after the content, and through the shortcode.
 */
import { test, expect } from '../fixtures';

test.describe( 'Front end', () => {
	test.beforeEach( async ( { rp4wp } ) => {
		await rp4wp.reset();
	} );

	test( 'shows related posts after the content of a single post only', async ( {
		page,
		rp4wp,
	} ) => {
		const posts = await rp4wp.createCorpus();
		await rp4wp.install();
		await rp4wp.link();

		await page.goto( posts[ 'climbing-roses' ].link );
		const block = page.locator( '.rp4wp-related-posts' );
		await expect( block ).toHaveCount( 1 );
		await expect( block.locator( 'h3' ) ).toHaveText( 'Related Posts' );
		await expect( block.locator( 'li' ) ).toHaveCount( 3 );
		await expect( block.locator( 'li p' ).first() ).not.toBeEmpty();

		// Not on the blog index.
		await page.goto( '/' );
		await expect( page.locator( '.rp4wp-related-posts' ) ).toHaveCount( 0 );
	} );

	test( 'the shortcode renders related posts on a page, with limit and offset', async ( {
		page,
		rp4wp,
		requestUtils,
	} ) => {
		const posts = await rp4wp.createCorpus();
		await rp4wp.install();
		await rp4wp.link();

		const id = posts[ 'sourdough-bread' ].id;
		const shortcodePage = await requestUtils.createPage( {
			title: 'Related posts shortcode',
			status: 'publish',
			content: `<!-- wp:shortcode -->[rp4wp id=${ id }]<!-- /wp:shortcode -->\n<!-- wp:shortcode -->[rp4wp id=${ id } limit=1 offset=1]<!-- /wp:shortcode -->`,
		} );

		await page.goto( shortcodePage.link );
		const blocks = page.locator( '.rp4wp-related-posts' );
		await expect( blocks ).toHaveCount( 2 );

		const all = await blocks.nth( 0 ).locator( 'li a' ).allTextContents();
		expect( all ).toHaveLength( 3 );

		// Links are ordered by menu_order, then ID (the relevance order), so the offset picks the second post.
		await expect( blocks.nth( 1 ).locator( 'li a' ) ).toHaveText( [ all[ 1 ] ] );
	} );
} );
