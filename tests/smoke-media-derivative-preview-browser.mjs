#!/usr/bin/env node
/** Browser proof for the Toolbox-owned media derivative preview projection. */

import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';

function env(name, fallback) {
	return process.env[name] || fallback;
}

function assert(condition, message) {
	if (!condition) {
		throw new Error(message);
	}
	console.log(`PASS: ${message}`);
}

function wpCli(args) {
	return execFileSync(
		env('WP_CLI_PHP', `${process.env.HOME}/Library/Application Support/Local/lightning-services/php-8.2.29+0/bin/darwin-arm64/bin/php`),
		[
			'-d', 'display_errors=0',
			'-d', 'error_reporting=8191',
			'-d', `mysqli.default_socket=${env('WP_DB_SOCKET', `${process.env.HOME}/Library/Application Support/Local/run/PvPC4seEm/mysql/mysqld.sock`)}`,
			env('WP_CLI_BIN', '/opt/homebrew/bin/wp'),
			`--path=${env('WP_PATH', '/Users/muze/Local Sites/npcink/app/public')}`,
			'--no-color',
			...args,
		],
		{ encoding: 'utf8' }
	).trim();
}

async function loadPlaywright() {
	try {
		return await import('playwright');
	} catch (error) {
		const require = createRequire(import.meta.url);
		const resolved = require.resolve('playwright', { paths: String(process.env.NODE_PATH || '').split(':').filter(Boolean) });
		const module = await import(pathToFileURL(resolved).href);
		return module.chromium ? module : module.default;
	}
}

function authCookies(baseUrl) {
	const cookieJson = wpCli(['eval', `
$users = get_users(array('role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'order' => 'ASC'));
$user = $users ? $users[0] : null; if (!$user) { exit(1); }
$expiration = time() + DAY_IN_SECONDS;
echo wp_json_encode(array(
	array('name' => AUTH_COOKIE, 'value' => wp_generate_auth_cookie($user->ID, $expiration, 'auth')),
	array('name' => SECURE_AUTH_COOKIE, 'value' => wp_generate_auth_cookie($user->ID, $expiration, 'secure_auth')),
	array('name' => LOGGED_IN_COOKIE, 'value' => wp_generate_auth_cookie($user->ID, $expiration, 'logged_in'))
));`]);
	const { hostname, protocol } = new URL(baseUrl);
	return JSON.parse(cookieJson).map((cookie) => ({
		name: String(cookie.name || ''), value: String(cookie.value || ''), domain: hostname, path: '/',
		httpOnly: true, secure: protocol === 'https:', sameSite: 'Lax',
	}));
}

const baseUrl = env('WP_BASE_URL', 'http://npcink.local').replace(/\/$/, '');
let browser = null;
let attachmentId = 0;
let readRequestId = '';
try {
	const { chromium } = await loadPlaywright();
	const launch = { headless: process.env.HEADLESS !== '0' };
	if (process.env.BROWSER_EXECUTABLE) {
		launch.executablePath = process.env.BROWSER_EXECUTABLE;
	} else if (existsSync('/Applications/Google Chrome.app/Contents/MacOS/Google Chrome')) {
		launch.executablePath = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
	}
	browser = await chromium.launch(launch);
	attachmentId = Number(wpCli([
		'eval',
		`$id=0; $path=''; try { $upload=wp_upload_dir(); if (!empty($upload['error'])) { throw new RuntimeException((string)$upload['error']); } $path=trailingslashit($upload['path']).'toolbox-browser-preview-'.wp_generate_password(8,false,false).'.png'; if (!function_exists('imagecreatetruecolor')) { throw new RuntimeException('GD is unavailable.'); } $im=imagecreatetruecolor(640,360); if (!$im) { throw new RuntimeException('Image allocation failed.'); } $bg=imagecolorallocate($im,32,94,150); imagefilledrectangle($im,0,0,640,360,$bg); if (!imagepng($im,$path)) { throw new RuntimeException('Fixture image write failed.'); } imagedestroy($im); $inserted=wp_insert_attachment(array('post_mime_type'=>'image/png','post_title'=>'Toolbox browser preview','post_status'=>'inherit'),$path,0,true); if (is_wp_error($inserted)) { throw new RuntimeException($inserted->get_error_message()); } $id=(int)$inserted; require_once ABSPATH.'wp-admin/includes/image.php'; $metadata=wp_generate_attachment_metadata($id,$path); $updated=is_array($metadata) ? wp_update_attachment_metadata($id,$metadata) : false; if (!is_array($metadata) || (!$updated && wp_get_attachment_metadata($id)!==$metadata)) { throw new RuntimeException('Attachment metadata generation failed.'); } echo $id; } catch (Throwable $error) { if ($id>0) { wp_delete_attachment($id,true); } elseif ($path && file_exists($path)) { wp_delete_file($path); } fwrite(STDERR,$error->getMessage()); exit(1); }`,
	]));
	assert(attachmentId > 0, 'Temporary browser-smoke attachment was created after Playwright launched.');

	const context = await browser.newContext({ ignoreHTTPSErrors: true });
	await context.addCookies(authCookies(baseUrl));
	const page = await context.newPage();
	const requests = [];
	page.on('request', (request) => requests.push({
		url: request.url(),
		method: request.method(),
		postData: request.postData() || '',
	}));
	await page.goto(`${baseUrl}/wp-admin/admin.php?page=npcink-toolbox&tab=image&tool=batch-optimize`, { waitUntil: 'domcontentloaded', timeout: 45000 });
	assert(!page.url().includes('wp-login.php'), 'Browser opened the Toolbox admin surface as an administrator.');
	await page.waitForFunction(() => Boolean(window.NpcinkToolbox && window.NpcinkToolbox.restUrl), null, { timeout: 15000 });

	await page.evaluate((id) => {
		const form = document.querySelector('form[data-toolbox-tool-panel="media-batch-optimize"]');
		if (!(form instanceof HTMLFormElement)) throw new Error('Batch media optimization form is missing.');
		const setValue = (name, value) => {
			const field = form.querySelector(`[name="${name}"]`);
			if (!(field instanceof HTMLInputElement || field instanceof HTMLSelectElement)) throw new Error(`Missing field: ${name}`);
			field.value = String(value);
			field.dispatchEvent(new Event(field instanceof HTMLSelectElement ? 'change' : 'input', { bubbles: true }));
		};
		setValue('attachment_ids', id);
		setValue('batch_scope_preset', 'all');
		setValue('batch_recipe', 'watermark');
		setValue('batch_max_items', '1');
		setValue('batch_exclude_formats', 'gif,svg');
		setValue('batch_min_dimensions', '0x0');
		setValue('batch_target_format', 'webp');
		setValue('max_width', '320');
		setValue('quality', '82');
		setValue('watermark_mode', 'text');
		setValue('watermark_text', 'B5');
		setValue('watermark_position', 'bottom_right');
		setValue('watermark_opacity', '35');
		setValue('watermark_font_size', '16');
		setValue('watermark_margin', '8');

		window.__npcinkMediaProgressSnapshots = [];
		new MutationObserver(() => {
			const text = form.querySelector('.npcink-toolbox__result')?.textContent || '';
			if (text) window.__npcinkMediaProgressSnapshots.push(text);
		}).observe(form.querySelector('.npcink-toolbox__result'), { childList: true, subtree: true, characterData: true });

		const nativeCreateObjectUrl = URL.createObjectURL.bind(URL);
		const nativeRevokeObjectUrl = URL.revokeObjectURL.bind(URL);
		window.__npcinkMediaObjectUrls = { created: [], revoked: [] };
		URL.createObjectURL = (blob) => {
			const url = nativeCreateObjectUrl(blob);
			window.__npcinkMediaObjectUrls.created.push(url);
			return url;
		};
		URL.revokeObjectURL = (url) => {
			window.__npcinkMediaObjectUrls.revoked.push(String(url));
			return nativeRevokeObjectUrl(url);
		};

		const nativeFetch = window.fetch.bind(window);
		const nativeSetTimeout = window.setTimeout.bind(window);
		window.__npcinkForcePendingMediaRun = true;
		window.__npcinkHoldLocalReview = false;
		window.__npcinkReleaseLocalReview = null;
		window.setTimeout = (callback, delay, ...args) => nativeSetTimeout(callback, Number(delay) === 1500 ? 0 : delay, ...args);
		window.fetch = async (resource, options = {}) => {
			const url = new URL(typeof resource === 'string' ? resource : resource.url, window.location.href);
			const method = String(options.method || (typeof resource === 'string' ? 'GET' : resource.method) || 'GET').toUpperCase();
			if (window.__npcinkForcePendingMediaRun && method === 'GET' && /\/media-derivative-preview\/[^/]+$/.test(url.pathname)) {
				return new Response(JSON.stringify({ cloud_run: { status: 'running' } }), { status: 200, headers: { 'Content-Type': 'application/json' } });
			}
			if (window.__npcinkHoldLocalReview && method === 'POST' && url.pathname.includes('/media-derivative-local-review/')) {
				await new Promise((resolve) => { window.__npcinkReleaseLocalReview = resolve; });
			}
			return nativeFetch(resource, options);
		};
	}, attachmentId);

	assert(await page.locator('[data-toolbox-submit-media-batch-proposals]').isDisabled(), 'Core submit starts disabled before any result read.');
	await page.locator('[data-toolbox-build-media-batch-plan]').click();
	await page.waitForSelector('[data-toolbox-authorize-media-batch-read]', { state: 'visible', timeout: 30000 });
	readRequestId = await page.evaluate(() => String(document.querySelector('form[data-toolbox-tool-panel="media-batch-optimize"]')?.__npcinkMediaDerivativeBatchReadAuthorization?.requestId || ''));
	assert(Boolean(readRequestId), 'Toolbox creates a bounded one-time Core read request before returning local media candidates.');
	assert(await page.locator('[data-toolbox-submit-media-batch-proposals]').isDisabled(), 'Core submit stays disabled while the bounded local media read awaits authorization.');
	await page.locator('[data-toolbox-authorize-media-batch-read]').click();
	await page.waitForFunction(() => {
		if (document.querySelectorAll('[data-toolbox-media-batch-candidate]').length > 0) return true;
		const text = document.querySelector('form[data-toolbox-tool-panel="media-batch-optimize"] .npcink-toolbox__result')?.textContent || '';
		return text.trim() !== ''
			&& !text.includes('Building media derivative batch plan')
			&& !text.includes('Core is recording the explicit one-time read authorization');
	}, null, { timeout: 30000 });
	const planState = await page.evaluate(() => ({
		candidateCount: document.querySelectorAll('[data-toolbox-media-batch-candidate]').length,
		previewDisabled: document.querySelector('[data-toolbox-run-media-batch-previews]')?.disabled,
		resultText: document.querySelector('form[data-toolbox-tool-panel="media-batch-optimize"] .npcink-toolbox__result')?.textContent || '',
	}));
	assert(planState.candidateCount === 1 && planState.previewDisabled === false, 'Review list contains the one explicit fixture and enables preview. Result: ' + planState.resultText);
	assert(requests.some((request) => request.method === 'POST' && new URL(request.url).pathname.endsWith('/npcink-openclaw-adapter/v1/read-requests')), 'Browser creates the bounded read request through Adapter.');
	assert(requests.some((request) => request.method === 'POST' && new URL(request.url).pathname.endsWith('/npcink-governance-core/v1/read-requests/' + readRequestId + '/approve')), 'The explicit operator action records approval through Core.');
	assert(requests.some((request) => {
		if (request.method !== 'POST' || !new URL(request.url).pathname.endsWith('/npcink-openclaw-adapter/v1/run-read-ability')) return false;
		try {
			const body = JSON.parse(request.postData);
			return body.ability_id === 'npcink-abilities-toolkit/build-media-derivative-batch-plan' && body.read_request_id === readRequestId;
		} catch (error) {
			return false;
		}
	}), 'Adapter executes the batch planner only after receiving the exact Core read request id.');
	assert(await page.locator('[data-toolbox-submit-media-batch-proposals]').isDisabled(), 'Core submit stays disabled after planning and before preview bytes load.');
	await page.locator('[data-toolbox-run-media-batch-previews]').click();
	await page.waitForFunction(() => {
		if (document.querySelector('[data-toolbox-continue-media-run]')) return true;
		const text = document.querySelector('form[data-toolbox-tool-panel="media-batch-optimize"] .npcink-toolbox__result')?.textContent || '';
		return text.includes('needs attention');
	}, null, { timeout: 30000 });
	const previewAttemptState = await page.evaluate(() => ({
		hasContinue: Boolean(document.querySelector('[data-toolbox-continue-media-run]')),
		resultText: document.querySelector('form[data-toolbox-tool-panel="media-batch-optimize"] .npcink-toolbox__result')?.textContent || '',
	}));
	assert(previewAttemptState.hasContinue, 'Timed-out preview exposes the same-run continuation. Result: ' + previewAttemptState.resultText);
	assert(await page.locator('[data-toolbox-submit-media-batch-proposals]').isDisabled(), 'Timed-out polling keeps Core submit disabled.');
	const timeoutState = await page.evaluate(() => ({
		text: document.querySelector('form[data-toolbox-tool-panel="media-batch-optimize"] .npcink-toolbox__result')?.textContent || '',
		progress: window.__npcinkMediaProgressSnapshots || [],
		createCount: window.__npcinkMediaProgressSnapshots?.length || 0,
	}));
	assert(timeoutState.text.includes('Continue checking this run'), 'Timeout exposes one explicit continue-checking action.');
	assert(timeoutState.progress.some((text) => text.includes('Upload source')) && timeoutState.progress.some((text) => text.includes('Cloud processing')), 'Real UI rendered upload and Cloud-processing progress before timeout.');

	await page.evaluate(() => {
		window.__npcinkForcePendingMediaRun = false;
		window.__npcinkHoldLocalReview = true;
	});
	await page.locator('[data-toolbox-continue-media-run]').click();
	await page.waitForFunction(() => {
		const form = document.querySelector('form[data-toolbox-tool-panel="media-batch-optimize"]');
		const state = form?.__npcinkMediaDerivativeBatchStates?.[0];
		return state?.derivative?.artifact_id && state.localReviewStatus === 'pending';
	}, null, { timeout: 30000 });
	assert(await page.locator('[data-toolbox-submit-media-batch-proposals]').isDisabled(), 'Exact result descriptor alone does not enable Core submit before image onload.');
	assert(!(await page.locator('form[data-toolbox-tool-panel="media-batch-optimize"] .npcink-toolbox__result').innerText()).includes('Verified preview ready'), 'UI does not claim a verified preview before image onload.');
	await page.evaluate(() => {
		if (typeof window.__npcinkReleaseLocalReview !== 'function') throw new Error('Held local review request was not observed.');
		window.__npcinkHoldLocalReview = false;
		window.__npcinkReleaseLocalReview();
	});
	await page.waitForFunction(() => {
		const form = document.querySelector('form[data-toolbox-tool-panel="media-batch-optimize"]');
		const state = form?.__npcinkMediaDerivativeBatchStates?.[0];
		return state?.localReviewStatus === 'verified' && !document.querySelector('[data-toolbox-submit-media-batch-proposals]')?.disabled;
	}, null, { timeout: 30000 });

	const uiState = await page.evaluate(() => {
		const form = document.querySelector('form[data-toolbox-tool-panel="media-batch-optimize"]');
		const state = form.__npcinkMediaDerivativeBatchStates[0];
		return {
			runId: state.runId,
			result: state.result,
			localReviewStatus: state.localReviewStatus,
			submitDisabled: document.querySelector('[data-toolbox-submit-media-batch-proposals]')?.disabled,
			objectUrls: window.__npcinkMediaObjectUrls,
			progress: window.__npcinkMediaProgressSnapshots,
		};
	});
	const created = { status: 202, payload: { run_id: uiState.runId } };
	const result = { status: 200, payload: uiState.result };
	assert(created.payload.run_id && uiState.localReviewStatus === 'verified' && uiState.submitDisabled === false, 'Image onload marks local review verified and enables the Core submit button.');
	assert(uiState.progress.some((text) => text.includes('Read result')), 'Real UI rendered the result-read stage.');
	assert(uiState.objectUrls.created.length >= 1 && JSON.stringify(uiState.objectUrls.created) === JSON.stringify(uiState.objectUrls.revoked), 'Every preview object URL created by the UI was revoked after image settlement.');
	const derivative = result?.payload?.cloud_result?.artifact || {};
	const localReview = result?.payload?.local_review || {};
	const localArtifact = localReview.artifact || {};
	const expectedLocalArtifactKeys = [
		'artifact_id', 'expires_at', 'mime_type', 'format', 'width', 'height',
		'filesize_bytes', 'sha256', 'suggested_filename', 'filename_basis', 'processing_warnings',
	];
	assert(
		derivative.artifact_id
			&& localReview.method === 'POST'
			&& localArtifact.artifact_id === derivative.artifact_id
			&& localArtifact.expires_at === derivative.expires_at
			&& Object.keys(localArtifact).length === expectedLocalArtifactKeys.length
			&& expectedLocalArtifactKeys.every((key) => Object.prototype.hasOwnProperty.call(localArtifact, key)),
		'Browser received exact Cloud artifact evidence and a separate canonical local review projection.'
	);
	const localReviewUrl = new URL(String(localReview.endpoint || ''));
	assert(
		localReviewUrl.origin === new URL(baseUrl).origin
			&& localReviewUrl.pathname.includes('/npcink-toolbox/v1/media-derivative-local-review/')
			&& localReviewUrl.search === ''
			&& localReviewUrl.hash === ''
			&& !/preview_sig|token|secret|storage|remote_url|_wpnonce|sha256|processing_warnings/i.test(localReviewUrl.href),
		'Local review endpoint is same-origin and carries no query, nonce, descriptor fact, or secret locator.'
	);
	const unauthorized = await page.evaluate(async ({ endpoint, artifact }) => (await fetch(endpoint, {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify({ artifact }),
	})).status, { endpoint: localReview.endpoint, artifact: localArtifact });
	assert(unauthorized === 401 || unauthorized === 403, 'Local review bytes reject cookie authentication without a WordPress REST nonce.');
	const nonce = await page.evaluate(() => window.NpcinkToolbox.nonce);
	const missingArtifact = await page.evaluate(async ({ endpoint, nonce: restNonce }) => {
		const response = await fetch(endpoint, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': restNonce },
			body: JSON.stringify({}),
		});
		return { status: response.status, payload: await response.json() };
	}, { endpoint: localReview.endpoint, nonce });
	assert(missingArtifact.status === 400 && missingArtifact.payload?.code === 'rest_missing_callback_param', 'Real WordPress REST dispatch rejects a missing whole artifact argument before the callback.');
	const preview = await page.evaluate(async ({ endpoint, artifact, nonce }) => {
		const response = await fetch(endpoint, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
			body: JSON.stringify({ artifact }),
		});
		return {
			status: response.status,
			type: response.headers.get('content-type') || '',
			cache: response.headers.get('cache-control') || '',
			nosniff: response.headers.get('x-content-type-options') || '',
			bytes: (await response.arrayBuffer()).byteLength,
		};
	}, { endpoint: localReview.endpoint, artifact: localArtifact, nonce });
	assert(preview.status === 200 && preview.type.includes('image/webp') && preview.bytes > 0, 'Browser loaded verified WebP bytes through Toolbox and Cloud Addon.');
	assert(preview.cache.includes('no-store') && preview.nosniff === 'nosniff', 'Local review bytes are non-cacheable and protected from MIME sniffing.');
	const localReviewRequests = requests.filter((request) => request.url.includes('/npcink-toolbox/v1/media-derivative-local-review/'));
	assert(localReviewRequests.length >= 3 && localReviewRequests.every((request) => request.method === 'POST'), 'Every browser local-review request uses POST.');
	assert(localReviewRequests.every((request) => new URL(request.url).search === '' && !/_wpnonce|sha256|processing_warnings/i.test(request.url)), 'Every browser local-review request keeps nonce and descriptor facts out of the URL.');
	const exactArtifactRequests = localReviewRequests.filter((request) => {
		try {
			const body = JSON.parse(request.postData);
			return Object.keys(body).length === 1 && JSON.stringify(body.artifact) === JSON.stringify(localArtifact);
		} catch (error) {
			return false;
		}
	});
	assert(exactArtifactRequests.length >= 2, 'Unauthorized and authorized browser local-review requests both send one exact JSON artifact body.');
	assert(requests.some((request) => request.url.includes('/npcink-toolbox/v1/media-derivative-preview')), 'Browser traffic uses the Toolbox preview namespace.');
	const previewCreateRequests = requests.filter((request) => request.method === 'POST' && new URL(request.url).pathname.endsWith('/media-derivative-preview'));
	assert(previewCreateRequests.length === 1, 'Browser created exactly one media derivative preview run.');
	assert(previewCreateRequests.every((request) => {
		try {
			const input = JSON.parse(request.postData).input || {};
			const legacyKeys = ['target_format', 'max_width', 'watermark_enabled'];
			return input.preferred_format === 'webp'
				&& input.target_max_width === 320
				&& input.watermark?.type === 'text'
				&& input.watermark?.text === 'B5'
				&& legacyKeys.every((key) => !Object.prototype.hasOwnProperty.call(input, key));
		} catch (error) {
			return false;
		}
	}), 'Browser preview traffic uses preferred_format, target_max_width, and canonical watermark fields with no legacy aliases.');
	assert(!requests.some((request) => /npcink-openclaw-adapter\/v1\/media-derivative-(runs|proposal-payload)/.test(request.url)), 'Browser traffic contains no removed Adapter Cloud route.');
} finally {
	try {
		if (browser) {
			await browser.close();
		}
	} finally {
		if (readRequestId) {
			wpCli(['eval', `$id=${JSON.stringify(readRequestId)}; global $wpdb; $wpdb->delete($wpdb->prefix.'npcink_governance_core_audit_log', array('proposal_id'=>$id), array('%s')); $wpdb->delete($wpdb->prefix.'npcink_governance_core_read_requests', array('request_id'=>$id), array('%s'));`]);
		}
		if (attachmentId > 0) {
			wpCli(['eval', `if (!wp_delete_attachment(${attachmentId}, true)) { fwrite(STDERR, 'Attachment cleanup failed.'); exit(1); }`]);
		}
	}
}
