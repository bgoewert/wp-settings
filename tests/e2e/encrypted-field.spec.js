// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * The `encrypted` arg driven the way an admin drives it.
 *
 * What is only true in a browser: the value makes a real options.php POST,
 * where `save()` and the registered sanitizer both run against the submitted
 * string rather than one a test handed straight to update_option(). The
 * password case is the one no PHP test can reach at all — the input renders
 * empty by design, so "save the page again without retyping the secret" is an
 * interaction, and getting it wrong wipes a stored credential silently.
 */

const SETTINGS_URL = '/wp-admin/options-general.php?page=wp_settings_harness';
const NOTE = '#wp_settings_harness_secret_note';
const BLOCK = '#wp_settings_harness_secret_block';
const TOKEN = '#wp_settings_harness_api_token';
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

test('an encrypted text field round-trips its value through a real save', async ({ page }) => {
	await page.fill(NOTE, 'hunter2-text');
	await save(page);
	await page.goto(SETTINGS_URL);

	await expect(page.locator(NOTE)).toHaveValue('hunter2-text');
});

test('an encrypted textarea round-trips its value, newlines and all', async ({ page }) => {
	await page.fill(BLOCK, 'first line\nsecond line');
	await save(page);
	await page.goto(SETTINGS_URL);

	await expect(page.locator(BLOCK)).toHaveValue('first line\nsecond line');
});

test('the ciphertext never reaches the page', async ({ page }) => {
	await page.fill(NOTE, 'hunter2-text');
	await save(page);
	await page.goto(SETTINGS_URL);

	// Decryption on render happens before escaping, so a marker in the HTML
	// would mean the input is holding the stored value rather than the value.
	expect(await page.content()).not.toContain('wps.aesgcm.');
});

test('an encrypted field left empty stays empty rather than storing ciphertext of nothing', async ({ page }) => {
	await page.fill(NOTE, 'hunter2-text');
	await save(page);
	await page.goto(SETTINGS_URL);

	await page.fill(NOTE, '');
	await save(page);
	await page.goto(SETTINGS_URL);

	await expect(page.locator(NOTE)).toHaveValue('');
});

test('a password field keeps its stored secret when the page is saved without retyping it', async ({ page }) => {
	await page.fill(TOKEN, 'hunter2-password');
	await save(page);
	await page.goto(SETTINGS_URL);

	// Rendered empty by design, so a save that leaves it alone must not wipe it.
	await expect(page.locator(TOKEN)).toHaveValue('');
	await page.fill(PLAIN, 'something else entirely');
	await save(page);
	await page.goto(SETTINGS_URL);

	await expect(page.locator(TOKEN)).toHaveAttribute('placeholder', /Value saved/);
});

test('a field that never declared encrypted is stored in the clear', async ({ page }) => {
	await page.fill(PLAIN, 'not a secret');
	await save(page);
	await page.goto(SETTINGS_URL);

	await expect(page.locator(PLAIN)).toHaveValue('not a secret');
});
