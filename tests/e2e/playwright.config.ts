/**
 * Playwright configuration for the E2E suite.
 *
 * Runs against the wp-env test site (`npm run env:test:start`, http://localhost:8711). The specs share one WordPress
 * site, so they run one at a time and reset the site through the E2E helpers mu-plugin
 * (tests/Fixtures/mu-plugins/rp4wp-e2e-helpers.php).
 */
import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';

process.env.WP_BASE_URL ??= 'http://localhost:8711';

const STORAGE_STATE_PATH = path.join( __dirname, '.auth', 'admin.json' );
process.env.STORAGE_STATE_PATH ??= STORAGE_STATE_PATH;

export default defineConfig( {
	testDir: './specs',
	outputDir: path.join( __dirname, '../../artifacts/e2e/test-results' ),
	globalSetup: require.resolve( './global-setup.ts' ),
	fullyParallel: false,
	workers: 1,
	forbidOnly: !! process.env.CI,
	retries: process.env.CI ? 1 : 0,
	timeout: 60_000,
	reporter: process.env.CI
		? [
				[ 'list' ],
				[
					'html',
					{
						open: 'never',
						outputFolder: path.join(
							__dirname,
							'../../artifacts/e2e/report'
						),
					},
				],
			]
		: [ [ 'list' ] ],
	use: {
		baseURL: process.env.WP_BASE_URL,
		storageState: STORAGE_STATE_PATH,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		video: 'off',
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
} );
