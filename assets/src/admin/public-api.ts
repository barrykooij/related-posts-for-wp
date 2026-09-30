/**
 * The API the free plugin's admin app offers to other scripts as window.rp4wp.admin: this module's exports, as they
 * are. The premium plugin imports it as `@rp4wp/admin`, which its build maps to that global, and gets its types from
 * this file.
 *
 * Only add to it. Raise apiVersion when something changes in a way that breaks a script built against an older
 * version, so such a script can tell.
 */
import { registry } from './registry/registry';

export type { FieldProps, Route, SetupStep } from './registry/registry';

export const apiVersion = 1;

export const registerRoute = registry.registerRoute;
export const registerFieldType = registry.registerFieldType;
export const registerSetupStep = registry.registerSetupStep;
