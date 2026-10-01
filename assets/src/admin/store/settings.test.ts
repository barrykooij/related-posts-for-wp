import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { SettingsPage } from '../api/types';
import {
	discardEdits,
	editField,
	isDirty,
	loadSettings,
	saveEdits,
	settingsStore,
	valueOf,
} from './settings';

vi.mock( '../api/client', () => ( {
	getSettings: vi.fn(),
	savePage: vi.fn(),
	errorMessage: ( _error: unknown, fallback: string ) => fallback,
} ) );

const client = await import( '../api/client' );

const pages: SettingsPage[] = [
	{
		id: 'general',
		title: 'General',
		description: '',
		option: 'rp4wp',
		sections: [],
		values: { heading_text: 'Related Posts', excerpt_length: 15 },
	},
	{
		id: 'styling',
		title: 'Styling',
		description: '',
		option: 'rp4wp',
		sections: [],
		values: { display_image: false },
	},
];

describe( 'settings', () => {
	beforeEach( async () => {
		vi.mocked( client.getSettings ).mockResolvedValue( { pages } );
		vi.mocked( client.savePage ).mockReset();
		await loadSettings();
	} );

	it( 'keeps edits apart from the saved values', () => {
		editField( 'general', 'heading_text', 'You might also like' );

		const state = settingsStore.getState();
		expect( valueOf( state, 'general', 'heading_text' ) ).toBe(
			'You might also like'
		);
		expect( state.saved.general.heading_text ).toBe( 'Related Posts' );
		expect( isDirty( state ) ).toBe( true );
	} );

	it( 'an edit back to the saved value is no edit', () => {
		editField( 'general', 'excerpt_length', 20 );
		editField( 'general', 'excerpt_length', 15 );

		expect( isDirty( settingsStore.getState() ) ).toBe( false );
	} );

	it( 'discards edits', () => {
		editField( 'styling', 'display_image', true );
		discardEdits();

		expect(
			valueOf( settingsStore.getState(), 'styling', 'display_image' )
		).toBe( false );
	} );

	it( 'saves each page with edits, only its edited fields', async () => {
		vi.mocked( client.savePage ).mockImplementation(
			async ( page, values ) => ( {
				page,
				values: {
					...pages.find( ( item ) => item.id === page )?.values,
					...values,
				},
			} )
		);

		editField( 'general', 'heading_text', 'You might also like' );
		editField( 'styling', 'display_image', true );
		await saveEdits();

		expect( client.savePage ).toHaveBeenCalledWith( 'general', {
			heading_text: 'You might also like',
		} );
		expect( client.savePage ).toHaveBeenCalledWith( 'styling', {
			display_image: true,
		} );

		const state = settingsStore.getState();
		expect( isDirty( state ) ).toBe( false );
		expect( state.saved.general.heading_text ).toBe(
			'You might also like'
		);
		expect( state.saving ).toBe( false );
	} );

	it( 'keeps what could not be saved', async () => {
		vi.mocked( client.savePage ).mockRejectedValue(
			new Error( 'Invalid' )
		);
		editField( 'general', 'excerpt_length', 0 );

		await expect( saveEdits() ).rejects.toThrow( 'Invalid' );

		expect( isDirty( settingsStore.getState() ) ).toBe( true );
	} );
} );
