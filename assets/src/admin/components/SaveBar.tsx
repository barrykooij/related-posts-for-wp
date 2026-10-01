import { Button } from '@wordpress/components';
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { errorMessage } from '../api/client';
import {
	discardEdits,
	isDirty,
	saveEdits,
	useSettings,
} from '../store/settings';
import { notify } from './Snackbars';

/**
 * Sticks to the bottom of the screen while there are changes that are not saved, and warns before leaving the page
 * with them.
 */
export function SaveBar() {
	const state = useSettings();
	const dirty = isDirty( state );

	useEffect( () => {
		if ( ! dirty ) {
			return undefined;
		}

		const warn = ( event: BeforeUnloadEvent ) => {
			event.preventDefault();
			// Older browsers need a value.
			event.returnValue = '';
		};

		window.addEventListener( 'beforeunload', warn );

		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ dirty ] );

	if ( ! dirty ) {
		return null;
	}

	const save = async () => {
		try {
			await saveEdits();
			notify( __( 'Settings saved.', 'related-posts-for-wp' ) );
		} catch ( error ) {
			notify(
				errorMessage(
					error,
					__(
						'The settings could not be saved.',
						'related-posts-for-wp'
					)
				),
				'error'
			);
		}
	};

	return (
		<div
			className="rp4wp-savebar"
			role="region"
			aria-label={ __( 'Unsaved changes', 'related-posts-for-wp' ) }
		>
			<span className="rp4wp-savebar__text">
				{ __( 'You have unsaved changes.', 'related-posts-for-wp' ) }
			</span>
			<Button
				variant="tertiary"
				onClick={ discardEdits }
				disabled={ state.saving }
				__next40pxDefaultSize
			>
				{ __( 'Discard', 'related-posts-for-wp' ) }
			</Button>
			<Button
				variant="primary"
				onClick={ save }
				isBusy={ state.saving }
				disabled={ state.saving }
				__next40pxDefaultSize
			>
				{ __( 'Save changes', 'related-posts-for-wp' ) }
			</Button>
		</div>
	);
}
