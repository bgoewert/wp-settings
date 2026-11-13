// @ts-check
const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const { execFileSync } = require('node:child_process');

/**
 * An email preview on a real settings page, repainting as its template is typed.
 *
 * Only a browser runs the repaint, and only a real render through the site's
 * mail filter says what the repaint should equal.
 */

const MAIL_URL = '/wp-admin/options-general.php?page=wp_settings_harness&tab=mail';
const PREFIX = 'wp_settings_harness_';
const SUBJECT = `#${PREFIX}notice_subject`;
const BODY = `#${PREFIX}notice_body`;
const PREVIEW = '.wps-email-preview';
const WRAPPER = 'wp_settings_harness_mail_wrapper';

function wp(...args) {
	return execFileSync('ddev', ['exec', '--dir', '/var/www/html/.local/wp', 'wp', ...args], { stdio: 'pipe' }).toString();
}

/**
 * The harness notice with this subject and body, rendered server-side through the site's filter.
 */
function serverRender(subject, body) {
	const b64 = (s) => Buffer.from(s, 'utf8').toString('base64');
	const json = wp(
		'eval',
		`echo wp_json_encode(WP_Settings_Harness::notice_email(base64_decode('${b64(subject)}'), base64_decode('${b64(body)}'))->render());`,
	);
	return JSON.parse(json.slice(json.indexOf('{')));
}

async function open(page, wrapper) {
	wp('option', 'update', WRAPPER, wrapper);
	await page.goto('/wp-login.php');
	await page.fill('#user_login', 'admin');
	await page.fill('#user_pass', 'admin');
	await page.click('#wp-submit');
	await page.waitForURL(/wp-admin/);
	await page.goto(MAIL_URL);
}

test.afterAll(() => {
	wp('option', 'delete', WRAPPER);
	wp('option', 'delete', `${PREFIX}notice_subject`);
	wp('option', 'delete', `${PREFIX}notice_body`);
});

test('typing under a site wrapper repaints the frame to match a server render, with no request', async ({ page }) => {
	await open(page, 'brand');
	const frame = page.locator(`${PREVIEW} iframe`);
	await expect(frame).toHaveAttribute('sandbox', '');

	const requests = [];
	page.on('request', (request) => requests.push(request.url()));

	const subject = '{event_title} moved & "rescheduled"';
	const body = 'Hi {attendee_name},\n\nThe new venue is at https://example.com/map?a=1&b=2, see you there.\nBring <ID>.';
	await page.fill(SUBJECT, subject);
	await page.fill(BODY, body);

	const expected = serverRender(subject, body);
	await expect(page.locator(`${PREVIEW} [data-wps-email-subject]`)).toHaveText(expected.subject);
	expect(await frame.evaluate((el) => /** @type {HTMLIFrameElement} */ (el).srcdoc)).toBe(expected.message);

	const link = page.frameLocator(`${PREVIEW} iframe`).locator('a');
	await expect(link).toHaveAttribute('href', 'https://example.com/map?a=1&b=2');
	await expect(page.frameLocator(`${PREVIEW} iframe`).locator('p')).toHaveCount(2);
	expect(requests).toEqual([]);
});

test('typing with no wrapper repaints the plain-text preview', async ({ page }) => {
	await open(page, 'none');
	await expect(page.locator(`${PREVIEW} iframe`)).toHaveCount(0);

	await page.fill(BODY, 'Hello {attendee_name}, about {event_title}.');

	await expect(page.locator(`${PREVIEW} pre`)).toHaveText('Hello Jane Doe, about Sample Event.');
});

test('a wrapper that hides the body shows the saved-preview note and does not repaint', async ({ page }) => {
	await open(page, 'drop');
	const before = await page.locator(`${PREVIEW} iframe`).getAttribute('srcdoc');

	await page.fill(BODY, 'Something else entirely');

	await expect(page.locator(PREVIEW)).toContainText('The preview updates when the settings are saved.');
	expect(await page.locator(`${PREVIEW} iframe`).getAttribute('srcdoc')).toBe(before);
});

test('saving the tab stores the template and nothing for the preview, and the preview shows the saved text', async ({ page }) => {
	await open(page, 'brand');

	await page.fill(BODY, 'Saved body for {attendee_name}');
	await page.click('#submit');
	await page.waitForLoadState('networkidle');

	expect(wp('option', 'get', `${PREFIX}notice_body`).trim()).toBe('Saved body for {attendee_name}');
	expect(() => wp('option', 'get', `${PREFIX}notice_preview`)).toThrow();
	await expect(page.frameLocator(`${PREVIEW} iframe`).locator('p')).toHaveText('Saved body for Jane Doe');
});

test('the preview row has no accessibility violations', async ({ page }) => {
	await open(page, 'brand');

	// The frame's document is the site's template, and a sandbox without scripts
	// cannot host axe; the frame element itself is still checked for its title.
	// Legacy mode skips the blank page axe opens to merge frame results, whose
	// creation fails here with a Target.createTarget protocol error.
	const results = await new AxeBuilder({ page }).include(PREVIEW).options({ iframes: false }).setLegacyMode(true).analyze();

	expect(results.violations).toEqual([]);
});
