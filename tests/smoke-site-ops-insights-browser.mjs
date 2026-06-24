#!/usr/bin/env node
/**
 * Optional browser smoke for the Full-site Insights admin UI.
 *
 * This opens a real local WordPress admin page, generates the local report,
 * checks the summary-first panels, switches sub-tabs, and captures a screenshot.
 * It does not run Cloud analysis, create Core proposals, or write WordPress data.
 */

import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync } from 'node:fs';
import { createRequire } from 'node:module';
import { dirname, resolve } from 'node:path';
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
			'-d',
			`pdo_mysql.default_socket=${socket}`,
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
	return parsed.map((cookie) => ({
		name: String(cookie.name || ''),
		value: String(cookie.value || ''),
		url: baseUrl,
		httpOnly: true,
		secure: baseUrl.startsWith('https:'),
		sameSite: 'Lax',
	})).filter((cookie) => cookie.name && cookie.value);
}

function forbiddenRequests(requests) {
	return requests.filter((request) => /site_ops_cloud_analysis=1|proposals|governance-core|approve-and-execute|media-derivative-runs/i.test(request.url));
}

async function openToolboxPage(page, baseUrl) {
	const paths = [
		'/wp-admin/admin.php?page=npcink-toolbox&toolbox_tab=operations-insights',
		'/wp-admin/tools.php?page=npcink-toolbox&toolbox_tab=operations-insights',
	];
	for (const path of paths) {
		await page.goto(`${baseUrl}${path}`, { waitUntil: 'domcontentloaded', timeout: 45000 });
		const count = await page.locator('[data-toolbox-site-ops-insights]').count();
		if (count > 0) {
			return `${baseUrl}${path}`;
		}
	}
	const title = await page.title().catch(() => '');
	const body = await page.locator('body').innerText({ timeout: 5000 }).catch(() => '');
	fail(`Could not find the Full-site Insights admin panel. Last URL: ${page.url()}. Last page title: ${title}. Body: ${body.slice(0, 500)}`);
}

const { chromium } = await loadPlaywright();
const baseUrl = env('WP_BASE_URL', 'https://npcink.local').replace(/\/$/, '');
const screenshotPath = resolve(env('SITE_OPS_BROWSER_SCREENSHOT', 'build/smoke/site-ops-insights.png'));
const browserOptions = {
	headless: process.env.HEADLESS !== '0',
};
if (process.env.BROWSER_EXECUTABLE) {
	browserOptions.executablePath = process.env.BROWSER_EXECUTABLE;
} else {
	const normalChrome = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
	if (existsSync(normalChrome)) {
		browserOptions.executablePath = normalChrome;
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
		if (url.includes('/wp-admin/') || url.includes('/wp-json/')) {
			requests.push({ method: request.method(), url });
		}
	});

	await openToolboxPage(page, baseUrl);
	await page.waitForSelector('[data-toolbox-site-ops-insights]', { state: 'attached', timeout: 30000 });
	if (await page.locator('[data-toolbox-tab-panel="operations-insights"]').isHidden()) {
		await page.getByRole('button', { name: 'Full-site Insights', exact: true }).click();
	}
	await page.waitForSelector('[data-toolbox-tab-panel="operations-insights"]:not([hidden])', { timeout: 30000 });
	await page.getByRole('link', { name: 'Generate full-site report' }).click();
	await page.waitForLoadState('domcontentloaded');
	await page.waitForSelector('[data-toolbox-ops-tabs]', { timeout: 30000 });

	const overviewText = await page.locator('[data-toolbox-ops-panel="overview"]').innerText();
	assert(overviewText.includes('Local analysis summary'), 'Overview shows the local analysis summary first.');
	assert(overviewText.includes('Start with') || overviewText.includes('No priority site analysis findings'), 'Overview gives a clear first-read outcome.');
	assert(overviewText.includes('Core planning') || overviewText.includes('manual review') || overviewText.includes('high priority'), 'Overview shows priority or review-path context.');
	assert(await page.locator('[data-toolbox-ops-panel="advanced"]').isHidden(), 'Advanced JSON panel is hidden by default.');

	for (const tabName of ['Content', 'Media', 'Comments', 'Structure', 'Findings', 'Evidence', 'Cloud analysis', 'Advanced']) {
		await page.getByRole('button', { name: tabName, exact: true }).click();
		const selected = await page.getByRole('button', { name: tabName, exact: true }).getAttribute('aria-selected');
		assert(selected === 'true', `${tabName} sub-tab can be opened.`);
	}

	await page.getByRole('button', { name: 'Cloud analysis', exact: true }).click();
	const cloudText = await page.locator('[data-toolbox-ops-panel="cloud"]').innerText();
	assert(cloudText.includes('Cloud analysis has not run') || cloudText.includes('Cloud analysis result'), 'Cloud tab renders review-only status without automatic execution.');
	assert(forbiddenRequests(requests).length === 0, 'Full-site Insights browser smoke does not call Cloud analysis, Core proposals, or execute routes.');

	mkdirSync(dirname(screenshotPath), { recursive: true });
	await page.screenshot({ path: screenshotPath, fullPage: true });
	assert(existsSync(screenshotPath), `Screenshot captured at ${screenshotPath}.`);
} finally {
	await browser.close();
}

pass(`Full-site Insights browser smoke completed at ${baseUrl}.`);
