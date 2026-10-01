// @ts-check
const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const { execFileSync } = require('node:child_process');

/**
 * A `disabled` field in a real settings page.
 *
 * Only a real browser omits the disabled control from the POST, and only a
 * real options.php then writes the option from what is left.
 */

const SETTINGS_URL = '/wp-admin/options-general.php?page=wp_settings_harness';
const OPTION = 'wp_settings_harness_hide_quotes';

function wp(...args) {
	return execFileSync('ddev', ['exec', '--dir', '/var/www/html/.local/wp', 'wp', ...args], { stdio: 'pipe' })
		.toString()
		.trim()
		.split('\n')
		.pop()
		.trim();
}

test.afterAll(() => {
	wp('option', 'delete', OPTION);
});

test.beforeEach(async ({ page }) => {
	// Written past update_option(), which a disabled field's sanitizer would refuse.
	wp('db', 'query', `REPLACE INTO ${wp('db', 'prefix')}options (option_name, option_value, autoload) VALUES ('${OPTION}', '1', 'off')`);
	await page.goto('/wp-login.php');
	await page.fill('#user_login', 'admin');
	await page.fill('#user_pass', 'admin');
	await page.click('#wp-submit');
	await page.waitForURL(/wp-admin/);
	await page.goto(SETTINGS_URL);
});

test('the checkbox shows its stored value and cannot be changed', async ({ page }) => {
	const box = page.locator(`#${OPTION}`);

	await expect(box).toBeChecked();
	await expect(box).toBeDisabled();
});

test('saving the settings keeps the stored value', async ({ page }) => {
	await page.click('#submit');
	await page.waitForLoadState('networkidle');

	expect(wp('option', 'get', OPTION)).toBe('1');
	await expect(page.locator(`#${OPTION}`)).toBeChecked();
});

test('the row has no accessibility violations', async ({ page }) => {
	const results = await new AxeBuilder({ page }).include(`tr:has(#${OPTION})`).analyze();

	expect(results.violations).toEqual([]);
});
