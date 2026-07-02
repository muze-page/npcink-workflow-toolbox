#!/usr/bin/env node
/**
 * Optional browser smoke for the real Site Check Cloud detail and Scheduled Review UI.
 *
 * This opens a local WordPress admin page, generates the local Site Check,
 * explicitly runs Cloud detail, verifies the rendered Cloud result, switches
 * to Scheduled Review, and captures screenshots. It depends on a running local
 * WordPress site, a verified Cloud Addon connection, a running Cloud runtime,
 * and Playwright. It is intentionally not part of composer test:all.
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
	const fileName = `npcink-toolbox-cloud-detail-smoke-login-${randomBytes(8).toString('hex')}.php`;
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

function forbiddenWriteRequests(requests) {
	return requests.filter((request) => /proposals|governance-core|approve-and-execute|media-derivative-runs/i.test(request.url));
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

async function captureDiagnostics(page, requests, error) {
	const artifactDir = env('SMOKE_ARTIFACT_DIR', 'tests/artifacts');
	mkdirSync(artifactDir, { recursive: true });
	const screenshotPath = `${artifactDir}/site-ops-cloud-detail-browser-failure.png`;
	await page.screenshot({ path: screenshotPath, fullPage: true }).catch(() => {});
	const pageText = await page.locator('body').innerText({ timeout: 2000 }).catch(() => '');
	console.error(`FAIL: Site Check Cloud detail browser smoke diagnostic screenshot: ${screenshotPath}`);
	console.error(`FAIL: Site Check Cloud detail browser smoke current URL: ${page.url()}`);
	console.error(`FAIL: Site Check Cloud detail browser smoke page title: ${await page.title().catch(() => '')}`);
	console.error(`FAIL: Site Check Cloud detail browser smoke wp-admin/wp-json requests: ${requests.length}`);
	console.error(`FAIL: Site Check Cloud detail browser smoke visible text sample: ${pageText.replace(/\s+/g, ' ').trim().slice(0, 1200)}`);
	console.error(`FAIL: Site Check Cloud detail browser smoke error: ${error && error.message ? error.message : String(error || 'unknown error')}`);
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

async function openSiteCheckTab(page, target) {
	await page.evaluate((tabTarget) => {
		const tab = document.querySelector(`[data-toolbox-site-check-target="${tabTarget}"]`);
		if (tab && typeof tab.click === 'function') {
			tab.click();
		}
	}, target);
	await page.waitForFunction(
		(tabTarget) => {
			const tab = document.querySelector(`[data-toolbox-site-check-target="${tabTarget}"]`);
			const panel = document.querySelector(`[data-toolbox-site-check-panel="${tabTarget}"]`);
			return tab && panel && tab.getAttribute('aria-selected') === 'true' && !panel.hidden;
		},
		target,
		{ timeout: 10000 }
	);
}

async function captureViewportScreenshot(page, screenshotPath) {
	mkdirSync(dirname(screenshotPath), { recursive: true });
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
const cloudScreenshotPath = resolve(env('SITE_OPS_CLOUD_DETAIL_BROWSER_SCREENSHOT', 'build/smoke/site-ops-cloud-detail-browser.png'));
const scheduledScreenshotPath = resolve(env('SITE_OPS_SCHEDULED_REVIEW_BROWSER_SCREENSHOT', 'build/smoke/site-ops-scheduled-review-browser.png'));
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
	const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1280, height: 900 } });
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
		await page.waitForSelector('[data-toolbox-site-check-panel="current-check"]', { timeout: 30000 });
		assert(await page.locator('[data-toolbox-site-check-target="current-check"]').count() === 1, 'Operations Insights current-check tab is visible after login.');

		await page.locator('.npcink-toolbox__ops-status-row .npcink-toolbox__ops-status-actions a.button-primary').click();
		await page.waitForLoadState('domcontentloaded');
		await page.waitForSelector('[data-toolbox-site-ops-insights]', { timeout: 30000 });
		assert(await page.locator('[data-toolbox-ops-panel="overview"]').isVisible(), 'Current check report renders after clicking the real scan action.');

		await openOpsTab(page, 'cloud');
		const cloudPanel = page.locator('[data-toolbox-ops-panel="cloud"]');
		assert(await cloudPanel.locator('a.button').count() === 1, 'Cloud tab exposes one explicit Cloud detail action before the run.');
		await Promise.all([
			page.waitForLoadState('domcontentloaded'),
			cloudPanel.locator('a.button').click(),
		]);
		await page.waitForSelector('[data-toolbox-site-ops-insights]', { timeout: 120000 });
		await openOpsTab(page, 'cloud');
		await page.waitForSelector('[data-toolbox-ops-panel="cloud"] .npcink-toolbox__insight-cloud-result', { timeout: 120000 });
		const cloudState = await page.evaluate(() => {
			const panel = document.querySelector('[data-toolbox-ops-panel="cloud"]');
			const text = panel ? panel.innerText || '' : '';
			return {
				text,
				hasCloudResult: !!panel?.querySelector('.npcink-toolbox__insight-cloud-result'),
				hasRunId: /run_[A-Za-z0-9._:-]+/.test(text),
				hasBoundaryCopy: text.includes('Core') && text.includes('WordPress'),
				cloudDetailActionCount: panel ? panel.querySelectorAll('a.button').length : 0,
				errorNoticeCount: panel ? panel.querySelectorAll('.npcink-toolbox__result-notice.is-error').length : 0,
			};
		});
		assert(cloudState.hasCloudResult, 'Cloud detail returns a rendered result card.');
		assert(cloudState.hasRunId, 'Cloud detail result shows the Cloud runtime run id.');
		assert(cloudState.hasBoundaryCopy, 'Cloud detail result keeps Core and WordPress boundary text visible.');
		assert(cloudState.cloudDetailActionCount === 0, 'Cloud detail action disappears after the result renders.');
		assert(cloudState.errorNoticeCount === 0, 'Cloud detail result does not render an error notice.');
		await page.evaluate(() => {
			const panel = document.querySelector('[data-toolbox-ops-panel="cloud"]');
			if (panel) {
				panel.scrollIntoView({ block: 'start' });
			}
		});
		await captureViewportScreenshot(page, cloudScreenshotPath);
		assert(existsSync(cloudScreenshotPath), `Cloud detail screenshot captured at ${cloudScreenshotPath}.`);
		assert(forbiddenWriteRequests(requests).length === 0, 'Browser flow did not request Core proposals, approve-and-execute, or media execution routes.');

		await openSiteCheckTab(page, 'scheduled-review');
		const scheduledState = await page.evaluate(() => {
			const panel = document.querySelector('[data-toolbox-site-check-panel="scheduled-review"]');
			const text = panel ? panel.innerText || '' : '';
			const links = panel ? Array.from(panel.querySelectorAll('a')).map((link) => link.href) : [];
			return {
				text,
				hasPreviewButton: !!panel?.querySelector('a.button-primary'),
				hasCloudRecoveryLink: links.some((href) => href.includes('page=npcink-cloud-addon') && href.includes('tab=runtime_runs')),
				hasLocalCloudBatchControls: !!panel?.querySelector('[data-toolbox-nightly-cloud-batch]'),
				hasRunCloudInspectionText: text.includes('Run Cloud inspection'),
			};
		});
		assert(scheduledState.hasCloudRecoveryLink, 'Scheduled review exposes the Cloud Addon runtime-runs recovery link.');
		assert(!scheduledState.hasLocalCloudBatchControls, 'Scheduled review page does not expose local Nightly Cloud Batch controls.');
		assert(!scheduledState.hasRunCloudInspectionText, 'Scheduled review page does not expose local Run Cloud inspection action.');
		assert(scheduledState.hasPreviewButton, 'Scheduled review exposes one real preview action.');
		await Promise.all([
			page.waitForLoadState('domcontentloaded'),
			page.locator('[data-toolbox-site-check-panel="scheduled-review"] a.button-primary').click(),
		]);
		await page.waitForSelector('[data-toolbox-nightly-inspection-preview]', { timeout: 30000 });
		const previewState = await page.evaluate(() => {
			const panel = document.querySelector('[data-toolbox-site-check-panel="scheduled-review"]');
			const preview = panel?.querySelector('[data-toolbox-nightly-inspection-preview]');
			const text = panel ? panel.innerText || '' : '';
			return {
				hasPreview: !!preview,
				hasBoundaryCopy: text.includes('Cloud') && text.includes('Core') && text.includes('WordPress'),
				hasLocalCloudRunActions: /Run Cloud inspection|Load Cloud recent|Retry run/.test(text),
			};
		});
		assert(previewState.hasPreview, 'Scheduled review preview renders after clicking the real preview action.');
		assert(previewState.hasBoundaryCopy, 'Scheduled review preview states the no Cloud, Core proposal, and WordPress write boundary.');
		assert(!previewState.hasLocalCloudRunActions, 'Scheduled review preview keeps Cloud run actions out of Toolbox.');
		await page.evaluate(() => {
			const panel = document.querySelector('[data-toolbox-site-check-panel="scheduled-review"]');
			if (panel) {
				panel.scrollIntoView({ block: 'start' });
			}
		});
		await captureViewportScreenshot(page, scheduledScreenshotPath);
		assert(existsSync(scheduledScreenshotPath), `Scheduled review screenshot captured at ${scheduledScreenshotPath}.`);
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

pass(`Site Check Cloud detail browser smoke completed at ${baseUrl}.`);
