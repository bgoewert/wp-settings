// @ts-check
const { defineConfig, devices } = require('@playwright/test');
const { execFileSync } = require('child_process');

/**
 * The running ddev project's URL, which a git worktree names after itself.
 */
function ddevUrl() {
	try {
		return JSON.parse(execFileSync('ddev', ['describe', '-j'], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] })).raw.primary_url;
	} catch {
		return 'https://wp-settings.ddev.site';
	}
}

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
		baseURL: process.env.WP_BASE_URL || ddevUrl(),
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
