// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Conditions driven the way an admin drives them.
 *
 * What is only true in a browser: the heading of a section that does not apply
 * goes with its fields rather than sitting above an empty form-table (#24), and
 * a condition written against the declared field name finds the input, which on
 * a settings page renders under the prefixed slug.
 */

const SETTINGS_URL = '/wp-admin/options-general.php?page=wp_settings_harness';
const PROVIDER = '#wp_settings_harness_provider';
const SECTION = '.wps-section-wrapper[data-section="vimeo"]';
const SECTION_HEADING = `${SECTION} h2`;
const TOKEN = '#wp_settings_harness_vimeo_token';
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

test('a section that does not apply takes its heading with it', async ({ page }) => {
	await expect(page.locator(SECTION_HEADING)).toBeHidden();
	await expect(page.locator(TOKEN)).toBeHidden();

	await page.selectOption(PROVIDER, 'vimeo');

	await expect(page.locator(SECTION_HEADING)).toBeVisible();
	await expect(page.locator(TOKEN)).toBeVisible();
});

test('choosing another provider hides the section again', async ({ page }) => {
	await page.selectOption(PROVIDER, 'vimeo');
	await expect(page.locator(SECTION)).toBeVisible();

	await page.selectOption(PROVIDER, 'none');

	await expect(page.locator(SECTION)).toBeHidden();
});

test('the saved value decides what is on screen at load', async ({ page }) => {
	await page.selectOption(PROVIDER, 'vimeo');
	await page.click('input[type="submit"], button[type="submit"]');
	await page.waitForLoadState('networkidle');
	await page.goto(SETTINGS_URL);

	await expect(page.locator(SECTION_HEADING)).toBeVisible();
	await expect(page.locator(TOKEN)).toBeVisible();
	await expect(page.locator(NOTE)).toBeVisible();
});

/**
 * The condition names `provider`, the input is `wp_settings_harness_provider`.
 * Unresolved, the row it guards never appears whatever the admin chooses.
 */
test('a field condition written against the declared name still finds the input', async ({ page }) => {
	await expect(page.locator(NOTE)).toBeHidden();

	await page.selectOption(PROVIDER, 'vimeo');

	await expect(page.locator(NOTE)).toBeVisible();
});
