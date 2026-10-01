import { Button, Modal } from '@wordpress/components';
import type { ReactNode } from 'react';

interface Props {
	title: string;
	children: ReactNode;
	confirmLabel: string;
	cancelLabel: string;
	/** The action can't be undone. */
	isDestructive?: boolean;
	onConfirm: () => void;
	onCancel: () => void;
}

/**
 * Asks before an action that removes or stops something.
 *
 * @param props               The props.
 * @param props.title         The question.
 * @param props.children      What happens.
 * @param props.confirmLabel  The button that does it.
 * @param props.cancelLabel   The button that does not.
 * @param props.isDestructive Whether the action can't be undone.
 * @param props.onConfirm     Called to do it.
 * @param props.onCancel      Called to close without doing it.
 */
export function ConfirmModal( {
	title,
	children,
	confirmLabel,
	cancelLabel,
	isDestructive = false,
	onConfirm,
	onCancel,
}: Props ) {
	return (
		<Modal title={ title } onRequestClose={ onCancel } size="medium">
			<div className="rp4wp-modal__body">{ children }</div>
			<div className="rp4wp-modal__actions">
				<Button
					variant="tertiary"
					onClick={ onCancel }
					__next40pxDefaultSize
				>
					{ cancelLabel }
				</Button>
				<Button
					variant="primary"
					isDestructive={ isDestructive }
					onClick={ onConfirm }
					__next40pxDefaultSize
				>
					{ confirmLabel }
				</Button>
			</div>
		</Modal>
	);
}
