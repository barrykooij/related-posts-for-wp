import { render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { installStore } from '../store/install';
import { Sidebar } from './Sidebar';

function setInstalled( installed: boolean ): void {
	installStore.setState( {
		loaded: true,
		state: { installed, job: null, args: {} },
	} );
}

describe( 'Sidebar', () => {
	beforeEach( () => {
		setInstalled( true );
	} );

	afterEach( () => {
		delete window.rp4wpAdminData;
	} );

	it( 'offers Premium and the support forum in the free plugin', () => {
		window.rp4wpAdminData = { version: '3.0.0', edition: 'free' };

		render( <Sidebar /> );

		const upgrade = screen.getByRole( 'link', {
			name: /Upgrade to Premium/,
		} );

		expect( upgrade.getAttribute( 'href' ) ).toContain(
			'relatedpostsforwp.com/upgrade-premium/'
		);
		expect( upgrade.getAttribute( 'target' ) ).toBe( '_blank' );
		expect(
			screen
				.getByRole( 'link', { name: /Ask on the support forum/ } )
				.getAttribute( 'href' )
		).toContain( 'wordpress.org/support/plugin/related-posts-for-wp/' );
	} );

	it( 'leaves out the offer and links to email support with Premium', () => {
		window.rp4wpAdminData = { version: '3.0.0', edition: 'premium' };

		render( <Sidebar /> );

		expect(
			screen.queryByRole( 'link', { name: /Upgrade to Premium/ } )
		).toBeNull();
		expect(
			screen.queryByRole( 'link', { name: /support forum/ } )
		).toBeNull();
		expect(
			screen
				.getByRole( 'link', { name: /Priority email support/ } )
				.getAttribute( 'href' )
		).toContain( 'relatedpostsforwp.com/contact/' );
	} );

	it( 'links the documentation', () => {
		render( <Sidebar /> );

		expect(
			screen
				.getByRole( 'link', { name: /All documentation/ } )
				.getAttribute( 'href' )
		).toContain( 'relatedpostsforwp.com/documentation/' );
	} );

	it( 'asks for a review only once the posts are installed', () => {
		setInstalled( false );

		const { rerender } = render( <Sidebar /> );

		expect(
			screen.queryByRole( 'link', { name: /Leave a review/ } )
		).toBeNull();

		setInstalled( true );
		rerender( <Sidebar /> );

		expect(
			screen
				.getByRole( 'link', { name: /Leave a review/ } )
				.getAttribute( 'href' )
		).toContain(
			'wordpress.org/support/plugin/related-posts-for-wp/reviews/'
		);
	} );
} );
