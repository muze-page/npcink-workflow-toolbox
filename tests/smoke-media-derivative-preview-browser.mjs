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
		`$id=0; $path=''; try { $upload=wp_upload_dir(); if (!empty($upload['error'])) { throw new RuntimeException((string)$upload['error']); } $path=trailingslashit($upload['path']).'toolbox-browser-preview-'.wp_generate_password(8,false,false).'.png'; if (!function_exists('imagecreatetruecolor')) { throw new RuntimeException('GD is unavailable.'); } $im=imagecreatetruecolor(640,360); if (!$im) { throw new RuntimeException('Image allocation failed.'); } $bg=imagecolorallocate($im,32,94,150); imagefilledrectangle($im,0,0,640,360,$bg); if (!imagepng($im,$path)) { throw new RuntimeException('Fixture image write failed.'); } imagedestroy($im); $inserted=wp_insert_attachment(array('post_mime_type'=>'image/png','post_title'=>'Toolbox browser preview','post_status'=>'inherit'),$path,0,true); if (is_wp_error($inserted)) { throw new RuntimeException($inserted->get_error_message()); } $id=(int)$inserted; require_once ABSPATH.'wp-admin/includes/image.php'; $metadata=wp_generate_attachment_metadata($id,$path); if (!is_array($metadata) || !wp_update_attachment_metadata($id,$metadata)) { throw new RuntimeException('Attachment metadata generation failed.'); } echo $id; } catch (Throwable $error) { if ($id>0) { wp_delete_attachment($id,true); } elseif ($path && file_exists($path)) { wp_delete_file($path); } fwrite(STDERR,$error->getMessage()); exit(1); }`,
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

	const created = await page.evaluate(async (id) => {
		const response = await fetch(`${window.NpcinkToolbox.restUrl.replace(/\/$/, '')}/media-derivative-preview`, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.NpcinkToolbox.nonce },
			body: JSON.stringify({ input: { attachment_id: id, target_format: 'webp', max_width: 320, quality: 82, watermark_enabled: false } }),
		});
		return { status: response.status, payload: await response.json() };
	}, attachmentId);
	assert(created.status === 202 && created.payload.run_id, 'Browser submitted the Toolbox preview route and received a Cloud run id.');

	let result = null;
	for (let attempt = 0; attempt < 40; attempt += 1) {
		await page.waitForTimeout(attempt === 0 ? 250 : 750);
		result = await page.evaluate(async (runId) => {
			const response = await fetch(`${window.NpcinkToolbox.restUrl.replace(/\/$/, '')}/media-derivative-preview/${encodeURIComponent(runId)}/result`, { headers: { 'X-WP-Nonce': window.NpcinkToolbox.nonce } });
			return { status: response.status, payload: await response.json() };
		}, created.payload.run_id);
		if (['succeeded', 'completed'].includes(result.payload?.cloud_result?.status)) break;
	}
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
	assert(!requests.some((request) => /npcink-openclaw-adapter\/v1\/media-derivative-(runs|proposal-payload)/.test(request.url)), 'Browser traffic contains no removed Adapter Cloud route.');
} finally {
	try {
		if (browser) {
			await browser.close();
		}
	} finally {
		if (attachmentId > 0) {
			wpCli(['eval', `if (!wp_delete_attachment(${attachmentId}, true)) { fwrite(STDERR, 'Attachment cleanup failed.'); exit(1); }`]);
		}
	}
}
