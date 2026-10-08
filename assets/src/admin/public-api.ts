/**
 * The API the free plugin's admin app offers to other scripts as window.rp4wp.admin: this module's exports, as they
 * are. The premium plugin imports it as `@rp4wp/admin`, which its build maps to that global, and gets its types from
 * this file.
 *
 * Only add to it. Raise apiVersion when something changes in a way that breaks a script built against an older
 * version, so such a script can tell.
 */
import { registry } from './registry/registry';

export type {
	FieldProps,
	Route,
	SetupStep,
	SetupStepProps,
} from './registry/registry';
export type {
	ArgSchema,
	Field,
	InstallRequest,
	InstallState,
	Job,
	JobLabels,
} from './api/types';

export const apiVersion = 1;

export const registerRoute = registry.registerRoute;
export const registerFieldType = registry.registerFieldType;
export const registerSetupStep = registry.registerSetupStep;
export const unregisterRoute = registry.unregisterRoute;

/** The installer: its state, and starting an installation (which the progress card then follows). */
export {
	useInstall,
	start as startInstall,
	loadInstall,
} from './store/install';

/** The settings as the admin edits them: a field can read and change the other fields of its page. */
export { editField as editSetting, useSettingValue } from './store/settings';

/** Building blocks for screens that look like the rest of the app. */
export { notify } from './components/Snackbars';
export { ConfirmModal } from './components/ConfirmModal';
export { AmountField, defaultAmount } from './install/AmountField';
