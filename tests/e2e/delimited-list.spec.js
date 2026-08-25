// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * The `delimiter` arg driven the way an admin drives it.
 *
 * What is only true in a browser: the value survives a real options.php POST,
 * and "Reset to Default" writes the joined string rather than the array
 * JavaScript would coerce with commas — which is what 4.3.1 fixed and what no
 * PHP test could have caught, because the coercion happens in `input.value =`.
 */

const SETTINGS_URL = '/wp-admin/options-general.php?page=wp_settings_harness';
const TAGS = '#wp_settings_harness_rental_tags';
const DOMAINS = '#wp_settings_harness_allowed_domains';
const PLAIN = '#wp_settings_harness_plain_note';

test.beforeEach(async ({ page }) => {
	await page.goto('/wp-login.php');
	await page.fill('#user_login', 'admin');
	await page.fill('#user_pass', 'admin');
	await page.click('#wp-submit');
	await page.waitForURL(/wp-admin/);
	await page.goto(SETTINGS_URL);
});

async function save(page) {
	await page.click('input[type="submit"], button[type="submit"]');
	await page.waitForLoadState('networkidle');
}

test('a comma list survives a save and comes back normalized', async ({ page }) => {
	await page.fill(TAGS, 'alpha ,  beta ,, gamma ');
	await save(page);
	await page.goto(SETTINGS_URL);

	// Trimmed, empties dropped, rejoined the way an admin types it.
	await expect(page.locator(TAGS)).toHaveValue('alpha, beta, gamma');
});

test('reset to default restores the delimiter-joined default, not a comma-joined array', async ({ page }) => {
	await page.fill(TAGS, 'something-else');
	await save(page);
	await page.goto(SETTINGS_URL);

	await page.click('#wps-rb-wp_settings_harness_rental_tags');

	await expect(page.locator(TAGS)).toHaveValue('rental, demo');
});

test('a newline delimiter keeps one item per line in the textarea', async ({ page }) => {
	await page.fill(DOMAINS, 'example.com\nexample.org\n');
	await save(page);
	await page.goto(SETTINGS_URL);

	await expect(page.locator(DOMAINS)).toHaveValue('example.com\nexample.org');
});

test('a field without a delimiter keeps its commas verbatim', async ({ page }) => {
	await page.fill(PLAIN, 'one, two');
	await save(page);
	await page.goto(SETTINGS_URL);

	await expect(page.locator(PLAIN)).toHaveValue('one, two');
});
