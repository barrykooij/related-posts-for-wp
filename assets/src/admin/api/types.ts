/**
 * What the REST API of the plugin (rp4wp/v1) answers.
 */

export type FieldValue = unknown;

export interface Field {
	id: string;
	/** The control, for example `toggle` or `number`; a registered field type. */
	type: string;
	label: string;
	/** HTML, passed through wp_kses_post() on the server. */
	description: string;
	default: FieldValue;
	/** A filter decides the value, so it can't be changed here. */
	filtered: boolean;
	min?: number;
	max?: number;
	options?: { value: string; label: string }[];
	href?: string;
	/** The control takes the width of the card, below the label. */
	wide?: boolean;
	[ key: string ]: unknown;
}

export interface Section {
	id: string;
	title: string;
	description: string;
	fields: Field[];
}

export type Values = Record< string, FieldValue >;

export interface SettingsPage {
	id: string;
	title: string;
	description: string;
	option: string;
	sections: Section[];
	values: Values;
	/** Pages with the same group share a tab, titled group_title, with a switcher that shows each group_label. */
	group?: string;
	group_title?: string;
	group_label?: string;
}

export interface InstallStep {
	id: string;
	label: string;
	/** The items when the step started; null before it started. */
	total: number | null;
	/** The items left, for the step that runs now. */
	remaining: number | null;
	done: boolean;
	current: boolean;
}

export type JobStatus = 'running' | 'done' | 'cancelled' | 'failed';

export interface Job {
	id: string;
	status: JobStatus;
	request: Record< string, unknown >;
	steps: InstallStep[];
	/** Unix timestamps. */
	started: number;
	last_progress: number;
	ended: number;
	error: string | null;
	/** Running, but no progress for a while and no request works on it. */
	stalled: boolean;
	/** Whether the job installs the plugin; false for other background work, such as premium's refresh. */
	install?: boolean;
	/** What the screen calls a job that is not an installation; keys that are left out get the installation's words. */
	labels?: JobLabels;
}

export interface JobLabels {
	running?: string;
	done?: string;
	failed?: string;
	cancelled?: string;
	/** The question before cancelling. */
	cancel?: string;
	cancel_button?: string;
}

export interface ArgSchema {
	description?: string;
	type?: string;
	minimum?: number;
	maximum?: number;
	default?: unknown;
	[ key: string ]: unknown;
}

export interface InstallState {
	installed: boolean;
	job: Job | null;
	/** What a new installation accepts, as JSON schema per key. */
	args: Record< string, ArgSchema >;
	/** Only in the answer to a tick. */
	ran?: boolean;
}

export type InstallRequest = Record< string, unknown >;
