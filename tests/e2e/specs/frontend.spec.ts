/**
 * Related posts on the front end: after the content, and through the shortcode.
 */
import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { crc32, deflateSync } from 'node:zlib';
import type { Page } from '@playwright/test';
import { test, expect } from '../fixtures';

/**
 * Write a plain grey PNG, so posts can get a featured image without a binary fixture.
 *
 * @param width  The width.
 * @param height The height.
 * @return The path of the file.
 */
function png( width: number, height: number ): string {
	const chunk = ( type: string, data: Buffer ) => {
		const length = Buffer.alloc( 4 );
		length.writeUInt32BE( data.length );
		const body = Buffer.concat( [ Buffer.from( type ), data ] );
		const crc = Buffer.alloc( 4 );
		crc.writeUInt32BE( crc32( body ) );

		return Buffer.concat( [ length, body, crc ] );
	};

	const header = Buffer.alloc( 13 );
	header.writeUInt32BE( width, 0 );
	header.writeUInt32BE( height, 4 );
	header[ 8 ] = 8; // Bit depth.
	header[ 9 ] = 2; // Truecolour.

	// Each row: filter type 0, then grey RGB pixels.
	const row = Buffer.concat( [
		Buffer.from( [ 0 ] ),
		Buffer.alloc( width * 3, 0x99 ),
	] );
	const file = join( mkdtempSync( join( tmpdir(), 'rp4wp-' ) ), 'grey.png' );

	writeFileSync(
		file,
		Buffer.concat( [
			Buffer.from( [ 0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a ] ),
			chunk( 'IHDR', header ),
			chunk(
				'IDAT',
				deflateSync( Buffer.concat( Array( height ).fill( row ) ) )
			),
			chunk( 'IEND', Buffer.alloc( 0 ) ),
		] )
	);

	return file;
}

/**
 * Where the image and the text of the first related post are, and how they are laid out.
 *
 * @param page The page.
 */
async function firstPostLayout( page: Page ) {
	return page
		.locator( '.rp4wp-related-posts li' )
		.first()
		.evaluate( ( li ) => {
			const image = li.querySelector(
				'.rp4wp-related-post-image'
			) as HTMLElement;
			const content = li.querySelector(
				'.rp4wp-related-post-content'
			) as HTMLElement;
			const box = ( element: HTMLElement ) =>
				element.getBoundingClientRect().toJSON() as DOMRect;

			return {
				display: getComputedStyle( li ).display,
				floats: [ li, image, content ].map(
					( element ) => getComputedStyle( element ).float
				),
				li: box( li ),
				image: box( image ),
				content: box( content ),
			};
		} );
}

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
		await expect( blocks.nth( 1 ).locator( 'li a' ) ).toHaveText( [
			all[ 1 ],
		] );
	} );

	test( 'lays a related post out as a row, with the image where the text starts', async ( {
		page,
		rp4wp,
		requestUtils,
	} ) => {
		const posts = await rp4wp.createCorpus();
		await rp4wp.install();
		await rp4wp.link();

		const image = await requestUtils.uploadMedia( png( 300, 200 ) );
		for ( const post of Object.values( posts ) ) {
			await requestUtils.rest( {
				method: 'POST',
				path: `/wp/v2/posts/${ post.id }`,
				data: { featured_media: image.id },
			} );
		}
		await requestUtils.rest( {
			method: 'PUT',
			path: '/rp4wp/v1/settings/styling',
			data: { values: { display_image: true } },
		} );

		await page.goto( posts[ 'climbing-roses' ].link );
		const ltr = await firstPostLayout( page );

		expect( ltr.display ).toBe( 'flex' );
		expect( ltr.floats ).toEqual( [ 'none', 'none', 'none' ] );
		expect( ltr.image.left ).toBeCloseTo( ltr.li.left, 0 );
		expect( ltr.content.left ).toBeGreaterThan( ltr.image.right );
		expect( ltr.image.width ).toBeLessThanOrEqual(
			ltr.li.width * 0.35 + 1
		);

		// The same CSS mirrors itself for right-to-left languages.
		await page.evaluate( () => {
			document.documentElement.dir = 'rtl';
		} );
		const rtl = await firstPostLayout( page );

		expect( rtl.image.right ).toBeCloseTo( rtl.li.right, 0 );
		expect( rtl.content.right ).toBeLessThan( rtl.image.left );
	} );

	test( 'styles the related posts of the shortcode on a page', async ( {
		page,
		rp4wp,
		requestUtils,
	} ) => {
		const posts = await rp4wp.createCorpus();
		await rp4wp.install();
		await rp4wp.link();

		const shortcodePage = await requestUtils.createPage( {
			title: 'Styled related posts',
			status: 'publish',
			content: `<!-- wp:shortcode -->[rp4wp id=${ posts[ 'rye-bread' ].id }]<!-- /wp:shortcode -->`,
		} );

		await page.goto( shortcodePage.link );
		await expect(
			page.locator( '.rp4wp-related-posts li' ).first()
		).toHaveCSS( 'display', 'flex' );
		await expect(
			page.locator( '.rp4wp-related-posts ul' ).first()
		).toHaveCSS( 'list-style-type', 'none' );
	} );
} );
