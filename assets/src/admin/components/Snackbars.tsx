import { SnackbarList } from '@wordpress/components';
import { dispatch, useDispatch, useSelect } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';

const CONTEXT = 'rp4wp-admin';

/**
 * Show a short message at the bottom of the screen.
 *
 * @param message The message.
 * @param type    Whether it tells of a success or an error.
 */
export function notify(
	message: string,
	type: 'success' | 'error' = 'success'
): void {
	const notices = dispatch( noticesStore );
	const create =
		type === 'error'
			? notices.createErrorNotice
			: notices.createSuccessNotice;

	create( message, { type: 'snackbar', context: CONTEXT } );
}

export function Snackbars() {
	const notices = useSelect(
		( select ) =>
			select( noticesStore )
				.getNotices( CONTEXT )
				// Only what a snackbar shows; the store's types and the component's differ on the rest.
				.map( ( notice ) => ( {
					id: notice.id,
					content: notice.content,
					spokenMessage: notice.spokenMessage,
				} ) ),
		[]
	);
	const { removeNotice } = useDispatch( noticesStore );

	return (
		<SnackbarList
			className="rp4wp-admin__snackbars"
			notices={ notices }
			onRemove={ ( id: string ) => removeNotice( id, CONTEXT ) }
		/>
	);
}
