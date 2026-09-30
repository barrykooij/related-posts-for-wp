import type { AdminApi } from './public-api';

declare global {
	interface Window {
		/** What PHP passes to the app (Admin\App\Page::data()). */
		rp4wpAdminData?: {
			version: string;
			edition: 'free' | 'premium';
		};
		rp4wp?: {
			admin?: AdminApi;
		};
	}
}

export {};
