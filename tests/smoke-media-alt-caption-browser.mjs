#!/usr/bin/env node
/**
 * Optional browser smoke for the Media ALT/Caption admin review UI.
 *
 * This opens a real local WordPress admin page, builds a bounded review set
 * from explicit real attachment ids, and checks operator-facing handoff state.
 * It does not click the Core handoff button, create proposals, or write media.
 */

import { execFileSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { existsSync, mkdirSync, readdirSync, unlinkSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { resolve } from 'node:path';
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

function intEnv(name, fallback) {
	const parsed = parseInt(env(name, String(fallback)), 10);
	return Number.isInteger(parsed) && parsed >= 0 ? parsed : fallback;
}

function wpPath() {
	return env('WP_PATH', '/Users/muze/Local Sites/magick-ai/app/public');
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

function parseAttachmentIds() {
	const raw = env('MEDIA_ALT_CAPTION_BROWSER_ATTACHMENT_IDS', '7774,769,767,766,765');
	const ids = raw
		.split(',')
		.map((value) => parseInt(value.trim(), 10))
		.filter((value) => Number.isInteger(value) && value > 0);
	if (!ids.length) {
		fail('MEDIA_ALT_CAPTION_BROWSER_ATTACHMENT_IDS did not contain any positive attachment ids.');
	}
	return ids;
}

function verifyAttachmentIds(ids) {
	const code = `
$ids = array_filter(array_map('absint', explode(',', '${ids.join(',')}')));
$valid = array();
foreach ($ids as $id) {
	$post = get_post($id);
	$mime = get_post_mime_type($id);
	if ($post && 'attachment' === $post->post_type && is_string($mime) && str_starts_with($mime, 'image/')) {
		$valid[] = $id;
	}
}
echo wp_json_encode(array_values($valid));
`;
	const output = wpCli(['eval', code], { stdio: ['ignore', 'pipe', 'pipe'] });
	let valid;
	try {
		valid = JSON.parse(output.replace(/Deprecated:[\s\S]*?\n/g, '').trim());
	} catch (error) {
		fail(`Could not parse attachment validation output. ${error.message}`);
	}
	assert(Array.isArray(valid) && valid.length === ids.length, 'Smoke attachment ids exist and are image attachments.');
}

function createLoginHelper(baseUrl) {
	const token = randomBytes(24).toString('hex');
	const fileName = `npcink-toolbox-media-alt-smoke-login-${randomBytes(8).toString('hex')}.php`;
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
wp_safe_redirect(admin_url('admin.php?page=npcink-toolbox&tab=image&tool=media-alt-caption-review'));
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

function createRuntimeFilter() {
	const directory = `${wpPath().replace(/\/$/, '')}/wp-content/mu-plugins`;
	mkdirSync(directory, { recursive: true });
	const filePath = `${directory}/npcink-toolbox-media-alt-smoke-runtime-${randomBytes(8).toString('hex')}.php`;
	writeFileSync(filePath, `<?php
/**
 * Temporary Media ALT/Caption browser-smoke runtime filter.
 *
 * This file is created and removed by tests/smoke-media-alt-caption-browser.mjs.
 */
add_filter(
	'npcink_toolbox_hosted_ai_site_helper_cloud_request',
	static function ( $handled, array $runtime_payload, array $input ) {
		if ( 'media_alt_suggestions' !== (string) ( $input['intent'] ?? '' ) ) {
			return $handled;
		}

		return array(
			'status' => 'ready',
			'run_id' => 'media_alt_caption_browser_smoke',
			'result' => array(
				'status' => 'ready',
				'model_id' => 'local_browser_smoke_no_cloud_runtime',
				'output_text' => 'Browser smoke: build the metadata-only review set locally and confirm operator handoff controls.',
			),
		);
	},
	10,
	3
);
`);
	return {
		cleanup: () => {
			try {
				unlinkSync(filePath);
			} catch (error) {
				// The smoke should not fail only because cleanup raced a local server.
			}
		},
	};
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

function forbiddenWriteRequests(requests) {
	return requests.filter((request) => {
		if (!/POST|PUT|PATCH|DELETE/.test(request.method)) {
			return false;
		}
		return /proposals|governance-core|approve-and-execute|media-derivative-runs|local-admin-consent|\/wp\/v2\/media/i.test(request.url);
	});
}

async function openAltCaptionPanel(page, baseUrl) {
	const paths = [
		'/wp-admin/admin.php?page=npcink-toolbox&tab=image&tool=media-alt-caption-review',
		'/wp-admin/tools.php?page=npcink-toolbox&tab=image&tool=media-alt-caption-review',
	];
	for (const path of paths) {
		await page.goto(`${baseUrl}${path}`, { waitUntil: 'domcontentloaded', timeout: 45000 });
		const count = await page.locator('[data-toolbox-media-alt-caption-review]').count();
		if (count > 0) {
			return `${baseUrl}${path}`;
		}
	}
	const body = await page.locator('body').innerText({ timeout: 5000 }).catch(() => '');
	fail(`Could not find the Media ALT/Caption admin panel. Last URL: ${page.url()}. Body: ${body.slice(0, 800)}`);
}

async function captureDiagnostics(page, requests, error) {
	const artifactDir = env('SMOKE_ARTIFACT_DIR', 'tests/artifacts');
	mkdirSync(artifactDir, { recursive: true });
	const screenshotPath = `${artifactDir}/media-alt-caption-browser-failure.png`;
	await page.screenshot({ path: screenshotPath, fullPage: true }).catch(() => {});
	const pageText = await page.locator('body').innerText({ timeout: 2000 }).catch(() => '');
	console.error(`FAIL: Media ALT/Caption browser smoke diagnostic screenshot: ${screenshotPath}`);
	console.error(`FAIL: Media ALT/Caption browser smoke current URL: ${page.url()}`);
	console.error(`FAIL: Media ALT/Caption browser smoke page title: ${await page.title().catch(() => '')}`);
	console.error(`FAIL: Media ALT/Caption browser smoke wp-json requests: ${requests.length}`);
	console.error(`FAIL: Media ALT/Caption browser smoke visible text sample: ${pageText.replace(/\s+/g, ' ').trim().slice(0, 1200)}`);
	console.error(`FAIL: Media ALT/Caption browser smoke error: ${error && error.message ? error.message : String(error || 'unknown error')}`);
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
const attachmentIds = parseAttachmentIds();
const screenshotPath = resolve(env('MEDIA_ALT_CAPTION_BROWSER_SCREENSHOT', 'build/smoke/media-alt-caption-browser.png'));
const expectedReviewRowsMin = intEnv('MEDIA_ALT_CAPTION_BROWSER_EXPECT_REVIEW_ROWS_MIN', attachmentIds.length);
const expectedCaptionOnlyMin = intEnv('MEDIA_ALT_CAPTION_BROWSER_EXPECT_CAPTION_ONLY_MIN', 1);
const expectedContextRowsMin = intEnv('MEDIA_ALT_CAPTION_BROWSER_EXPECT_CONTEXT_ROWS_MIN', 4);
const expectedReadyRowsMin = intEnv('MEDIA_ALT_CAPTION_BROWSER_EXPECT_READY_ROWS_MIN', 0);
verifyAttachmentIds(attachmentIds);

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
let runtimeFilter = null;
let page = null;
const requests = [];
try {
	runtimeFilter = createRuntimeFilter();
	const context = await browser.newContext({ ignoreHTTPSErrors: true });
	page = await context.newPage();
	page.on('request', (request) => {
		const url = request.url();
		if (url.includes('/wp-json/')) {
			requests.push({ method: request.method(), url, body: request.postData() || '' });
		}
	});

	try {
		loginHelper = createLoginHelper(baseUrl);
		await page.goto(loginHelper.url, { waitUntil: 'domcontentloaded', timeout: 45000 });
		await openAltCaptionPanel(page, baseUrl);
		await page.waitForSelector('[data-toolbox-media-alt-caption-review]:not([hidden])', { timeout: 30000 });
		const form = page.locator('[data-toolbox-media-alt-caption-review]');
		const submit = form.locator('button[type="submit"]');
		assert(await submit.isEnabled(), 'Media ALT/Caption review-set button is enabled for the local admin smoke.');
		await form.locator('[data-toolbox-selected-attachment-ids]').fill(attachmentIds.join(', '));
		await form.locator('select[name="sample_size"]').selectOption('10');
		await form.locator('select[name="review_set_limit"]').selectOption('5');
		await form.locator('select[name="media_filter"]').selectOption('all_recent');
		await submit.click();
		await page.waitForSelector('[data-toolbox-media-alt-caption-item]', { timeout: 45000 });
		await page.waitForFunction(
			(expectations) => {
				const rows = Array.from(document.querySelectorAll('.npcink-toolbox__alt-review-row'));
				const statuses = rows.map((row) => row.__npcinkMediaAltCaptionItem && row.__npcinkMediaAltCaptionItem.candidate_review_status).filter(Boolean);
				return rows.length >= expectations.reviewRowsMin
					&& statuses.filter((status) => status === 'caption_review_only').length >= expectations.captionOnlyMin
					&& statuses.filter((status) => status === 'needs_context_confirmation').length >= expectations.contextRowsMin
					&& statuses.filter((status) => status === 'ready_for_review').length >= expectations.readyRowsMin;
			},
			{
				reviewRowsMin: expectedReviewRowsMin,
				captionOnlyMin: expectedCaptionOnlyMin,
				contextRowsMin: expectedContextRowsMin,
				readyRowsMin: expectedReadyRowsMin,
			},
			{ timeout: 15000 }
		);

		const initial = await page.evaluate(() => {
			const rows = Array.from(document.querySelectorAll('.npcink-toolbox__alt-review-row'));
			const handoffButton = document.querySelector('[data-toolbox-media-alt-caption-handoff]');
			const selectedCount = document.querySelector('[data-toolbox-media-alt-caption-selected-count]');
			const text = document.querySelector('[data-toolbox-media-alt-caption-review] .npcink-toolbox__result')?.innerText || '';
			return {
				text,
				rowCount: rows.length,
				captionOnlyRows: rows.filter((row) => row.__npcinkMediaAltCaptionItem && row.__npcinkMediaAltCaptionItem.candidate_review_status === 'caption_review_only').length,
				contextRows: rows.filter((row) => row.__npcinkMediaAltCaptionItem && row.__npcinkMediaAltCaptionItem.candidate_review_status === 'needs_context_confirmation').length,
				readyRows: rows.filter((row) => row.__npcinkMediaAltCaptionItem && row.__npcinkMediaAltCaptionItem.candidate_review_status === 'ready_for_review').length,
				contextConfirmInputs: document.querySelectorAll('[data-toolbox-media-alt-caption-context-confirmed]').length,
				emptyAltRows: rows.filter((row) => {
					const input = row.querySelector('[data-toolbox-media-alt-caption-accepted-alt]');
					return input && !String(input.value || '').trim();
				}).length,
				initialSelectedCount: selectedCount ? selectedCount.textContent : '',
				handoffDisabled: handoffButton instanceof HTMLButtonElement ? handoffButton.disabled : null,
				readyLabelVisible: text.includes('Ready to update'),
				reviewRowsLabelVisible: /Review rows|审核行|审阅行/.test(text),
					captionNotAppliedVisible: /Caption suggestions are not included in this ALT handoff preview|说明文字建议不会包含在这个 ALT 交接预览中|Caption.*ALT/.test(text),
				contextWarningVisible: /Location or proper-name context must be confirmed or removed before handoff|地点或专名上下文.*确认|确认地点或专名上下文/.test(text),
				noWriteNoticeVisible: /Toolbox will not change media ALT here|Toolbox 不会在这里更改媒体 ALT|这里不会更改媒体 ALT/.test(text),
			};
		});

		assert(initial.rowCount >= expectedReviewRowsMin, 'Review UI renders the expected real media rows.');
		if (expectedCaptionOnlyMin > 0) {
			assert(initial.captionOnlyRows >= expectedCaptionOnlyMin, 'Review UI labels caption-only rows distinctly.');
			assert(initial.emptyAltRows >= expectedCaptionOnlyMin, 'Caption-only rows keep the ALT input empty.');
				assert(initial.captionNotAppliedVisible, 'Caption-only rows tell operators captions are not included in the ALT handoff preview.');
		} else {
			pass('Caption-only row assertions are not required for this sample.');
		}
		assert(initial.contextRows >= expectedContextRowsMin, 'Review UI labels location/proper-name rows as context-confirmation rows.');
		assert(initial.readyRows >= expectedReadyRowsMin, 'Review UI renders the expected ready-for-review rows.');
		if (expectedContextRowsMin > 0) {
			assert(initial.contextConfirmInputs >= expectedContextRowsMin, 'Review UI exposes explicit context confirmation controls.');
		}
		assert(initial.initialSelectedCount === String(initial.readyRows), 'Initial ALT handoff count matches ready rows only.');
		assert(initial.handoffDisabled === (initial.readyRows === 0), 'ALT handoff button state follows ready rows and required context confirmation.');
		assert(initial.reviewRowsLabelVisible && !initial.readyLabelVisible, 'Summary counts review rows without implying they are ready to update.');
		if (expectedContextRowsMin > 0) {
			assert(initial.contextWarningVisible, 'Context rows tell operators to confirm or remove location/proper-name context.');
		}
		assert(initial.noWriteNoticeVisible, 'Review shell keeps the no-write boundary visible.');

		await page.evaluate(() => {
			document.querySelectorAll('[data-toolbox-media-alt-caption-item]').forEach((input) => {
				if (input instanceof HTMLInputElement) {
					input.checked = true;
					input.dispatchEvent(new Event('change', { bubbles: true }));
				}
			});
		});
		const afterSelectingRows = await page.evaluate(() => ({
			selectedCount: document.querySelector('[data-toolbox-media-alt-caption-selected-count]')?.textContent || '',
			handoffDisabled: document.querySelector('[data-toolbox-media-alt-caption-handoff]')?.disabled ?? null,
		}));
		assert(afterSelectingRows.selectedCount === String(initial.readyRows) && afterSelectingRows.handoffDisabled === (initial.readyRows === 0), 'Selecting rows alone still excludes unconfirmed-context and caption-only rows from ALT handoff.');

		if (initial.contextRows > 0) {
			await page.locator('[data-toolbox-media-alt-caption-context-confirmed]').first().check();
			const afterConfirmingOne = await page.evaluate(() => ({
				selectedCount: document.querySelector('[data-toolbox-media-alt-caption-selected-count]')?.textContent || '',
				handoffDisabled: document.querySelector('[data-toolbox-media-alt-caption-handoff]')?.disabled ?? null,
			}));
			assert(afterConfirmingOne.selectedCount === String(initial.readyRows + 1) && afterConfirmingOne.handoffDisabled === false, 'Confirming one context row enables one additional ALT handoff candidate.');
		}
		assert(forbiddenWriteRequests(requests).length === 0, 'Building and reviewing the UI does not call proposal, Core, media write, or local consent routes.');
		assert(requests.some((request) => request.method === 'POST' && request.url.includes('/wp-json/npcink-toolbox/v1/ai/site-helpers')), 'Browser smoke builds the review set through the Toolbox site-helper route.');

		mkdirSync(resolve(screenshotPath, '..'), { recursive: true });
		await captureViewportScreenshot(page, screenshotPath);
		pass(`Media ALT/Caption browser smoke screenshot: ${screenshotPath}`);
	} catch (error) {
		await captureDiagnostics(page, requests, error);
		throw error;
	}
} finally {
	if (loginHelper) {
		loginHelper.cleanup();
	}
	if (runtimeFilter) {
		runtimeFilter.cleanup();
	}
	await browser.close();
}

pass(`Media ALT/Caption browser smoke completed at ${baseUrl}.`);
