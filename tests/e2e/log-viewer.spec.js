// @ts-check
const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const { execFileSync } = require('node:child_process');

/**
 * The Log Viewer on the built-in Logging tab.
 *
 * Opening a file has to happen in place: the page reload it used to do
 * rebuilt the URL from the text domain, which is not the menu slug on every site.
 */

const LOGGING_URL = '/wp-admin/options-general.php?page=wp_settings_harness&tab=logging';
const LOG_DIR = '/var/www/html/.local/wp/wp-content/wp-settings-harness-logs';
const OLDER = 'wp_settings_harness-2000-01-01.log';
const NEWER = 'wp_settings_harness-2000-01-02.log';

function sh(script) {
	execFileSync('ddev', ['exec', 'bash', '-c', script], { stdio: 'pipe' });
}

test.beforeAll(() => {
	sh(`mkdir -p ${LOG_DIR} && printf 'older entry\\n' > ${LOG_DIR}/${OLDER} && printf 'newer entry\\n' > ${LOG_DIR}/${NEWER}`);
});

test.afterAll(() => {
	sh(`rm -rf ${LOG_DIR}`);
});

test.beforeEach(async ({ page }) => {
	await page.goto('/wp-login.php');
	await page.fill('#user_login', 'admin');
	await page.fill('#user_pass', 'admin');
	await page.click('#wp-submit');
	await page.waitForURL(/wp-admin/);
	await page.goto(LOGGING_URL);
});

test('Open loads the selected file into the viewer without reloading the page', async ({ page }) => {
	const viewer = page.locator('#wps-log-viewer');
	await expect(viewer).toHaveText('newer entry');

	await page.evaluate(() => { window.wpsNoReload = true; });
	await page.getByLabel('Log file').selectOption(OLDER);
	await page.getByRole('button', { name: 'Open' }).click();

	await expect(viewer).toHaveText('older entry');
	expect(await page.evaluate(() => window.wpsNoReload)).toBe(true);
	expect(page.url()).not.toContain('log_file');
});

test('Ctrl+A inside the viewer selects only the log contents', async ({ page }) => {
	const viewer = page.locator('#wps-log-viewer');
	await viewer.click();
	await page.keyboard.press('ControlOrMeta+a');

	expect(await page.evaluate(() => window.getSelection()?.toString())).toBe('newer entry');
});

test('Copy puts the log contents on the clipboard', async ({ page, context }) => {
	await context.grantPermissions(['clipboard-read', 'clipboard-write']);
	await page.getByRole('button', { name: 'Copy' }).click();

	await expect(page.locator('#wps-log-status')).toHaveText('Copied');
	expect(await page.evaluate(() => navigator.clipboard.readText())).toBe('newer entry');
});

test('the viewer controls have no axe violations', async ({ page }) => {
	const results = await new AxeBuilder({ page }).include('#wps-log-viewer').include('#wps-log-file').analyze();

	expect(results.violations).toEqual([]);
});
