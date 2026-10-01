import { Card, CardBody, CardHeader } from '@wordpress/components';
import { RawHTML } from '@wordpress/element';
import { FieldRow } from '../components/FieldRow';
import { editField, useSettings, valueOf } from '../store/settings';

interface Props {
	page: string;
}

/**
 * A settings page: a card per section, a row per field.
 *
 * @param props      The props.
 * @param props.page The page ID.
 */
export function SettingsScreen( { page }: Props ) {
	const state = useSettings();
	const settings = state.pages.find( ( item ) => item.id === page );

	if ( ! settings ) {
		return null;
	}

	return (
		<div className="rp4wp-page">
			{ settings.sections.map( ( section ) => (
				<Card key={ section.id } className="rp4wp-card">
					<CardHeader>
						<div>
							<h2 className="rp4wp-card__title">
								{ section.title }
							</h2>
							{ section.description && (
								<RawHTML className="rp4wp-card__description">
									{ section.description }
								</RawHTML>
							) }
						</div>
					</CardHeader>
					<CardBody className="rp4wp-card__body">
						{ section.fields.map( ( field ) => (
							<FieldRow
								key={ field.id }
								field={ field }
								value={ valueOf( state, page, field.id ) }
								onChange={ ( value ) =>
									editField( page, field.id, value )
								}
							/>
						) ) }
					</CardBody>
				</Card>
			) ) }
		</div>
	);
}
