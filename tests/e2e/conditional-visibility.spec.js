// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Conditions driven the way an admin drives them.
 *
 * What is only true in a browser: a condition written against the declared
 * field name has to find the input, which on a settings page renders under the
 * prefixed slug — unresolved, the row it guards never appears whatever the
 * admin chooses.
 */

const SETTINGS_URL = '/wp-admin/options-general.php?page=wp_settings_harness';
const PROVIDER = '#wp_settings_harness_provider';
const NOTE = '#wp_settings_harness_provider_note';

test.beforeEach(async ({ page }) => {
	await page.goto('/wp-login.php');
	await page.fill('#user_login', 'admin');
	await page.fill('#user_pass', 'admin');
	await page.click('#wp-submit');
	await page.waitForURL(/wp-admin/);
	await page.goto(SETTINGS_URL);
	await page.selectOption(PROVIDER, 'none');
	await page.click('input[type="submit"], button[type="submit"]');
	await page.waitForLoadState('networkidle');
});

test('a field condition written against the declared name finds the input', async ({ page }) => {
	await expect(page.locator(NOTE)).toBeHidden();

	await page.selectOption(PROVIDER, 'vimeo');

	await expect(page.locator(NOTE)).toBeVisible();
});

test('the saved value decides what is on screen at load', async ({ page }) => {
	await page.selectOption(PROVIDER, 'vimeo');
	await page.click('input[type="submit"], button[type="submit"]');
	await page.waitForLoadState('networkidle');
	await page.goto(SETTINGS_URL);

	await expect(page.locator(NOTE)).toBeVisible();
});
