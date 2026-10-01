/**
 * The settings pages, their saved values and the edits that are not saved yet.
 */
import { __ } from '@wordpress/i18n';
import { errorMessage, getSettings, savePage } from '../api/client';
import type { FieldValue, SettingsPage, Values } from '../api/types';
import { createStore, useStore } from './createStore';

export interface SettingsState {
	status: 'loading' | 'ready' | 'error';
	error: string | null;
	pages: SettingsPage[];
	/** The saved values, by page. */
	saved: Record< string, Values >;
	/** The changed values that are not saved yet, by page. */
	edits: Record< string, Values >;
	saving: boolean;
}

export const settingsStore = createStore< SettingsState >( {
	status: 'loading',
	error: null,
	pages: [],
	saved: {},
	edits: {},
	saving: false,
} );

export function useSettings(): SettingsState {
	return useStore( settingsStore );
}

/**
 * Load the pages and their values.
 *
 * @return Resolves when loaded.
 */
export async function loadSettings(): Promise< void > {
	try {
		const { pages } = await getSettings();
		const saved: Record< string, Values > = {};

		pages.forEach( ( page ) => {
			saved[ page.id ] = { ...page.values };
		} );

		settingsStore.setState( {
			status: 'ready',
			error: null,
			pages,
			saved,
			edits: {},
		} );
	} catch ( error ) {
		settingsStore.setState( {
			status: 'error',
			error: errorMessage(
				error,
				__(
					'The settings could not be loaded.',
					'related-posts-for-wp'
				)
			),
		} );
	}
}

/**
 * The value of a field as the admin sees it: the edit, or else the saved value.
 *
 * @param state The state.
 * @param page  The page ID.
 * @param field The field ID.
 * @return The value.
 */
export function valueOf(
	state: SettingsState,
	page: string,
	field: string
): FieldValue {
	const edits = state.edits[ page ] ?? {};

	return field in edits ? edits[ field ] : state.saved[ page ]?.[ field ];
}

/**
 * Change a field. Changing it back to the saved value is no change.
 *
 * @param page  The page ID.
 * @param field The field ID.
 * @param value The value.
 */
export function editField(
	page: string,
	field: string,
	value: FieldValue
): void {
	const { edits, saved } = settingsStore.getState();
	const pageEdits = { ...( edits[ page ] ?? {} ) };

	if ( Object.is( saved[ page ]?.[ field ], value ) ) {
		delete pageEdits[ field ];
	} else {
		pageEdits[ field ] = value;
	}

	const next = { ...edits, [ page ]: pageEdits };

	if ( Object.keys( pageEdits ).length === 0 ) {
		delete next[ page ];
	}

	settingsStore.setState( { edits: next } );
}

export function isDirty( state: SettingsState ): boolean {
	return Object.keys( state.edits ).length > 0;
}

export function discardEdits(): void {
	settingsStore.setState( { edits: {} } );
}

/**
 * Save every page with edits.
 *
 * @return Resolves when saved; rejects with the first error, after saving what could be saved.
 */
export async function saveEdits(): Promise< void > {
	const { edits } = settingsStore.getState();

	settingsStore.setState( { saving: true } );

	try {
		for ( const [ page, values ] of Object.entries( edits ) ) {
			const response = await savePage( page, values );
			const state = settingsStore.getState();
			const remaining = { ...state.edits };

			delete remaining[ page ];

			settingsStore.setState( {
				saved: { ...state.saved, [ page ]: { ...response.values } },
				edits: remaining,
			} );
		}
	} finally {
		settingsStore.setState( { saving: false } );
	}
}
