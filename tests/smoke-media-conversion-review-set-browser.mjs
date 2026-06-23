#!/usr/bin/env node
/**
 * Browser smoke for the media conversion local automation review-set UI.
 *
 * This is intentionally not part of composer test:all. It needs a running local
 * WordPress site, WP-CLI access, and Playwright.
 */

import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';

function pass(message) {
	console.log(`PASS: ${message}`);
}

function fail(message) {
	console.error(`FAIL: ${message}`);
	process.exit(1);
}

function assert(condition, message) {
	if (!condition) {
		fail(message);
	}
	pass(message);
}

async function loadPlaywright() {
	try {
		return await import('playwright');
	} catch (error) {
		const require = createRequire(import.meta.url);
		const paths = String(process.env.NODE_PATH || '').split(':').filter(Boolean);
		try {
			const resolved = require.resolve('playwright', { paths });
			const module = await import(pathToFileURL(resolved).href);
			return module.chromium ? module : module.default;
		} catch (fallbackError) {
			fail(`Playwright is not available. Install it or set NODE_PATH to the bundled runtime before running this smoke. ${fallbackError.message || error.message}`);
		}
	}
}

function env(name, fallback) {
	return process.env[name] || fallback;
}

function wpCli(args, options = {}) {
	const php = env('WP_CLI_PHP', `${process.env.HOME}/Library/Application Support/Local/lightning-services/php-8.5.3+1/bin/darwin-arm64/bin/php`);
	const wp = env('WP_CLI_BIN', '/opt/homebrew/bin/wp');
	const socket = env('WP_DB_SOCKET', `${process.env.HOME}/Library/Application Support/Local/run/NPb24Zg9g/mysql/mysqld.sock`);
	const wpPath = env('WP_PATH', '/Users/muze/Local Sites/npcink/app/public');
	return execFileSync(
		php,
		[
			'-d',
			'display_errors=0',
			'-d',
			'error_reporting=8191',
			'-d',
			`mysqli.default_socket=${socket}`,
			wp,
			`--path=${wpPath}`,
			'--no-color',
			...args,
		],
		{
			encoding: 'utf8',
			stdio: ['ignore', 'pipe', 'pipe'],
			...options,
		}
	).trim();
}

function authCookies(baseUrl) {
	const cookieJson = wpCli([
		'eval',
		`
$users = get_users(array('role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'order' => 'ASC'));
$user = $users ? $users[0] : null;
if (!$user) { fwrite(STDERR, 'No administrator user found.'); exit(1); }
$expiration = time() + DAY_IN_SECONDS;
$cookies = array(
	array('name' => AUTH_COOKIE, 'value' => wp_generate_auth_cookie($user->ID, $expiration, 'auth')),
	array('name' => SECURE_AUTH_COOKIE, 'value' => wp_generate_auth_cookie($user->ID, $expiration, 'secure_auth')),
	array('name' => LOGGED_IN_COOKIE, 'value' => wp_generate_auth_cookie($user->ID, $expiration, 'logged_in')),
);
echo wp_json_encode($cookies);
`,
	]);
	let parsed;
	try {
		parsed = JSON.parse(cookieJson);
	} catch (error) {
		fail(`Could not parse WordPress auth cookie JSON. ${error.message}`);
	}
	const { hostname, protocol } = new URL(baseUrl);
	return parsed.map((cookie) => ({
		name: String(cookie.name || ''),
		value: String(cookie.value || ''),
		domain: hostname,
		path: '/',
		httpOnly: true,
		secure: protocol === 'https:',
		sameSite: 'Lax',
	})).filter((cookie) => cookie.name && cookie.value);
}

function wpJsonRequests(requests) {
	return requests.filter((request) => request.url.includes('/wp-json/'));
}

function forbiddenWriteRequests(requests) {
	return wpJsonRequests(requests).filter((request) => {
		if (request.url.includes('/wp-json/npcink-openclaw-adapter/v1/run-read-ability')) {
			return false;
		}
		return /media-derivative-runs|proposals|governance-core|approve-and-execute|media-derivative-proposal/i.test(request.url);
	});
}

async function waitForReviewSet(page, timeoutMs = 15000) {
	await page.waitForFunction(
		() => {
			const panel = document.querySelector('[data-toolbox-media-batch-plan]');
			const text = panel ? panel.innerText || '' : '';
			return !panel.hidden && text.includes('npcink_local_automation_media_conversion_review_set.v1');
		},
		null,
		{ timeout: timeoutMs }
	);
}

const { chromium } = await loadPlaywright();
const baseUrl = env('WP_BASE_URL', 'https://npcink.local').replace(/\/$/, '');
const browserOptions = {
	headless: process.env.HEADLESS !== '0',
};
if (process.env.BROWSER_EXECUTABLE) {
	browserOptions.executablePath = process.env.BROWSER_EXECUTABLE;
} else {
	const chrome = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
	if (existsSync(chrome)) {
		browserOptions.executablePath = chrome;
	}
}

const browser = await chromium.launch(browserOptions);
try {
	const context = await browser.newContext({ ignoreHTTPSErrors: true });
	await context.addCookies(authCookies(baseUrl));
	const page = await context.newPage();
	const requests = [];
	page.on('request', (request) => {
		const url = request.url();
		if (!url.includes('/wp-json/')) {
			return;
		}
		requests.push({
			method: request.method(),
			url,
			body: request.postData() || '',
		});
	});

	await page.goto(`${baseUrl}/wp-admin/admin.php?page=npcink-toolbox&toolbox_tab=tools&toolbox_tool=media-derivative`, { waitUntil: 'domcontentloaded', timeout: 45000 });
	await page.waitForSelector('[data-toolbox-build-media-batch-plan]', { timeout: 30000 });
	await page.locator('[data-toolbox-build-media-batch-plan]').click();
	await waitForReviewSet(page);

	const ui = await page.evaluate(() => {
		const panel = document.querySelector('[data-toolbox-media-batch-plan]');
		const text = panel ? panel.innerText || '' : '';
		const selected = Array.from(document.querySelectorAll('[data-toolbox-media-batch-candidate]'));
		return {
			text,
			hidden: panel ? panel.hidden : true,
			selectedCount: selected.length,
			previewDisabled: document.querySelector('[data-toolbox-run-media-batch-previews]')?.disabled ?? null,
			proposalDisabled: document.querySelector('[data-toolbox-submit-media-batch-proposals]')?.disabled ?? null,
		};
	});

	assert(!ui.hidden, 'Media batch review-set panel is visible after building the plan.');
	assert(ui.text.includes('Media conversion review set'), 'Review-set UI title is visible.');
	assert(ui.text.includes('npcink_local_automation_media_conversion_review_set.v1'), 'Review-set UI shows the local automation contract.');
	assert(ui.text.includes('npcink-local-automation-runtime'), 'Review-set UI shows the runtime owner.');
	assert(ui.text.includes('governed_review_set'), 'Review-set UI shows governed review-set mode.');
	assert(ui.text.includes('Toolbox is rendering a governed review set only'), 'Review-set UI keeps the no-execution boundary visible.');
	assert(ui.selectedCount > 0, 'Review-set UI exposes selected media candidates for operator review.');
	assert(ui.proposalDisabled === true, 'Core proposal submission remains disabled before selected previews exist.');
	assert(forbiddenWriteRequests(requests).length === 0, 'Building the review set does not call preview, proposal, Core, or execute routes.');
	assert(
		wpJsonRequests(requests).some((request) => request.method === 'POST' && request.url.includes('/wp-json/npcink-openclaw-adapter/v1/run-read-ability')),
		'Building the review set calls only the Adapter read-ability route for the batch plan.'
	);
} finally {
	await browser.close();
}

pass(`Media conversion review-set browser smoke completed at ${baseUrl}.`);
