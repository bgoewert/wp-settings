// @ts-check
const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const { execFileSync } = require('node:child_process');

/**
 * The `media` field driven through the real wp.media modal.
 *
 * Only a browser proves the script and the modal agree: that wp.media is
 * defined when Choose is pressed, that the selection lands in the hidden input
 * that posts, and that Remove clears the preview as well as the value.
 */

const SETTINGS_URL = '/wp-admin/options-general.php?page=wp_settings_harness';
const OPTION = 'wp_settings_harness_site_logo';
const FIELD = `.wps-media[data-field="${OPTION}"]`;
const VALUE = `#${OPTION}`;

// The last line only: the harness's wp-config can print notices to stdout ahead
// of the value a command returns.
function wp(...args) {
	return execFileSync('ddev', ['exec', '--dir', '/var/www/html/.local/wp', 'wp', ...args], { stdio: 'pipe' })
		.toString()
		.trim()
		.split('\n')
		.pop()
		.trim();
}

let attachmentId = '';

test.beforeAll(() => {
	attachmentId = wp(
		'media', 'import', '/var/www/html/tests/e2e/fixtures/logo.png',
		'--title=Harness Logo', '--alt=Harness wordmark', '--porcelain',
	);
});

test.afterAll(() => {
	wp('post', 'delete', attachmentId, '--force');
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

async function save(page) {
	await page.click('#submit');
	await page.waitForLoadState('networkidle');
}

async function chooseInModal(page) {
	await page.click(`${FIELD} .wps-media-choose`);
	const modal = page.locator('.media-modal:visible');
	await expect(modal.locator('.media-frame-title')).toContainText('Choose Site Logo');
	// Core opens on Upload for a user who has never picked a tab.
	await modal.getByRole('tab', { name: 'Media Library' }).click();
	await modal.locator(`.attachment[data-id="${attachmentId}"]`).click();
	await modal.locator('.media-button-select').click();
	await expect(modal).toBeHidden();
}

test('choosing an image stores its attachment id and previews it after saving', async ({ page }) => {
	await chooseInModal(page);

	await expect(page.locator(VALUE)).toHaveValue(attachmentId);
	await expect(page.locator(`${FIELD} .wps-media-image`)).toHaveAttribute('alt', 'Harness wordmark');

	await save(page);
	await page.goto(SETTINGS_URL);

	await expect(page.locator(VALUE)).toHaveValue(attachmentId);
	await expect(page.locator(`${FIELD} .wps-media-image`)).toHaveAttribute('alt', 'Harness wordmark');
	await expect(page.locator(`${FIELD} .wps-media-remove`)).toBeVisible();
});

test('removing clears the preview and the value, and saves no image', async ({ page }) => {
	wp('option', 'update', OPTION, attachmentId);
	await page.goto(SETTINGS_URL);

	await page.click(`${FIELD} .wps-media-remove`);

	await expect(page.locator(`${FIELD} .wps-media-image`)).toHaveCount(0);
	await expect(page.locator(`${FIELD} .wps-media-remove`)).toBeHidden();
	await expect(page.locator(`${FIELD} .wps-media-choose`)).toBeFocused();

	await save(page);
	await page.goto(SETTINGS_URL);

	await expect(page.locator(VALUE)).toHaveValue('');
	expect(wp('option', 'get', OPTION, '--format=json')).toBe('""');
});

test('a page with a media field has no accessibility violations on it', async ({ page }) => {
	wp('option', 'update', OPTION, attachmentId);
	await page.goto(SETTINGS_URL);

	const results = await new AxeBuilder({ page }).include(FIELD).analyze();

	expect(results.violations).toEqual([]);
});
