// @ts-check
const { test, expect } = require('@playwright/test');
const { execFileSync } = require('node:child_process');

/**
 * A color field's `palette` against the active theme's real theme.json.
 *
 * The unit suite stubs wp_kses() to return its input, so only a real WordPress
 * proves the <datalist> survives the allowlist and the browser links it to the
 * input. The system color dialog itself is outside the page and stays a manual
 * check.
 */

const SETTINGS_URL = '/wp-admin/options-general.php?page=wp_settings_harness';
const FIELD = 'wp_settings_harness_brand_color';

function themePalette() {
	const json = execFileSync('ddev', [
		'exec', '--dir', '/var/www/html/.local/wp', 'wp', 'eval',
		'echo wp_json_encode( wp_get_global_settings( array( "color", "palette", "theme" ) ) );',
	], { stdio: 'pipe' }).toString().trim().split('\n').pop();

	return [...new Set(JSON.parse(json || '[]')
		.map((preset) => String(preset.color).toLowerCase())
		.filter((color) => /^#[0-9a-f]{6}$/.test(color)))];
}

test.beforeEach(async ({ page }) => {
	await page.goto('/wp-login.php');
	await page.fill('#user_login', 'admin');
	await page.fill('#user_pass', 'admin');
	await page.click('#wp-submit');
	await page.waitForURL(/wp-admin/);
	await page.goto(SETTINGS_URL);
});

test('the color input suggests the theme.json palette', async ({ page }) => {
	const expected = themePalette();
	expect(expected.length, 'the active theme declares hex palette colors').toBeGreaterThan(0);

	const suggested = await page.locator(`#${FIELD}`).evaluate((input) =>
		Array.from(/** @type {HTMLInputElement} */ (input).list?.options ?? [], (option) => option.value)
	);

	expect(suggested).toEqual(expected);
});
