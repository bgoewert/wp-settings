// @ts-check
const { test, expect } = require('@playwright/test');
const { execFileSync } = require('node:child_process');

/**
 * The dual listbox driven the way an admin drives it.
 *
 * Only a browser runs the script that mirrors the chosen <select> into the
 * hidden inputs, and that mirroring is the whole field: a `<select multiple>`
 * submits what the user highlighted, not what is in it, so without the script
 * a save would store the highlight and lose the list.
 */

const SETTINGS_URL = '/wp-admin/options-general.php?page=wp_settings_harness';
const FIELD = '.wps-dual-list[data-field="wp_settings_harness_attendee_columns"]';
const AVAILABLE = `${FIELD} [data-role="available"]`;
const CHOSEN = `${FIELD} [data-role="chosen"]`;

// Each test saves, so without this every test after the first would start from
// whatever the previous one left behind.
function resetField() {
	const reset = (option, json) =>
		execFileSync('ddev', [
			'exec', '--dir', '/var/www/html/.local/wp',
			'wp', 'option', 'update', option, json, '--format=json',
		], { stdio: 'pipe' });

	reset('wp_settings_harness_attendee_columns', '["primary_info","ticket"]');
	reset('wp_settings_harness_quoted_columns', '[]');
}

test.beforeEach(async ({ page }) => {
	resetField();

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

const chosenKeys = (page) =>
	page.locator(`${CHOSEN} option`).evaluateAll((options) => options.map((o) => o.value));

test('moving an option across and saving keeps it chosen', async ({ page }) => {
	await page.selectOption(AVAILABLE, 'email');
	await page.click(`${FIELD} [data-move="add"]`);
	await save(page);
	await page.goto(SETTINGS_URL);

	expect(await chosenKeys(page)).toEqual(['primary_info', 'ticket', 'email']);
});

test('the order the admin arranges is the order that saves', async ({ page }) => {
	await page.selectOption(CHOSEN, 'ticket');
	await page.click(`${FIELD} [data-move="up"]`);
	await save(page);
	await page.goto(SETTINGS_URL);

	expect(await chosenKeys(page)).toEqual(['ticket', 'primary_info']);
});

test('removing every option saves an empty list rather than keeping the old one', async ({ page }) => {
	await page.selectOption(CHOSEN, ['primary_info', 'ticket']);
	await page.click(`${FIELD} [data-move="remove"]`);
	await save(page);
	await page.goto(SETTINGS_URL);

	expect(await chosenKeys(page)).toEqual([]);
	await expect(page.locator(`${AVAILABLE} option`)).toHaveCount(3);
});

test('double-clicking an option moves it across', async ({ page }) => {
	await page.locator(`${AVAILABLE} option[value="email"]`).dblclick();

	expect(await chosenKeys(page)).toEqual(['primary_info', 'ticket', 'email']);
});

/**
 * The script used to build the hidden inputs as an HTML string with the key
 * escaped as text, which leaves `"` alone — so a key holding one closed the
 * value attribute early and the rest was parsed as markup (#22).
 */
test('an option key containing a double quote survives a save', async ({ page }) => {
	const field = '.wps-dual-list[data-field="wp_settings_harness_quoted_columns"]';

	await page.selectOption(`${field} [data-role="available"]`, 'a"b');
	await page.click(`${field} [data-move="add"]`);

	// The value has to reach the form as one input, not as broken markup.
	const posted = await page
		.locator(`${field} [data-role="inputs"] input`)
		.evaluateAll((inputs) => inputs.map((i) => i.value));
	expect(posted).toEqual(['', 'a"b']);

	await save(page);
	await page.goto(SETTINGS_URL);

	const chosen = await page
		.locator(`${field} [data-role="chosen"] option`)
		.evaluateAll((options) => options.map((o) => o.value));
	expect(chosen).toEqual(['a"b']);
});

test('the moves are reachable by keyboard alone', async ({ page }) => {
	await page.locator(AVAILABLE).focus();
	await page.selectOption(AVAILABLE, 'email');

	// Tab from the available list lands on the first move button.
	await page.keyboard.press('Tab');
	await expect(page.locator(`${FIELD} [data-move="add"]`)).toBeFocused();
	await page.keyboard.press('Enter');

	expect(await chosenKeys(page)).toEqual(['primary_info', 'ticket', 'email']);
});
