#!/usr/bin/env node
/**
 * Browser smoke for the local progressive editor recommendation surface.
 *
 * This is intentionally not part of composer test:all. It needs a running local
 * WordPress site, WP-CLI access, and Playwright.
 */

import { execFileSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { existsSync, mkdirSync, unlinkSync, writeFileSync } from 'node:fs';
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

function wpPath() {
	return env('WP_PATH', '/Users/muze/Local Sites/npcink/app/public');
}

function wpCli(args, options = {}) {
	const php = env('WP_CLI_PHP', `${process.env.HOME}/Library/Application Support/Local/lightning-services/php-8.5.3+1/bin/darwin-arm64/bin/php`);
	const wp = env('WP_CLI_BIN', '/opt/homebrew/bin/wp');
	const socket = env('WP_DB_SOCKET', `${process.env.HOME}/Library/Application Support/Local/run/NPb24Zg9g/mysql/mysqld.sock`);
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
			`--path=${wpPath()}`,
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

function createLoginHelper(baseUrl, postId) {
	const token = randomBytes(24).toString('hex');
	const fileName = `npcink-toolbox-browser-smoke-login-${randomBytes(8).toString('hex')}.php`;
	const filePath = `${wpPath().replace(/\/$/, '')}/${fileName}`;
	const requestedPostId = parseInt(postId, 10) || 0;
	writeFileSync(filePath, `<?php
declare(strict_types=1);
$expected = '${token}';
if (!isset($_GET['token']) || !hash_equals($expected, (string) $_GET['token'])) {
	http_response_code(403);
	exit('forbidden');
}
require __DIR__ . '/wp-load.php';
$action = isset($_GET['action']) ? sanitize_key((string) $_GET['action']) : 'login';
if ('cleanup' === $action) {
	$post_id = absint($_GET['post_id'] ?? 0);
	if ($post_id > 0) {
		wp_delete_post($post_id, true);
	}
	echo 'deleted';
	exit;
}
$users = get_users(array('role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'order' => 'ASC'));
$user = $users ? $users[0] : null;
if (!$user) {
	http_response_code(500);
	exit('no_admin_user');
}
wp_set_current_user($user->ID);
wp_set_auth_cookie($user->ID, false, is_ssl());
$post_id = ${requestedPostId};
if ($post_id <= 0) {
	$post_id = wp_insert_post(array(
		'post_type'    => 'post',
		'post_status'  => 'draft',
		'post_author'  => $user->ID,
		'post_title'   => 'Npcink browser smoke fixture ' . wp_generate_uuid4(),
		'post_content' => 'This temporary draft exists only for the editor progressive browser smoke. It should be deleted by the smoke cleanup.',
	), true);
	if (is_wp_error($post_id)) {
		http_response_code(500);
		exit($post_id->get_error_message());
	}
}
wp_safe_redirect(admin_url('post.php?post=' . absint($post_id) . '&action=edit'));
exit;
`);
	return {
		url: `${baseUrl}/${fileName}?token=${token}`,
		cleanupUrl: (cleanupPostId) => `${baseUrl}/${fileName}?token=${token}&action=cleanup&post_id=${parseInt(cleanupPostId, 10) || 0}`,
		cleanupFile: () => {
			try {
				unlinkSync(filePath);
			} catch (error) {
				// The smoke should not fail only because cleanup raced a local server.
			}
		},
	};
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

async function captureDiagnostics(page, requests, error) {
	const artifactDir = env('SMOKE_ARTIFACT_DIR', 'tests/artifacts');
	mkdirSync(artifactDir, { recursive: true });
	const screenshotPath = `${artifactDir}/editor-progressive-browser-failure.png`;
	await page.screenshot({ path: screenshotPath, fullPage: true }).catch(() => {});
	const pageText = await page.locator('body').innerText({ timeout: 2000 }).catch(() => '');
	console.error(`FAIL: Browser smoke diagnostic screenshot: ${screenshotPath}`);
	console.error(`FAIL: Browser smoke current URL: ${page.url()}`);
	console.error(`FAIL: Browser smoke page title: ${await page.title().catch(() => '')}`);
	console.error(`FAIL: Browser smoke wp-json requests: ${requests.length}`);
	console.error(`FAIL: Browser smoke visible text sample: ${pageText.replace(/\s+/g, ' ').trim().slice(0, 1200)}`);
	console.error(`FAIL: Browser smoke error: ${error && error.message ? error.message : String(error || 'unknown error')}`);
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

async function clickButtonWithText(page, pattern) {
	await page.locator('button').filter({ hasText: pattern }).first().click({ timeout: 30000 });
}

async function dismissEditorOverlays(page) {
	for (let index = 0; index < 3; index += 1) {
		const overlayCount = await page.locator('.components-modal__screen-overlay').count().catch(() => 0);
		if (!overlayCount) {
			return;
		}
		await page.keyboard.press('Escape').catch(() => {});
		await page.waitForTimeout(250);
	}
}

const { chromium } = await loadPlaywright();
const baseUrl = env('WP_BASE_URL', 'https://npcink.local').replace(/\/$/, '');
const requestedPostId = process.env.POST_ID || '';
let activePostId = requestedPostId;
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
let loginHelper = null;
let page = null;
try {
	const context = await browser.newContext({ ignoreHTTPSErrors: true });
	page = await context.newPage();
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

	try {
		loginHelper = createLoginHelper(baseUrl, requestedPostId);
		await page.goto(loginHelper.url, { waitUntil: 'domcontentloaded', timeout: 45000 });
		activePostId = new URL(page.url()).searchParams.get('post') || activePostId;
		await page.waitForFunction(() => window.wp && window.wp.data && window.wp.data.dispatch, null, { timeout: 30000 });
		await dismissEditorOverlays(page);
		await page.evaluate(() => {
			const target = 'npcink-toolbox-editor-content-support/npcink-content-support-sidebar';
			const stores = ['core/edit-post', 'core/interface', 'core/editor'];
			for (let index = 0; index < stores.length; index += 1) {
				try {
					const dispatch = window.wp.data.dispatch(stores[index]);
					if (dispatch && typeof dispatch.openGeneralSidebar === 'function') {
						dispatch.openGeneralSidebar(target);
						return;
					}
				} catch (error) {
					// Older editor builds may not expose every store.
				}
			}
		});
		await waitForProgressiveRequest(requests, 1);
		await page.waitForSelector('text=/Run fixed support flows|围绕当前草稿运行固定支持流程/', { timeout: 30000 });
		const defaultProgressiveCardCount = await page.locator('text=/Fast recommendations|快速推荐|Local suggestions are ready|本地建议已就绪/').count();
		assert(defaultProgressiveCardCount === 0, 'Successful local progressive recommendations stay hidden by default.');
		const defaultLocalSuggestionsButtonCount = await page.locator('button').filter({ hasText: /Local suggestions|本地建议/ }).count();
		assert(defaultLocalSuggestionsButtonCount === 0, 'Successful local progressive recommendations do not add a default Local suggestions button.');

		const firstRequest = progressiveRequests(requests)[0];
		assert(firstRequest.method === 'POST', 'Automatic prefetch uses POST /editor/content-support.');
		assert(firstRequest.body.includes('progressive_recommendations'), 'Automatic prefetch sends the progressive_recommendations intent.');
		assert(!/writing_support|proposal|adapterRestUrl/i.test(firstRequest.body), 'Automatic prefetch does not send writing support or proposal handoff data.');
		assert(forbiddenRequests(requests).length === 0, 'Automatic prefetch does not call Cloud, Adapter, or Core proposal routes.');
	} catch (error) {
		await captureDiagnostics(page, requests, error);
		throw error;
	}
} finally {
	if (loginHelper) {
		if (!requestedPostId && activePostId && page) {
			await page.goto(loginHelper.cleanupUrl(activePostId), { waitUntil: 'domcontentloaded', timeout: 10000 }).catch((error) => {
				console.error(`WARN: Could not delete temporary browser smoke post ${activePostId}. ${error.message || error}`);
			});
		}
		loginHelper.cleanupFile();
	}
	await browser.close();
}

pass(`Browser smoke completed for post ${activePostId} at ${baseUrl}.`);
