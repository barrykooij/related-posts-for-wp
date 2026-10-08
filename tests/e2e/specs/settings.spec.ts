/**
 * The settings screen: the tabs, saving and discarding, and what the front end does with the settings.
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
			page.getByRole( 'heading', { name: 'Related Posts', level: 1 } )
		).toBeVisible();

		// Nothing to save yet.
		await expect(
			page.getByRole( 'button', { name: 'Save changes' } )
		).toHaveCount( 0 );

		await page
			.getByRole( 'textbox', { name: 'Heading text' } )
			.fill( 'You might also like' );
		await page
			.getByRole( 'spinbutton', { name: 'Excerpt length' } )
			.fill( '0' );
		await page.getByRole( 'button', { name: 'Save changes' } ).click();

		await expect(
			page
				.locator( '.components-snackbar' )
				.getByText( 'Settings saved.' )
		).toBeVisible();
		await expect(
			page.getByRole( 'button', { name: 'Save changes' } )
		).toHaveCount( 0 );

		await page.reload();
		await expect(
			page.getByRole( 'textbox', { name: 'Heading text' } )
		).toHaveValue( 'You might also like' );

		await page.goto( posts[ 'banana-bread' ].link );
		const block = page.locator( '.rp4wp-related-posts' );
		await expect( block.locator( 'h3' ) ).toHaveText(
			'You might also like'
		);
		await expect( block.locator( 'li p' ) ).toHaveCount( 0 );
	} );

	test( 'changes can be discarded', async ( { page, admin, rp4wp } ) => {
		await rp4wp.install();

		await admin.visitAdminPage( 'options-general.php', 'page=rp4wp' );
		const heading = page.getByRole( 'textbox', { name: 'Heading text' } );
		await heading.fill( 'Not this' );
		await page.getByRole( 'button', { name: 'Discard' } ).click();

		await expect( heading ).toHaveValue( 'Related Posts' );
		await expect(
			page.getByRole( 'button', { name: 'Save changes' } )
		).toHaveCount( 0 );
	} );

	test( 'every settings section is a tab, with the installer last', async ( {
		page,
		admin,
		rp4wp,
	} ) => {
		await rp4wp.install();

		await admin.visitAdminPage( 'options-general.php', 'page=rp4wp' );
		const tabs = page.getByRole( 'navigation', {
			name: 'Related Posts settings',
		} );
		await expect( tabs.getByRole( 'link' ) ).toHaveText( [
			'General',
			'Styling',
			'Misc',
			'Installer',
		] );

		await tabs.getByRole( 'link', { name: 'Styling' } ).click();
		await expect( page ).toHaveURL( /#\/styling$/ );
		await expect(
			page.getByRole( 'textbox', { name: 'CSS' } )
		).toHaveValue( /\.rp4wp-related-posts ul/ );

		// A change on one tab is saved with the others.
		await page.getByLabel( 'Display Image' ).check();
		await tabs.getByRole( 'link', { name: 'Misc' } ).click();
		await page.getByLabel( 'Remove Data on Uninstall?' ).check();
		await page.getByRole( 'button', { name: 'Save changes' } ).click();
		await expect(
			page
				.locator( '.components-snackbar' )
				.getByText( 'Settings saved.' )
		).toBeVisible();

		await page.reload();
		await expect(
			page.getByLabel( 'Remove Data on Uninstall?' )
		).toBeChecked();
		await tabs.getByRole( 'link', { name: 'Styling' } ).click();
		await expect( page.getByLabel( 'Display Image' ) ).toBeChecked();
	} );

	test( 'custom CSS can be put back to the default', async ( {
		page,
		admin,
		rp4wp,
		requestUtils,
	} ) => {
		await rp4wp.install();
		await requestUtils.rest( {
			method: 'PUT',
			path: '/rp4wp/v1/settings/styling',
			data: { values: { css: '.rp4wp-related-posts h3{color:red;}' } },
		} );

		await admin.visitAdminPage(
			'options-general.php',
			'page=rp4wp#/styling'
		);
		const css = page.getByRole( 'textbox', { name: 'CSS' } );
		await expect( css ).toHaveValue(
			'.rp4wp-related-posts h3{color:red;}'
		);

		await page.getByRole( 'button', { name: 'Restore default' } ).click();
		await expect( css ).toHaveValue( /display:flex/ );
		await expect(
			page.getByRole( 'button', { name: 'Restore default' } )
		).toHaveCount( 0 );

		await page.getByRole( 'button', { name: 'Save changes' } ).click();
		await expect(
			page
				.locator( '.components-snackbar' )
				.getByText( 'Settings saved.' )
		).toBeVisible();

		const saved = ( await requestUtils.rest( {
			path: '/rp4wp/v1/settings/styling',
		} ) ) as { values: { css: string } };
		expect( saved.values.css ).toContain( 'display:flex' );
	} );
} );
