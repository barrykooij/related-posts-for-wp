/**
 * Playwright configuration for the WordPress.org screenshots: `npm run screenshots`.
 *
 * Runs against the same wp-env test site as the E2E suite, and resets it. The screenshots are written to
 * .wordpress-org, which the release workflow deploys to the assets folder on WordPress.org with a stable release.
 * They are 2560 by 1456 pixels: a 1280 by 728 window at twice the pixel density.
 */
import { defineConfig, devices } from '@playwright/test';
import base from './playwright.config';

export default defineConfig( {
	...base,
	testDir: './screenshots',
	retries: 0,
	timeout: 180_000,
	reporter: [ [ 'list' ] ],
	projects: [
		{
			name: 'chromium',
			use: {
				...devices[ 'Desktop Chrome' ],
				viewport: { width: 1280, height: 728 },
				deviceScaleFactor: 2,
			},
		},
	],
} );
