declare global {
	interface Window {
		/** What PHP passes to the app (Admin\App\Assets::data()). */
		rp4wpAdminData?: {
			version: string;
			edition: 'free' | 'premium';
		};
		rp4wp?: {
			admin?: typeof import( './public-api' );
		};
	}
}

export {};
