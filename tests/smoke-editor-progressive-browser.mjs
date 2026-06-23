#!/usr/bin/env node
/**
 * Browser smoke for the local progressive editor recommendation surface.
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

function getPostId() {
	if (process.env.POST_ID) {
		return process.env.POST_ID;
	}
	const id = wpCli([
		'eval',
		`$posts = get_posts(array('post_type' => 'post', 'post_status' => array('draft','publish','pending','future','private'), 'posts_per_page' => 1, 'orderby' => 'modified', 'order' => 'DESC', 'fields' => 'ids')); echo absint($posts[0] ?? 0);`,
	]);
	if (!id) {
		fail('No local post found. Create a post or set POST_ID before running the browser smoke.');
	}
	return id;
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

function progressiveRequests(requests) {
	return requests.filter((request) => request.url.includes('/wp-json/npcink-toolbox/v1/editor/content-support'));
}

function forbiddenRequests(requests) {
	return requests.filter((request) => {
		if (!request.url.includes('/wp-json/')) {
			return false;
		}
		if (request.url.includes('/wp-json/npcink-toolbox/v1/editor/content-support')) {
			return false;
		}
		return /proposal|adapter|governance-core|ai\/content-support|cloud/i.test(request.url);
	});
}

async function waitForProgressiveRequest(requests, count, timeoutMs = 7000) {
	const start = Date.now();
	while (Date.now() - start < timeoutMs) {
		if (progressiveRequests(requests).length >= count) {
			return;
		}
		await new Promise((resolve) => setTimeout(resolve, 100));
	}
	fail(`Timed out waiting for ${count} progressive content-support request(s). Saw ${progressiveRequests(requests).length}.`);
}

const { chromium } = await loadPlaywright();
const baseUrl = env('WP_BASE_URL', 'https://npcink.local').replace(/\/$/, '');
const postId = getPostId();
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

	await page.goto(`${baseUrl}/wp-admin/post.php?post=${postId}&action=edit`, { waitUntil: 'domcontentloaded', timeout: 45000 });
	await page.waitForFunction(() => window.wp && window.wp.data && window.wp.data.dispatch, null, { timeout: 30000 });
	await page.evaluate(() => {
		const dispatch = window.wp.data.dispatch('core/edit-post') || window.wp.data.dispatch('core/editor');
		if (dispatch && dispatch.openGeneralSidebar) {
			dispatch.openGeneralSidebar('npcink-toolbox-editor-content-support/npcink-content-support-sidebar');
		}
	});
	await waitForProgressiveRequest(requests, 1);
	await page.waitForSelector('text=/Run fixed support flows|围绕当前草稿运行固定支持流程/', { timeout: 30000 });
	const defaultProgressiveCardCount = await page.locator('text=/Fast recommendations|快速推荐|Local suggestions are ready|本地建议已就绪/').count();
	assert(defaultProgressiveCardCount === 0, 'Successful local progressive recommendations stay hidden by default.');

	const firstRequest = progressiveRequests(requests)[0];
	assert(firstRequest.method === 'POST', 'Automatic prefetch uses POST /editor/content-support.');
	assert(firstRequest.body.includes('progressive_recommendations'), 'Automatic prefetch sends the progressive_recommendations intent.');
	assert(!/writing_support|proposal|adapterRestUrl/i.test(firstRequest.body), 'Automatic prefetch does not send writing support or proposal handoff data.');
	assert(forbiddenRequests(requests).length === 0, 'Automatic prefetch does not call Cloud, Adapter, or Core proposal routes.');

	await page.getByRole('button', { name: /Local suggestions|本地建议/ }).click();
	await page.waitForSelector('text=/View suggestions|查看建议/', { timeout: 10000 });

	const beforeRefresh = progressiveRequests(requests).length;
	await page.getByRole('button', { name: /Refresh|刷新/ }).click();
	await waitForProgressiveRequest(requests, beforeRefresh + 1);
	const afterRefresh = progressiveRequests(requests).length;
	assert(afterRefresh === beforeRefresh + 1, 'Refresh triggers exactly one more local progressive request.');
	assert(forbiddenRequests(requests).length === 0, 'Refresh remains local-only and does not call Cloud, Adapter, or Core proposal routes.');

	await page.getByRole('button', { name: /View suggestions|查看建议/ }).click();
	await page.waitForSelector('text=/Source:|来源：|Action:|动作：/', { timeout: 10000 });
	const reviewText = await page.locator('.npcink-toolbox-editor-support').innerText({ timeout: 10000 });
	assert(/Source:|来源：/.test(reviewText) && /Action:|动作：/.test(reviewText), 'Review view shows candidate source and action labels.');
	assert(!reviewText.includes('Post Formats'), 'Review view does not surface generic Post Formats taxonomy noise.');
} finally {
	await browser.close();
}

pass(`Browser smoke completed for post ${postId} at ${baseUrl}.`);
