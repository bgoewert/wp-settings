// @ts-check
const { defineConfig, devices } = require('@playwright/test');

/**
 * Browser tests for the settings page the ddev harness registers.
 *
 * ddev issues its own certificate, so ignoreHTTPSErrors is what lets the run
 * work from a plain `bun run test:e2e` instead of a mkcert setup step.
 */
module.exports = defineConfig({
	testDir: './tests/e2e',
	fullyParallel: false,
	workers: 1,
	reporter: process.env.CI ? 'line' : 'list',
	use: {
		baseURL: process.env.WP_BASE_URL || 'https://wp-settings.ddev.site',
		ignoreHTTPSErrors: true,
		trace: 'retain-on-failure',
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices['Desktop Chrome'] },
		},
	],
});
