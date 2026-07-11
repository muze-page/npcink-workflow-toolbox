#!/usr/bin/env node
/**
 * Browser smoke for per-occurrence contextual article image ALT review.
 *
 * This is intentionally outside composer test:all. It needs a running local
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

function assert(condition, message) {
	if (!condition) {
		throw new Error(message);
	}
	pass(message);
}

function env(name, fallback) {
	return process.env[name] || fallback;
}

async function waitForCount(readCount, expected, timeoutMs = 10000) {
	const started = Date.now();
	while (Date.now() - started < timeoutMs) {
		if (readCount() >= expected) {
			return;
		}
		await new Promise((resolve) => setTimeout(resolve, 100));
	}
	throw new Error(`Timed out waiting for ${expected} matching browser event(s).`);
}

async function loadPlaywright() {
	try {
		return await import('playwright');
	} catch (error) {
		const require = createRequire(import.meta.url);
		const paths = String(process.env.NODE_PATH || '').split(':').filter(Boolean);
		const resolved = require.resolve('playwright', { paths });
		const module = await import(pathToFileURL(resolved).href);
		return module.chromium ? module : module.default;
	}
}

function wpPath() {
	return env('WP_PATH', '/Users/muze/Local Sites/magick-ai/app/public');
}

function wpCli(args) {
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
		{ encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }
	).trim();
}

function createLoginHelper(baseUrl, postId) {
	const token = randomBytes(24).toString('hex');
	const fileName = `npcink-toolbox-contextual-alt-login-${randomBytes(8).toString('hex')}.php`;
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
wp_safe_redirect(admin_url('post.php?post=${parseInt(postId, 10)}&action=edit'));
exit;
`);
	return {
		url: `${baseUrl}/${fileName}?token=${token}`,
		cleanup: () => {
			try {
				unlinkSync(filePath);
			} catch (error) {
				// A cleanup race must not hide the primary smoke result.
			}
		},
	};
}

function createFixture() {
	const attachments = JSON.parse(wpCli([
		'post',
		'list',
		'--post_type=attachment',
		'--post_mime_type=image',
		'--post_status=inherit',
		'--posts_per_page=1',
		'--fields=ID,guid',
		'--format=json',
	]));
	assert(Array.isArray(attachments) && attachments.length === 1, 'A local image attachment is available for the contextual ALT fixture.');
	const attachmentId = parseInt(attachments[0].ID, 10);
	const imageUrl = String(attachments[0].guid || '');
	assert(attachmentId > 0 && imageUrl, 'The fixture attachment exposes an id and URL.');
	const attachmentAlt = wpCli(['eval', `echo (string) get_post_meta(${attachmentId}, '_wp_attachment_image_alt', true);`]);
	const existingAlt = '人工填写的蓝色陶瓷杯 ALT';
	const imageBlock = `<!-- wp:image {"id":${attachmentId},"sizeSlug":"large","linkDestination":"none"} --><figure class="wp-block-image size-large"><img src="${imageUrl}" alt="" class="wp-image-${attachmentId}"/></figure><!-- /wp:image -->`;
	const imageBlockWithExistingAlt = `<!-- wp:image {"id":${attachmentId},"sizeSlug":"large","linkDestination":"none"} --><figure class="wp-block-image size-large"><img src="${imageUrl}" alt="${existingAlt}" class="wp-image-${attachmentId}"/></figure><!-- /wp:image -->`;
	const content = [
		'<!-- wp:heading --><h2 class="wp-block-heading">工作日咖啡补给</h2><!-- /wp:heading -->',
		'<!-- wp:paragraph --><p>早晨先倒入手冲咖啡，蓝色马克杯放在白色桌面上，帮助读者理解办公桌上的饮用场景。</p><!-- /wp:paragraph -->',
		imageBlock,
		'<!-- wp:heading --><h2 class="wp-block-heading">蓝色陶瓷杯的设计细节</h2><!-- /wp:heading -->',
		'<!-- wp:paragraph --><p>杯身采用简洁的蓝色釉面和圆润把手，本图用于说明产品外观，而不是咖啡冲泡步骤。</p><!-- /wp:paragraph -->',
		imageBlockWithExistingAlt,
	].join('\n');
	const postId = parseInt(wpCli([
		'post',
		'create',
		'--post_type=post',
		'--post_status=draft',
		'--post_title=上下文 ALT SEO 浏览器验收（临时）',
		`--post_content=${content}`,
		'--porcelain',
	]), 10);
	assert(postId > 0, 'The browser smoke created its temporary contextual ALT article.');
	return { postId, attachmentId, attachmentAlt, existingAlt };
}

const fixture = createFixture();
const postId = fixture.postId;

const { chromium } = await loadPlaywright();
const baseUrl = env('WP_BASE_URL', 'https://magick-ai.local').replace(/\/$/, '');
const artifactDir = env('SMOKE_ARTIFACT_DIR', 'build/smoke');
mkdirSync(artifactDir, { recursive: true });
const screenshotPath = `${artifactDir}/contextual-alt-seo-editor.png`;
const failurePath = `${artifactDir}/contextual-alt-seo-editor-failure.png`;
const browserOptions = { headless: process.env.HEADLESS !== '0' };
const chrome = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
if (existsSync(chrome)) {
	browserOptions.executablePath = chrome;
}

let browser = null;
let page = null;
let loginHelper = null;
const auditEventIds = [];
try {
	browser = await chromium.launch(browserOptions);
	const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 1800 } });
	page = await context.newPage();
	const requests = [];
	const contextualResponses = [];
	const confirmationResponses = [];
	page.on('request', (request) => {
		if (request.url().includes('/wp-json/')) {
			requests.push({ url: request.url(), body: request.postData() || '' });
		}
	});
	page.on('response', async (response) => {
		if (response.url().includes('/wp-json/npcink-toolbox/v1/editor/content-support')) {
			try {
				const payload = await response.json();
				if (payload && payload.intent === 'image_alt_suggestions') {
					contextualResponses.push(payload);
				}
			} catch (error) {
				// The explicit response assertions below report malformed payloads.
			}
		}
		if (!response.url().includes('/wp-json/npcink-toolbox/v1/editor/contextual-alt-audit')) {
			return;
		}
		try {
			const payload = await response.json();
			confirmationResponses.push(payload);
			const eventId = payload && payload.audit ? String(payload.audit.event_id || '') : '';
			if (eventId) {
				auditEventIds.push(eventId);
			}
		} catch (error) {
			// The explicit response assertions below report malformed payloads.
		}
	});

	loginHelper = createLoginHelper(baseUrl, postId);
	await page.goto(loginHelper.url, { waitUntil: 'domcontentloaded', timeout: 45000 });
	await page.waitForFunction(() => window.wp && window.wp.data && window.wp.data.dispatch, null, { timeout: 30000 });
	await page.evaluate(() => {
		const target = 'npcink-toolbox-editor-content-support/npcink-content-support-sidebar';
		for (const store of ['core/edit-post', 'core/interface', 'core/editor']) {
			try {
				const dispatch = window.wp.data.dispatch(store);
				if (dispatch && typeof dispatch.openGeneralSidebar === 'function') {
					dispatch.openGeneralSidebar(target);
					return;
				}
			} catch (error) {
				// Older editor builds do not expose every store.
			}
		}
	});

	await page.waitForSelector('.npcink-toolbox-editor-support__flow', { timeout: 30000 });
	const flow = page.locator('.npcink-toolbox-editor-support__flow').filter({ hasText: /Article image ALT|文章图片 ALT/ });
	assert(await flow.count() === 1, 'Editor exposes one default Article Image ALT (SEO) workflow.');
	await flow.locator('button').click({ timeout: 30000 });

	await page.waitForSelector('.npcink-toolbox-editor-support__contextual-alt-card', { timeout: 30000 });
	const cards = page.locator('.npcink-toolbox-editor-support__contextual-alt-card');
	assert(await cards.count() === 2, 'The same attachment is reviewed as two article occurrences.');
	assert(await page.getByText(/Existing ALT preserved|已有 ALT 已保留/).count() === 1, 'The editor visibly marks the existing human ALT as preserved.');
	const inputs = page.locator('[data-toolbox-contextual-alt-input]');
	assert(await inputs.count() === 2, 'Each image occurrence gets an editable contextual ALT draft.');
	const values = await inputs.evaluateAll((elements) => elements.map((element) => element.value));
	assert(values.every(Boolean), 'Both occurrence drafts contain contextual ALT text.');
	assert(values[0] !== values[1], 'Different article contexts produce different ALT drafts for the same attachment.');
	assert(values[0].includes('工作日咖啡补给'), 'The first ALT draft uses the nearest coffee-section heading.');
	assert(values[1].includes('蓝色陶瓷杯的设计细节'), 'The second ALT draft uses the nearest product-detail heading.');

	const contextualRequests = requests.filter((request) => request.url.includes('/wp-json/npcink-toolbox/v1/editor/content-support') && request.body.includes('image_alt_suggestions'));
	assert(contextualRequests.length === 1, 'Contextual ALT review makes one bounded Toolbox REST request.');
	await waitForCount(() => contextualResponses.length, 1);
	const contextualSection = contextualResponses[0].sections.image_alt_suggestions;
	assert(contextualSection.provider_execution === 'none' && contextualSection.visual_fallback_used_count === 0, 'Useful article context avoids an unnecessary Cloud vision fallback.');
	assert((contextualRequests[0].body.match(new RegExp(String(fixture.attachmentId), 'g')) || []).length >= 2, 'The request preserves both occurrences of the reused attachment.');
	assert(!requests.some((request) => /proposal|approve-and-execute|cloud/i.test(request.url)), 'The preview calls no Core proposal, execution, or Cloud route.');

	await page.waitForSelector('text=/automatically applied to the current editor|自动应用到当前编辑器/', { timeout: 30000 });
	await waitForCount(() => confirmationResponses.length, 2);
	assert(confirmationResponses.every((response) => response && response.decision === 'editor_confirmed' && response.auto_accepted === true), 'Requested and completed receipts are automatically accepted as editor-confirmed records.');
	assert(confirmationResponses.every((response) => response && response.apply_mode === 'automatic_missing_alt'), 'Core receipts distinguish automatic missing-ALT application from optional manual edits.');
	assert(confirmationResponses.every((response) => response && response.proposal_created === false && response.direct_wordpress_write === false), 'Editor confirmation receipts create no proposal and perform no server-side WordPress write.');
	assert(auditEventIds.length === 2, 'Governance Core returns requested and completed audit event ids.');
	const phpAuditIds = auditEventIds.map((eventId) => `'${eventId.replace(/[^a-z0-9-]/gi, '')}'`).join(',');
	const auditRows = JSON.parse(wpCli(['eval', `global $wpdb; $rows = array(); foreach (array(${phpAuditIds}) as $event_id) { $row = $wpdb->get_row($wpdb->prepare("SELECT event_name, metadata_json FROM {$wpdb->prefix}npcink_governance_core_audit_log WHERE event_id = %s", $event_id), ARRAY_A); if ($row) { $row['metadata'] = json_decode((string) $row['metadata_json'], true); unset($row['metadata_json']); $rows[] = $row; } } echo wp_json_encode($rows);`]));
	assert(auditRows.length === 2 && auditRows.some((row) => row.event_name === 'local_admin_consent.requested') && auditRows.some((row) => row.event_name === 'local_admin_consent.completed'), 'Core persists the linked requested and completed local-consent events.');
	assert(auditRows.every((row) => row.metadata.apply_mode === 'automatic_missing_alt'), 'Core audit stores the automatic missing-ALT apply mode.');
	assert(auditRows.every((row) => JSON.stringify(row.metadata.reviewed_items.map((item) => item.final_alt)) === JSON.stringify([values[0]])), 'Core audit preserves the exact Unicode ALT value automatically applied to the missing occurrence.');
	assert(auditRows.every((row) => row.metadata.reviewed_items.every((item) => item.generation_basis === 'article_context')), 'Core audit records article context as the basis when silent vision fallback is unnecessary.');
	assert(auditRows.every((row) => row.metadata.reviewed_items.every((item) => /^[a-f0-9]{64}$/.test(item.context_fingerprint))), 'Every Core audit item keeps its required article-context fingerprint.');
	const appliedAltValues = await page.evaluate(() => window.wp.data.select('core/block-editor').getBlocks()
		.filter((block) => block.name === 'core/image')
		.map((block) => String(block.attributes.alt || '')));
	assert(appliedAltValues.length === 2 && appliedAltValues[0] === values[0] && appliedAltValues[1] === fixture.existingAlt, 'Automatic apply fills the missing occurrence and preserves the existing human ALT on the reused image.');
	const editorDirty = await page.evaluate(() => Boolean(window.wp.data.select('core/editor').isEditedPostDirty()));
	assert(editorDirty, 'Automatic missing-ALT application marks the Gutenberg post dirty for the native Save or Update action.');
	const confirmationRequests = requests.filter((request) => request.url.includes('/wp-json/npcink-toolbox/v1/editor/contextual-alt-audit'));
	assert(confirmationRequests.length === 2 && confirmationRequests.every((request) => request.body.includes('"apply_mode":"automatic_missing_alt"')) && confirmationRequests.some((request) => request.body.includes('"stage":"requested"')) && confirmationRequests.some((request) => request.body.includes('"stage":"completed"')), 'Browser sends linked requested and completed automatic-apply audit calls.');
	const editedApplyButton = page.locator('button').filter({ hasText: /Apply edited ALT changes|应用编辑后的 ALT/ });
	assert(await editedApplyButton.count() === 1, 'Manual apply remains optional for later ALT edits instead of blocking the automatic path.');
	assert(!requests.some((request) => /\/wp-json\/wp\/v2\/(posts|media)\//.test(request.url) && request.body), 'The apply action does not save the post or mutate media through wp/v2.');
	const attachmentAltAfter = wpCli(['eval', `echo (string) get_post_meta(${fixture.attachmentId}, '_wp_attachment_image_alt', true);`]);
	assert(attachmentAltAfter === fixture.attachmentAlt, 'The contextual article ALT apply leaves attachment-global media ALT unchanged.');

	await page.screenshot({ path: screenshotPath, fullPage: true });
	pass(`Contextual ALT screenshot: ${screenshotPath}`);
} catch (error) {
	if (page) {
		await page.screenshot({ path: failurePath, fullPage: true }).catch(() => {});
	}
	console.error(`FAIL: ${error.message || error}`);
	console.error(`FAIL: Diagnostic screenshot: ${failurePath}`);
	process.exitCode = 1;
} finally {
	if (loginHelper) {
		loginHelper.cleanup();
	}
	if (auditEventIds.length) {
		try {
			const safeIds = auditEventIds.filter((eventId) => /^[a-z0-9-]{8,64}$/i.test(eventId));
			const phpIds = safeIds.map((eventId) => `'${eventId}'`).join(',');
			wpCli(['eval', `global $wpdb; foreach (array(${phpIds}) as $event_id) { $wpdb->delete($wpdb->prefix . 'npcink_governance_core_audit_log', array('event_id' => $event_id), array('%s')); }`]);
			pass(`Cleaned ${safeIds.length} contextual ALT Core audit event(s).`);
		} catch (error) {
			console.error(`WARN: Could not clean contextual ALT Core audit events. ${error.message || error}`);
		}
	}
	try {
		wpCli(['post', 'delete', String(postId), '--force']);
		pass(`Temporary contextual ALT article ${postId} was deleted.`);
	} catch (error) {
		console.error(`WARN: Could not delete temporary contextual ALT article ${postId}. ${error.message || error}`);
	}
	if (browser) {
		await browser.close();
	}
}
