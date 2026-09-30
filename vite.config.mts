/**
 * Builds the admin app, assets/src/admin, into assets/build/admin. `npm run build:admin` builds it once, `npm run dev`
 * rebuilds it on every change.
 */
import { defineConfig } from 'vitest/config';
import { wpScripts } from './scripts/vite-wp.mts';

export default defineConfig( {
	plugins: [ wpScripts() ],
	oxc: {
		jsx: { runtime: 'automatic', importSource: 'react' },
	},
	build: {
		outDir: 'assets/build/admin',
		emptyOutDir: true,
		// The browsers WordPress supports.
		target: 'es2020',
		sourcemap: false,
		// Terser keeps the translator comments and plain string literals, which `wp i18n make-pot` needs.
		minify: 'terser',
		terserOptions: {
			format: { comments: /translators:/i },
		},
		rolldownOptions: {
			// Keep /** translators: */ comments for terser, which keeps only those.
			output: {
				comments: { legal: true, annotation: true, jsdoc: true },
			},
		},
		lib: {
			entry: 'assets/src/admin/index.tsx',
			name: 'rp4wpAdminApp',
			formats: [ 'iife' ],
			fileName: () => 'index.js',
			cssFileName: 'index',
		},
	},
	test: {
		environment: 'jsdom',
		setupFiles: [ 'assets/src/test-setup.ts' ],
		include: [ 'assets/src/**/*.test.{ts,tsx}', 'scripts/**/*.test.mts' ],
	},
} );
