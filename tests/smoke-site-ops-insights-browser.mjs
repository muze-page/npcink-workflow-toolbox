#!/usr/bin/env node
/**
 * Optional browser smoke for the Full-site Insights admin UI.
 *
 * This opens a real local WordPress admin page, generates the local report,
 * checks the summary-first panels, switches sub-tabs, and captures a screenshot.
 * It does not run Cloud analysis, create Core proposals, or write WordPress data.
 */

import { randomBytes } from 'node:crypto';
import { existsSync, mkdirSync, readdirSync, unlinkSync, writeFileSync } from 'node:fs';
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

function wpPath() {
	return env('WP_PATH', '/Users/muze/Local Sites/magick-ai/app/public');
}

function createLoginHelper(baseUrl) {
	const token = randomBytes(24).toString('hex');
	const fileName = `npcink-toolbox-site-ops-smoke-login-${randomBytes(8).toString('hex')}.php`;
	const filePath = `${wpPath().replace(/\/$/, '')}/${fileName}`;
	writeFileSync(filePath, `<?php
declare(strict_types=1);
$expected = '${token}';
if (!isset($_GET['token']) || !hash_equals($expected, (string) $_GET['token'])) {
	http_response_code(403);
	exit('forbidden');
}
require __DIR__ . '/wp-load.php';
$users = get_users(array('role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'order' => 'ASC'));
$user = $users ? $users[0] : null;
if (!$user) {
	http_response_code(500);
	exit('no_admin_user');
}
wp_set_current_user($user->ID);
wp_set_auth_cookie($user->ID, false, is_ssl());
wp_safe_redirect(admin_url('admin.php?page=npcink-toolbox&toolbox_tab=operations-insights'));
exit;
`);
	return {
		url: `${baseUrl}/${fileName}?token=${token}`,
		cleanup: () => {
			try {
				unlinkSync(filePath);
			} catch (error) {
				// The smoke should not fail only because cleanup raced a local server.
			}
		},
	};
}

function forbiddenRequests(requests) {
	return requests.filter((request) => /site_ops_cloud_analysis=1|proposals|governance-core|approve-and-execute|media-derivative-runs/i.test(request.url));
}

function findPlaywrightChromiumExecutable(chromium) {
	const candidates = [];
	try {
		candidates.push(chromium.executablePath());
	} catch (error) {
		// Some bundled runtimes can resolve Playwright without an installed browser.
	}
	const cacheRoot = `${process.env.HOME}/Library/Caches/ms-playwright`;
	if (existsSync(cacheRoot)) {
		const revisions = readdirSync(cacheRoot)
			.filter((entry) => /^chromium-\d+$/.test(entry))
			.sort((left, right) => parseInt(right.replace('chromium-', ''), 10) - parseInt(left.replace('chromium-', ''), 10));
		for (const revision of revisions) {
			candidates.push(`${cacheRoot}/${revision}/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing`);
		}
	}
	return candidates.find((candidate) => candidate && existsSync(candidate)) || '';
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

async function captureDiagnostics(page, requests, error) {
	const artifactDir = env('SMOKE_ARTIFACT_DIR', 'tests/artifacts');
	mkdirSync(artifactDir, { recursive: true });
	const screenshotPath = `${artifactDir}/site-ops-insights-browser-failure.png`;
	await page.screenshot({ path: screenshotPath, fullPage: true }).catch(() => {});
	const pageText = await page.locator('body').innerText({ timeout: 2000 }).catch(() => '');
	console.error(`FAIL: Full-site Insights browser smoke diagnostic screenshot: ${screenshotPath}`);
	console.error(`FAIL: Full-site Insights browser smoke current URL: ${page.url()}`);
	console.error(`FAIL: Full-site Insights browser smoke page title: ${await page.title().catch(() => '')}`);
	console.error(`FAIL: Full-site Insights browser smoke wp-admin/wp-json requests: ${requests.length}`);
	console.error(`FAIL: Full-site Insights browser smoke visible text sample: ${pageText.replace(/\s+/g, ' ').trim().slice(0, 1200)}`);
	console.error(`FAIL: Full-site Insights browser smoke error: ${error && error.message ? error.message : String(error || 'unknown error')}`);
}

async function openOpsTab(page, target) {
	await page.evaluate((tabTarget) => {
		const tab = document.querySelector(`[data-toolbox-ops-target="${tabTarget}"]`);
		if (tab && typeof tab.click === 'function') {
			tab.click();
		}
	}, target);
	await page.waitForFunction(
		(tabTarget) => {
			const tab = document.querySelector(`[data-toolbox-ops-target="${tabTarget}"]`);
			const panel = document.querySelector(`[data-toolbox-ops-panel="${tabTarget}"]`);
			return tab && panel && tab.getAttribute('aria-selected') === 'true' && !panel.hidden;
		},
		target,
		{ timeout: 10000 }
	);
}

async function captureViewportScreenshot(page, screenshotPath) {
	try {
		await page.screenshot({ path: screenshotPath, fullPage: false, animations: 'disabled', timeout: 15000 });
		return;
	} catch (error) {
		// Some system Chrome builds hang on Playwright screenshots; fall back to CDP.
	}
	const client = await page.context().newCDPSession(page);
	const result = await client.send('Page.captureScreenshot', {
		format: 'png',
		fromSurface: true,
		captureBeyondViewport: false,
	});
	writeFileSync(screenshotPath, Buffer.from(result.data, 'base64'));
	await client.detach().catch(() => {});
}

const { chromium } = await loadPlaywright();
const baseUrl = env('WP_BASE_URL', 'https://magick-ai.local').replace(/\/$/, '');
const screenshotPath = resolve(env('SITE_OPS_BROWSER_SCREENSHOT', 'build/smoke/site-ops-insights.png'));
const browserOptions = {
	headless: process.env.HEADLESS !== '0',
};
if (process.env.BROWSER_EXECUTABLE) {
	browserOptions.executablePath = process.env.BROWSER_EXECUTABLE;
} else {
	const playwrightChrome = findPlaywrightChromiumExecutable(chromium);
	if (playwrightChrome) {
		browserOptions.executablePath = playwrightChrome;
	} else {
		const normalChrome = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
		if (existsSync(normalChrome)) {
			browserOptions.executablePath = normalChrome;
		}
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
		if (url.includes('/wp-admin/') || url.includes('/wp-json/')) {
			requests.push({ method: request.method(), url });
		}
	});

	try {
		loginHelper = createLoginHelper(baseUrl);
		await page.goto(loginHelper.url, { waitUntil: 'domcontentloaded', timeout: 45000 });
		await openToolboxPage(page, baseUrl);
		await page.waitForSelector('[data-toolbox-site-ops-insights]', { state: 'attached', timeout: 30000 });
		if (await page.locator('[data-toolbox-tab-panel="operations-insights"]').isHidden()) {
			await page.locator('[data-toolbox-tab-target="operations-insights"]').click();
		}
		await page.waitForSelector('[data-toolbox-tab-panel="operations-insights"]:not([hidden])', { timeout: 30000 });
		await page.locator('[data-toolbox-site-ops-insights] .npcink-toolbox__section-actions a.button-primary').click();
		await page.waitForLoadState('domcontentloaded');
		await page.waitForSelector('[data-toolbox-ops-tabs]', { timeout: 30000 });

		const overviewText = await page.locator('[data-toolbox-ops-panel="overview"]').innerText();
		assert(/Start with the priority decision queue|从优先决策队列开始/.test(overviewText), 'Overview tells the operator where to start.');
		assert(/Priority decision queue|优先决策队列/.test(overviewText), 'Overview shows the priority decision queue.');
		assert(/Issue|问题/.test(overviewText) && /Evidence|依据|证据/.test(overviewText), 'Decision queue explains the problem and evidence.');
		assert(/Path|路径/.test(overviewText) && /Next step|下一步/.test(overviewText), 'Decision queue explains the handling path and next step.');
		assert(/Follow-up path|后续处理路径/.test(overviewText) && /Handling mode|处理方式/.test(overviewText), 'Decision queue exposes the follow-up path without executing it.');
		assert(/proposal_ready=false|不会从此报告创建提案|不会创建任务|不会创建自动动作/.test(overviewText), 'Follow-up path keeps proposals, queues, and writes outside the report.');
		assert(/Local analysis summary|本地分析摘要|Coverage snapshot|覆盖快照/.test(overviewText), 'Overview keeps local coverage detail after the decision queue.');
		assert(/Start with|No priority site analysis findings|从|没有/.test(overviewText), 'Overview gives a clear first-read outcome.');
		assert(/Core planning|manual review|high priority|Core|人工|高优先级/.test(overviewText), 'Overview shows priority or review-path context.');
		assert(await page.locator('[data-toolbox-ops-panel="advanced"]').isHidden(), 'Advanced JSON panel is hidden by default.');

		for (const target of ['content', 'media', 'comments', 'structure', 'findings', 'evidence', 'cloud', 'advanced']) {
			await openOpsTab(page, target);
			const selected = await page.locator(`[data-toolbox-ops-target="${target}"]`).getAttribute('aria-selected');
			assert(selected === 'true', `${target} sub-tab can be opened.`);
		}

		await openOpsTab(page, 'cloud');
		const cloudText = await page.locator('[data-toolbox-ops-panel="cloud"]').innerText();
		assert(/Cloud analysis has not run|Cloud analysis result|Cloud 分析|云端分析/.test(cloudText), 'Cloud tab renders review-only status without automatic execution.');
		assert(forbiddenRequests(requests).length === 0, 'Full-site Insights browser smoke does not call Cloud analysis, Core proposals, or execute routes.');

		await openOpsTab(page, 'overview');
		await page.evaluate(() => {
			const panel = document.querySelector('[data-toolbox-site-ops-insights]');
			if (panel) {
				panel.scrollIntoView({ block: 'start' });
			}
		});
		mkdirSync(dirname(screenshotPath), { recursive: true });
		await captureViewportScreenshot(page, screenshotPath);
		assert(existsSync(screenshotPath), `Screenshot captured at ${screenshotPath}.`);
	} catch (error) {
		await captureDiagnostics(page, requests, error);
		throw error;
	}
} finally {
	if (loginHelper) {
		loginHelper.cleanup();
	}
	await browser.close();
}

pass(`Full-site Insights browser smoke completed at ${baseUrl}.`);
