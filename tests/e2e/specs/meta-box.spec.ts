/**
 * Managing related posts by hand from the post editor.
 */
import { test, expect } from '../fixtures';
import type { Page } from '@playwright/test';

/**
 * Open the meta box area of the block editor. Newer WordPress versions show meta boxes in a collapsible pane below
 * the content; older versions always show them.
 * @param page
 */
async function openMetaBoxes( page: Page ): Promise< void > {
	const toggle = page.getByRole( 'button', { name: 'Meta Boxes' } );
	if (
		( await toggle.count() ) > 0 &&
		( await toggle.getAttribute( 'aria-expanded' ) ) === 'false'
	) {
		// The pane's resize handle covers the middle of the toggle, so click on its label at the left.
		await toggle.click( { position: { x: 30, y: 10 } } );
	}
	await page
		.locator( '#rp4wp_metabox_related_posts' )
		.scrollIntoViewIfNeeded();
}

/**
 * The titles in the related posts meta box, in order.
 * @param page
 */
async function metaBoxTitles( page: Page ): Promise< string[] > {
	return page
		.locator(
			'#rp4wp_metabox_related_posts .rp4wp_table_manage .row-title'
		)
		.allTextContents();
}

test.describe( 'Related posts meta box', () => {
	// Some WordPress versions show meta boxes in a short pane below the editor. A tall window keeps every row visible,
	// which dragging rows to reorder them needs.
	test.use( { viewport: { width: 1280, height: 1400 } } );

	test.beforeEach( async ( { rp4wp } ) => {
		await rp4wp.reset();
	} );

	test( 'links a post through the link screen, reorders and unlinks it', async ( {
		page,
		admin,
		rp4wp,
	} ) => {
		const posts = await rp4wp.createCorpus();
		await rp4wp.install();
		await rp4wp.link();

		await admin.editPost( posts[ 'rye-bread' ].id );
		await openMetaBoxes( page );
		const metaBox = page.locator( '#rp4wp_metabox_related_posts' );
		await expect( metaBox ).toBeVisible();
		const before = await metaBoxTitles( page );
		expect( before ).toHaveLength( 3 );

		// Add a post from the other topic through the link screen, using its search.
		await metaBox
			.getByRole( 'link', { name: 'Add Related Posts' } )
			.click();
		await expect( page ).toHaveURL( /page=rp4wp_link_related/ );

		// The screen opens with suggested posts; searching is done in the "All Posts" view.
		await page
			.locator( '.subsubsub' )
			.getByRole( 'link', { name: 'All Posts' } )
			.click();
		await page.locator( '#sp-search-search-input' ).fill( 'Feeding roses' );
		await page.getByRole( 'button', { name: 'Search' } ).click();
		const row = page.getByRole( 'row', {
			name: /Feeding roses in summer/,
		} );
		await row.hover();
		await row.getByRole( 'link', { name: 'Link Post' } ).click();

		// Back in the editor, the new post is at the end of the list.
		await expect( page ).toHaveURL( /post\.php\?post=\d+&action=edit/ );
		await openMetaBoxes( page );
		await expect
			.poll( () => metaBoxTitles( page ) )
			.toEqual( [ ...before, 'Feeding roses in summer' ] );

		// Drag the new post to the top.
		const rows = metaBox.locator( '.rp4wp_table_manage tbody tr' );
		await rows.last().scrollIntoViewIfNeeded();
		const last = await rows.last().boundingBox();
		const first = await rows.first().boundingBox();
		if ( ! last || ! first ) {
			throw new Error( 'Meta box rows are not visible.' );
		}
		const sorted = page.waitForResponse(
			( response ) =>
				response.url().includes( 'admin-ajax.php' ) &&
				response
					.request()
					.postData()
					?.includes( 'rp4wp_related_sort' ) === true
		);
		await page.mouse.move( last.x + 20, last.y + last.height / 2 );
		await page.mouse.down();
		await page.mouse.move( first.x + 20, first.y + first.height / 2, {
			steps: 10,
		} );
		await page.mouse.move( first.x + 20, first.y + 2, { steps: 5 } );
		await page.mouse.up();
		await sorted;

		await page.reload();
		await openMetaBoxes( page );
		await expect
			.poll( () => metaBoxTitles( page ) )
			.toEqual( [ 'Feeding roses in summer', ...before ] );

		// Unlink it again; the plugin asks for confirmation.
		page.once( 'dialog', ( dialog ) => dialog.accept() );
		const unlinked = page.waitForResponse(
			( response ) =>
				response.url().includes( 'admin-ajax.php' ) &&
				response
					.request()
					.postData()
					?.includes( 'rp4wp_delete_link' ) === true
		);
		const newRow = metaBox.getByRole( 'row', {
			name: /Feeding roses in summer/,
		} );
		await newRow.hover();
		await newRow
			.getByRole( 'link', { name: 'Unlink Related Post' } )
			.click();
		await unlinked;

		await page.reload();
		await openMetaBoxes( page );
		await expect.poll( () => metaBoxTitles( page ) ).toEqual( before );

		// The front end follows.
		await page.goto( posts[ 'rye-bread' ].link );
		await expect( page.locator( '.rp4wp-related-posts li a' ) ).toHaveText(
			before
		);
	} );

	test( 'links several posts at once with the bulk action', async ( {
		page,
		admin,
		rp4wp,
	} ) => {
		const posts = await rp4wp.createCorpus();
		await rp4wp.install();
		await rp4wp.link();

		await admin.editPost( posts[ 'banana-bread' ].id );
		await openMetaBoxes( page );
		const before = await metaBoxTitles( page );

		await page
			.locator( '#rp4wp_metabox_related_posts' )
			.getByRole( 'link', { name: 'Add Related Posts' } )
			.click();
		await page
			.locator( '.subsubsub' )
			.getByRole( 'link', { name: 'All Posts' } )
			.click();

		for ( const title of [
			'Pruning roses in spring',
			'Common rose diseases',
		] ) {
			await page
				.getByRole( 'row', { name: new RegExp( title ) } )
				.getByRole( 'checkbox' )
				.check();
		}
		await page
			.locator( '#bulk-action-selector-top' )
			.selectOption( 'link' );
		await page.locator( '#doaction' ).click();

		// Back in the editor, both posts are added after the existing ones.
		await expect( page ).toHaveURL( /post\.php\?post=\d+&action=edit/ );
		await openMetaBoxes( page );
		const after = await metaBoxTitles( page );
		expect( after.slice( 0, before.length ) ).toEqual( before );
		expect( after.slice( before.length ).sort() ).toEqual( [
			'Common rose diseases',
			'Pruning roses in spring',
		] );
	} );
} );
