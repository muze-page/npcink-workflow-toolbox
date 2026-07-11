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
		env('WP_CLI_PHP', `${process.env.HOME}/Library/Application Support/Local/lightning-services/php-8.5.3+1/bin/darwin-arm64/bin/php`),
		[
			'-d', 'display_errors=0',
			'-d', 'error_reporting=8191',
			'-d', `mysqli.default_socket=${env('WP_DB_SOCKET', `${process.env.HOME}/Library/Application Support/Local/run/NPb24Zg9g/mysql/mysqld.sock`)}`,
			env('WP_CLI_BIN', '/opt/homebrew/bin/wp'),
			`--path=${env('WP_PATH', '/Users/muze/Local Sites/magick-ai/app/public')}`,
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

const baseUrl = env('WP_BASE_URL', 'https://magick-ai.local').replace(/\/$/, '');
const attachmentId = Number(wpCli([
	'eval',
	`$upload=wp_upload_dir(); $path=trailingslashit($upload['path']).'toolbox-browser-preview-'.wp_generate_password(8,false,false).'.png'; $im=imagecreatetruecolor(640,360); $bg=imagecolorallocate($im,32,94,150); imagefilledrectangle($im,0,0,640,360,$bg); imagepng($im,$path); $id=wp_insert_attachment(array('post_mime_type'=>'image/png','post_title'=>'Toolbox browser preview','post_status'=>'inherit'),$path); require_once ABSPATH.'wp-admin/includes/image.php'; wp_update_attachment_metadata($id,wp_generate_attachment_metadata($id,$path)); echo $id;`,
]));
assert(attachmentId > 0, 'Temporary browser-smoke attachment was created.');

const { chromium } = await loadPlaywright();
const launch = { headless: process.env.HEADLESS !== '0' };
if (process.env.BROWSER_EXECUTABLE) {
	launch.executablePath = process.env.BROWSER_EXECUTABLE;
} else if (existsSync('/Applications/Google Chrome.app/Contents/MacOS/Google Chrome')) {
	launch.executablePath = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
}

const browser = await chromium.launch(launch);
try {
	const context = await browser.newContext({ ignoreHTTPSErrors: true });
	await context.addCookies(authCookies(baseUrl));
	const page = await context.newPage();
	const requests = [];
	page.on('request', (request) => requests.push(request.url()));
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
	const derivative = result?.payload?.cloud_result?.derivative || {};
	assert(derivative.artifact_id && derivative.preview_url, 'Browser received derivative evidence and a same-origin signed preview URL.');
	const preview = await page.evaluate(async (url) => {
		const response = await fetch(url);
		return { status: response.status, type: response.headers.get('content-type') || '', bytes: (await response.arrayBuffer()).byteLength };
	}, derivative.preview_url);
	assert(preview.status === 200 && preview.type.includes('image/webp') && preview.bytes > 0, 'Browser loaded the signed WebP preview bytes through Toolbox and Cloud Addon.');
	assert(requests.some((url) => url.includes('/npcink-toolbox/v1/media-derivative-preview')), 'Browser traffic uses the Toolbox preview namespace.');
	assert(!requests.some((url) => /npcink-openclaw-adapter\/v1\/media-derivative-(runs|proposal-payload)/.test(url)), 'Browser traffic contains no removed Adapter Cloud route.');
} finally {
	await browser.close();
	wpCli(['eval', `wp_delete_attachment(${attachmentId}, true);`]);
}
