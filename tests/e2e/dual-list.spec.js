// @ts-check
const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const { execFileSync } = require('node:child_process');

/**
 * The dual listbox driven the way an admin drives it.
 *
 * Only a browser runs the script, and the script is the whole field: the two
 * lists are `ul[role=listbox]`, so selection, the keyboard and dragging are all
 * its work, and the chosen list is mirrored into the hidden inputs that post.
 */

const SETTINGS_URL = '/wp-admin/options-general.php?page=wp_settings_harness';
const FIELD = '.wps-dual-list[data-field="wp_settings_harness_attendee_columns"]';
const AVAILABLE = `${FIELD} [data-role="available"]`;
const CHOSEN = `${FIELD} [data-role="chosen"]`;

// Each test saves, so without this every test after the first would start from
// whatever the previous one left behind.
function resetField(columns = '["primary_info","ticket"]') {
	const reset = (option, json) =>
		execFileSync('ddev', [
			'exec', '--dir', '/var/www/html/.local/wp',
			'wp', 'option', 'update', option, json, '--format=json',
		], { stdio: 'pipe' });

	reset('wp_settings_harness_attendee_columns', columns);
	reset('wp_settings_harness_quoted_columns', '[]');
}

async function login(page) {
	await page.goto('/wp-login.php');
	await page.fill('#user_login', 'admin');
	await page.fill('#user_pass', 'admin');
	await page.click('#wp-submit');
	await page.waitForURL(/wp-admin/);
	await page.goto(SETTINGS_URL);
}

test.beforeEach(async ({ page }) => {
	resetField();
	await login(page);
});

async function save(page) {
	await page.click('input[type="submit"], button[type="submit"]');
	await page.waitForLoadState('networkidle');
}

const keysIn = (page, list) =>
	page.locator(`${list} .wps-dual-list-item`).evaluateAll((items) => items.map((i) => i.dataset.key));

const chosenKeys = (page) => keysIn(page, CHOSEN);

const item = (page, list, key) => page.locator(`${list} .wps-dual-list-item[data-key="${key}"]`);

test('moving an item across and saving keeps it chosen', async ({ page }) => {
	await item(page, AVAILABLE, 'email').click();
	await page.click(`${FIELD} [data-move="add"]`);
	await save(page);
	await page.goto(SETTINGS_URL);

	expect(await chosenKeys(page)).toEqual(['primary_info', 'ticket', 'email']);
});

test('the order the admin arranges is the order that saves', async ({ page }) => {
	await item(page, CHOSEN, 'ticket').click();
	await page.click(`${FIELD} [data-move="up"]`);
	await save(page);
	await page.goto(SETTINGS_URL);

	expect(await chosenKeys(page)).toEqual(['ticket', 'primary_info']);
});

test('removing every item saves an empty list rather than keeping the old one', async ({ page }) => {
	await item(page, CHOSEN, 'primary_info').click();
	await item(page, CHOSEN, 'ticket').click({ modifiers: ['Shift'] });
	await page.click(`${FIELD} [data-move="remove"]`);
	await save(page);
	await page.goto(SETTINGS_URL);

	expect(await chosenKeys(page)).toEqual([]);
	await expect(page.locator(`${AVAILABLE} .wps-dual-list-item`)).toHaveCount(3);
});

test('double-clicking an item moves it across', async ({ page }) => {
	await item(page, AVAILABLE, 'email').dblclick();

	expect(await chosenKeys(page)).toEqual(['primary_info', 'ticket', 'email']);
});

/**
 * The reason the sides are listboxes and not `select[multiple]`: an `<option>`
 * fires no drag events in Firefox or Safari, so the field could not be dragged
 * at all (#23). Playwright's drag uses real pointer events, so this exercises
 * the same path a person does.
 */
test('an item can be dragged to a new position and saved there', async ({ page }) => {
	resetField('["primary_info","ticket","email"]');
	await page.goto(SETTINGS_URL);

	await item(page, CHOSEN, 'email').dragTo(item(page, CHOSEN, 'primary_info'));

	expect(await chosenKeys(page)).toEqual(['email', 'primary_info', 'ticket']);

	await save(page);
	await page.goto(SETTINGS_URL);

	expect(await chosenKeys(page)).toEqual(['email', 'primary_info', 'ticket']);
});

test('dragging an item to the other list moves it', async ({ page }) => {
	await item(page, AVAILABLE, 'email').dragTo(page.locator(CHOSEN));

	expect(await chosenKeys(page)).toEqual(['primary_info', 'ticket', 'email']);

	await save(page);
	await page.goto(SETTINGS_URL);

	expect(await chosenKeys(page)).toEqual(['primary_info', 'ticket', 'email']);
});

/**
 * Pressing an item that is already selected must not collapse the selection, or
 * a group drag moves one item; and a group has to land against one reference
 * node, or inserting each item in turn reverses it.
 */
test('a multi-item selection drags as a group and keeps its order', async ({ page }) => {
	resetField('["primary_info","ticket","email"]');
	await page.goto(SETTINGS_URL);

	await item(page, CHOSEN, 'primary_info').click();
	await item(page, CHOSEN, 'ticket').click({ modifiers: ['Shift'] });
	await item(page, CHOSEN, 'primary_info').dragTo(page.locator(CHOSEN));

	expect(await chosenKeys(page)).toEqual(['email', 'primary_info', 'ticket']);
});

/**
 * The script used to build the hidden inputs as an HTML string with the key
 * escaped as text, which leaves `"` alone — so a key holding one closed the
 * value attribute early and the rest was parsed as markup (#22).
 */
test('an item key containing a double quote survives a save', async ({ page }) => {
	const field = '.wps-dual-list[data-field="wp_settings_harness_quoted_columns"]';

	await page.locator(`${field} [data-role="available"] .wps-dual-list-item[data-key='a"b']`).click();
	await page.click(`${field} [data-move="add"]`);

	// The value has to reach the form as one input, not as broken markup.
	const posted = await page
		.locator(`${field} [data-role="inputs"] input`)
		.evaluateAll((inputs) => inputs.map((i) => i.value));
	expect(posted).toEqual(['', 'a"b']);

	await save(page);
	await page.goto(SETTINGS_URL);

	const chosen = await page
		.locator(`${field} [data-role="chosen"] .wps-dual-list-item`)
		.evaluateAll((items) => items.map((i) => i.dataset.key));
	expect(chosen).toEqual(['a"b']);
});

test('the moves are reachable by keyboard alone', async ({ page }) => {
	await page.locator(AVAILABLE).focus();

	// Arrows walk the list, space selects, and Tab lands on the first button.
	await page.keyboard.press('ArrowDown');
	await expect(item(page, AVAILABLE, 'email')).toHaveAttribute('aria-selected', 'true');

	await page.keyboard.press('Tab');
	await expect(page.locator(`${FIELD} [data-move="add"]`)).toBeFocused();
	await page.keyboard.press('Enter');

	expect(await chosenKeys(page)).toEqual(['primary_info', 'ticket', 'email']);
});

/** Enter on a list is the keyboard's double-click, and must not submit the form. */
test('enter moves the selection to the other list without submitting', async ({ page }) => {
	await item(page, AVAILABLE, 'email').click();
	await page.locator(AVAILABLE).press('Enter');

	expect(await chosenKeys(page)).toEqual(['primary_info', 'ticket', 'email']);
	await expect(page).toHaveURL(new RegExp('page=wp_settings_harness$'));
});

/**
 * Roving focus: one tabindex on the list, the active item tracked in
 * aria-activedescendant, and shift+arrow extending the selection.
 */
test('the active item and the selection are exposed on the list', async ({ page }) => {
	await item(page, CHOSEN, 'primary_info').click();
	await page.locator(CHOSEN).press('Shift+ArrowDown');

	const list = page.locator(CHOSEN);
	await expect(list).toHaveAttribute('aria-activedescendant', /_opt_1$/);
	await expect(item(page, CHOSEN, 'primary_info')).toHaveAttribute('aria-selected', 'true');
	await expect(item(page, CHOSEN, 'ticket')).toHaveAttribute('aria-selected', 'true');
	await expect(list).toHaveAttribute('role', 'listbox');
	await expect(list).toHaveAttribute('aria-multiselectable', 'true');
});

/**
 * A native select carried its own semantics; a hand-built listbox carries only
 * what the markup says, so the pattern is checked rather than assumed.
 */
test('the field has no accessibility violations', async ({ page }) => {
	const results = await new AxeBuilder({ page }).include(FIELD).analyze();

	expect(results.violations).toEqual([]);
});

/** A block that has reached the top stops there instead of transposing. */
test('a selected block stops at the top instead of shuffling', async ({ page }) => {
	resetField('["primary_info","ticket","email"]');
	await page.goto(SETTINGS_URL);

	await item(page, CHOSEN, 'ticket').click();
	await item(page, CHOSEN, 'email').click({ modifiers: ['Shift'] });

	// Once to reach the top, twice more against it.
	for (let press = 0; press < 3; press++) {
		await page.click(`${FIELD} [data-move="up"]`);
	}

	expect(await chosenKeys(page)).toEqual(['ticket', 'email', 'primary_info']);
});
