// @ts-check
const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const { execFileSync } = require('node:child_process');

/**
 * A settings table's selection column in a real settings page.
 *
 * Only a real browser computes the accessible name axe checks, from a label
 * bound by id.
 */

const TABLE_URL = '/wp-admin/options-general.php?page=wp_settings_harness&tab=rows';
const OPTION = 'wp_settings_harness_fees';

function wp(...args) {
	return execFileSync('ddev', ['exec', '--dir', '/var/www/html/.local/wp', 'wp', ...args], { stdio: 'pipe' })
		.toString()
		.trim();
}

test.afterAll(() => {
	wp('option', 'delete', OPTION);
});

test.beforeEach(async ({ page }) => {
	wp('option', 'update', OPTION, JSON.stringify({ 'rush-fee': { id: 'rush-fee', name: 'Rush Fee', enabled: true } }), '--format=json');
	await page.goto('/wp-login.php');
	await page.fill('#user_login', 'admin');
	await page.fill('#user_pass', 'admin');
	await page.click('#wp-submit');
	await page.waitForURL(/wp-admin/);
	await page.goto(TABLE_URL);
});

test('the select-all and row checkboxes are named', async ({ page }) => {
	await expect(page.getByRole('checkbox', { name: 'Select all' })).toBeVisible();
	await expect(page.getByRole('checkbox', { name: 'Select Rush Fee' })).toBeVisible();
});

test('the table passes an axe check', async ({ page }) => {
	const results = await new AxeBuilder({ page }).include('.wps-table-form table').analyze();

	expect(results.violations).toEqual([]);
});
