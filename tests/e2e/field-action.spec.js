// @ts-check
const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const { execFileSync } = require('node:child_process');

/**
 * A field action pressed in a real browser.
 *
 * Only a browser proves the button inside the settings form submits the footer
 * form it names rather than the settings form around it, and that the request
 * clears admin-post's nonce check and lands back on the page.
 */

const SETTINGS_URL = '/wp-admin/options-general.php?page=wp_settings_harness';
const OPTION = 'wp_settings_harness_signing_key';

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
	wp('option', 'update', OPTION, '');
	await page.goto('/wp-login.php');
	await page.fill('#user_login', 'admin');
	await page.fill('#user_pass', 'admin');
	await page.click('#wp-submit');
	await page.waitForURL(/wp-admin/);
	await page.goto(SETTINGS_URL);
});

test('pressing Generate runs the handler and returns to the settings page', async ({ page }) => {
	await page.getByRole('button', { name: 'Generate Signing Key' }).click();
	await page.waitForURL(/page=wp_settings_harness/);

	const stored = wp('option', 'get', OPTION);
	expect(stored).toMatch(/^[A-Za-z0-9]{32}$/);
	await expect(page.locator(`#${OPTION}`)).toHaveValue(stored);
});

// The action's button sits in the settings form's markup but belongs to another
// form, so it must not become the settings form's default button.
test('pressing Enter in the field saves the settings instead of running the action', async ({ page }) => {
	await page.fill(`#${OPTION}`, 'typed-by-hand');
	await page.press(`#${OPTION}`, 'Enter');
	await page.waitForLoadState('networkidle');

	expect(wp('option', 'get', OPTION)).toBe('typed-by-hand');
});

test('an action request without the nonce is refused',async ({ page }) => {
	const response = await page.request.post('/wp-admin/admin-post.php', {
		form: { action: 'wp_settings_harness_generate' },
	});

	expect(response.status()).toBe(403);
	expect(wp('option', 'get', OPTION, '--format=json')).toBe('""');
});

test('a field with an action has no accessibility violations on it', async ({ page }) => {
	const results = await new AxeBuilder({ page }).include(`tr:has(#${OPTION})`).analyze();

	expect(results.violations).toEqual([]);
});
