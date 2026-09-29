// @ts-check
const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const { execFileSync } = require('node:child_process');

/**
 * A status row in a real settings page.
 *
 * Only a real save through the settings form proves the row posts nothing that
 * lands in an option, and only a real press proves its action re-renders it.
 */

const SETTINGS_URL = '/wp-admin/options-general.php?page=wp_settings_harness';
const KEY = 'wp_settings_harness_signing_key';
const STATUS = 'wp_settings_harness_key_status';

function wp(...args) {
	return execFileSync('ddev', ['exec', '--dir', '/var/www/html/.local/wp', 'wp', ...args], { stdio: 'pipe' })
		.toString()
		.trim()
		.split('\n')
		.pop()
		.trim();
}

test.afterAll(() => {
	wp('option', 'delete', KEY);
});

test.beforeEach(async ({ page }) => {
	wp('option', 'update', KEY, 'abc123');
	await page.goto('/wp-login.php');
	await page.fill('#user_login', 'admin');
	await page.fill('#user_pass', 'admin');
	await page.click('#wp-submit');
	await page.waitForURL(/wp-admin/);
	await page.goto(SETTINGS_URL);
});

test('the row shows the derived value as text in the settings table', async ({ page }) => {
	const row = page.locator(`tr:has(#${STATUS})`);

	await expect(row.locator('th')).toHaveText('Key Status');
	await expect(page.locator(`#${STATUS}`)).toHaveText('Set');
	await expect(row.locator('input, textarea, select')).toHaveCount(0);
});

test('pressing its action runs the handler and the row re-renders', async ({ page }) => {
	await page.getByRole('button', { name: 'Clear Key Status' }).click();
	await page.waitForURL(/page=wp_settings_harness/);

	await expect(page.locator(`#${STATUS}`)).toHaveText('Not set');
});

test('saving the settings stores nothing for the row', async ({ page }) => {
	await page.click('#submit');
	await page.waitForLoadState('networkidle');

	expect(() => wp('option', 'get', STATUS)).toThrow();
});

test('the row has no accessibility violations', async ({ page }) => {
	const results = await new AxeBuilder({ page }).include(`tr:has(#${STATUS})`).analyze();

	expect(results.violations).toEqual([]);
});
