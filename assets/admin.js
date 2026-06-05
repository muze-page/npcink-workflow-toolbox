(function () {
	'use strict';

	const config = window.MagickAIToolbox || {};

	function serialize(form) {
		const data = {};
		new FormData(form).forEach((value, key) => {
			data[key] = value;
		});
		return data;
	}

	function clearNode(node) {
		while (node.firstChild) {
			node.removeChild(node.firstChild);
		}
	}

	function el(tagName, className, text) {
		const node = document.createElement(tagName);
		if (className) {
			node.className = className;
		}
		if (text !== undefined && text !== null && text !== '') {
			node.textContent = String(text);
		}
		return node;
	}

	function appendMeta(container, label, value) {
		if (value === undefined || value === null || value === '') {
			return;
		}

		const item = el('span', 'magick-ai-toolbox__result-meta-item');
		item.appendChild(el('span', 'magick-ai-toolbox__result-meta-label', label));
		item.appendChild(el('span', 'magick-ai-toolbox__result-meta-value', value));
		container.appendChild(item);
	}

	function formatDateTime(value) {
		const raw = String(value || '').trim();
		if (!raw) {
			return '';
		}

		const hasTimezone = /(?:Z|UTC|[+-]\d{2}:?\d{2})$/i.test(raw);
		const normalized = hasTimezone ? raw.replace(/\s+UTC$/i, 'Z') : raw.replace(' ', 'T') + 'Z';
		const date = new Date(normalized);
		if (Number.isNaN(date.getTime())) {
			return raw;
		}

		const config = window.MagickAIToolbox && window.MagickAIToolbox.dateTime ? window.MagickAIToolbox.dateTime : {};
		if (config.timeZone && !/^[+-]/.test(String(config.timeZone))) {
			try {
				const parts = new Intl.DateTimeFormat('en-US', {
					timeZone: config.timeZone,
					year: 'numeric',
					month: '2-digit',
					day: '2-digit',
					hour: '2-digit',
					minute: '2-digit',
					second: '2-digit',
					hour12: false
				}).formatToParts(date).reduce((carry, part) => {
					carry[part.type] = part.value;
					return carry;
				}, {});

				const hour = parts.hour === '24' ? '00' : parts.hour;
				return parts.year + '-' + parts.month + '-' + parts.day + ' ' + hour + ':' + parts.minute + ':' + parts.second;
			} catch (error) {
				// Fall through to the offset formatter below when Intl rejects a timezone.
			}
		}

		const offsetMinutes = Number.isFinite(Number(config.offsetMinutes)) ? Number(config.offsetMinutes) : 0;
		const shifted = new Date(date.getTime() + offsetMinutes * 60000);
		const pad = (number) => String(number).padStart(2, '0');
		return shifted.getUTCFullYear() + '-' + pad(shifted.getUTCMonth() + 1) + '-' + pad(shifted.getUTCDate()) + ' ' + pad(shifted.getUTCHours()) + ':' + pad(shifted.getUTCMinutes()) + ':' + pad(shifted.getUTCSeconds());
	}

	function formatLabel(value) {
		return String(value || '')
			.replace(/[_-]+/g, ' ')
			.replace(/\b\w/g, (letter) => letter.toUpperCase());
	}

	function truncate(value, limit) {
		const text = String(value || '').trim();
		if (!text || text.length <= limit) {
			return text;
		}

		return text.slice(0, limit - 1).trim() + '...';
	}

	function appendHighlightedText(container, text, query) {
		const source = String(text || '');
		const needle = String(query || '').trim();
		if (!source || !needle) {
			container.textContent = source;
			return;
		}

		const sourceLower = source.toLowerCase();
		const needleLower = needle.toLowerCase();
		let start = 0;
		let index = sourceLower.indexOf(needleLower, start);
		if (index < 0) {
			container.textContent = source;
			return;
		}

		while (index >= 0) {
			if (index > start) {
				container.appendChild(document.createTextNode(source.slice(start, index)));
			}
			container.appendChild(el('mark', '', source.slice(index, index + needle.length)));
			start = index + needle.length;
			index = sourceLower.indexOf(needleLower, start);
		}
		if (start < source.length) {
			container.appendChild(document.createTextNode(source.slice(start)));
		}
	}

	function stringifyDisplayValue(value) {
		if (value === undefined || value === null || value === '') {
			return '';
		}
		if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
			return String(value);
		}
		if (Array.isArray(value)) {
			return value.map(stringifyDisplayValue).filter(Boolean).join('\n');
		}

		try {
			return JSON.stringify(value, null, 2);
		} catch (error) {
			return String(value);
		}
	}

	function collectErrorText(value, messages, seen) {
		if (value === undefined || value === null || value === '') {
			return;
		}
		if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
			const text = String(value).trim();
			if (text && text !== 'Array') {
				messages.push(text);
			}
			return;
		}
		if (Array.isArray(value)) {
			value.forEach((item) => collectErrorText(item, messages, seen));
			return;
		}
		if (typeof value !== 'object') {
			return;
		}
		if (seen.has(value)) {
			return;
		}
		seen.add(value);

		['message', 'error', 'error_message', 'detail', 'description'].forEach((key) => {
			collectErrorText(value[key], messages, seen);
		});
		if (value.code && typeof value.code !== 'object') {
			messages.push('Code: ' + String(value.code));
		}
		if (value.status && typeof value.status !== 'object') {
			messages.push('Status: ' + String(value.status));
		}
		collectErrorText(value.errors, messages, seen);
		if (value.data && typeof value.data === 'object') {
			['message', 'error', 'error_message', 'detail', 'status'].forEach((key) => {
				collectErrorText(value.data[key], messages, seen);
			});
		}
	}

	function formatErrorMessage(error, fallback) {
		const messages = [];
		collectErrorText(error, messages, new WeakSet());
		const unique = messages.filter((message, index) => message && messages.indexOf(message) === index);
		const advice = watermarkErrorAdvice(error);
		if (advice && unique.indexOf(advice) === -1) {
			unique.push(advice);
		}
		if (unique.length) {
			return unique.join('\n');
		}

		const text = stringifyDisplayValue(error).trim();
		return text && text !== 'Array' ? text : (fallback || 'Request failed.');
	}

	function errorContainsCode(value, code, seen) {
		if (!value || typeof value !== 'object') {
			return false;
		}
		if (seen.has(value)) {
			return false;
		}
		seen.add(value);
		if (String(value.code || value.error_code || '') === code) {
			return true;
		}
		return Object.keys(value).some((key) => errorContainsCode(value[key], code, seen));
	}

	function watermarkErrorAdvice(error) {
		if (errorContainsCode(error, 'cloud_media_derivative_watermark_source_missing', new WeakSet())) {
			return 'Image/logo watermarks need a configured Core logo source. Switch this run to Text watermark, or configure the Core media watermark logo before retrying.';
		}
		if (errorContainsCode(error, 'cloud_media_derivative_text_watermark_source_unexpected', new WeakSet())) {
			return 'Text watermarks should not include a logo artifact or upload. Retry with Text watermark fields only.';
		}
		return '';
	}

	function createLink(url, label) {
		const link = el('a', '', label || url);
		link.href = url;
		link.target = '_blank';
		link.rel = 'noreferrer';
		return link;
	}

	function toolboxAdminUrl(params) {
		const url = new URL(window.location.href);
		url.searchParams.set('page', 'magick-ai-toolbox');
		Object.keys(params || {}).forEach((key) => {
			const value = params[key];
			if (value === null || value === undefined || value === '') {
				url.searchParams.delete(key);
			} else {
				url.searchParams.set(key, value);
			}
		});
		return url.toString();
	}

	function joinRestUrl(base, path) {
		return String(base || '').replace(/\/$/, '') + '/' + String(path || '').replace(/^\//, '');
	}

	function withRestNonce(url) {
		if (!url) {
			return '';
		}

		try {
			const parsed = new URL(String(url), window.location.href);
			if (parsed.origin !== window.location.origin) {
				return '';
			}
			if (config.nonce && !parsed.searchParams.has('_wpnonce')) {
				parsed.searchParams.set('_wpnonce', config.nonce);
			}
			return parsed.toString();
		} catch (error) {
			return '';
		}
	}

	async function postJson(base, path, payload) {
		const response = await fetch(joinRestUrl(base, path), {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce || '',
			},
			body: JSON.stringify(payload || {}),
		});
		const body = await response.json().catch(() => ({}));
		if (!response.ok) {
			throw Object.assign({ status: response.status }, body || {});
		}
		return body;
	}

	async function getJson(base, path) {
		const response = await fetch(joinRestUrl(base, path), {
			method: 'GET',
			headers: {
				'X-WP-Nonce': config.nonce || '',
			},
		});
		const body = await response.json().catch(() => ({}));
		if (!response.ok) {
			throw Object.assign({ status: response.status }, body || {});
		}
		return body;
	}

	function sleep(ms) {
		return new Promise((resolve) => window.setTimeout(resolve, ms));
	}

	function createSection(title) {
		const section = el('section', 'magick-ai-toolbox__result-section');
		section.appendChild(el('h3', '', title));
		return section;
	}

	function createRawDetails(payload, title) {
		const details = el('details', 'magick-ai-toolbox__result-details');
		details.appendChild(el('summary', '', title || 'Complete payload'));
		const pre = el('pre', 'magick-ai-toolbox__result-raw');
		pre.textContent = JSON.stringify(payload, null, 2);
		details.appendChild(pre);
		return details;
	}

	function providerLabel(payload) {
		if (!payload || !payload.provider) {
			return 'Toolbox';
		}

		return formatLabel(payload.provider);
	}

	function renderShell(form, payload, title, summary) {
		const result = form.querySelector('.magick-ai-toolbox__result');
		if (!result) {
			return null;
		}

		result.hidden = false;
		result.classList.remove('is-empty');
		clearNode(result);

		const summaryNode = el('div', 'magick-ai-toolbox__result-summary');
		summaryNode.appendChild(el('div', 'magick-ai-toolbox__result-kicker', providerLabel(payload)));
		summaryNode.appendChild(el('h3', '', title));
		summaryNode.appendChild(el('p', '', summary));
		result.appendChild(summaryNode);

		const meta = el('div', 'magick-ai-toolbox__result-meta');
		appendMeta(meta, 'Provider', providerLabel(payload));
		appendMeta(meta, 'Query', payload && payload.query);
		appendMeta(meta, 'Topic', payload && payload.topic);
		appendMeta(meta, 'Collection', payload && payload.collection);
		appendMeta(meta, 'Input', payload && payload.input_type ? formatLabel(payload.input_type) : '');
		appendMeta(meta, 'Embedding', payload && payload.embedding_provider ? formatLabel(payload.embedding_provider) : '');
		appendMeta(meta, 'Model', payload && payload.embedding_model);
		appendMeta(meta, 'Dimensions', payload && payload.embedding_dimensions);
		if (meta.childNodes.length) {
			result.appendChild(meta);
		}

		return result;
	}

	function renderTextResult(form, value, kind) {
		const result = form.querySelector('.magick-ai-toolbox__result');
		if (!result) {
			return;
		}

		result.hidden = false;
		result.classList.remove('is-empty');
		clearNode(result);
		const notice = el('div', 'magick-ai-toolbox__result-notice ' + (kind ? 'is-' + kind : ''));
		notice.textContent = stringifyDisplayValue(value);
		result.appendChild(notice);
	}

	function renderErrorResult(form, error, fallback) {
		const result = form.querySelector('.magick-ai-toolbox__result');
		if (!result) {
			return;
		}

		result.hidden = false;
		result.classList.remove('is-empty');
		clearNode(result);
		result.appendChild(el('div', 'magick-ai-toolbox__result-notice is-error', formatErrorMessage(error, fallback)));
		if (error && typeof error === 'object') {
			result.appendChild(createRawDetails(error, 'Error payload'));
		}
	}

	function extractOperatorFeedback(payload) {
		if (!payload || typeof payload !== 'object') {
			return null;
		}

		if (payload.operator_feedback && typeof payload.operator_feedback === 'object') {
			return payload.operator_feedback;
		}

		if (payload.data && payload.data.operator_feedback && typeof payload.data.operator_feedback === 'object') {
			return payload.data.operator_feedback;
		}

		return null;
	}

	function renderOperatorFeedback(form, payload) {
		const feedback = extractOperatorFeedback(payload);
		if (!feedback) {
			return false;
		}

		const result = renderShell(
			form,
			{ provider: 'toolbox' },
			'Operator feedback',
			feedback.message || 'The governed handoff needs operator revision before it can continue.'
		);
		if (!result) {
			return true;
		}

		const meta = el('div', 'magick-ai-toolbox__result-meta');
		appendMeta(meta, 'Status', feedback.status ? formatLabel(feedback.status) : '');
		appendMeta(meta, 'Severity', feedback.severity ? formatLabel(feedback.severity) : '');
		appendMeta(meta, 'Retry after revision', feedback.can_retry_after_revision === true ? 'Yes' : 'No');
		if (feedback.core_evidence && feedback.core_evidence.core_error_code) {
			appendMeta(meta, 'Core code', feedback.core_evidence.core_error_code);
		}
		if (feedback.core_evidence && feedback.core_evidence.proposal_id) {
			appendMeta(meta, 'Proposal', feedback.core_evidence.proposal_id);
		}
		if (meta.childNodes.length) {
			result.appendChild(meta);
		}

		if (Array.isArray(feedback.reasons) && feedback.reasons.length) {
			const section = createSection('Reasons');
			const list = el('ul', 'magick-ai-toolbox__step-list');
			feedback.reasons.forEach((reason) => {
				list.appendChild(el('li', '', reason));
			});
			section.appendChild(list);
			result.appendChild(section);
		}

		if (Array.isArray(feedback.revision_fields) && feedback.revision_fields.length) {
			result.appendChild(el('div', 'magick-ai-toolbox__result-notice is-warning', 'Revise fields: ' + feedback.revision_fields.join(', ')));
		}

		if (Array.isArray(feedback.next_steps) && feedback.next_steps.length) {
			const section = createSection('Next steps');
			const list = el('ol', 'magick-ai-toolbox__step-list');
			feedback.next_steps.forEach((step) => {
				list.appendChild(el('li', '', step));
			});
			section.appendChild(list);
			result.appendChild(section);
		}

		result.appendChild(createRawDetails(payload, 'Feedback payload'));
		return true;
	}

	function renderSourceList(container, results) {
		if (!Array.isArray(results) || !results.length) {
			return;
		}

		const section = createSection('Sources');
		const list = el('div', 'magick-ai-toolbox__result-list');
		results.forEach((item) => {
			const row = el('article', 'magick-ai-toolbox__result-item');
			const title = el('h4', '', item.title || item.url || 'Source');
			row.appendChild(title);
			if (item.url) {
				row.appendChild(createLink(item.url, item.url));
			}
			if (item.content) {
				row.appendChild(el('p', '', truncate(item.content, 260)));
			}
			const meta = el('div', 'magick-ai-toolbox__result-meta');
			appendMeta(meta, 'Score', item.score);
			if (meta.childNodes.length) {
				row.appendChild(meta);
			}
			list.appendChild(row);
		});
		section.appendChild(list);
		container.appendChild(section);
	}

	function renderImageList(container, images) {
		if (!Array.isArray(images) || !images.length) {
			return;
		}

		const section = createSection('Image-source candidates');
		const list = el('div', 'magick-ai-toolbox__image-list');
		images.forEach((image) => {
			const row = el('article', 'magick-ai-toolbox__image-item');
			const previewUrl = image.thumbnail_url || image.thumb_url || image.small_url || image.download_url || image.regular_url;
			if (previewUrl) {
				const preview = el('img', 'magick-ai-toolbox__image-thumb');
				preview.src = previewUrl;
				preview.alt = image.alt_description || image.description || '';
				preview.loading = 'lazy';
				row.appendChild(preview);
			}

			const body = el('div', 'magick-ai-toolbox__image-body');
			body.appendChild(el('h4', '', image.description || image.alt_description || image.id || 'Image candidate'));
			if (image.attribution) {
				body.appendChild(el('p', '', image.attribution));
			}
			const links = el('div', 'magick-ai-toolbox__result-actions');
			if (image.html_url) {
				links.appendChild(createLink(image.html_url, 'Open on ' + formatLabel(image.provider || 'source')));
			}
			if (image.photographer_url) {
				links.appendChild(createLink(image.photographer_url, 'Photographer'));
			}
			if (links.childNodes.length) {
				body.appendChild(links);
			}
			const meta = el('div', 'magick-ai-toolbox__result-meta');
			appendMeta(meta, 'Provider', image.provider ? formatLabel(image.provider) : '');
			appendMeta(meta, 'ID', image.id);
			appendMeta(meta, 'Suggested filename', image.suggested_filename);
			appendMeta(meta, 'License review', image.license_review_status ? formatLabel(image.license_review_status) : '');
			appendMeta(meta, 'Source type', image.source_type ? formatLabel(image.source_type) : '');
			appendMeta(meta, 'Download tracking', image.download_location ? 'Preserved' : '');
			appendMeta(meta, 'Photographer', image.photographer);
			if (meta.childNodes.length) {
				body.appendChild(meta);
			}
			if (image.requires_human_license_review) {
				body.appendChild(el('div', 'magick-ai-toolbox__result-notice is-warning', 'License or source review is required before Core approval.'));
			}
			if (image.download_location || image.suggested_filename || image.filename_basis) {
				const details = el('details', 'magick-ai-toolbox__result-details');
				details.appendChild(el('summary', '', 'Attribution metadata'));
				const pre = el('pre', 'magick-ai-toolbox__result-raw');
				pre.textContent = JSON.stringify({
					attribution: image.attribution || '',
					download_location: image.download_location || '',
					regular_url: image.regular_url || '',
					suggested_filename: image.suggested_filename || '',
					filename_basis: image.filename_basis || {},
				}, null, 2);
				details.appendChild(pre);
				body.appendChild(details);
			}
			row.appendChild(body);
			list.appendChild(row);
		});
		section.appendChild(list);
		container.appendChild(section);
	}

	function renderPointList(container, points) {
		if (!Array.isArray(points) || !points.length) {
			return;
		}

		const section = createSection('Vector matches');
		const list = el('div', 'magick-ai-toolbox__result-list');
		points.forEach((point, index) => {
			const row = el('article', 'magick-ai-toolbox__result-item');
			row.appendChild(el('h4', '', point.id ? 'Point ' + point.id : 'Match ' + (index + 1)));
			const meta = el('div', 'magick-ai-toolbox__result-meta');
			appendMeta(meta, 'Score', point.score);
			appendMeta(meta, 'Version', point.version);
			if (meta.childNodes.length) {
				row.appendChild(meta);
			}
			if (point.payload) {
				row.appendChild(createRawDetails(point.payload, 'Payload'));
			}
			list.appendChild(row);
		});
		section.appendChild(list);
		container.appendChild(section);
	}

	function renderHandoff(container, handoff) {
		if (!handoff || typeof handoff !== 'object') {
			return;
		}

		const section = createSection('Governed handoff');
		const meta = el('div', 'magick-ai-toolbox__result-meta');
		appendMeta(meta, 'Write posture', handoff.write_posture || 'suggestion_only');
		appendMeta(meta, 'Final write path', handoff.final_write_path || 'Core proposal required');
		section.appendChild(meta);

		if (Array.isArray(handoff.next_steps) && handoff.next_steps.length) {
			const list = el('ol', 'magick-ai-toolbox__step-list');
			handoff.next_steps.forEach((step) => {
				list.appendChild(el('li', '', step));
			});
			section.appendChild(list);
		}
		container.appendChild(section);
	}

	function renderArtifactSummary(container, title, artifact) {
		if (!artifact || typeof artifact !== 'object') {
			return;
		}

		const section = createSection(title);
		const meta = el('div', 'magick-ai-toolbox__result-meta');
		Object.keys(artifact).slice(0, 4).forEach((key) => {
			const value = artifact[key];
			if (Array.isArray(value)) {
				appendMeta(meta, formatLabel(key), value.length + ' item' + (value.length === 1 ? '' : 's'));
			} else if (value && typeof value === 'object') {
				appendMeta(meta, formatLabel(key), 'Included');
			} else {
				appendMeta(meta, formatLabel(key), truncate(value, 80));
			}
		});
		if (meta.childNodes.length) {
			section.appendChild(meta);
		}
		section.appendChild(createRawDetails(artifact, title + ' payload'));
		container.appendChild(section);
	}

	function renderImageSourceCandidates(form, payload, title) {
		const count = Array.isArray(payload.images) ? payload.images.length : 0;
		const result = renderShell(
			form,
			payload,
			title || 'Image-source candidates',
			count
				? count + ' candidates returned from Cloud-managed image-source runtime. Review license evidence before adoption.'
				: 'No image-source candidates were returned.'
		);
		if (!result) {
			return;
		}

		const meta = el('div', 'magick-ai-toolbox__result-meta');
		appendMeta(meta, 'Cloud runtime', payload.cloud_runtime || 'magick_ai_cloud_addon');
		appendMeta(meta, 'Provider mode', payload.provider_mode ? formatLabel(payload.provider_mode) : '');
		appendMeta(meta, 'Auto strategy', payload.auto_strategy ? formatLabel(payload.auto_strategy) : '');
		appendMeta(meta, 'Resolved provider', payload.resolved_provider ? formatLabel(payload.resolved_provider) : '');
		appendMeta(meta, 'Candidate contract', payload.candidate_contract_version);
		if (Array.isArray(payload.active_sources) && payload.active_sources.length) {
			appendMeta(
				meta,
				'Active sources',
				payload.active_sources.map((source) => {
					const provider = source && source.provider ? formatLabel(source.provider) : 'Cloud';
					const countValue = source && source.count !== undefined ? ' (' + source.count + ')' : '';
					return provider + countValue;
				}).join(', ')
			);
		}
		if (meta.childNodes.length) {
			result.appendChild(meta);
		}

		result.appendChild(el('div', 'magick-ai-toolbox__result-notice is-ok', 'Cloud returned image candidates only. Media import still requires an Adopt New Image plan and Core approval.'));
		renderImageList(result, payload.images);
		if (payload.raw) {
			result.appendChild(createRawDetails(payload.raw, 'Provider raw response'));
		}
		result.appendChild(createRawDetails(payload, 'Complete payload'));
	}

	function renderUnsplash(form, payload, title) {
		renderImageSourceCandidates(form, payload, title);
	}

	function renderQdrant(form, payload) {
		const count = Array.isArray(payload.points) ? payload.points.length : 0;
		const result = renderShell(
			form,
			payload,
			'Vector search',
			count
				? count + ' vector matches returned from the configured collection.'
				: 'No vector matches were returned.'
		);
		if (!result) {
			return;
		}

		renderPointList(result, payload.points);
		if (payload.raw) {
			result.appendChild(createRawDetails(payload.raw, 'Provider raw response'));
		}
		result.appendChild(createRawDetails(payload, 'Complete payload'));
	}

	function renderSiteKnowledgeStatusNode(container, payload) {
		const coverage = payload && payload.coverage && typeof payload.coverage === 'object' ? payload.coverage : {};
		const progress = payload && payload.progress && typeof payload.progress === 'object' ? payload.progress : {};
		const activeRun = payload && payload.active_run && typeof payload.active_run === 'object' ? payload.active_run : {};
		clearNode(container);

		const status = String(payload && payload.status ? payload.status : 'unknown');
		const noticeKind = status === 'ready' ? 'ok' : (status === 'failed' ? 'error' : 'pending');
		container.appendChild(el('div', 'magick-ai-toolbox__result-notice is-' + noticeKind, 'Status: ' + formatLabel(status)));
		if (progress.message) {
			container.appendChild(el('div', 'magick-ai-toolbox__result-notice is-' + noticeKind, progress.message));
		}

		const meta = el('div', 'magick-ai-toolbox__result-meta');
		appendMeta(meta, 'Stage', progress.stage ? formatLabel(progress.stage) : '');
		appendMeta(meta, 'Progress', typeof progress.percent === 'number' ? progress.percent + '%' : '');
		appendMeta(
			meta,
			'Processed',
			typeof progress.total_documents === 'number' && progress.total_documents > 0
				? String(progress.processed_documents || 0) + ' / ' + String(progress.total_documents)
				: ''
		);
		appendMeta(meta, 'Indexed posts', coverage.indexed_posts);
		appendMeta(meta, 'Indexed chunks', coverage.indexed_chunks);
		appendMeta(meta, 'Truncated documents', coverage.truncated_documents);
		appendMeta(meta, 'Failures', progress.failed_documents);
		appendMeta(meta, 'Last sync', formatDateTime(coverage.last_sync_at));
		appendMeta(meta, 'Active run', activeRun.run_id);
		appendMeta(meta, 'Comments', coverage.comments_enabled === true ? 'Enabled in Cloud' : 'Disabled in Cloud');
		if (meta.childNodes.length) {
			container.appendChild(meta);
		}

		if (coverage.post_type_coverage || coverage.source_type_coverage) {
			const details = el('details', 'magick-ai-toolbox__result-details');
			details.appendChild(el('summary', '', 'Coverage detail'));
			const pre = el('pre', 'magick-ai-toolbox__result-raw');
			pre.textContent = JSON.stringify({
				post_type_coverage: coverage.post_type_coverage || {},
				source_type_coverage: coverage.source_type_coverage || {},
				has_stale_content: coverage.has_stale_content === true,
			}, null, 2);
			details.appendChild(pre);
			container.appendChild(details);
		}
	}

	function renderSiteKnowledgeStatus(form, payload) {
		const result = renderShell(
			form,
			payload,
			'Site knowledge status',
			'Cloud-managed coverage summary for this WordPress site.'
		);
		if (!result) {
			return;
		}

		const panel = el('div', 'magick-ai-toolbox__knowledge-summary');
		renderSiteKnowledgeStatusNode(panel, payload);
		result.appendChild(panel);
		result.appendChild(createRawDetails(payload, 'Status payload'));
	}

	function renderSiteKnowledgeSync(form, payload) {
		const sync = payload && payload.sync && typeof payload.sync === 'object' ? payload.sync : {};
		const result = renderShell(
			form,
			payload,
			'Site knowledge sync',
			payload.status === 'queued'
				? 'Cloud accepted the sync request. Refresh status to watch coverage and completion.'
				: 'Cloud returned a site knowledge sync response.'
		);
		if (!result) {
			return;
		}

		const meta = el('div', 'magick-ai-toolbox__result-meta');
		appendMeta(meta, 'Status', payload.status ? formatLabel(payload.status) : '');
		appendMeta(meta, 'Run', payload.run_id);
		appendMeta(meta, 'Action', sync.sync_mode ? 'Index refresh' : '');
		appendMeta(meta, 'Accepted documents', sync.accepted_documents);
		appendMeta(meta, 'Indexed documents', sync.indexed_documents);
		appendMeta(meta, 'Indexed chunks', sync.indexed_chunks);
		appendMeta(meta, 'Truncated documents', sync.truncated_documents);
		appendMeta(meta, 'Failed documents', sync.failed_documents);
		result.appendChild(meta);
		if (payload.message) {
			result.appendChild(el('div', 'magick-ai-toolbox__result-notice is-pending', payload.message));
		}
		renderHandoff(result, payload.handoff);
		result.appendChild(createRawDetails(payload, 'Sync payload'));
	}

	function renderSiteKnowledgeResults(form, payload) {
		const results = Array.isArray(payload.results) ? payload.results : [];
		const exactResults = results.filter((item) => item && item.exact_query_match === true);
		const visibleResults = exactResults.length ? exactResults : results;
		const hiddenSemanticCount = exactResults.length ? Math.max(0, results.length - exactResults.length) : 0;
		const queryInput = form ? form.querySelector('[name="query"]') : null;
		const query = queryInput ? queryInput.value : '';
		const result = renderShell(
			form,
			payload,
			'Site knowledge search',
			visibleResults.length
				? visibleResults.length + ' site knowledge results returned for review.'
				: 'No indexed site knowledge results were returned.'
		);
		if (!result) {
			return;
		}

		const meta = el('div', 'magick-ai-toolbox__result-meta');
		appendMeta(meta, 'Intent', payload.intent ? formatLabel(payload.intent) : '');
		appendMeta(meta, 'Status', payload.status ? formatLabel(payload.status) : '');
		if (payload.evidence_gate && typeof payload.evidence_gate === 'object') {
			appendMeta(meta, 'Evidence', payload.evidence_gate.status ? formatLabel(payload.evidence_gate.status) : '');
		}
		if (payload.rerank && typeof payload.rerank === 'object') {
			appendMeta(meta, 'Rerank', payload.rerank.status ? formatLabel(payload.rerank.status) : '');
			appendMeta(meta, 'Rerank provider', payload.rerank.provider ? formatLabel(payload.rerank.provider) : '');
			appendMeta(meta, 'Rerank model', payload.rerank.model);
			appendMeta(meta, 'Rerank candidates', payload.rerank.candidate_count);
			appendMeta(meta, 'Reranked', payload.rerank.reranked_count);
			appendMeta(meta, 'Rerank fallback', payload.rerank.fallback ? formatLabel(payload.rerank.fallback) : '');
		}
		result.appendChild(meta);
		if (payload.rerank && typeof payload.rerank === 'object' && payload.rerank.status === 'failed') {
			result.appendChild(el('div', 'magick-ai-toolbox__result-notice is-pending', 'Cloud rerank failed; vector order was used as the fallback.'));
		}
		if (hiddenSemanticCount > 0) {
			result.appendChild(el('div', 'magick-ai-toolbox__result-notice is-pending', hiddenSemanticCount + ' semantic-only result' + (hiddenSemanticCount === 1 ? '' : 's') + ' hidden because exact query matches were found. Expand Search payload to inspect them.'));
		}

		if (visibleResults.length) {
			const section = createSection('Results');
			const list = el('div', 'magick-ai-toolbox__result-list');
			visibleResults.forEach((item) => {
				const row = el('article', 'magick-ai-toolbox__result-item');
				row.appendChild(el('h4', '', item.title || 'Indexed source'));
				if (item.url) {
					row.appendChild(createLink(item.url, item.url));
				}
				const context = item.match_context || item.chunk || '';
				const contextNode = el('p', '');
				appendHighlightedText(contextNode, truncate(context, 420), item.exact_query_match ? query : '');
				row.appendChild(contextNode);
				const rowMeta = el('div', 'magick-ai-toolbox__result-meta');
				appendMeta(rowMeta, 'Score', item.score);
				appendMeta(rowMeta, 'Match', item.match_type ? formatLabel(item.match_type) : '');
				appendMeta(rowMeta, 'Exact hits', item.match_count);
				appendMeta(rowMeta, 'Source', item.source_type ? formatLabel(item.source_type) : '');
				appendMeta(rowMeta, 'Use', item.suggested_use ? formatLabel(item.suggested_use) : '');
				appendMeta(rowMeta, 'Post', item.post_id);
				row.appendChild(rowMeta);
				list.appendChild(row);
			});
			section.appendChild(list);
			result.appendChild(section);
		}
		result.appendChild(createRawDetails(payload, 'Search payload'));
	}

	function renderWebSearchResults(form, payload) {
		const results = Array.isArray(payload.results) ? payload.results : [];
		const result = renderShell(
			form,
			payload,
			'Cloud web search',
			results.length
				? results.length + ' external search results returned from Cloud.'
				: 'Cloud search completed without usable external results.'
		);
		if (!result) {
			return;
		}

		const meta = el('div', 'magick-ai-toolbox__result-meta');
		appendMeta(meta, 'Status', payload.status ? formatLabel(payload.status) : '');
		appendMeta(meta, 'Intent', payload.intent ? formatLabel(payload.intent) : '');
		appendMeta(meta, 'Provider calls', payload.provider_call_count);
		appendMeta(meta, 'Run', payload.run_id);
		if (payload.usage_summary && typeof payload.usage_summary === 'object') {
			appendMeta(meta, 'Failure', payload.usage_summary.failure_reason ? formatLabel(payload.usage_summary.failure_reason) : '');
		}
		if (payload.evidence_gate && typeof payload.evidence_gate === 'object') {
			appendMeta(meta, 'Evidence', payload.evidence_gate.status ? formatLabel(payload.evidence_gate.status) : '');
			appendMeta(meta, 'Sources', payload.evidence_gate.source_count);
		}
		if (meta.childNodes.length) {
			result.appendChild(meta);
		}

		if (results.length) {
			const section = createSection('Results');
			const list = el('div', 'magick-ai-toolbox__result-list');
			results.forEach((item) => {
				const row = el('article', 'magick-ai-toolbox__result-item');
				row.appendChild(el('h4', '', item.title || item.url || 'Search result'));
				if (item.url) {
					row.appendChild(createLink(item.url, item.url));
				}
				row.appendChild(el('p', '', truncate(item.snippet || '', 360)));
				const rowMeta = el('div', 'magick-ai-toolbox__result-meta');
				appendMeta(rowMeta, 'Score', item.score);
				appendMeta(rowMeta, 'Source', item.source ? formatLabel(item.source) : '');
				appendMeta(rowMeta, 'Write posture', item.write_posture ? formatLabel(item.write_posture) : '');
				row.appendChild(rowMeta);
				list.appendChild(row);
			});
			section.appendChild(list);
			result.appendChild(section);
		}

		renderHandoff(result, payload.handoff);
		result.appendChild(createRawDetails(payload, 'Search payload'));
	}

	function renderWebSearchDiagnostics(form, payload) {
		const search = payload.workflow_search && typeof payload.workflow_search === 'object' ? payload.workflow_search : {};
		const result = renderShell(
			form,
			payload,
			'Workflow search diagnostic',
			payload.search_triggered === true
				? 'The selected Toolbox workflow attached Cloud web search evidence.'
				: 'The selected Toolbox workflow did not attach usable Cloud web search evidence.'
		);
		if (!result) {
			return;
		}

		const meta = el('div', 'magick-ai-toolbox__result-meta');
		appendMeta(meta, 'Scenario', payload.scenario ? formatLabel(payload.scenario) : '');
		appendMeta(meta, 'Triggered', payload.search_triggered === true ? 'Yes' : 'No');
		appendMeta(meta, 'Status', payload.status ? formatLabel(payload.status) : '');
		appendMeta(meta, 'Workflow', payload.workflow_artifact_type ? formatLabel(payload.workflow_artifact_type) : '');
		appendMeta(meta, 'Provider', payload.cloud_provider ? formatLabel(payload.cloud_provider) : '');
		appendMeta(meta, 'Provider calls', payload.provider_call_count);
		appendMeta(meta, 'Results', payload.result_count);
		appendMeta(meta, 'Sources', payload.source_count);
		appendMeta(meta, 'Error code', payload.error_code ? formatLabel(payload.error_code) : '');
		if (payload.usage_summary && typeof payload.usage_summary === 'object') {
			appendMeta(meta, 'Evidence', payload.usage_summary.evidence_status ? formatLabel(payload.usage_summary.evidence_status) : '');
		}
		result.appendChild(meta);

		if (payload.search_triggered !== true) {
			result.appendChild(el('div', 'magick-ai-toolbox__result-notice is-warning', 'Check Cloud connection before relying on external evidence.'));
		}

		if (Array.isArray(search.sources) && search.sources.length) {
			const section = createSection('Attached sources');
			const list = el('div', 'magick-ai-toolbox__result-list');
			search.sources.forEach((item) => {
				const row = el('article', 'magick-ai-toolbox__result-item');
				row.appendChild(el('h4', '', item.title || item.url || 'Attached source'));
				if (item.url) {
					row.appendChild(createLink(item.url, item.url));
				}
				row.appendChild(el('p', '', truncate(item.summary || item.snippet || '', 280)));
				const rowMeta = el('div', 'magick-ai-toolbox__result-meta');
				appendMeta(rowMeta, 'Source', item.source_type ? formatLabel(item.source_type) : item.source ? formatLabel(item.source) : '');
				appendMeta(rowMeta, 'Status', item.verification_status ? formatLabel(item.verification_status) : '');
				row.appendChild(rowMeta);
				list.appendChild(row);
			});
			section.appendChild(list);
			result.appendChild(section);
		}

		renderHandoff(result, payload.handoff);
		result.appendChild(createRawDetails(payload, 'Diagnostic payload'));
	}

	function renderArticleBrief(form, payload) {
		const result = renderShell(
			form,
			payload,
			'Article planning bundle',
			'Fallback planning bundle only. Review sources, candidates, and handoff notes before creating a Core proposal.'
		);
		if (!result) {
			return;
		}

		if (payload.research && payload.research.error) {
			const notice = el('div', 'magick-ai-toolbox__result-notice is-warning', payload.research.error);
			notice.appendChild(createLink(
				toolboxAdminUrl({
					toolbox_tab: 'cloud-checks',
					toolbox_cloud_check: 'search',
					toolbox_cloud_check_group: 'search-test',
					toolbox_tool: null,
				}),
				'Open Cloud Search test'
			));
			result.appendChild(notice);
		} else if (payload.research) {
			const section = createSection('External search');
			section.appendChild(el('div', 'magick-ai-toolbox__result-notice is-pending', 'Live Cloud web search verification belongs in Cloud Checks. Use this bundle for combined fallback planning and handoff context.'));
			section.appendChild(createLink(
				toolboxAdminUrl({
					toolbox_tab: 'cloud-checks',
					toolbox_cloud_check: 'search',
					toolbox_cloud_check_group: 'search-test',
					toolbox_tool: null,
				}),
				'Open Cloud Search test'
			));
			result.appendChild(section);
		}

		if (payload.images && payload.images.error) {
			result.appendChild(el('div', 'magick-ai-toolbox__result-notice is-warning', payload.images.error));
		} else if (payload.images) {
			renderImageList(result, payload.images.images);
		}

		if (payload.knowledge && payload.knowledge.error) {
			result.appendChild(el('div', 'magick-ai-toolbox__result-notice is-warning', payload.knowledge.error));
		} else if (payload.knowledge) {
			renderPointList(result, payload.knowledge.points);
		}

		renderHandoff(result, payload.handoff);
		result.appendChild(createRawDetails(payload, 'Complete payload'));
	}

	function supportItems(section) {
		if (!section || typeof section !== 'object') {
			return [];
		}
		return section.items || section.results || section.candidates || [];
	}

	function renderSupportItems(container, title, items, emptyMessage) {
		const section = createSection(title);
		if (!Array.isArray(items) || !items.length) {
			section.appendChild(el('div', 'magick-ai-toolbox__result-notice is-warning', emptyMessage || 'No suggestions returned.'));
			container.appendChild(section);
			return;
		}

		const list = el('div', 'magick-ai-toolbox__result-list');
		items.slice(0, 10).forEach((item, index) => {
			const row = el('article', 'magick-ai-toolbox__result-item');
			const titleText = item.name || item.title || item.label || item.source_title || item.url || item.id || 'Candidate ' + (index + 1);
			row.appendChild(el('h4', '', titleText));
			const detail = item.reason || item.detail || item.excerpt || item.snippet || item.source_url || item.status || '';
			if (detail) {
				row.appendChild(el('p', '', truncate(detail, 260)));
			}
			if (item.url) {
				row.appendChild(createLink(item.url, item.url));
			}
			const meta = el('div', 'magick-ai-toolbox__result-meta');
			appendMeta(meta, 'Score', item.score);
			appendMeta(meta, 'Taxonomy', item.taxonomy ? formatLabel(item.taxonomy) : '');
			appendMeta(meta, 'Post', item.post_id);
			appendMeta(meta, 'Status', item.status ? formatLabel(item.status) : '');
			appendMeta(meta, 'Provider', item.provider ? formatLabel(item.provider) : '');
			if (meta.childNodes.length) {
				row.appendChild(meta);
			}
			list.appendChild(row);
		});
		section.appendChild(list);
		container.appendChild(section);
	}

	function renderEditorContentSupport(form, payload) {
		const sections = payload.sections && typeof payload.sections === 'object' ? payload.sections : {};
		const title = payload.intent ? formatLabel(payload.intent) : 'Content support';
		const result = renderShell(
			form,
			payload,
			title,
			'Fixed support flow returned suggestions only. Final WordPress writes still require Core proposal approval.'
		);
		if (!result) {
			return;
		}

		const meta = el('div', 'magick-ai-toolbox__result-meta');
		appendMeta(meta, 'Intent', payload.intent ? formatLabel(payload.intent) : '');
		appendMeta(meta, 'Write posture', payload.write_posture || 'suggestion_only');
		appendMeta(meta, 'Final path', payload.final_write_path || 'core_proposal_required');
		if (payload.post_context && payload.post_context.post_id) {
			appendMeta(meta, 'Post', payload.post_context.post_id);
		}
		result.appendChild(meta);

		if (sections.checks) {
			renderSupportItems(result, 'Checks', supportItems(sections.checks), 'No checks returned.');
		}
		if (sections.taxonomy_terms) {
			renderSupportItems(result, 'Taxonomy and tag candidates', supportItems(sections.taxonomy_terms), 'No matching existing terms found.');
		}
		if (sections.site_knowledge) {
			renderSupportItems(result, 'Internal link candidates', supportItems(sections.site_knowledge), 'No related public content returned.');
		}
		if (sections.duplicate_check) {
			renderSupportItems(result, 'Duplicate risk', supportItems(sections.duplicate_check), 'No duplicate-risk candidates returned.');
		}
		if (sections.image_candidates) {
			if (sections.image_candidates.status === 'error') {
				result.appendChild(el('div', 'magick-ai-toolbox__result-notice is-warning', sections.image_candidates.message || 'Image candidate search failed.'));
			} else {
				renderImageList(result, sections.image_candidates.images || sections.image_candidates.image_candidates || sections.image_candidates.candidates);
			}
		}
		if (sections.discoverability && sections.discoverability.candidate_suggestions) {
			const suggestions = Object.keys(sections.discoverability.candidate_suggestions).map((field) => ({
				name: formatLabel(field),
				detail: String(sections.discoverability.candidate_suggestions[field] || ''),
			}));
			renderSupportItems(result, 'Discoverability suggestions', suggestions, 'No discoverability suggestions returned.');
		}

		renderHandoff(result, payload.handoff);
		result.appendChild(createRawDetails(payload, 'Complete payload'));
	}

	function renderArticlePlan(form, payload) {
		const risk = payload.article_risk_report || {};
		const ready = risk.ready_for_proposal === true;
		const action = Array.isArray(payload.write_actions) ? payload.write_actions[0] || {} : {};
		const actionInput = action.input || {};
		const result = renderShell(
			form,
			payload,
			'Article write plan',
			ready
				? 'Core-ready planning artifact. Review it, then hand it to Core from-plan intake for approval.'
				: 'Plan is not ready for Core proposal intake. Review risk and blocked claims before handoff.'
		);
		if (!result) {
			return;
		}

		const meta = el('div', 'magick-ai-toolbox__result-meta');
		appendMeta(meta, 'Artifact', payload.artifact_type);
		appendMeta(meta, 'Batch', payload.batch_id);
		appendMeta(meta, 'Risk', risk.risk_level ? formatLabel(risk.risk_level) : '');
		appendMeta(meta, 'Ready', ready ? 'Yes' : 'No');
		appendMeta(meta, 'Final ability', action.target_ability_id);
		appendMeta(meta, 'Post status', actionInput.status);
		result.appendChild(meta);

		if (Array.isArray(risk.blocked_claims) && risk.blocked_claims.length) {
			result.appendChild(el('div', 'magick-ai-toolbox__result-notice is-error', 'Blocked claims must be resolved before Core handoff.'));
		}
		if (risk.risk_level === 'high') {
			result.appendChild(el('div', 'magick-ai-toolbox__result-notice is-warning', 'High-risk plans are expected to fail Core proposal intake until revised.'));
		}

		renderArtifactSummary(result, 'Goal brief', payload.article_goal_brief);
		renderArtifactSummary(result, 'Evidence pack', payload.research_evidence_pack);
		renderArtifactSummary(result, 'Outline', payload.article_outline);
		renderArtifactSummary(result, 'Draft candidate', payload.article_draft_candidate);
		renderArtifactSummary(result, 'Discoverability', payload.discoverability_pack);
		renderArtifactSummary(result, 'Risk report', risk);
		renderHandoff(result, payload.handoff);
		result.appendChild(createRawDetails(payload, 'Complete payload'));
	}

	function renderArticleAssistant(form, payload) {
		const risk = payload.article_risk_report || {};
		const ready = risk.ready_for_proposal === true;
		const hasWritePlan = payload.article_write_plan && payload.article_write_plan.artifact_type === 'article_write_plan';
		const result = renderShell(
			form,
			payload,
			'Article assistant',
			ready
				? 'Local workbench is ready to hand its article_write_plan to Core proposal intake.'
				: 'Local workbench artifact returned. Revise evidence, context, or reviewed draft before Core handoff.'
		);
		if (!result) {
			return;
		}

		const meta = el('div', 'magick-ai-toolbox__result-meta');
		appendMeta(meta, 'Recipe', payload.source_recipe_id);
		appendMeta(meta, 'Risk', risk.risk_level ? formatLabel(risk.risk_level) : '');
		appendMeta(meta, 'Ready', ready ? 'Yes' : 'No');
		appendMeta(meta, 'Write plan', hasWritePlan ? 'Included' : 'Not ready');
		appendMeta(meta, 'Final path', payload.final_write_path || (payload.handoff && payload.handoff.final_write_path));
		if (payload.research_evidence_pack && payload.research_evidence_pack.research_status) {
			appendMeta(meta, 'Search', payload.research_evidence_pack.research_status.status ? formatLabel(payload.research_evidence_pack.research_status.status) : '');
			appendMeta(meta, 'Search results', payload.research_evidence_pack.research_status.result_count);
		}
		result.appendChild(meta);

		if (Array.isArray(risk.needs_review) && risk.needs_review.length) {
			result.appendChild(el('div', 'magick-ai-toolbox__result-notice is-warning', 'Review required: ' + risk.needs_review.join(', ')));
		}
		if (Array.isArray(risk.blocked_claims) && risk.blocked_claims.length) {
			result.appendChild(el('div', 'magick-ai-toolbox__result-notice is-error', 'Blocked claims must be removed before Core handoff.'));
		}

		renderArtifactSummary(result, 'Goal brief', payload.article_goal_brief);
		renderArtifactSummary(result, 'Evidence pack', payload.research_evidence_pack);
		if (payload.image_candidates && payload.image_candidates.error) {
			result.appendChild(el('div', 'magick-ai-toolbox__result-notice is-warning', payload.image_candidates.error));
		} else if (payload.image_candidates) {
			renderImageList(result, payload.image_candidates.images);
		}
		renderArtifactSummary(result, 'Outline', payload.article_outline);
		renderArtifactSummary(result, 'Draft candidate', payload.article_draft_candidate);
		renderArtifactSummary(result, 'Discoverability', payload.discoverability_pack);
		if (hasWritePlan) {
			renderArtifactSummary(result, 'Article write plan', payload.article_write_plan);
		}
		renderHandoff(result, payload.handoff);
		result.appendChild(createRawDetails(payload, 'Complete payload'));
	}

	function renderMediaDerivativeHandoff(form, payload) {
		const abilityInput = payload.ability_input || {};
		const result = renderShell(
			form,
			payload,
			'Media derivative handoff',
			'One-run planning artifact. Run the local ability and review the derivative through Core governance before any WordPress media write.'
		);
		if (!result) {
			return;
		}

		const meta = el('div', 'magick-ai-toolbox__result-meta');
		appendMeta(meta, 'Attachment', payload.attachment_id);
		appendMeta(meta, 'Ability', payload.ability_id);
		appendMeta(meta, 'Format', abilityInput.preferred_format ? String(abilityInput.preferred_format).toUpperCase() : '');
		appendMeta(meta, 'Max width', abilityInput.target_max_width ? abilityInput.target_max_width + 'px' : '');
		appendMeta(meta, 'Quality', abilityInput.quality);
		appendMeta(meta, 'Watermark', mediaDerivativeWatermarkLabel(abilityInput));
		appendMeta(meta, 'Core policy', payload.core_policy_available ? 'Available' : 'Fallback defaults');
		result.appendChild(meta);

		if (Array.isArray(payload.warnings) && payload.warnings.length) {
			payload.warnings.forEach((warning) => {
				result.appendChild(el('div', 'magick-ai-toolbox__result-notice is-warning', warning));
			});
		}

		renderArtifactSummary(result, 'Ability input', abilityInput);
		renderHandoff(result, payload.handoff);
		result.appendChild(createRawDetails(payload, 'Complete payload'));
	}

	function renderImageCandidateAdoptionPlan(form, payload) {
		const candidate = payload.selected_image_candidate || {};
		const preview = Array.isArray(payload.preview) ? payload.preview[0] || {} : {};
		const actions = Array.isArray(payload.write_actions) ? payload.write_actions : [];
		const result = renderShell(
			form,
			payload,
			'Image import proposal plan',
			'Review the selected image and source evidence, then submit this plan to Core for approval before any media import or featured-image write.'
		);
		if (!result) {
			return;
		}

		const meta = el('div', 'magick-ai-toolbox__result-meta');
		appendMeta(meta, 'Source type', candidate.source_type ? formatLabel(candidate.source_type) : '');
		appendMeta(meta, 'Provider', candidate.provider ? formatLabel(candidate.provider) : '');
		appendMeta(meta, 'License', candidate.license_review_status ? formatLabel(candidate.license_review_status) : '');
		appendMeta(meta, 'Actions', actions.length);
		appendMeta(meta, 'Post', preview.post_id || '');
		appendMeta(meta, 'Featured image', preview.set_featured_image ? 'Yes' : 'No');
		result.appendChild(meta);

		if (preview.thumbnail_url || candidate.thumbnail_url || candidate.download_url) {
			const section = createSection('Selected image');
			const image = document.createElement('img');
			image.alt = candidate.alt_description || candidate.description || 'Selected image candidate';
			image.src = preview.thumbnail_url || candidate.thumbnail_url || candidate.download_url;
			image.loading = 'lazy';
			image.className = 'magick-ai-toolbox__image-preview';
			section.appendChild(image);
			if (candidate.download_url) {
				section.appendChild(createLink(candidate.download_url, 'Open selected image'));
			}
			if (candidate.source_url) {
				section.appendChild(createLink(candidate.source_url, 'Open source page'));
			}
			result.appendChild(section);
		}

		if (candidate.attribution || preview.attribution) {
			result.appendChild(el('div', 'magick-ai-toolbox__result-notice is-ok', 'Attribution: ' + (candidate.attribution || preview.attribution)));
		}
		if (candidate.requires_human_license_review) {
			result.appendChild(el('div', 'magick-ai-toolbox__result-notice is-warning', 'License or source review is required before approval.'));
		}

		renderArtifactSummary(result, 'Candidate evidence', candidate);
		renderArtifactSummary(result, 'Planned write actions', actions);
		renderHandoff(result, payload.handoff);
		result.appendChild(createRawDetails(payload, 'Complete payload'));
	}

	function derivativeFromResult(payload) {
		const cloudResult = payload && payload.cloud_result ? payload.cloud_result : payload;
		if (!cloudResult || typeof cloudResult !== 'object') {
			return {};
		}
		if (cloudResult.derivative && typeof cloudResult.derivative === 'object') {
			return cloudResult.derivative;
		}
		if (cloudResult.data && cloudResult.data.derivative && typeof cloudResult.data.derivative === 'object') {
			return cloudResult.data.derivative;
		}
		if (cloudResult.data && cloudResult.data.result && cloudResult.data.result.artifact) {
			return cloudResult.data.result.artifact;
		}
		return {};
	}

	function cloudStatus(payload) {
		const cloudRun = payload && payload.cloud_run ? payload.cloud_run : payload && payload.cloud_result ? payload.cloud_result : payload;
		if (!cloudRun || typeof cloudRun !== 'object') {
			return '';
		}
		return String(cloudRun.status || (cloudRun.data && cloudRun.data.status) || '');
	}

	function mediaDerivativeInput(form) {
		const raw = serialize(form);
		const input = {};
		['attachment_id', 'target_format', 'max_width', 'quality'].forEach((key) => {
			if (raw[key] !== undefined && raw[key] !== null && String(raw[key]).trim() !== '') {
				input[key] = raw[key];
			}
		});
		Object.assign(input, mediaDerivativeWatermarkInput(raw));
		return input;
	}

	function mediaDetailsInput(form) {
		const raw = serialize(form);
		const details = {};
		const fields = {
			media_title: 'title',
			media_alt: 'alt',
			media_caption: 'caption',
			media_description: 'description',
			media_source_type: 'source_type',
		};
		Object.keys(fields).forEach((field) => {
			const value = raw[field];
			if (value !== undefined && value !== null && String(value).trim() !== '') {
				details[fields[field]] = String(value).trim();
			}
		});
		return details;
	}

	function hasReviewedMediaDetails(details) {
		return details && typeof details === 'object' && Object.keys(details).length > 0;
	}

	function mediaDerivativeWatermarkInput(raw) {
		raw = raw || {};
		const mode = String(raw.watermark_mode || 'core');
		if (mode === 'off') {
			return { watermark_enabled: false };
		}
		if (mode !== 'text' && mode !== 'image' && mode !== 'override') {
			return {};
		}

		const opacity = clampInteger(raw.watermark_opacity, 80, 0, 100) / 100;
		const margin = clampInteger(raw.watermark_margin, 24, 0, 1000);
		const position = String(raw.watermark_position || 'bottom_right');
		if (mode === 'text') {
			const text = String(raw.watermark_text || 'AI').trim().slice(0, 64) || 'AI';
			return {
				watermark_enabled: true,
				watermark: {
					type: 'text',
					text,
					position,
					opacity,
					font_size: clampInteger(raw.watermark_font_size, 48, 8, 256),
					color: String(raw.watermark_color || '#FFFFFF').trim() || '#FFFFFF',
					background: String(raw.watermark_background || 'rgba(0,0,0,0.35)').trim() || 'rgba(0,0,0,0.35)',
					margin_px: margin,
				},
			};
		}

		return {
			watermark_enabled: true,
			watermark: {
				type: 'image',
				position,
				opacity,
				scale_percent: clampInteger(raw.watermark_scale, 20, 1, 100),
				margin_px: margin,
			},
		};
	}

	function clampInteger(value, fallback, min, max) {
		const parsed = parseInt(value, 10);
		const integer = Number.isNaN(parsed) ? fallback : parsed;
		return Math.max(min, Math.min(max, integer));
	}

	function mediaDerivativeWatermarkLabel(input) {
		if (!input || typeof input !== 'object') {
			return '';
		}
		if (input.watermark_enabled === false) {
			return 'Disabled for this run';
		}
		if (input.watermark && typeof input.watermark === 'object') {
			const watermark = input.watermark;
			if (watermark.type === 'text') {
				return [
					'Text "' + String(watermark.text || 'AI') + '"',
					watermark.position ? formatLabel(watermark.position) : '',
					watermark.opacity !== undefined ? String(Math.round(Number(watermark.opacity) * 100)) + '% opacity' : '',
					watermark.font_size ? String(watermark.font_size) + 'px font' : '',
					watermark.margin_px !== undefined ? String(watermark.margin_px) + 'px margin' : '',
				].filter(Boolean).join(' · ');
			}
			return [
				'Image logo',
				watermark.position ? formatLabel(watermark.position) : '',
				watermark.opacity !== undefined ? String(Math.round(Number(watermark.opacity) * 100)) + '% opacity' : '',
				watermark.scale_percent ? String(watermark.scale_percent) + '% scale' : '',
				watermark.margin_px !== undefined ? String(watermark.margin_px) + 'px margin' : '',
			].filter(Boolean).join(' · ');
		}
		return 'Core default';
	}

	function dimensionsFromText(value, fallbackWidth, fallbackHeight) {
		const parts = String(value || '').toLowerCase().split('x');
		const width = parseInt(parts[0] || String(fallbackWidth || 0), 10) || fallbackWidth || 0;
		const height = parseInt(parts[1] || parts[0] || String(fallbackHeight || 0), 10) || fallbackHeight || 0;
		return { width, height };
	}

	function mediaDerivativeBatchPlanInput(form) {
		const raw = serialize(form);
		const dimensions = dimensionsFromText(raw.batch_min_dimensions || '0x0', 0, 0);
		const targetFormat = String(raw.batch_target_format || 'webp');
		const input = {
			mime_type: 'image',
			target_format: targetFormat,
			exclude_formats: commaList(raw.batch_exclude_formats || targetFormat),
			min_width: dimensions.width,
			min_height: dimensions.height,
			max_items: parseInt(raw.batch_max_items || '20', 10) || 20,
		};
		if (raw.batch_date_from) {
			input.date_from = String(raw.batch_date_from);
		}
		if (raw.batch_date_to) {
			input.date_to = String(raw.batch_date_to) + ' 23:59:59';
		}
		if (raw.max_width) {
			input.target_max_width = raw.max_width;
		}
		if (raw.quality) {
			input.quality = raw.quality;
		}
		const watermarkInput = mediaDerivativeWatermarkInput(raw);
		if (watermarkInput.watermark) {
			input.watermark = watermarkInput.watermark;
		}
		return input;
	}

	function mediaAttachmentId(form) {
		const field = form.querySelector('[data-toolbox-media-attachment]');
		if (!(field instanceof HTMLInputElement)) {
			return 0;
		}
		return parseInt(field.value || '0', 10) || 0;
	}

	function mediaUrlValue(form) {
		const field = form.querySelector('[data-toolbox-media-url]');
		return field instanceof HTMLInputElement ? String(field.value || '').trim() : '';
	}

	function basenameFromPath(value) {
		const parts = String(value || '').split('/');
		return parts.length ? parts[parts.length - 1] : '';
	}

	function mediaResolutionCandidateAttachment(candidate) {
		candidate = candidate || {};
		return {
			id: parseInt(candidate.attachment_id || '0', 10) || 0,
			filename: candidate.title || basenameFromPath(candidate.relative_file || candidate.matched_relative_file || candidate.url || ''),
			url: candidate.url || '',
			alt: candidate.title || '',
		};
	}

	function referenceRepairInput(form) {
		return {
			attachment_id: mediaAttachmentId(form),
			max_posts: 20,
			max_replacements_per_post: 20,
		};
	}

	function commaList(value) {
		return String(value || '')
			.split(',')
			.map((item) => item.trim())
			.filter(Boolean);
	}

	function settingsReferenceRepairInput(form) {
		const raw = serialize(form);
		const dimensions = String(raw.settings_min_dimensions || '64x64').toLowerCase().split('x');
		const minWidth = parseInt(dimensions[0] || '64', 10) || 64;
		const minHeight = parseInt(dimensions[1] || dimensions[0] || '64', 10) || 64;
		return {
			attachment_id: mediaAttachmentId(form),
			max_settings: 50,
			max_replacements_per_setting: 20,
			excluded_formats: commaList(raw.settings_excluded_formats || 'svg,gif,ico,pdf'),
			min_width: minWidth,
			min_height: minHeight,
			excluded_filename_patterns: ['logo', 'favicon', 'icon', 'brand', 'payment', 'placeholder'],
		};
	}

	function proposalInputFromState(state) {
		const artifact = state.derivative || {};
		const abilityInput = state.abilityInput || {};
		return {
			attachment_id: abilityInput.attachment_id,
			derivative_artifact: {
				artifact_id: artifact.artifact_id || artifact.id || '',
				expires_at: artifact.expires_at || '',
				expires_ts: artifact.expires_ts || '',
				mime_type: artifact.mime_type || '',
				format: artifact.format || '',
				width: artifact.width || 0,
				height: artifact.height || 0,
				filesize_bytes: artifact.filesize_bytes || 0,
				checksum: artifact.checksum || artifact.sha256 || '',
				sha256: artifact.sha256 || artifact.checksum || '',
				processing_warnings: Array.isArray(artifact.processing_warnings) ? artifact.processing_warnings : [],
				cloud_run_id: state.runId || '',
			},
			expected_derivative_mime_type: artifact.mime_type || '',
			backup_suffix: 'magick-ai-cloud-backup',
			dry_run: true,
			commit: false,
			idempotency_key: 'media-derivative-' + String(artifact.artifact_id || artifact.id || state.runId || Date.now()),
		};
	}

	function planDataFromEnvelope(payload) {
		if (payload && payload.result && payload.result.success && payload.result.data) {
			return payload.result.data;
		}
		if (payload && payload.success && payload.data) {
			return payload.data;
		}
		if (payload && payload.result) {
			return payload.result;
		}
		return payload && payload.data ? payload.data : payload;
	}

	function proposalFromPlanResponse(payload) {
		if (payload && Array.isArray(payload.proposals) && payload.proposals.length) {
			return payload.proposals[0];
		}
		if (payload && payload.proposal) {
			return payload.proposal;
		}
		return payload;
	}

	function renderMediaDerivativeRun(form, state, message) {
		const payload = state.result || state.create || {};
		const derivative = state.derivative || derivativeFromResult(payload);
		const result = renderShell(
			form,
			{ provider: 'cloud runtime' },
			'Media derivative preview',
			message || 'Cloud generated a short-lived derivative artifact. Submit a Core replacement proposal before any local adoption.'
		);
		if (!result) {
			return;
		}

		const meta = el('div', 'magick-ai-toolbox__result-meta');
		appendMeta(meta, 'Run', state.runId);
		appendMeta(meta, 'Artifact', derivative.artifact_id || derivative.id);
		appendMeta(meta, 'Format', derivative.format ? String(derivative.format).toUpperCase() : '');
		appendMeta(meta, 'MIME', derivative.mime_type);
		appendMeta(meta, 'Size', derivative.width && derivative.height ? derivative.width + ' x ' + derivative.height : '');
		appendMeta(meta, 'Bytes', derivative.filesize_bytes);
		appendMeta(meta, 'Expires', formatDateTime(derivative.expires_at));
		appendMeta(meta, 'Watermark', mediaDerivativeWatermarkLabel(state.abilityInput));
		result.appendChild(meta);

		if (Array.isArray(derivative.processing_warnings) && derivative.processing_warnings.length) {
			derivative.processing_warnings.forEach((warning) => {
				result.appendChild(el('div', 'magick-ai-toolbox__result-notice is-warning', warning));
			});
		}

		const previewUrl = withRestNonce(derivative.preview_url || '');
		if (previewUrl) {
			const preview = el('figure', 'magick-ai-toolbox__derivative-preview');
			const image = el('img');
			image.src = previewUrl;
			image.alt = 'Generated derivative preview';
			image.loading = 'lazy';
			preview.appendChild(image);
			preview.appendChild(el('figcaption', '', 'Same-origin signed preview proxy. This is not a public Cloud URL or a WordPress media write.'));
			result.appendChild(preview);
			result.appendChild(el('div', 'magick-ai-toolbox__result-notice is-ok', 'Preview is served through Adapter and Cloud Addon with local authorization.'));
		} else {
			result.appendChild(el('div', 'magick-ai-toolbox__result-notice is-warning', 'Preview uses artifact evidence only. The local signed preview proxy did not return a display URL.'));
		}
		renderArtifactSummary(result, 'Derivative artifact', derivative);
		if (state.fromPlanRequest) {
			result.appendChild(el('div', 'magick-ai-toolbox__result-notice is-ok', 'Optimization plan is ready for one Core proposal approval.'));
			renderArtifactSummary(result, 'Media optimization plan', state.fromPlanRequest.plan || {});
		} else if (state.proposalEnvelope) {
			const guard = state.proposalEnvelope.ability_guard || {};
			const nextStep = state.proposalEnvelope.next_step || 'Add reviewed media details, then generate the preview again before Core proposal submission.';
			result.appendChild(el('div', 'magick-ai-toolbox__result-notice is-warning', nextStep));
			if (guard.missing_capability_behavior) {
				result.appendChild(el('div', 'magick-ai-toolbox__result-notice is-warning', 'If Core lacks the media optimization plan ability, update Core and Abilities before continuing. Do not split this optimization into two proposals.'));
			}
		}
		if (state.proposalPayload) {
			renderArtifactSummary(result, 'Derivative-only payload', state.proposalPayload);
		}
		result.appendChild(createRawDetails(payload, 'Cloud result payload'));
	}

	function renderMediaDerivativeBatchPlan(form, planEnvelope, plan) {
		const panel = form.querySelector('[data-toolbox-media-batch-plan]');
		if (!panel) {
			return;
		}
		const candidates = Array.isArray(plan.candidates) ? plan.candidates : [];
		const skipped = Array.isArray(plan.skipped) ? plan.skipped : [];
		const summary = plan.summary || {};
		panel.hidden = false;
		panel.innerHTML = '';

		const heading = el('div', 'magick-ai-toolbox__batch-heading');
		heading.appendChild(el('h4', '', 'Batch plan'));
		const meta = el('div', 'magick-ai-toolbox__result-meta');
		appendMeta(meta, 'Candidates', summary.candidate_count || candidates.length);
		appendMeta(meta, 'Skipped', summary.skipped_count || skipped.length);
		appendMeta(meta, 'Matched', summary.total_matched);
		appendMeta(meta, 'Mode', plan.plan_mode || 'dry_run');
		heading.appendChild(meta);
		panel.appendChild(heading);

		if (!candidates.length) {
			panel.appendChild(el('div', 'magick-ai-toolbox__result-notice is-warning', 'No candidates are ready for derivative previews. Review skipped reasons or adjust filters.'));
		}

		const list = el('div', 'magick-ai-toolbox__batch-list');
		candidates.forEach((candidate, index) => {
			const row = el('label', 'magick-ai-toolbox__batch-row');
			const checkbox = document.createElement('input');
			checkbox.type = 'checkbox';
			checkbox.checked = true;
			checkbox.setAttribute('data-toolbox-media-batch-candidate', String(candidate.attachment_id || ''));
			row.appendChild(checkbox);
			const body = el('span', 'magick-ai-toolbox__batch-row-body');
			body.appendChild(el('strong', '', '#' + String(candidate.attachment_id || '') + ' ' + String(candidate.title || 'Untitled media')));
			const detail = [
				candidate.source_format ? String(candidate.source_format).toUpperCase() : '',
				candidate.target_format ? 'to ' + String(candidate.target_format).toUpperCase() : '',
				candidate.width && candidate.height ? String(candidate.width) + ' x ' + String(candidate.height) : '',
				candidate.filesize_bytes ? String(candidate.filesize_bytes) + ' bytes' : '',
			].filter(Boolean).join(' · ');
			body.appendChild(el('small', '', detail));
			row.appendChild(body);
			row.__magickMediaBatchCandidate = Object.assign({}, candidate, { batch_index: index });
			list.appendChild(row);
		});
		panel.appendChild(list);

		if (skipped.length) {
			const details = el('details', 'magick-ai-toolbox__result-details');
			details.appendChild(el('summary', '', 'Skipped media'));
			const skippedList = el('div', 'magick-ai-toolbox__batch-list');
			skipped.slice(0, 20).forEach((item) => {
				const row = el('div', 'magick-ai-toolbox__batch-row is-skipped');
				const body = el('span', 'magick-ai-toolbox__batch-row-body');
				body.appendChild(el('strong', '', '#' + String(item.attachment_id || '') + ' ' + String(item.title || 'Skipped media')));
				body.appendChild(el('small', '', String(item.reason || 'skipped')));
				row.appendChild(body);
				skippedList.appendChild(row);
			});
			details.appendChild(skippedList);
			panel.appendChild(details);
		}

		panel.appendChild(createRawDetails(planEnvelope, 'Batch plan payload'));
	}

	function renderMediaUrlResolution(form, resolutionEnvelope, resolution) {
		const panel = form.querySelector('[data-toolbox-media-url-resolution]');
		if (!panel) {
			return;
		}

		const candidates = Array.isArray(resolution.candidates) ? resolution.candidates : [];
		panel.hidden = false;
		clearNode(panel);

		const heading = el('div', 'magick-ai-toolbox__batch-heading');
		heading.appendChild(el('h4', '', 'URL resolution'));
		const meta = el('div', 'magick-ai-toolbox__result-meta');
		appendMeta(meta, 'Status', resolution.match_status ? formatLabel(resolution.match_status) : '');
		appendMeta(meta, 'Quality', resolution.resolution_quality ? formatLabel(resolution.resolution_quality) : '');
		appendMeta(meta, 'Attachment', resolution.attachment_id);
		appendMeta(meta, 'Candidates', candidates.length);
		appendMeta(meta, 'Requested', resolution.requested_relative_file || mediaUrlValue(form));
		heading.appendChild(meta);
		panel.appendChild(heading);

		if (resolution.attachment_id) {
			const resolved = candidates.find((candidate) => parseInt(candidate.attachment_id || '0', 10) === parseInt(resolution.attachment_id || '0', 10)) || {
				attachment_id: resolution.attachment_id,
				url: resolution.normalized_url || mediaUrlValue(form),
				relative_file: resolution.requested_relative_file || '',
			};
			renderSelectedMedia(form, mediaResolutionCandidateAttachment(resolved));
			panel.appendChild(el('div', 'magick-ai-toolbox__result-notice is-ok', 'Attachment ID resolved locally. Generate a preview before submitting a Core proposal.'));
		} else if (!candidates.length) {
			panel.appendChild(el('div', 'magick-ai-toolbox__result-notice is-warning', 'No attachment candidate matched this local uploads URL.'));
		} else {
			panel.appendChild(el('div', 'magick-ai-toolbox__result-notice is-warning', 'Review candidate evidence before choosing one attachment.'));
		}

		if (Array.isArray(resolution.warnings) && resolution.warnings.length) {
			resolution.warnings.forEach((warning) => {
				panel.appendChild(el('div', 'magick-ai-toolbox__result-notice is-warning', warning));
			});
		}

		if (candidates.length) {
			const list = el('div', 'magick-ai-toolbox__batch-list');
			candidates.forEach((candidate) => {
				const row = el('div', 'magick-ai-toolbox__batch-row');
				row.setAttribute('data-toolbox-media-resolution-candidate', String(candidate.attachment_id || ''));
				row.__magickMediaResolutionCandidate = candidate;
				const button = el('button', 'button button-small', 'Use attachment');
				button.type = 'button';
				button.setAttribute('data-toolbox-use-media-resolution-candidate', String(candidate.attachment_id || ''));
				row.appendChild(button);

				const body = el('span', 'magick-ai-toolbox__batch-row-body');
				body.appendChild(el('strong', '', '#' + String(candidate.attachment_id || '') + ' ' + String(candidate.title || 'Media attachment')));
				const detail = [
					candidate.match_type ? formatLabel(candidate.match_type) : '',
					candidate.match_score ? 'score ' + String(candidate.match_score) : '',
					candidate.mime_type || '',
					candidate.relative_file || candidate.matched_relative_file || '',
				].filter(Boolean).join(' · ');
				body.appendChild(el('small', '', detail));
				row.appendChild(body);
				list.appendChild(row);
			});
			panel.appendChild(list);
		}

		panel.appendChild(createRawDetails(resolutionEnvelope, 'URL resolution payload'));
	}

	function selectedMediaBatchCandidates(form) {
		const rows = Array.from(form.querySelectorAll('[data-toolbox-media-batch-candidate]'));
		return rows
			.filter((checkbox) => checkbox instanceof HTMLInputElement && checkbox.checked)
			.map((checkbox) => {
				const row = checkbox.closest('.magick-ai-toolbox__batch-row');
				return row && row.__magickMediaBatchCandidate ? row.__magickMediaBatchCandidate : null;
			})
			.filter(Boolean);
	}

	function renderMediaDerivativeBatchResults(form, states, title, summary) {
		const result = renderShell(
			form,
			{ provider: 'core governance' },
			title || 'Batch media derivative previews',
			summary || 'Selected media now have short-lived derivative artifact evidence. Submit Core proposals before artifact expiry.'
		);
		if (!result) {
			return;
		}

		const meta = el('div', 'magick-ai-toolbox__result-meta');
		appendMeta(meta, 'Previewed', states.length);
		appendMeta(meta, 'Proposal path', 'Core review');
		appendMeta(meta, 'Watermark', states.length ? mediaDerivativeWatermarkLabel(states[0].abilityInput) : '');
		result.appendChild(meta);

		const list = el('div', 'magick-ai-toolbox__result-list');
		states.forEach((state) => {
			const derivative = state.derivative || {};
			const row = el('article', 'magick-ai-toolbox__result-item');
			row.appendChild(el('h4', '', '#' + String(state.abilityInput && state.abilityInput.attachment_id ? state.abilityInput.attachment_id : '') + ' ' + String(derivative.format || '').toUpperCase()));
			const itemMeta = el('div', 'magick-ai-toolbox__result-meta');
			appendMeta(itemMeta, 'Artifact', derivative.artifact_id || derivative.id);
			appendMeta(itemMeta, 'Size', derivative.width && derivative.height ? derivative.width + ' x ' + derivative.height : '');
			appendMeta(itemMeta, 'Expires', formatDateTime(derivative.expires_at));
			appendMeta(itemMeta, 'Watermark', mediaDerivativeWatermarkLabel(state.abilityInput));
			row.appendChild(itemMeta);
			const previewUrl = withRestNonce(derivative.preview_url || '');
			if (previewUrl) {
				const link = createLink(previewUrl, 'Open preview');
				row.appendChild(link);
			}
			list.appendChild(row);
		});
		result.appendChild(list);
	}

	function renderProposalCreated(form, proposal, options) {
		options = options || {};
		const proposalId = proposal && proposal.proposal_id ? proposal.proposal_id : '';
		const result = renderShell(
			form,
			{ provider: 'core governance' },
			options.title || 'Core proposal submitted',
			options.summary || 'The derivative artifact is now in Core review as a local media replacement proposal. WordPress writes still require Core approval and preflight.'
		);
		if (!result) {
			return;
		}

		const meta = el('div', 'magick-ai-toolbox__result-meta');
		appendMeta(meta, 'Proposal', proposalId);
		appendMeta(meta, 'Status', proposal && proposal.status ? formatLabel(proposal.status) : '');
		appendMeta(meta, 'Ability', proposal && proposal.ability_id);
		result.appendChild(meta);
		if (proposalId && config.coreAdminUrl) {
			const actions = el('div', 'magick-ai-toolbox__result-actions');
			actions.appendChild(createLink(config.coreAdminUrl + '&proposal_id=' + encodeURIComponent(proposalId), 'Open in Core review'));
			result.appendChild(actions);
		}
		result.appendChild(createRawDetails(proposal, options.rawTitle || 'Core proposal'));
	}

	function renderStructuredResult(form, payload) {
		if (typeof payload === 'string') {
			renderTextResult(form, payload, 'pending');
			return;
		}

		if (!payload || typeof payload !== 'object') {
			renderTextResult(form, '', 'pending');
			return;
		}

		if (renderOperatorFeedback(form, payload)) {
			return;
		}

		if (payload.artifact_type === 'image_source_candidates') {
			renderImageSourceCandidates(form, payload);
			return;
		}

		if (payload.provider === 'unsplash') {
			renderUnsplash(form, payload);
			return;
		}

		if (payload.provider === 'qdrant') {
			renderQdrant(form, payload);
			return;
		}

		if (payload.artifact_type === 'site_knowledge_status') {
			renderSiteKnowledgeStatus(form, payload);
			return;
		}

		if (payload.artifact_type === 'site_knowledge_sync_request') {
			renderSiteKnowledgeSync(form, payload);
			return;
		}

		if (payload.artifact_type === 'site_knowledge_results') {
			renderSiteKnowledgeResults(form, payload);
			return;
		}

		if (payload.artifact_type === 'web_search_results') {
			renderWebSearchResults(form, payload);
			return;
		}

		if (payload.artifact_type === 'web_search_diagnostics') {
			renderWebSearchDiagnostics(form, payload);
			return;
		}

		if (payload.artifact_type === 'editor_content_support_flow') {
			renderEditorContentSupport(form, payload);
			return;
		}

		if (payload.artifact_type === 'article_write_plan') {
			renderArticlePlan(form, payload);
			return;
		}

		if (payload.artifact_type === 'article_assistant_workbench') {
			renderArticleAssistant(form, payload);
			return;
		}

		if (payload.artifact_type === 'media_derivative_handoff') {
			renderMediaDerivativeHandoff(form, payload);
			return;
		}

		if (payload.artifact_type === 'image_candidate_adoption_plan') {
			renderImageCandidateAdoptionPlan(form, payload);
			return;
		}

		if (payload.provider === 'toolbox' && payload.handoff) {
			renderArticleBrief(form, payload);
			return;
		}

		const result = renderShell(form, payload, 'Toolbox result', 'Structured result returned for operator review.');
		if (result) {
			result.appendChild(createRawDetails(payload, 'Complete payload'));
		}
	}

	async function runTool(form) {
		const endpoint = form.getAttribute('data-toolbox-endpoint');
		if (!endpoint || !config.restUrl) {
			return;
		}

		renderTextResult(form, config.labels && config.labels.running ? config.labels.running : 'Running...', 'pending');

		const response = await fetch(config.restUrl.replace(/\/$/, '') + '/' + endpoint, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce || '',
			},
			body: JSON.stringify(serialize(form)),
		});

		const payload = await response.json();
		if (!response.ok) {
			throw Object.assign({ status: response.status }, payload || {});
		}

		if (payload && payload.text) {
			let text = payload.text;
			if (payload.annotations && payload.annotations.length) {
				text += '\n\nAnnotations:\n' + JSON.stringify(payload.annotations, null, 2);
			}
			renderTextResult(form, text, 'ok');
			return;
		}

		renderStructuredResult(form, payload);
	}

	async function waitForMediaDerivativeResult(runId) {
		let lastStatus = '';
		for (let attempt = 0; attempt < 30; attempt += 1) {
			const statusPayload = await getJson(config.adapterRestUrl, 'media-derivative-runs/' + encodeURIComponent(runId));
			lastStatus = cloudStatus(statusPayload);
			if (lastStatus === 'failed' || lastStatus === 'error') {
				throw statusPayload;
			}
			if (lastStatus === 'succeeded' || lastStatus === 'complete' || lastStatus === 'completed') {
				return getJson(config.adapterRestUrl, 'media-derivative-runs/' + encodeURIComponent(runId) + '/result');
			}
			await sleep(1500);
		}
		throw { message: 'Media derivative run did not finish before the preview timeout. Poll the run result again from Adapter.' };
	}

	async function createMediaDerivativePreview(input, mediaDetails, previewOnly) {
		if (!input.attachment_id) {
			throw { message: 'Select an image attachment before generating a preview.' };
		}

		const createPayload = await postJson(config.adapterRestUrl, 'media-derivative-runs', { input });
		const runId = createPayload.run_id || (createPayload.cloud_run && createPayload.cloud_run.run_id) || '';
		if (!runId) {
			throw { message: 'Adapter did not return a Cloud run id.' };
		}

		const resultPayload = await waitForMediaDerivativeResult(runId);
		const derivative = derivativeFromResult(resultPayload);
		if (!derivative || !derivative.artifact_id) {
			throw { message: 'Cloud result did not include a derivative artifact id.' };
		}

		if (previewOnly) {
			return {
				abilityInput: input,
				mediaDetailsInput: mediaDetails || {},
				create: createPayload,
				result: resultPayload,
				runId,
				derivative,
			};
		}

		const proposalRequest = {
			ability_response: createPayload.ability_response || {},
			cloud_result: resultPayload.cloud_result || resultPayload,
			derivative_artifact: derivative,
		};
		if (hasReviewedMediaDetails(mediaDetails)) {
			proposalRequest.media_details_input = mediaDetails;
		}
		const proposalEnvelope = await postJson(config.adapterRestUrl, 'media-derivative-proposal-payload', proposalRequest);

		return {
			abilityInput: input,
			mediaDetailsInput: mediaDetails || {},
			create: createPayload,
			result: resultPayload,
			runId,
			derivative,
			proposalPayload: proposalEnvelope.proposal_payload || {},
			proposalEnvelope,
			fromPlanRequest: proposalEnvelope.from_plan_request || null,
		};
	}

	async function runMediaDerivative(form) {
		if (!config.adapterRestUrl) {
			throw { message: 'Magick AI Adapter REST URL is unavailable.' };
		}

		const input = mediaDerivativeInput(form);
		const mediaDetails = mediaDetailsInput(form);
		const previewOnly = form.hasAttribute('data-toolbox-media-derivative-preview-only');
		renderTextResult(form, 'Submitting media derivative run...', 'pending');
		const state = await createMediaDerivativePreview(input, mediaDetails, previewOnly);
		form.__magickMediaDerivativeState = state;
		const submitButton = form.querySelector('[data-toolbox-submit-media-proposal]');
		if (submitButton instanceof HTMLButtonElement) {
			submitButton.disabled = !state.fromPlanRequest;
		}
		renderMediaDerivativeRun(
			form,
			state,
			previewOnly ? 'Cloud generated a short-lived derivative preview. This check does not submit a Core proposal or write media.' : ''
		);
	}

	async function resolveMediaAttachmentUrl(form) {
		if (!config.adapterRestUrl) {
			throw { message: 'Magick AI Adapter REST URL is unavailable.' };
		}
		const url = mediaUrlValue(form);
		if (!url) {
			throw { message: 'Paste a local uploads URL before resolving an attachment.' };
		}

		renderTextResult(form, 'Resolving media URL...', 'pending');
		const resolutionEnvelope = await postJson(config.adapterRestUrl, 'run-read-ability', {
			ability_id: 'magick-ai/resolve-media-attachment-by-url',
			input: {
				url,
				max_candidates: 10,
			},
		});
		const resolution = planDataFromEnvelope(resolutionEnvelope) || {};
		renderMediaUrlResolution(form, resolutionEnvelope, resolution);
		if (resolution.attachment_id) {
			renderTextResult(form, 'Media URL resolved to attachment #' + String(resolution.attachment_id) + '. Generate a preview to continue.', 'ok');
			return;
		}
		renderTextResult(form, 'Media URL resolution returned candidates. Choose one attachment before generating a preview.', 'warning');
	}

	async function buildMediaDerivativeBatchPlan(form) {
		if (!config.adapterRestUrl) {
			throw { message: 'Magick AI Adapter REST URL is unavailable.' };
		}

		const input = mediaDerivativeBatchPlanInput(form);
		renderTextResult(form, 'Building media derivative batch plan...', 'pending');
		const planEnvelope = await postJson(config.adapterRestUrl, 'run-read-ability', {
			ability_id: 'magick-ai/build-media-derivative-batch-plan',
			input,
		});
		const plan = planDataFromEnvelope(planEnvelope) || {};
		form.__magickMediaDerivativeBatchPlan = plan;
		form.__magickMediaDerivativeBatchStates = [];
		renderMediaDerivativeBatchPlan(form, planEnvelope, plan);
		renderTextResult(form, 'Batch plan ready. Review candidates and generate selected previews.', 'ok');
		const runButton = form.querySelector('[data-toolbox-run-media-batch-previews]');
		const submitButton = form.querySelector('[data-toolbox-submit-media-batch-proposals]');
		if (runButton instanceof HTMLButtonElement) {
			runButton.disabled = !(Array.isArray(plan.candidates) && plan.candidates.length > 0);
		}
		if (submitButton instanceof HTMLButtonElement) {
			submitButton.disabled = true;
		}
	}

	async function runMediaDerivativeBatchPreviews(form) {
		if (!config.adapterRestUrl) {
			throw { message: 'Magick AI Adapter REST URL is unavailable.' };
		}

		const candidates = selectedMediaBatchCandidates(form);
		if (!candidates.length) {
			throw { message: 'Select at least one batch candidate before generating previews.' };
		}
		const watermarkInput = mediaDerivativeWatermarkInput(serialize(form));
		const states = [];
		for (let index = 0; index < candidates.length; index += 1) {
			const candidate = candidates[index] || {};
			const input = Object.assign({}, candidate.cloud_request_input || {}, watermarkInput);
			if (!input.attachment_id && candidate.attachment_id) {
				input.attachment_id = candidate.attachment_id;
			}
			renderTextResult(form, 'Generating preview ' + String(index + 1) + ' of ' + String(candidates.length) + '...', 'pending');
			states.push(await createMediaDerivativePreview(input));
		}

		form.__magickMediaDerivativeBatchStates = states;
		const submitButton = form.querySelector('[data-toolbox-submit-media-batch-proposals]');
		if (submitButton instanceof HTMLButtonElement) {
			submitButton.disabled = states.length <= 0;
		}
		renderMediaDerivativeBatchResults(form, states);
	}

	async function submitMediaDerivativeBatchProposals(form) {
		if (!config.adapterRestUrl) {
			throw { message: 'Magick AI Adapter REST URL is unavailable.' };
		}

		const states = Array.isArray(form.__magickMediaDerivativeBatchStates) ? form.__magickMediaDerivativeBatchStates : [];
		if (!states.length) {
			throw { message: 'Generate selected batch previews before submitting Core proposals.' };
		}

		const proposals = [];
		for (let index = 0; index < states.length; index += 1) {
			const state = states[index];
			renderTextResult(form, 'Submitting Core proposal ' + String(index + 1) + ' of ' + String(states.length) + '...', 'pending');
			proposals.push(await postJson(config.adapterRestUrl, 'proposals', {
				ability_id: 'magick-ai/adopt-cloud-media-derivative',
				title: 'Replace media file with Cloud derivative',
				summary: 'Review one short-lived Cloud derivative artifact before local WordPress media replacement. Final writes require Core approval and preflight.',
				input: proposalInputFromState(state),
				preview: state.proposalPayload,
			}));
		}
		renderMediaDerivativeBatchResults(form, states, 'Batch proposals submitted', 'Selected derivative artifacts are now in Core review. WordPress writes still require Core approval and preflight.');
		const result = form.querySelector('.magick-ai-toolbox__result');
		if (result) {
			result.appendChild(createRawDetails({ proposals }, 'Core proposals'));
		}
	}

	async function submitMediaDerivativeProposal(form) {
		if (!config.adapterRestUrl) {
			throw { message: 'Magick AI Adapter REST URL is unavailable.' };
		}

		const state = form.__magickMediaDerivativeState;
		if (!state || !state.proposalEnvelope || !state.derivative) {
			throw { message: 'Generate a derivative preview before submitting a Core proposal.' };
		}
		if (!state.fromPlanRequest) {
			throw {
				message: 'Reviewed media details are required before Toolbox can submit one media optimization proposal. Add title, alt, caption, description, or source type, then generate the preview again.',
				data: state.proposalEnvelope,
			};
		}

		renderTextResult(form, 'Submitting Core optimization proposal...', 'pending');
		const bridge = await postJson(config.adapterRestUrl, 'proposals/from-plan', Object.assign({}, state.fromPlanRequest, {
			plan_input: {
				attachment_id: state.abilityInput && state.abilityInput.attachment_id ? state.abilityInput.attachment_id : 0,
				source_type: state.mediaDetailsInput && state.mediaDetailsInput.source_type ? state.mediaDetailsInput.source_type : '',
			},
			caller: {
				external_thread_id: 'toolbox-media-optimization',
			},
		}));
		renderProposalCreated(form, proposalFromPlanResponse(bridge), {
			title: 'Media optimization proposal submitted',
			summary: 'Core created one proposal for reviewed media details and the Cloud derivative adoption.',
			rawTitle: 'Core media optimization response',
		});
	}

	async function refreshSiteKnowledgeStatus(root) {
		const summary = root.querySelector('[data-toolbox-site-knowledge-summary]');
		if (summary) {
			clearNode(summary);
			summary.appendChild(el('div', 'magick-ai-toolbox__result-notice is-pending', 'Loading Cloud status...'));
		}
		const payload = await getJson(config.restUrl, 'site-knowledge/status');
		if (summary) {
			renderSiteKnowledgeStatusNode(summary, payload);
			summary.appendChild(createRawDetails(payload, 'Status payload'));
		}
		updateSiteKnowledgeActionState(root, payload);
		return payload;
	}

	async function runSiteKnowledgeForm(form, endpoint) {
		renderTextResult(form, config.labels && config.labels.running ? config.labels.running : 'Running...', 'pending');
		const payload = await postJson(config.restUrl, endpoint, serialize(form));
		renderStructuredResult(form, payload);
		return payload;
	}

	function setSiteKnowledgeButtonsBusy(root, busy) {
		root.querySelectorAll('[data-toolbox-site-knowledge-status]').forEach((button) => {
			button.disabled = busy;
			button.setAttribute('aria-busy', busy ? 'true' : 'false');
		});
	}

	function setSiteKnowledgeSyncBusy(form, busy) {
		const submitButton = form.querySelector('[data-toolbox-site-knowledge-sync-submit]') || form.querySelector('button[type="submit"]');
		if (!submitButton) {
			return;
		}
		if (!submitButton.__magickOriginalText) {
			submitButton.__magickOriginalText = submitButton.textContent;
		}
		submitButton.disabled = busy;
		submitButton.setAttribute('aria-busy', busy ? 'true' : 'false');
		submitButton.textContent = busy ? 'Sync queued...' : submitButton.__magickOriginalText;
	}

	function updateSiteKnowledgeActionState(root, payload) {
		const button = root.querySelector('[data-toolbox-site-knowledge-sync-submit]');
		if (!button) {
			return;
		}
		const modeInput = root.querySelector('[data-toolbox-site-knowledge-sync] input[name="sync_mode"]');
		const coverage = payload && payload.coverage && typeof payload.coverage === 'object' ? payload.coverage : {};
		const indexedChunks = Number(coverage.indexed_chunks || 0);
		const hasIndex = indexedChunks > 0;
		const active = siteKnowledgeStatusStillActive(payload);
		const startLabel = button.getAttribute('data-start-label') || 'Start indexing';
		const refreshLabel = button.getAttribute('data-refresh-label') || 'Refresh index';
		button.dataset.indexState = hasIndex ? 'ready' : 'empty';
		button.__magickOriginalText = hasIndex ? refreshLabel : startLabel;
		if (modeInput) {
			modeInput.value = hasIndex ? 'rebuild' : 'refresh';
		}
		button.disabled = active;
		button.setAttribute('aria-busy', active ? 'true' : 'false');
		button.textContent = active ? 'Indexing...' : button.__magickOriginalText;
	}

	function siteKnowledgeStatusStillActive(payload) {
		const status = String(payload && payload.status ? payload.status : '').toLowerCase();
		return status === 'queued' || status === 'running' || status === 'syncing';
	}

	async function pollSiteKnowledgeStatus(root, attempts) {
		let payload = null;
		for (let index = 0; index < attempts; index += 1) {
			payload = await refreshSiteKnowledgeStatus(root);
			if (!siteKnowledgeStatusStillActive(payload)) {
				return payload;
			}
			await new Promise((resolve) => {
				window.setTimeout(resolve, 2000);
			});
		}
		return payload;
	}

	function initSiteKnowledge() {
		document.querySelectorAll('[data-toolbox-site-knowledge]').forEach((root) => {
			const renderStatusError = (error) => {
				const summary = root.querySelector('[data-toolbox-site-knowledge-summary]');
				if (summary) {
					clearNode(summary);
					summary.appendChild(el('div', 'magick-ai-toolbox__result-notice is-error', error.message || 'Site knowledge status failed.'));
					summary.appendChild(createRawDetails(error, 'Status error'));
				}
			};
			root.querySelectorAll('[data-toolbox-site-knowledge-status]').forEach((statusButton) => {
				statusButton.addEventListener('click', async () => {
					setSiteKnowledgeButtonsBusy(root, true);
					try {
						await refreshSiteKnowledgeStatus(root);
					} catch (error) {
						renderStatusError(error);
					} finally {
						setSiteKnowledgeButtonsBusy(root, false);
					}
				});
			});

			const syncForm = root.querySelector('[data-toolbox-site-knowledge-sync]');
			if (syncForm) {
				syncForm.addEventListener('submit', async (event) => {
					event.preventDefault();
					let latestPayload = null;
					setSiteKnowledgeSyncBusy(syncForm, true);
					setSiteKnowledgeButtonsBusy(root, true);
					try {
						const payload = await runSiteKnowledgeForm(syncForm, 'site-knowledge/sync');
						if (siteKnowledgeStatusStillActive(payload)) {
							latestPayload = await pollSiteKnowledgeStatus(root, 60);
						} else {
							latestPayload = await refreshSiteKnowledgeStatus(root);
						}
					} catch (error) {
						renderTextResult(syncForm, error.message || 'Site knowledge sync failed.', 'error');
					} finally {
						setSiteKnowledgeButtonsBusy(root, false);
						setSiteKnowledgeSyncBusy(syncForm, false);
						if (latestPayload) {
							updateSiteKnowledgeActionState(root, latestPayload);
						}
					}
				});
			}

			const searchForm = root.querySelector('[data-toolbox-site-knowledge-search]');
			if (searchForm) {
				searchForm.addEventListener('submit', async (event) => {
					event.preventDefault();
					try {
						await runSiteKnowledgeForm(searchForm, 'site-knowledge/search');
					} catch (error) {
						renderTextResult(searchForm, error.message || 'Site knowledge search failed.', 'error');
					}
				});
			}
		});
	}

	function refreshAllSiteKnowledgeStatus() {
		document.querySelectorAll('[data-toolbox-site-knowledge]').forEach((root) => {
			const summary = root.querySelector('[data-toolbox-site-knowledge-summary]');
			refreshSiteKnowledgeStatus(root).catch((error) => {
				if (summary) {
					clearNode(summary);
					summary.appendChild(el('div', 'magick-ai-toolbox__result-notice is-error', error.message || 'Site knowledge status failed.'));
					summary.appendChild(createRawDetails(error, 'Status error'));
				}
			});
		});
	}

	async function submitMediaReferenceRepairProposal(form) {
		if (!config.adapterRestUrl) {
			throw { message: 'Magick AI Adapter REST URL is unavailable.' };
		}

		const input = referenceRepairInput(form);
		if (!input.attachment_id) {
			throw { message: 'Select or enter an image attachment before building a URL repair proposal.' };
		}

		renderTextResult(form, 'Building media URL repair plan...', 'pending');
		const planEnvelope = await postJson(config.adapterRestUrl, 'run-read-ability', {
			ability_id: 'magick-ai/build-media-reference-repair-plan',
			input,
		});
		const plan = planDataFromEnvelope(planEnvelope) || {};
		const actionCount = Number(plan.action_count || (Array.isArray(plan.write_actions) ? plan.write_actions.length : 0));
		if (actionCount <= 0) {
			const result = renderShell(
				form,
				{ provider: 'core governance' },
				'No exact URL repairs found',
				'No proposal was submitted. Sized image variants and ambiguous references remain review-only.'
			);
			if (result) {
				if (Array.isArray(plan.manual_review) && plan.manual_review.length) {
					result.appendChild(el('div', 'magick-ai-toolbox__result-notice is-warning', 'Manual review found references that are not safe for exact automatic replacement.'));
				}
				result.appendChild(createRawDetails(planEnvelope, 'Reference repair plan'));
			}
			return;
		}

		renderTextResult(form, 'Submitting URL repair proposal...', 'pending');
		const bridge = await postJson(config.adapterRestUrl, 'proposals/from-plan', {
			plan_ability_id: 'magick-ai/build-media-reference-repair-plan',
			plan,
			plan_input: input,
		});
		renderProposalCreated(form, proposalFromPlanResponse(bridge), {
			title: 'URL repair proposal submitted',
			summary: 'Exact hard-coded media URLs are now in Core review as patch-post-content actions. WordPress writes still require Core approval and preflight.',
			rawTitle: 'Core plan-to-proposal response',
		});
	}

	async function submitMediaSettingsReferenceRepairProposal(form) {
		if (!config.adapterRestUrl) {
			throw { message: 'Magick AI Adapter REST URL is unavailable.' };
		}

		const input = settingsReferenceRepairInput(form);
		if (!input.attachment_id) {
			throw { message: 'Select or enter an image attachment before building a settings URL repair proposal.' };
		}

		renderTextResult(form, 'Building settings URL repair plan...', 'pending');
		const planEnvelope = await postJson(config.adapterRestUrl, 'run-read-ability', {
			ability_id: 'magick-ai/build-media-settings-reference-repair-plan',
			input,
		});
		const plan = planDataFromEnvelope(planEnvelope) || {};
		const actionCount = Number(plan.action_count || (Array.isArray(plan.write_actions) ? plan.write_actions.length : 0));
		if (actionCount <= 0) {
			const result = renderShell(
				form,
				{ provider: 'core governance' },
				'No exact settings URL repairs found',
				'No proposal was submitted. Excluded formats, small images, serialized values, sized variants, and ambiguous references remain review-only.'
			);
			if (result) {
				if (Array.isArray(plan.manual_review) && plan.manual_review.length) {
					result.appendChild(el('div', 'magick-ai-toolbox__result-notice is-warning', 'Manual review found setting references that are not safe for exact automatic replacement.'));
				}
				result.appendChild(createRawDetails(planEnvelope, 'Settings reference repair plan'));
			}
			return;
		}

		renderTextResult(form, 'Submitting settings URL repair proposal...', 'pending');
		const bridge = await postJson(config.adapterRestUrl, 'proposals/from-plan', {
			plan_ability_id: 'magick-ai/build-media-settings-reference-repair-plan',
			plan,
			plan_input: input,
		});
		renderProposalCreated(form, proposalFromPlanResponse(bridge), {
			title: 'Settings URL repair proposal submitted',
			summary: 'Exact hard-coded media URLs in settings are now in Core review as patch-setting-value actions. WordPress writes still require Core approval and preflight.',
			rawTitle: 'Core plan-to-proposal response',
		});
	}

	function activateTarget(container, buttonSelector, panelSelector, targetAttribute, panelAttribute, target) {
		const buttons = container.querySelectorAll(buttonSelector);
		const panelRoot = container.matches('[data-toolbox-tabs]') ? (container.closest('.magick-ai-toolbox') || document) : container;
		const panels = panelRoot.querySelectorAll(panelSelector);

		buttons.forEach((button) => {
			const active = button.getAttribute(targetAttribute) === target;
			button.classList.toggle('is-active', active);
			button.setAttribute('aria-selected', active ? 'true' : 'false');
		});

		panels.forEach((panel) => {
			panel.hidden = panel.getAttribute(panelAttribute) !== target;
		});
	}

	function hasTarget(container, selector, attribute, target) {
		let found = false;
		container.querySelectorAll(selector).forEach((node) => {
			if (!found && node.getAttribute(attribute) === target) {
				found = true;
			}
		});
		return found;
	}

	function activeTarget(container, selector, attribute) {
		const active = container.querySelector(selector + '.is-active');
		return active ? active.getAttribute(attribute) : '';
	}

	function updateToolboxUrl(values) {
		if (!window.history || typeof window.history.replaceState !== 'function') {
			return;
		}

		const url = new URL(window.location.href);
		Object.keys(values).forEach((key) => {
			const value = values[key];
			if (value === null || value === undefined || value === '') {
				url.searchParams.delete(key);
				return;
			}
			url.searchParams.set(key, value);
		});
		window.history.replaceState({}, '', url.toString());
	}

	function activeCloudCheckGroup() {
		const panel = document.querySelector('[data-toolbox-cloud-check-panel]:not([hidden])');
		if (!panel) {
			return '';
		}
		return activeTarget(panel, '[data-toolbox-cloud-check-group-target]', 'data-toolbox-cloud-check-group-target');
	}

	function updateUrlForTopTab(target) {
		if (target === 'tools') {
			updateToolboxUrl({
				toolbox_tab: 'tools',
				toolbox_tool: activeTarget(document, '[data-toolbox-tool-target]', 'data-toolbox-tool-target'),
				toolbox_cloud_check: null,
				toolbox_cloud_check_group: null,
			});
			return;
		}

		if (target === 'cloud-checks') {
			updateToolboxUrl({
				toolbox_tab: 'cloud-checks',
				toolbox_tool: null,
				toolbox_cloud_check: activeTarget(document, '[data-toolbox-cloud-check-target]', 'data-toolbox-cloud-check-target'),
				toolbox_cloud_check_group: activeCloudCheckGroup(),
			});
			return;
		}

		updateToolboxUrl({
			toolbox_tab: target,
			toolbox_tool: null,
			toolbox_cloud_check: null,
			toolbox_cloud_check_group: null,
		});
	}

	function activateTopTab(target, updateUrl) {
		const tabs = document.querySelector('[data-toolbox-tabs]');
		if (!tabs || !hasTarget(tabs, '[data-toolbox-tab-target]', 'data-toolbox-tab-target', target)) {
			return false;
		}

		activateTarget(
			tabs,
			'[data-toolbox-tab-target]',
			'[data-toolbox-tab-panel]',
			'data-toolbox-tab-target',
			'data-toolbox-tab-panel',
			target
		);

		if (updateUrl) {
			updateUrlForTopTab(target);
		}
		if (target === 'site-knowledge') {
			refreshAllSiteKnowledgeStatus();
		}
		return true;
	}

	function activateToolPanel(target, updateUrl) {
		const workspace = document.querySelector('[data-toolbox-tools]');
		if (!workspace || !hasTarget(workspace, '[data-toolbox-tool-target]', 'data-toolbox-tool-target', target)) {
			return false;
		}

		activateTarget(
			workspace,
			'[data-toolbox-tool-target]',
			'[data-toolbox-tool-panel]',
			'data-toolbox-tool-target',
			'data-toolbox-tool-panel',
			target
		);

		if (updateUrl) {
			updateToolboxUrl({
				toolbox_tab: 'tools',
				toolbox_tool: target,
				toolbox_cloud_check: null,
				toolbox_cloud_check_group: null,
			});
		}
		return true;
	}

	function activateCloudCheckPanel(target, updateUrl) {
		const workspace = document.querySelector('[data-toolbox-cloud-checks]');
		if (!workspace || !hasTarget(workspace, '[data-toolbox-cloud-check-target]', 'data-toolbox-cloud-check-target', target)) {
			return false;
		}

		activateTarget(
			workspace,
			'[data-toolbox-cloud-check-target]',
			'[data-toolbox-cloud-check-panel]',
			'data-toolbox-cloud-check-target',
			'data-toolbox-cloud-check-panel',
			target
		);

		if (updateUrl) {
			updateToolboxUrl({
				toolbox_tab: 'cloud-checks',
				toolbox_tool: null,
				toolbox_cloud_check: target,
				toolbox_cloud_check_group: activeCloudCheckGroup(),
			});
		}
		return true;
	}

	function initUrlState() {
		const params = new URL(window.location.href).searchParams;
		const requestedTab = params.get('toolbox_tab') || '';
		const requestedTool = params.get('toolbox_tool') || '';
		const requestedCloudCheck = params.get('toolbox_cloud_check') || '';
		const requestedCloudCheckGroup = params.get('toolbox_cloud_check_group') || '';
		let tab = requestedTab;

		if (!tab) {
			if (requestedTool && hasTarget(document, '[data-toolbox-tool-target]', 'data-toolbox-tool-target', requestedTool)) {
				tab = 'tools';
			} else if (requestedCloudCheck && hasTarget(document, '[data-toolbox-cloud-check-target]', 'data-toolbox-cloud-check-target', requestedCloudCheck)) {
				tab = 'cloud-checks';
			}
		}

		if (tab) {
			activateTopTab(tab, false);
		}
		if (tab === 'tools' && requestedTool) {
			activateToolPanel(requestedTool, false);
		}
		if (tab === 'cloud-checks' && requestedCloudCheck) {
			activateCloudCheckPanel(requestedCloudCheck, false);
		}
		if (tab === 'cloud-checks' && requestedCloudCheckGroup) {
			const panel = document.querySelector('[data-toolbox-cloud-check-panel]:not([hidden])');
			const workspace = panel ? panel.querySelector('[data-toolbox-cloud-check-groups]') : null;
			activateCloudCheckGroup(workspace, requestedCloudCheckGroup, false);
		}
	}

	function initTopTabs() {
		document.querySelectorAll('[data-toolbox-tabs]').forEach((tabs) => {
			tabs.addEventListener('click', (event) => {
				if (!(event.target instanceof Element)) {
					return;
				}

				const button = event.target.closest('[data-toolbox-tab-target]');
				if (!button || !tabs.contains(button)) {
					return;
				}

				activateTopTab(button.getAttribute('data-toolbox-tab-target'), true);
			});
		});
	}

	function initToolSwitcher() {
		document.querySelectorAll('[data-toolbox-tools]').forEach((workspace) => {
			workspace.addEventListener('click', (event) => {
				if (!(event.target instanceof Element)) {
					return;
				}

				const button = event.target.closest('[data-toolbox-tool-target]');
				if (!button || !workspace.contains(button)) {
					return;
				}

				activateToolPanel(button.getAttribute('data-toolbox-tool-target'), true);
			});
		});
	}

	function initCloudCheckSwitcher() {
		document.querySelectorAll('[data-toolbox-cloud-checks]').forEach((workspace) => {
			workspace.addEventListener('click', (event) => {
				if (!(event.target instanceof Element)) {
					return;
				}

				const button = event.target.closest('[data-toolbox-cloud-check-target]');
				if (!button || !workspace.contains(button)) {
					return;
				}

				activateCloudCheckPanel(button.getAttribute('data-toolbox-cloud-check-target'), true);
			});
		});
	}

	function activateCloudCheckGroup(workspace, target, updateUrl) {
		if (!workspace || !hasTarget(workspace, '[data-toolbox-cloud-check-group-target]', 'data-toolbox-cloud-check-group-target', target)) {
			return false;
		}

		activateTarget(
			workspace,
			'[data-toolbox-cloud-check-group-target]',
			'[data-toolbox-cloud-check-group-panel]',
			'data-toolbox-cloud-check-group-target',
			'data-toolbox-cloud-check-group-panel',
			target
		);
		if (updateUrl) {
			updateToolboxUrl({
				toolbox_tab: 'cloud-checks',
				toolbox_tool: null,
				toolbox_cloud_check: activeTarget(document, '[data-toolbox-cloud-check-target]', 'data-toolbox-cloud-check-target'),
				toolbox_cloud_check_group: target,
			});
		}
		return true;
	}

	function initCloudCheckGroupSwitcher() {
		document.querySelectorAll('[data-toolbox-cloud-check-groups]').forEach((workspace) => {
			workspace.addEventListener('click', (event) => {
				if (!(event.target instanceof Element)) {
					return;
				}

				const button = event.target.closest('[data-toolbox-cloud-check-group-target]');
				if (!button || !workspace.contains(button)) {
					return;
				}

				activateCloudCheckGroup(workspace, button.getAttribute('data-toolbox-cloud-check-group-target'), true);
			});
		});
	}

	function activateContextSection(target) {
		const workspace = document.querySelector('[data-toolbox-context-sections]');
		if (!workspace || !hasTarget(workspace, '[data-toolbox-context-target]', 'data-toolbox-context-target', target)) {
			return false;
		}

		activateTarget(
			workspace,
			'[data-toolbox-context-target]',
			'[data-toolbox-context-panel]',
			'data-toolbox-context-target',
			'data-toolbox-context-panel',
			target
		);
		return true;
	}

	function initContextSectionSwitcher() {
		document.querySelectorAll('[data-toolbox-context-sections]').forEach((workspace) => {
			workspace.addEventListener('click', (event) => {
				if (!(event.target instanceof Element)) {
					return;
				}

				const button = event.target.closest('[data-toolbox-context-target]');
				if (!button || !workspace.contains(button)) {
					return;
				}

				activateContextSection(button.getAttribute('data-toolbox-context-target'));
			});
		});
	}

	function activateContextGroup(workspace, target) {
		if (!workspace || !hasTarget(workspace, '[data-toolbox-context-group-target]', 'data-toolbox-context-group-target', target)) {
			return false;
		}

		activateTarget(
			workspace,
			'[data-toolbox-context-group-target]',
			'[data-toolbox-context-group-panel]',
			'data-toolbox-context-group-target',
			'data-toolbox-context-group-panel',
			target
		);
		return true;
	}

	function initContextGroupSwitcher() {
		document.querySelectorAll('[data-toolbox-context-groups]').forEach((workspace) => {
			workspace.addEventListener('click', (event) => {
				if (!(event.target instanceof Element)) {
					return;
				}

				const button = event.target.closest('[data-toolbox-context-group-target]');
				if (!button || !workspace.contains(button)) {
					return;
				}

				activateContextGroup(workspace, button.getAttribute('data-toolbox-context-group-target'));
			});
		});
	}

	function setContextField(form, key, value) {
		const option = config.contextOption || 'magick_ai_toolbox_content_context';
		const fieldName = option + '[' + key + ']';
		let field = null;
		form.querySelectorAll('[name]').forEach((candidate) => {
			if (!field && candidate.getAttribute('name') === fieldName) {
				field = candidate;
			}
		});
		if (!field) {
			return;
		}

		if (field instanceof HTMLInputElement && field.type === 'checkbox') {
			field.checked = Boolean(value);
			return;
		}

		field.value = Array.isArray(value) ? value.join('\n') : (value || '');
	}

	function setProposalFields(form, fields) {
		const option = config.contextOption || 'magick_ai_toolbox_content_context';
		const fieldName = option + '[proposal_allowed_fields][]';
		const allowed = Array.isArray(fields) ? fields : [];

		form.querySelectorAll('[name]').forEach((field) => {
			if (field instanceof HTMLInputElement && field.type === 'checkbox') {
				if (field.getAttribute('name') === fieldName) {
					field.checked = allowed.includes(field.value);
				}
			}
		});
	}

	function applyContextDraft(form, draft) {
		if (!draft) {
			return;
		}

		Object.keys(draft).forEach((key) => {
			if (key === 'proposal_allowed_fields') {
				setProposalFields(form, draft[key]);
				return;
			}

			setContextField(form, key, draft[key]);
		});

		form.querySelectorAll('.magick-ai-toolbox__disclosure').forEach((details) => {
			if (details instanceof HTMLDetailsElement) {
				details.open = true;
			}
		});
	}

	function clearContextForm(form) {
		form.querySelectorAll('textarea').forEach((field) => {
			field.value = '';
		});

		form.querySelectorAll('input[type="checkbox"]').forEach((field) => {
			field.checked = false;
		});
	}

	function initContextDrafts() {
		document.querySelectorAll('[data-toolbox-context-form]').forEach((form) => {
			form.addEventListener('click', (event) => {
				if (!(event.target instanceof Element)) {
					return;
				}

				const draftButton = event.target.closest('[data-toolbox-context-draft]');
				if (draftButton && form.contains(draftButton)) {
					const draftKey = draftButton.getAttribute('data-toolbox-context-draft');
					applyContextDraft(form, config.contextDrafts && config.contextDrafts[draftKey]);
					return;
				}

				const clearButton = event.target.closest('[data-toolbox-context-clear]');
				if (clearButton && form.contains(clearButton)) {
					clearContextForm(form);
				}
			});
		});
	}

	function renderSelectedMedia(form, attachment) {
		const preview = form.querySelector('[data-toolbox-media-preview]');
		const name = form.querySelector('[data-toolbox-media-name]');
		const idField = form.querySelector('[data-toolbox-media-attachment]');
		const repairButton = form.querySelector('[data-toolbox-submit-reference-repair]');
		const settingsRepairButton = form.querySelector('[data-toolbox-submit-settings-repair]');
		if (idField instanceof HTMLInputElement && attachment && attachment.id) {
			idField.value = String(attachment.id);
		}
		if (repairButton instanceof HTMLButtonElement) {
			repairButton.disabled = mediaAttachmentId(form) <= 0;
		}
		if (settingsRepairButton instanceof HTMLButtonElement) {
			settingsRepairButton.disabled = mediaAttachmentId(form) <= 0;
		}
		if (name) {
			name.textContent = attachment && attachment.filename ? attachment.filename : 'Selected attachment #' + (attachment && attachment.id ? attachment.id : '');
		}
		if (!preview) {
			return;
		}
		clearNode(preview);
		const url = attachment && attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment && attachment.url;
		if (url) {
			const image = el('img', 'magick-ai-toolbox__media-thumb');
			image.src = url;
			image.alt = attachment && attachment.alt ? attachment.alt : '';
			preview.appendChild(image);
			return;
		}
		preview.appendChild(el('span', '', 'Attachment selected'));
	}

	function initMediaDerivativeControls() {
		document.querySelectorAll('[data-toolbox-media-derivative]').forEach((form) => {
			const idField = form.querySelector('[data-toolbox-media-attachment]');
			const repairButton = form.querySelector('[data-toolbox-submit-reference-repair]');
			const settingsRepairButton = form.querySelector('[data-toolbox-submit-settings-repair]');
			if (idField instanceof HTMLInputElement) {
				idField.addEventListener('input', () => {
					if (repairButton instanceof HTMLButtonElement) {
						repairButton.disabled = mediaAttachmentId(form) <= 0;
					}
					if (settingsRepairButton instanceof HTMLButtonElement) {
						settingsRepairButton.disabled = mediaAttachmentId(form) <= 0;
					}
				});
			}

			form.addEventListener('click', (event) => {
				if (!(event.target instanceof Element)) {
					return;
				}

				const selectButton = event.target.closest('[data-toolbox-select-media]');
				if (selectButton && form.contains(selectButton)) {
					event.preventDefault();
					if (!window.wp || !window.wp.media) {
						renderTextResult(form, 'WordPress media picker is unavailable on this page.', 'error');
						return;
					}
					const frame = window.wp.media({
						title: 'Select image',
						button: { text: 'Use image' },
						library: { type: 'image' },
						multiple: false,
					});
					frame.on('select', () => {
						const attachment = frame.state().get('selection').first();
						renderSelectedMedia(form, attachment ? attachment.toJSON() : null);
					});
					frame.open();
					return;
				}

				const resolveButton = event.target.closest('[data-toolbox-resolve-media-url]');
				if (resolveButton && form.contains(resolveButton)) {
					event.preventDefault();
					resolveMediaAttachmentUrl(form).catch((error) => {
						renderTextResult(form, error && error.message ? error.message : (config.labels && config.labels.error ? config.labels.error : 'Request failed.'), 'error');
					});
					return;
				}

				const resolutionCandidateButton = event.target.closest('[data-toolbox-use-media-resolution-candidate]');
				if (resolutionCandidateButton && form.contains(resolutionCandidateButton)) {
					event.preventDefault();
					const row = resolutionCandidateButton.closest('[data-toolbox-media-resolution-candidate]');
					const candidate = row && row.__magickMediaResolutionCandidate ? row.__magickMediaResolutionCandidate : {
						attachment_id: resolutionCandidateButton.getAttribute('data-toolbox-use-media-resolution-candidate') || '',
					};
					renderSelectedMedia(form, mediaResolutionCandidateAttachment(candidate));
					renderTextResult(form, 'Attachment #' + String(candidate.attachment_id || '') + ' selected. Generate a preview to continue.', 'ok');
					return;
				}

				const runButton = event.target.closest('[data-toolbox-run-media-derivative]');
				if (runButton && form.contains(runButton)) {
					event.preventDefault();
					runMediaDerivative(form).catch((error) => {
						renderTextResult(form, error && error.message ? error.message : (config.labels && config.labels.error ? config.labels.error : 'Request failed.'), 'error');
					});
					return;
				}

				const batchPlanButton = event.target.closest('[data-toolbox-build-media-batch-plan]');
				if (batchPlanButton && form.contains(batchPlanButton)) {
					event.preventDefault();
					buildMediaDerivativeBatchPlan(form).catch((error) => {
						renderTextResult(form, error && error.message ? error.message : (config.labels && config.labels.error ? config.labels.error : 'Request failed.'), 'error');
					});
					return;
				}

				const batchPreviewButton = event.target.closest('[data-toolbox-run-media-batch-previews]');
				if (batchPreviewButton && form.contains(batchPreviewButton)) {
					event.preventDefault();
					runMediaDerivativeBatchPreviews(form).catch((error) => {
						renderTextResult(form, error && error.message ? error.message : (config.labels && config.labels.error ? config.labels.error : 'Request failed.'), 'error');
					});
					return;
				}

				const batchProposalButton = event.target.closest('[data-toolbox-submit-media-batch-proposals]');
				if (batchProposalButton && form.contains(batchProposalButton)) {
					event.preventDefault();
					submitMediaDerivativeBatchProposals(form).catch((error) => {
						renderTextResult(form, error && error.message ? error.message : (config.labels && config.labels.error ? config.labels.error : 'Request failed.'), 'error');
					});
					return;
				}

				const proposalButton = event.target.closest('[data-toolbox-submit-media-proposal]');
				if (proposalButton && form.contains(proposalButton)) {
					event.preventDefault();
					submitMediaDerivativeProposal(form).catch((error) => {
						renderTextResult(form, error && error.message ? error.message : (config.labels && config.labels.error ? config.labels.error : 'Request failed.'), 'error');
					});
					return;
				}

				const repairButton = event.target.closest('[data-toolbox-submit-reference-repair]');
				if (repairButton && form.contains(repairButton)) {
					event.preventDefault();
					submitMediaReferenceRepairProposal(form).catch((error) => {
						renderTextResult(form, error && error.message ? error.message : (config.labels && config.labels.error ? config.labels.error : 'Request failed.'), 'error');
					});
					return;
				}

				const settingsRepairButton = event.target.closest('[data-toolbox-submit-settings-repair]');
				if (settingsRepairButton && form.contains(settingsRepairButton)) {
					event.preventDefault();
					submitMediaSettingsReferenceRepairProposal(form).catch((error) => {
						renderTextResult(form, error && error.message ? error.message : (config.labels && config.labels.error ? config.labels.error : 'Request failed.'), 'error');
					});
				}
			});
		});
	}

	initTopTabs();
	initToolSwitcher();
	initCloudCheckSwitcher();
	initCloudCheckGroupSwitcher();
	initContextSectionSwitcher();
	initContextGroupSwitcher();
	initContextDrafts();
	initSiteKnowledge();
	initMediaDerivativeControls();
	initUrlState();

	document.addEventListener('submit', function (event) {
		const form = event.target;
		if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-toolbox-endpoint')) {
			return;
		}

		event.preventDefault();
		runTool(form).catch((error) => {
			if (renderOperatorFeedback(form, error)) {
				return;
			}
			renderErrorResult(form, error, config.labels && config.labels.error ? config.labels.error : 'Request failed.');
		});
	});
}());
