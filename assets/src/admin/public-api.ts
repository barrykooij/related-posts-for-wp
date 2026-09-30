/**
 * The API the free plugin's admin app offers to other scripts as window.rp4wp.admin. The premium plugin imports it as
 * `@rp4wp/admin`, which its build maps to that global, and gets its types from this file.
 *
 * Raise API_VERSION when something here changes in a way that breaks a script built against an older version.
 */
import { registry } from './registry/registry';

export type { FieldProps, Route, SetupStep } from './registry/registry';

export const API_VERSION = 1;

export const registerRoute = registry.registerRoute;
export const registerFieldType = registry.registerFieldType;
export const registerSetupStep = registry.registerSetupStep;

export interface AdminApi {
	apiVersion: number;
	registerRoute: typeof registerRoute;
	registerFieldType: typeof registerFieldType;
	registerSetupStep: typeof registerSetupStep;
}

export const api: AdminApi = {
	apiVersion: API_VERSION,
	registerRoute,
	registerFieldType,
	registerSetupStep,
};
