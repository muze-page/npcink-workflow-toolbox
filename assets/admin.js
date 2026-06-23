(function () {
	'use strict';

	const config = window.NpcinkToolbox || {};
	const i18n = window.wp && window.wp.i18n ? window.wp.i18n : {};
	const __ = typeof i18n.__ === 'function' ? i18n.__ : (text) => text;
	const NIGHTLY_CLOUD_RECENT_KEY = 'npcinkToolboxNightlyCloudRecentRun.v1';

	function t(text) {
		return __(String(text), 'npcink-toolbox');
	}

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
			node.textContent = t(text);
		}
		return node;
	}

	function appendMeta(container, label, value) {
		if (value === undefined || value === null || value === '') {
			return;
		}

		const item = el('span', 'npcink-toolbox__result-meta-item');
		item.appendChild(el('span', 'npcink-toolbox__result-meta-label', label));
		item.appendChild(el('span', 'npcink-toolbox__result-meta-value', value));
		container.appendChild(item);
	}

	function appendPositiveMeta(container, label, value) {
		const numeric = Number(value);
		if (!Number.isFinite(numeric) || numeric <= 0) {
			return;
		}
		appendMeta(container, label, numeric);
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

		const config = window.NpcinkToolbox && window.NpcinkToolbox.dateTime ? window.NpcinkToolbox.dateTime : {};
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

	function localizedLabel(value) {
		const label = formatLabel(value);
		return label ? t(label) : '';
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
			const text = normalizeRuntimeErrorText(String(value).trim());
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
			messages.push(t('Code: ') + String(value.code));
		}
		if (value.status && typeof value.status !== 'object') {
			messages.push(t('Status: ') + String(value.status));
		}
		collectErrorText(value.errors, messages, seen);
		if (value.data && typeof value.data === 'object') {
			['message', 'error', 'error_message', 'detail', 'status'].forEach((key) => {
				collectErrorText(value.data[key], messages, seen);
			});
		}
	}

	function normalizeRuntimeErrorText(text) {
		if (text.toLowerCase().indexOf('runtime quota') >= 0 && text.toLowerCase().indexOf('exhausted') >= 0) {
			return t('Cloud runtime quota is exhausted for this request. Check Cloud quota or billing limits, then retry.');
		}
		const profileTextInputMatch = text.match(/^profile '([^']+)' expects 'text', received ''$/);
		if (profileTextInputMatch) {
			return t('Cloud runtime profile expects text input but received an empty value. Check the Cloud route/profile input mapping.') + ' (' + profileTextInputMatch[1] + ')';
		}

		return text;
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
		return text && text !== 'Array' ? text : t(fallback || 'Request failed.');
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
			return t('Image/logo watermarks need a configured Toolbox logo source. Switch this run to Text watermark, or configure the Toolbox media watermark logo before retrying.');
		}
		if (errorContainsCode(error, 'cloud_media_derivative_text_watermark_source_unexpected', new WeakSet())) {
			return t('Text watermarks should not include a logo artifact or upload. Retry with Text watermark fields only.');
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
		url.searchParams.set('page', 'npcink-toolbox');
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
		const section = el('section', 'npcink-toolbox__result-section');
		section.appendChild(el('h3', '', title));
		return section;
	}

	function createRawDetails(payload, title) {
		const details = el('details', 'npcink-toolbox__result-details');
		details.appendChild(el('summary', '', title || 'Complete payload'));
		const pre = el('pre', 'npcink-toolbox__result-raw');
		pre.textContent = JSON.stringify(payload, null, 2);
		details.appendChild(pre);
		return details;
	}

	function providerLabel(payload) {
		if (payload && payload.provider_label) {
			return formatLabel(payload.provider_label);
		}

		if (!payload || !payload.provider) {
			return 'Toolbox';
		}

		return formatLabel(payload.provider);
	}

	function renderShell(form, payload, title, summary) {
		const result = form.querySelector('.npcink-toolbox__result');
		if (!result) {
			return null;
		}

		result.hidden = false;
		result.classList.remove('is-empty');
		clearNode(result);

		const summaryNode = el('div', 'npcink-toolbox__result-summary');
		summaryNode.appendChild(el('div', 'npcink-toolbox__result-kicker', providerLabel(payload)));
		summaryNode.appendChild(el('h3', '', title));
		summaryNode.appendChild(el('p', '', summary));
		result.appendChild(summaryNode);

		const meta = el('div', 'npcink-toolbox__result-meta');
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
		const result = form.querySelector('.npcink-toolbox__result');
		if (!result) {
			return;
		}

		result.hidden = false;
		result.classList.remove('is-empty');
		clearNode(result);
		const notice = el('div', 'npcink-toolbox__result-notice ' + (kind ? 'is-' + kind : ''));
		notice.textContent = stringifyDisplayValue(value);
		result.appendChild(notice);
	}

	function renderCoreHandoffError(form, error, fallback, options) {
		options = options || {};
		const result = renderShell(
			form,
			{ provider: 'core governance' },
			options.title || 'Core handoff failed',
			formatErrorMessage(error, fallback || 'Could not submit the Core handoff.')
		);
		if (!result) {
			return;
		}
		const receipt = coreHandoffReceipt(error, Object.assign({}, options.receiptContext || {}, {
			status: 'handoff_failed',
			operatorNextAction: 'review_adapter_core_error',
		}));
		const receiptNode = renderCoreHandoffReceipt(receipt);
		if (receiptNode) {
			result.appendChild(receiptNode);
		}
		const feedback = extractOperatorFeedback(error);
		if (feedback) {
			const meta = el('div', 'npcink-toolbox__result-meta');
			appendMeta(meta, 'Feedback status', feedback.status ? formatLabel(feedback.status) : '');
			appendMeta(meta, 'Severity', feedback.severity ? formatLabel(feedback.severity) : '');
			appendMeta(meta, 'Retry after revision', feedback.can_retry_after_revision === true ? 'Yes' : 'No');
			if (meta.childNodes.length) {
				result.appendChild(meta);
			}
			if (Array.isArray(feedback.next_steps) && feedback.next_steps.length) {
				const section = createSection('Next steps');
				const list = el('ol', 'npcink-toolbox__step-list');
				feedback.next_steps.forEach((step) => {
					list.appendChild(el('li', '', step));
				});
				section.appendChild(list);
				result.appendChild(section);
			}
		}
		if (error && typeof error === 'object') {
			result.appendChild(createRawDetails(error, options.rawTitle || 'Core handoff error payload'));
		}
	}

	function renderErrorResult(form, error, fallback, options) {
		if (options && options.receiptContext) {
			renderCoreHandoffError(form, error, fallback, options);
			return;
		}
		const result = form.querySelector('.npcink-toolbox__result');
		if (!result) {
			return;
		}

		result.hidden = false;
		result.classList.remove('is-empty');
		clearNode(result);
		result.appendChild(el('div', 'npcink-toolbox__result-notice is-error', formatErrorMessage(error, fallback)));
		if (error && typeof error === 'object') {
			result.appendChild(createRawDetails(error, 'Error payload'));
		}
	}

	function renderCoreHandoffStatusError(statusNode, error, fallback, receiptContext, rawTitle) {
		if (!statusNode) {
			return;
		}
		statusNode.className = 'npcink-toolbox__result-notice is-error';
		clearNode(statusNode);
		statusNode.appendChild(el('strong', '', 'Core handoff failed'));
		statusNode.appendChild(el('span', '', formatErrorMessage(error, fallback || 'Could not submit the Core handoff.')));
		const receiptNode = renderCoreHandoffReceipt(coreHandoffReceipt(error, Object.assign({}, receiptContext || {}, {
			status: 'handoff_failed',
			operatorNextAction: 'review_adapter_core_error',
		})));
		if (receiptNode) {
			statusNode.appendChild(receiptNode);
		}
		if (error && typeof error === 'object') {
			statusNode.appendChild(createRawDetails(error, rawTitle || 'Core handoff error payload'));
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

		const meta = el('div', 'npcink-toolbox__result-meta');
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
			const list = el('ul', 'npcink-toolbox__step-list');
			feedback.reasons.forEach((reason) => {
				list.appendChild(el('li', '', reason));
			});
			section.appendChild(list);
			result.appendChild(section);
		}

		if (Array.isArray(feedback.revision_fields) && feedback.revision_fields.length) {
			result.appendChild(el('div', 'npcink-toolbox__result-notice is-warning', t('Revise fields: ') + feedback.revision_fields.join(', ')));
		}

		if (Array.isArray(feedback.next_steps) && feedback.next_steps.length) {
			const section = createSection('Next steps');
			const list = el('ol', 'npcink-toolbox__step-list');
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
		const list = el('div', 'npcink-toolbox__result-list');
		results.forEach((item) => {
			const row = el('article', 'npcink-toolbox__result-item');
			const title = el('h4', '', item.title || item.url || 'Source');
			row.appendChild(title);
			if (item.url) {
				row.appendChild(createLink(item.url, item.url));
			}
			if (item.content) {
				row.appendChild(el('p', '', truncate(item.content, 260)));
			}
			const meta = el('div', 'npcink-toolbox__result-meta');
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
		const list = el('div', 'npcink-toolbox__image-list');
		images.forEach((image) => {
			const row = el('article', 'npcink-toolbox__image-item');
			const previewUrl = image.thumbnail_url || image.thumb_url || image.small_url || image.download_url || image.regular_url;
			if (previewUrl) {
				const preview = el('img', 'npcink-toolbox__image-thumb');
				preview.src = previewUrl;
				preview.alt = image.alt_description || image.description || '';
				preview.loading = 'lazy';
				row.appendChild(preview);
			}

			const body = el('div', 'npcink-toolbox__image-body');
			body.appendChild(el('h4', '', image.title || image.alt_description || image.description || image.id || 'Image candidate'));
			if (image.attribution) {
				body.appendChild(el('p', '', image.attribution));
			}
			const links = el('div', 'npcink-toolbox__result-actions');
			if (image.html_url) {
				links.appendChild(createLink(image.html_url, t('Open on ') + formatLabel(image.provider || 'source')));
			}
			if (image.photographer_url) {
				links.appendChild(createLink(image.photographer_url, 'Photographer'));
			}
			if (links.childNodes.length) {
				body.appendChild(links);
			}
			const meta = el('div', 'npcink-toolbox__result-meta');
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
				body.appendChild(el('div', 'npcink-toolbox__result-notice is-warning', 'License or source review is required before Core approval.'));
			}
			if (image.download_location || image.suggested_filename || image.filename_basis) {
				const details = el('details', 'npcink-toolbox__result-details');
				details.appendChild(el('summary', '', 'Attribution metadata'));
				const pre = el('pre', 'npcink-toolbox__result-raw');
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

	function aiGenerationHandoff(payload) {
		if (!payload || typeof payload !== 'object') {
			return null;
		}
		if (payload.ai_generation_handoff && typeof payload.ai_generation_handoff === 'object') {
			return payload.ai_generation_handoff;
		}
		if (payload.handoff && payload.handoff.ai_generation_handoff && typeof payload.handoff.ai_generation_handoff === 'object') {
			return payload.handoff.ai_generation_handoff;
		}
		return null;
	}

	function defaultAiImagePrompt(payload, handoff) {
		const brief = payload && payload.visual_brief && typeof payload.visual_brief === 'object' ? payload.visual_brief : {};
		const plan = handoff && handoff.prompt_prefill_plan && typeof handoff.prompt_prefill_plan === 'object' ? handoff.prompt_prefill_plan : {};
		const fields = Array.isArray(plan.local_prompt_fields) ? plan.local_prompt_fields : [];
		const subject = brief.visual_intent || payload.optimized_query || payload.query || '';
		const style = brief.style || (fields.indexOf('style') >= 0 ? 'editorial, natural light, high quality' : '');
		const composition = brief.preferred_orientation ? 'Composition: ' + formatLabel(brief.preferred_orientation) + ' image suitable for a WordPress article.' : 'Composition: image suitable for a WordPress article.';
		const constraints = 'Avoid visible text, brand logos, watermarks, distorted hands or faces, and copyrighted characters.';
		return [
			'Create an original image for: ' + subject,
			composition,
			style ? 'Style: ' + style : '',
			constraints
		].filter(Boolean).join('\n');
	}

	function aiGenerationAspectRatio(payload, handoff) {
		const defaults = handoff && handoff.input_defaults && typeof handoff.input_defaults === 'object' ? handoff.input_defaults : {};
		const ratio = String(defaults.aspect_ratio || '').trim();
		if (ratio) {
			return ratio;
		}
		const brief = payload && payload.visual_brief && typeof payload.visual_brief === 'object' ? payload.visual_brief : {};
		if (brief.preferred_orientation === 'portrait') {
			return '3:4';
		}
		if (brief.preferred_orientation === 'squarish') {
			return '1:1';
		}
		return '16:9';
	}

	function appendAiGenerationResult(container, payload) {
		const existing = container.querySelector('[data-toolbox-ai-generation-result]');
		if (existing) {
			existing.remove();
		}

		const section = createSection('AI-generated image candidates');
		section.setAttribute('data-toolbox-ai-generation-result', 'true');
		const count = Array.isArray(payload.images) ? payload.images.length : 0;
		section.appendChild(el('div', count ? 'npcink-toolbox__result-notice is-ok' : 'npcink-toolbox__result-notice is-warning', count ? 'Cloud returned AI-generated image candidates. Review the image and source status before adoption.' : 'Cloud did not return a usable image URL.'));
		if (!count && payload.message) {
			section.appendChild(el('div', 'npcink-toolbox__result-notice is-warning', payload.message));
		}
		const meta = el('div', 'npcink-toolbox__result-meta');
		appendMeta(meta, 'Model', payload.model_id || (payload.usage_summary && payload.usage_summary.model_id));
		appendMeta(meta, 'Run', payload.run_id);
		appendMeta(meta, 'Candidates', count);
		if (payload.ai_generation && payload.ai_generation.aspect_ratio) {
			appendMeta(meta, 'Aspect ratio', payload.ai_generation.aspect_ratio);
		}
		if (meta.childNodes.length) {
			section.appendChild(meta);
		}
		renderImageList(section, payload.images);
		appendImageAgentFeedbackControls(section, payload, 'toolbox_ai_image_generation');
		section.appendChild(el('div', 'npcink-toolbox__result-notice is-pending', 'Generated images are candidates only. Use Adopt New Image and Core review before importing or inserting media.'));
		section.appendChild(createRawDetails(payload, 'AI generation payload'));
		container.appendChild(section);
	}

	function appendAiImageGenerationHandoff(form, container, payload) {
		const handoff = aiGenerationHandoff(payload);
		if (!handoff || handoff.trigger !== 'manual_user_action') {
			return;
		}

		const section = createSection('AI image generation');
		section.appendChild(el('div', 'npcink-toolbox__result-notice is-pending', 'Generate an original image only after reviewing the prompt. Cloud returns candidates; WordPress media writes stay local.'));
		const promptLabel = el('label', '');
		promptLabel.appendChild(el('span', '', 'Reviewed prompt'));
		const prompt = el('textarea', '');
		prompt.name = 'ai_generation_prompt';
		prompt.rows = 5;
		prompt.value = defaultAiImagePrompt(payload, handoff);
		promptLabel.appendChild(prompt);
		section.appendChild(promptLabel);

		const controls = el('div', 'npcink-toolbox__split');
		const ratioLabel = el('label', '');
		ratioLabel.appendChild(el('span', '', 'Aspect ratio'));
		const ratio = el('select', '');
		ratio.name = 'ai_generation_aspect_ratio';
		['16:9', '1:1', '4:3', '3:4', '9:16'].forEach((value) => {
			const option = el('option', '', value);
			option.value = value;
			if (value === aiGenerationAspectRatio(payload, handoff)) {
				option.selected = true;
			}
			ratio.appendChild(option);
		});
		ratioLabel.appendChild(ratio);
		controls.appendChild(ratioLabel);

		const countLabel = el('label', '');
		countLabel.appendChild(el('span', '', 'Count'));
		const count = el('input', '');
		count.type = 'number';
		count.min = '1';
		count.max = '4';
		count.step = '1';
		count.value = '1';
		countLabel.appendChild(count);
		controls.appendChild(countLabel);
		section.appendChild(controls);

		const actions = el('div', 'npcink-toolbox__result-actions');
		const button = el('button', 'button button-primary', 'Generate AI image');
		button.type = 'button';
		button.addEventListener('click', async () => {
			const reviewedPrompt = String(prompt.value || '').trim();
			if (!reviewedPrompt) {
				appendAiGenerationResult(container, { images: [], message: 'Prompt is required.' });
				return;
			}
			button.disabled = true;
			const originalText = button.textContent;
			button.textContent = t('Generating...');
			try {
				const response = await postJson(config.restUrl, 'ai/image-generation', {
					prompt: reviewedPrompt,
					aspect_ratio: ratio.value,
					resolution: handoff.input_defaults && handoff.input_defaults.resolution ? handoff.input_defaults.resolution : 'high',
					response_format: 'url',
					n: count.value,
					prompt_reviewed_by_operator: true,
					media_title: payload.query || payload.primary_query || '',
					media_description: payload.message || '',
					handoff
				});
				appendAiGenerationResult(container, response);
			} catch (error) {
				appendAiGenerationResult(container, { images: [], message: formatErrorMessage(error, 'AI image generation failed.'), error });
			} finally {
				button.disabled = false;
				button.textContent = originalText;
			}
		});
		actions.appendChild(button);
		section.appendChild(actions);
		section.appendChild(createRawDetails(handoff, 'AI generation handoff'));
		container.appendChild(section);
	}

	function renderPointList(container, points) {
		if (!Array.isArray(points) || !points.length) {
			return;
		}

		const section = createSection('Vector matches');
		const list = el('div', 'npcink-toolbox__result-list');
		points.forEach((point, index) => {
			const row = el('article', 'npcink-toolbox__result-item');
			row.appendChild(el('h4', '', point.id ? 'Point ' + point.id : 'Match ' + (index + 1)));
			const meta = el('div', 'npcink-toolbox__result-meta');
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

	function siteKnowledgeProposalCandidate(handoff) {
		const proposalInput = handoff && handoff.proposal_input && typeof handoff.proposal_input === 'object' ? handoff.proposal_input : {};
		const evidenceRefs = Array.isArray(proposalInput.evidence_refs) ? proposalInput.evidence_refs : [];
		const blockedOutputs = Array.isArray(proposalInput.blocked_outputs) ? proposalInput.blocked_outputs : [];

		return {
			artifact_type: 'site_knowledge_core_proposal_candidate',
			version: 1,
			status: 'candidate_ready_for_operator_review',
			core_submission: 'not_submitted',
			approval_state: 'operator_review_required',
			agent_id: handoff.agent_id || '',
			agent_version: handoff.agent_version || '',
			workflow: handoff.workflow || proposalInput.workflow || '',
			cloud_output: handoff.cloud_output || proposalInput.cloud_output || '',
			intent: proposalInput.intent || handoff.workflow || '',
			local_next_action: handoff.local_next_action || proposalInput.local_next_action || '',
			write_posture: handoff.write_posture || 'suggestion_only',
			final_writes: handoff.final_writes || 'core_proposal_required',
			direct_wordpress_write: false,
			evidence_gate_status: handoff.evidence_gate_status || '',
			evidence_count: handoff.evidence_count || evidenceRefs.length,
			evidence_refs: evidenceRefs,
			blocked_outputs: blockedOutputs,
			next_steps: [
				'Review evidence and decide whether a local write plan is warranted.',
				'Create a specific Core proposal only after a human chooses the target WordPress action.',
				'Keep Core approval, preflight, audit, and final WordPress writes local.'
			]
		};
	}

	function appendSiteKnowledgeProposalCandidate(container, handoff) {
		const existing = container.querySelector('[data-toolbox-site-knowledge-candidate]');
		if (existing) {
			existing.remove();
		}

		const candidate = siteKnowledgeProposalCandidate(handoff);
		const section = createSection('Local Core proposal candidate');
		section.setAttribute('data-toolbox-site-knowledge-candidate', 'true');

		const meta = el('div', 'npcink-toolbox__result-meta');
		appendMeta(meta, 'Status', formatLabel(candidate.status));
		appendMeta(meta, 'Core submission', formatLabel(candidate.core_submission));
		appendMeta(meta, 'Approval', formatLabel(candidate.approval_state));
		appendMeta(meta, 'Evidence', candidate.evidence_count);
		section.appendChild(meta);
		section.appendChild(el('div', 'npcink-toolbox__result-notice is-pending', 'Candidate prepared locally only. It has not been submitted to Core, approved, preflighted, or executed.'));

		if (config.coreAdminUrl) {
			const actions = el('div', 'npcink-toolbox__result-actions');
			actions.appendChild(createLink(config.coreAdminUrl, 'Open Core review'));
			section.appendChild(actions);
		}

		section.appendChild(createRawDetails(candidate, 'Local proposal candidate packet'));
		container.appendChild(section);
	}

	function siteKnowledgeEvidenceRefIds(handoff) {
		const proposalInput = handoff && handoff.proposal_input && typeof handoff.proposal_input === 'object' ? handoff.proposal_input : {};
		const refs = Array.isArray(proposalInput.evidence_refs) ? proposalInput.evidence_refs : [];
		const ids = [];
		refs.forEach((ref, index) => {
			if (!ref || typeof ref !== 'object') {
				return;
			}
			let value = ref.id || ref.ref_id || '';
			if (!value) {
				const source = ref.source_type || 'evidence';
				const sourceId = ref.source_id || ref.post_id || ref.url || (index + 1);
				value = String(source) + ':' + String(sourceId);
			}
			if (value && ids.indexOf(value) === -1) {
				ids.push(String(value).slice(0, 191));
			}
		});
		return ids.slice(0, 24);
	}

	function siteKnowledgeAgentFeedbackPayload(handoff, outcome, labels) {
		const proposalInput = handoff && handoff.proposal_input && typeof handoff.proposal_input === 'object' ? handoff.proposal_input : {};
		const agentId = handoff.agent_id || proposalInput.agent_id || 'site_knowledge_suggestion_agent';
		const handoffType = handoff.handoff_type || 'proposal_input';
		const handoffId = handoff.handoff_id || [
			'site_knowledge',
			agentId,
			handoff.workflow || proposalInput.workflow || '',
			handoff.local_next_action || proposalInput.local_next_action || '',
			handoff.evidence_count || siteKnowledgeEvidenceRefIds(handoff).length
		].join(':');

		return {
			contract_version: 'cloud_agent_feedback.v1',
			agent_id: agentId,
			agent_version: handoff.agent_version || proposalInput.agent_version || '',
			source_runtime: 'site_knowledge',
			source_run_id: handoff.source_run_id || handoff.run_id || '',
			handoff_id: handoffId,
			handoff_type: handoffType,
			local_surface: 'toolbox_site_knowledge',
			local_outcome: outcome,
			feedback_labels: labels,
			operator_note: '',
			local_proposal_id: '',
			evidence_ref_ids: siteKnowledgeEvidenceRefIds(handoff),
			redaction_status: 'metadata_only',
			retention_class: 'quality_eval',
			created_at: new Date().toISOString(),
			handoff: handoff || {}
		};
	}

	async function submitSiteKnowledgeAgentFeedback(statusNode, handoff, button, outcome, labels) {
		const originalText = button ? button.textContent : '';
		const root = button ? button.closest('[data-toolbox-site-knowledge]') : null;
		if (button) {
			button.disabled = true;
			button.textContent = 'Sending...';
		}
		statusNode.className = 'npcink-toolbox__result-notice is-pending';
		statusNode.textContent = 'Sending feedback to Cloud eval...';

		try {
			const receipt = await postJson(config.restUrl, 'agent-feedback', siteKnowledgeAgentFeedbackPayload(handoff, outcome, labels));
			statusNode.className = 'npcink-toolbox__result-notice is-ok';
			statusNode.textContent = receipt && receipt.accepted_for_eval
				? 'Feedback accepted for Cloud eval. WordPress approval and writes remain local.'
				: 'Feedback sent. WordPress approval and writes remain local.';
			if (root) {
				refreshAgentFeedbackSummary(root).catch(() => {});
			}
		} catch (error) {
			statusNode.className = 'npcink-toolbox__result-notice is-error';
			statusNode.textContent = (error && error.message ? error.message : 'Could not send Agent feedback.') + ' WordPress approval and writes remain local.';
		} finally {
			if (button) {
				button.disabled = false;
				button.textContent = originalText;
			}
		}
	}

	function appendSiteKnowledgeAgentFeedbackControls(section, handoff) {
		const feedback = el('div', 'npcink-toolbox__result-feedback');
		feedback.setAttribute('data-toolbox-site-knowledge-agent-feedback', 'true');
		feedback.setAttribute('data-toolbox-agent-feedback-quick', 'true');
		feedback.appendChild(el('h4', '', 'Quick Agent feedback'));
		const actions = el('div', 'npcink-toolbox__result-actions');
		const status = el('div', 'npcink-toolbox__result-notice is-pending', 'Feedback updates Cloud eval only. Core approval, preflight, and final WordPress writes stay local.');
		const options = [
			{ label: 'Useful', outcome: 'accepted', labels: ['evidence_useful', 'operator_confidence_high'] },
			{ label: 'Edited and accepted', outcome: 'edited_before_accept', labels: ['evidence_useful', 'good_but_needs_human_draft'] },
			{ label: 'Evidence weak', outcome: 'rejected', labels: ['evidence_weak', 'operator_confidence_low'] },
			{ label: 'Wrong next step', outcome: 'rejected', labels: ['wrong_next_step'] },
			{ label: 'Missing context', outcome: 'rejected', labels: ['missing_context', 'operator_confidence_low'] },
			{ label: 'Not relevant', outcome: 'rejected', labels: ['not_relevant_to_site'] }
		];
		options.forEach((option) => {
			const button = el('button', 'button', option.label);
			button.type = 'button';
			button.title = 'Send metadata-only Agent feedback to Cloud eval.';
			button.setAttribute('data-toolbox-agent-feedback-outcome', option.outcome);
			button.setAttribute('data-toolbox-agent-feedback-labels', option.labels.join(','));
			button.addEventListener('click', () => submitSiteKnowledgeAgentFeedback(status, handoff, button, option.outcome, option.labels));
			actions.appendChild(button);
		});
		feedback.appendChild(actions);
		feedback.appendChild(status);
		section.appendChild(feedback);
	}

	function imageAgentFeedbackEvidenceRefIds(payload) {
		const images = Array.isArray(payload && payload.images) ? payload.images : [];
		const ids = [];
		images.slice(0, 12).forEach((image, index) => {
			if (!image || typeof image !== 'object') {
				return;
			}
			const provider = String(image.provider || payload.provider_mode || payload.resolved_provider || 'image').trim();
			const sourceType = String(image.source_type || payload.provider_mode || 'candidate').trim();
			const id = String(image.id || image.asset_id || image.suggested_filename || (index + 1)).trim();
			const value = ['image', sourceType, provider, id].filter(Boolean).join(':').slice(0, 191);
			if (value && ids.indexOf(value) === -1) {
				ids.push(value);
			}
		});
		if (!ids.length && payload && payload.run_id) {
			ids.push(String('image_run:' + payload.run_id).slice(0, 191));
		}
		return ids.slice(0, 24);
	}

	function imageAgentFeedbackPayload(payload, localSurface, outcome, labels) {
		const count = Array.isArray(payload && payload.images) ? payload.images.length : 0;
		const surface = localSurface || 'toolbox_image_candidates';
		const sourceRuntime = surface === 'toolbox_ai_image_generation' ? 'ai_image_generation' : 'image_candidates';
		const runId = payload && payload.run_id ? String(payload.run_id) : '';
		const handoffId = [
			sourceRuntime,
			payload && payload.provider_mode ? payload.provider_mode : '',
			payload && payload.resolved_provider ? payload.resolved_provider : '',
			runId || String(count)
		].filter(Boolean).join(':') || sourceRuntime;

		return {
			contract_version: 'cloud_agent_feedback.v1',
			agent_id: surface === 'toolbox_ai_image_generation' ? 'ai_image_generation_candidate_agent' : 'image_source_candidate_agent',
			agent_version: payload && payload.candidate_contract_version ? String(payload.candidate_contract_version) : '',
			source_runtime: sourceRuntime,
			source_run_id: runId,
			handoff_id: handoffId,
			handoff_type: 'image_candidate_result',
			local_surface: surface,
			local_outcome: outcome,
			feedback_labels: labels,
			operator_note: '',
			local_proposal_id: '',
			evidence_ref_ids: imageAgentFeedbackEvidenceRefIds(payload || {}),
			redaction_status: 'metadata_only',
			retention_class: 'quality_eval',
			created_at: new Date().toISOString()
		};
	}

	function refreshVisibleAgentFeedbackSummaries() {
		document.querySelectorAll('[data-toolbox-site-knowledge]').forEach((root) => {
			refreshAgentFeedbackSummary(root).catch(() => {});
		});
	}

	async function submitImageAgentFeedback(statusNode, payload, localSurface, button, outcome, labels) {
		const originalText = button ? button.textContent : '';
		if (button) {
			button.disabled = true;
			button.textContent = 'Sending...';
		}
		statusNode.className = 'npcink-toolbox__result-notice is-pending';
		statusNode.textContent = 'Sending image feedback to Cloud eval...';

		try {
			const receipt = await postJson(config.restUrl, 'agent-feedback', imageAgentFeedbackPayload(payload, localSurface, outcome, labels));
			statusNode.className = 'npcink-toolbox__result-notice is-ok';
			statusNode.textContent = receipt && receipt.accepted_for_eval
				? 'Image feedback accepted for Cloud eval. WordPress media import and writes remain local.'
				: 'Image feedback sent. WordPress media import and writes remain local.';
			refreshVisibleAgentFeedbackSummaries();
		} catch (error) {
			statusNode.className = 'npcink-toolbox__result-notice is-error';
			statusNode.textContent = (error && error.message ? error.message : 'Could not send image feedback.') + ' WordPress media import and writes remain local.';
		} finally {
			if (button) {
				button.disabled = false;
				button.textContent = originalText;
			}
		}
	}

	function appendImageAgentFeedbackControls(section, payload, localSurface) {
		const feedback = el('div', 'npcink-toolbox__result-feedback');
		feedback.setAttribute('data-toolbox-image-agent-feedback', 'true');
		feedback.setAttribute('data-toolbox-agent-feedback-quick', 'true');
		feedback.appendChild(el('h4', '', 'Quick image feedback'));
		const actions = el('div', 'npcink-toolbox__result-actions');
		const status = el('div', 'npcink-toolbox__result-notice is-pending', 'Feedback updates Cloud eval only. Core approval, media import, and final WordPress writes stay local.');
		const options = [
			{ label: 'Useful candidates', outcome: 'accepted', labels: ['evidence_useful', 'operator_confidence_high'] },
			{ label: 'Adoption planned', outcome: 'accepted', labels: ['evidence_useful', 'good_but_needs_human_draft'] },
			{ label: 'Low visual quality', outcome: 'rejected', labels: ['visual_quality_low', 'operator_confidence_low'] },
			{ label: 'Source risk', outcome: 'rejected', labels: ['source_or_license_risk', 'operator_confidence_low'] },
			{ label: 'Not relevant', outcome: 'rejected', labels: ['not_relevant_to_site'] }
		];
		options.forEach((option) => {
			const button = el('button', 'button', option.label);
			button.type = 'button';
			button.title = 'Send metadata-only image feedback to Cloud eval.';
			button.setAttribute('data-toolbox-image-feedback-outcome', option.outcome);
			button.setAttribute('data-toolbox-image-feedback-labels', option.labels.join(','));
			button.addEventListener('click', () => submitImageAgentFeedback(status, payload, localSurface, button, option.outcome, option.labels));
			actions.appendChild(button);
		});
		feedback.appendChild(actions);
		feedback.appendChild(status);
		section.appendChild(feedback);
	}

	async function submitSiteKnowledgeReviewProposal(container, handoff, button) {
		const form = container.closest('form');
		if (!form) {
			return;
		}

		const originalText = button ? button.textContent : '';
		if (button) {
			button.disabled = true;
			button.textContent = 'Submitting Core review...';
		}

		try {
			if (!config.adapterRestUrl) {
				throw { message: 'Npcink Adapter REST URL is unavailable.' };
			}
			renderTextResult(form, 'Building Site Knowledge review plan...', 'pending');
			const plan = await postJson(config.restUrl, 'flows/site-knowledge-review-plan', {
				proposal_input: handoff && handoff.proposal_input ? handoff.proposal_input : {},
				handoff: handoff || {},
			});
			const bridge = await postJson(config.adapterRestUrl, 'proposals/from-plan', {
				plan_ability_id: 'npcink-toolbox/build-site-knowledge-review-plan',
				plan,
				plan_input: {
					source: 'site_knowledge_agent_handoff',
					proposal_input: handoff && handoff.proposal_input ? handoff.proposal_input : {},
				},
				caller: {
					external_thread_id: 'toolbox-site-knowledge-review',
					source: 'toolbox_site_knowledge_agent',
				},
			});
			renderProposalCreated(form, proposalFromPlanResponse(bridge), {
				title: 'Site Knowledge review proposal submitted',
				summary: 'Core created a blocked review proposal from Site Knowledge evidence. Human title and content input are required before approval, preflight, or execution can proceed.',
				rawTitle: 'Core Site Knowledge review response',
				receiptContext: {
					handoffType: 'site_knowledge_review_plan',
					sourceItemId: 'site_knowledge_agent_handoff',
					sourceLabel: 'Site Knowledge review evidence',
					targetAbilityId: 'npcink-abilities-toolkit/create-draft',
				},
			});
		} catch (error) {
			renderErrorResult(form, error, 'Could not submit the Site Knowledge review proposal.', {
				title: 'Site Knowledge Core handoff failed',
				rawTitle: 'Site Knowledge Core handoff error payload',
				receiptContext: {
					handoffType: 'site_knowledge_review_plan',
					sourceItemId: 'site_knowledge_agent_handoff',
					sourceLabel: 'Site Knowledge review evidence',
					targetAbilityId: 'npcink-abilities-toolkit/create-draft',
				},
			});
		} finally {
			if (button) {
				button.disabled = false;
				button.textContent = originalText;
			}
		}
	}

	function renderHandoff(container, handoff) {
		if (!handoff || typeof handoff !== 'object') {
			return;
		}

		const section = createSection('Governed handoff');
		const meta = el('div', 'npcink-toolbox__result-meta');
		appendMeta(meta, 'Write posture', handoff.write_posture || 'suggestion_only');
		appendMeta(meta, 'Final write path', handoff.final_write_path || 'Core proposal required');
		appendMeta(meta, 'Handoff type', handoff.handoff_type ? formatLabel(handoff.handoff_type) : '');
		appendMeta(meta, 'Agent', handoff.agent_id ? formatLabel(handoff.agent_id) : '');
		appendMeta(meta, 'Workflow', handoff.workflow ? formatLabel(handoff.workflow) : '');
		appendMeta(meta, 'Cloud output', handoff.cloud_output ? formatLabel(handoff.cloud_output) : '');
		appendMeta(meta, 'Evidence', handoff.evidence_count);
		appendMeta(meta, 'Approval', handoff.requires_local_approval === true ? 'Local Core required' : '');
		section.appendChild(meta);

		if (Array.isArray(handoff.next_steps) && handoff.next_steps.length) {
			const list = el('ol', 'npcink-toolbox__step-list');
			handoff.next_steps.forEach((step) => {
				list.appendChild(el('li', '', step));
			});
			section.appendChild(list);
		}
		if (handoff.local_next_action) {
			section.appendChild(el('div', 'npcink-toolbox__result-notice is-pending', 'Next local action: ' + formatLabel(handoff.local_next_action)));
		}
		if (handoff.handoff_type === 'proposal_input') {
			section.appendChild(el('div', 'npcink-toolbox__result-notice is-pending', 'Proposal candidate only. Review evidence, then use Core governance for approval, preflight, audit, and final WordPress writes.'));
			const actions = el('div', 'npcink-toolbox__result-actions');
			const button = el('button', 'button', 'Prepare local proposal candidate');
			button.type = 'button';
			button.setAttribute('data-toolbox-site-knowledge-proposal-candidate', 'true');
			button.addEventListener('click', () => appendSiteKnowledgeProposalCandidate(container, handoff));
			actions.appendChild(button);
			const submitButton = el('button', 'button button-primary', 'Submit Core review proposal');
			submitButton.type = 'button';
			submitButton.setAttribute('data-toolbox-site-knowledge-review-submit', 'true');
			submitButton.addEventListener('click', () => submitSiteKnowledgeReviewProposal(container, handoff, submitButton));
			actions.appendChild(submitButton);
			if (config.coreAdminUrl) {
				actions.appendChild(createLink(config.coreAdminUrl, 'Open Core review'));
			}
			section.appendChild(actions);
			appendSiteKnowledgeAgentFeedbackControls(section, handoff);
		}
		if (handoff.proposal_input && typeof handoff.proposal_input === 'object' && Object.keys(handoff.proposal_input).length) {
			const proposalInput = handoff.proposal_input;
			const evidenceRefs = Array.isArray(proposalInput.evidence_refs) ? proposalInput.evidence_refs : [];
			if (evidenceRefs.length) {
				const refs = el('div', 'npcink-toolbox__result-list');
				evidenceRefs.slice(0, 5).forEach((ref, index) => {
					const row = el('article', 'npcink-toolbox__result-item');
					row.appendChild(el('h4', '', ref.title || 'Evidence ' + (index + 1)));
					if (ref.url) {
						row.appendChild(createLink(ref.url, ref.url));
					}
					const refMeta = el('div', 'npcink-toolbox__result-meta');
					appendMeta(refMeta, 'Source', ref.source_type ? formatLabel(ref.source_type) : '');
					appendMeta(refMeta, 'Post', ref.post_id);
					appendMeta(refMeta, 'Score', ref.score);
					appendMeta(refMeta, 'Use', ref.suggested_use ? formatLabel(ref.suggested_use) : '');
					row.appendChild(refMeta);
					refs.appendChild(row);
				});
				section.appendChild(refs);
			}
			section.appendChild(createRawDetails(proposalInput, 'Agent proposal input'));
		}
		container.appendChild(section);
	}

	function renderArtifactSummary(container, title, artifact) {
		if (!artifact || typeof artifact !== 'object') {
			return;
		}

		const section = createSection(title);
		const meta = el('div', 'npcink-toolbox__result-meta');
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
				? String(count) + t(' candidates returned from Cloud-managed image-source runtime. Review license evidence before adoption.')
				: 'No image-source candidates were returned.'
		);
		if (!result) {
			return;
		}

		const meta = el('div', 'npcink-toolbox__result-meta');
		appendMeta(meta, 'Cloud runtime', payload.cloud_runtime || 'npcink_cloud_addon');
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

		result.appendChild(el('div', 'npcink-toolbox__result-notice is-ok', 'Cloud returned image candidates only. Media import still requires an Adopt New Image plan and Core approval.'));
		renderImageList(result, payload.images);
		appendImageAgentFeedbackControls(
			result,
			payload,
			payload.provider_mode === 'ai_generated' ? 'toolbox_ai_image_generation' : 'toolbox_image_candidates'
		);
		appendAiImageGenerationHandoff(form, result, payload);
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
		const quota = coverage.quota && typeof coverage.quota === 'object' ? coverage.quota : {};
		const progress = payload && payload.progress && typeof payload.progress === 'object' ? payload.progress : {};
		const activeRun = payload && payload.active_run && typeof payload.active_run === 'object' ? payload.active_run : {};
		clearNode(container);

		const status = String(payload && payload.status ? payload.status : 'unknown');
		const noticeKind = status === 'ready' ? 'ok' : (status === 'failed' ? 'error' : 'pending');
		container.appendChild(el('div', 'npcink-toolbox__result-notice is-' + noticeKind, t('Status: ') + localizedLabel(status)));
		if (siteKnowledgeStatusStillActive(payload)) {
			container.appendChild(el('div', 'npcink-toolbox__result-notice is-pending', siteKnowledgeActiveStatusMessage(payload)));
		}
		if (progress.message) {
			container.appendChild(el('div', 'npcink-toolbox__result-notice is-' + noticeKind, siteKnowledgeProgressMessage(progress.message)));
		}

		const meta = el('div', 'npcink-toolbox__result-meta');
		appendMeta(meta, 'Stage', progress.stage ? localizedLabel(progress.stage) : '');
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
		appendMeta(meta, 'Skipped', progress.skipped_documents);
		appendMeta(meta, 'Quota skipped', progress.skipped_due_to_quota || quota.skipped_due_to_quota);
		appendMeta(meta, 'Last sync', formatDateTime(coverage.last_sync_at));
		appendMeta(meta, 'Active run', activeRun.run_id);
		appendMeta(meta, 'Comments', coverage.comments_enabled === true ? 'Enabled in Cloud' : 'Disabled in Cloud');
		appendMeta(meta, 'Cloud quota', quota.status ? localizedLabel(quota.status) : '');
		appendMeta(
			meta,
			'Indexed documents quota',
			quota.max_indexed_documents_per_site
				? String(quota.indexed_documents || coverage.indexed_posts || 0) + ' / ' + String(quota.max_indexed_documents_per_site)
				: ''
		);
		appendMeta(
			meta,
			'Indexed chunks quota',
			quota.max_indexed_chunks_per_site
				? String(quota.indexed_chunks || coverage.indexed_chunks || 0) + ' / ' + String(quota.max_indexed_chunks_per_site)
				: ''
		);
		appendMeta(
			meta,
			'Run batch cap',
			quota.max_sync_documents_per_run
				? String(quota.max_sync_documents_per_run) + ' ' + t('documents') + ' / ' + String(quota.max_sync_chunks_per_run || 0) + ' ' + t('chunks')
				: ''
		);
		if (meta.childNodes.length) {
			container.appendChild(meta);
		}

		if (coverage.post_type_coverage || coverage.source_type_coverage) {
			const details = el('details', 'npcink-toolbox__result-details');
			details.appendChild(el('summary', '', 'Coverage detail'));
			const pre = el('pre', 'npcink-toolbox__result-raw');
			pre.textContent = JSON.stringify({
				post_type_coverage: coverage.post_type_coverage || {},
				source_type_coverage: coverage.source_type_coverage || {},
				has_stale_content: coverage.has_stale_content === true,
			}, null, 2);
			details.appendChild(pre);
			container.appendChild(details);
		}

		renderSiteKnowledgeAutoSync(container, payload && payload.auto_sync && typeof payload.auto_sync === 'object' ? payload.auto_sync : {});
	}

	function siteKnowledgeProgressMessage(message) {
		const text = String(message || '').trim();
		return text ? t(text) : '';
	}

	function formatRate(value) {
		const number = Number(value);
		if (!Number.isFinite(number)) {
			return '';
		}
		return Math.round(number * 1000) / 10 + '%';
	}

	function renderAgentFeedbackSummaryNode(container, payload) {
		const outcomes = payload && payload.outcomes && typeof payload.outcomes === 'object' ? payload.outcomes : {};
		const labels = payload && payload.labels && typeof payload.labels === 'object' ? payload.labels : {};
		const rates = payload && payload.rates && typeof payload.rates === 'object' ? payload.rates : {};
		const total = Number(payload && payload.events_total ? payload.events_total : 0);
		clearNode(container);

		container.appendChild(el('div', 'npcink-toolbox__result-notice is-ok', 'Agent feedback summary: ' + total + ' event' + (total === 1 ? '' : 's')));
		const meta = el('div', 'npcink-toolbox__result-meta');
		appendMeta(meta, 'Window', payload && payload.window_hours ? String(payload.window_hours) + 'h' : '');
		appendMeta(meta, 'Accepted', outcomes.accepted || outcomes.edited_before_accept ? String(Number(outcomes.accepted || 0) + Number(outcomes.edited_before_accept || 0)) : '');
		appendMeta(meta, 'Rejected', outcomes.rejected);
		appendMeta(meta, 'Ignored', outcomes.ignored);
		appendMeta(meta, 'Accepted rate', formatRate(rates.accepted_rate));
		appendMeta(meta, 'Evidence useful', formatRate(rates.evidence_useful_rate));
		appendMeta(meta, 'Evidence weak', formatRate(rates.evidence_weak_rate));
		appendMeta(meta, 'Wrong next step', formatRate(rates.wrong_next_step_rate));
		appendMeta(meta, 'Write truth', payload && payload.final_write_truth ? formatLabel(payload.final_write_truth) : '');
		if (meta.childNodes.length) {
			container.appendChild(meta);
		}

		const importantLabels = ['evidence_useful', 'evidence_weak', 'wrong_next_step', 'wrong_priority', 'already_handled', 'missing_context', 'visual_quality_low', 'source_or_license_risk', 'operator_confidence_high', 'operator_confidence_low'];
		const labelItems = importantLabels
			.filter((label) => Number(labels[label] || 0) > 0)
			.map((label) => ({ name: formatLabel(label), value: labels[label] }));
		if (labelItems.length) {
			renderSupportItems(container, 'Feedback labels', labelItems, 'No feedback labels returned.');
		}

		const lowQualityItems = Array.isArray(payload && payload.low_quality_labels) ? payload.low_quality_labels : [];
		if (lowQualityItems.length) {
			renderSupportItems(
				container,
				'Low quality labels',
				lowQualityItems.map((item) => ({
					name: formatLabel(item.label || ''),
					value: item.count,
				})),
				'No low quality labels returned.'
			);
		}

		const rejectedItems = Array.isArray(payload && payload.rejection_reasons) ? payload.rejection_reasons : [];
		if (rejectedItems.length) {
			renderSupportItems(
				container,
				'Rejected reasons',
				rejectedItems.map((item) => ({
					name: formatLabel(item.label || ''),
					value: item.count,
				})),
				'No rejected reasons returned.'
			);
		}

		const nightly = payload && payload.nightly_inspection && typeof payload.nightly_inspection === 'object' ? payload.nightly_inspection : {};
		if (Number(nightly.events_total || 0) > 0) {
			const nightlyMeta = el('div', 'npcink-toolbox__result-meta');
			appendMeta(nightlyMeta, 'Nightly events', nightly.events_total);
			appendMeta(nightlyMeta, 'Action feedback', nightly.action_feedback_total);
			appendMeta(nightlyMeta, 'Avg source score', nightly.average_source_score);
			appendMeta(nightlyMeta, 'Wrong priority', nightly.rates && nightly.rates.wrong_priority_rate !== undefined ? formatRate(nightly.rates.wrong_priority_rate) : '');
			appendMeta(nightlyMeta, 'Already handled', nightly.rates && nightly.rates.already_handled_rate !== undefined ? formatRate(nightly.rates.already_handled_rate) : '');
			if (nightlyMeta.childNodes.length) {
				const section = createSection('Nightly Inspection feedback');
				section.appendChild(nightlyMeta);
				const rejectedReasonCodes = Array.isArray(nightly.rejected_reason_codes) ? nightly.rejected_reason_codes : [];
				if (rejectedReasonCodes.length) {
					renderSupportItems(
						section,
						'Rejected reason codes',
						rejectedReasonCodes.map((item) => ({
							name: formatLabel(item.label || ''),
							value: item.count,
						})),
						'No rejected reason codes returned.'
					);
				}
				container.appendChild(section);
			}
		}

		const scenarios = Array.isArray(payload && payload.scenarios) ? payload.scenarios : [];
		if (scenarios.length) {
			renderSupportItems(
				container,
				'Scenarios',
				scenarios.slice(0, 4).map((item) => ({
					name: formatLabel(item.local_surface || item.source_runtime || 'Scenario'),
					value: (item.events_total || 0) + ' events',
					reason: 'Accepted ' + formatRate(item.accepted_rate) + ' · Weak evidence ' + formatRate(item.evidence_weak_rate) + ' · Wrong next step ' + formatRate(item.wrong_next_step_rate),
				})),
				'No scenario summary returned.'
			);
		}

		const trend = Array.isArray(payload && payload.quality_trend) ? payload.quality_trend : [];
		if (trend.length) {
			renderSupportItems(
				container,
				'Quality trend',
				trend.slice(-6).map((item) => ({
					name: formatDateTime(item.bucket || ''),
					value: (item.events_total || 0) + ' events',
					reason: 'Accepted ' + (item.accepted || 0) + ' · Rejected ' + (item.rejected || 0) + ' · Weak evidence ' + (item.evidence_weak || 0) + ' · Wrong next step ' + (item.wrong_next_step || 0),
				})),
				'No quality trend returned.'
			);
		}
	}

	function renderSiteKnowledgeAutoSync(container, health) {
		const status = String(health.status || 'idle');
		const queueCount = Number(health.queue_count || 0);
		const notice = siteKnowledgeAutoSyncNotice(health, status, queueCount);
		container.appendChild(el('div', notice.kind ? 'npcink-toolbox__result-notice is-' + notice.kind : 'npcink-toolbox__result-notice', notice.message));

		const meta = el('div', 'npcink-toolbox__result-meta');
		appendMeta(meta, 'Change bridge', localizedLabel(status));
		appendMeta(meta, 'Bridge owner', formatLabel(health.owner || 'cloud_addon'));
		appendMeta(meta, 'Bridge state', siteKnowledgeAutoSyncQueueMeaning(health, status, queueCount));
		appendMeta(meta, 'Buffered changes', health.queue_count);
		appendMeta(meta, 'Next flush', formatDateTime(health.next_flush_at || health.next_queue_run_at));
		appendMeta(meta, 'Daily check', formatDateTime(health.next_reconcile_at));
		appendMeta(meta, 'WP-Cron disabled', health.wp_cron_disabled === true ? 'Yes' : 'No');
		appendMeta(meta, 'Batch size', health.batch_size);
		appendMeta(meta, 'Last delivery', formatDateTime(health.last_delivery_at || health.last_delivered_at));
		appendMeta(meta, 'Last success', formatDateTime(health.last_success_at));
		appendMeta(meta, 'Last error', health.last_error_code);
		if (meta.childNodes.length) {
			container.appendChild(meta);
		}

		if (health.cron_command || health.wp_cli_command) {
			const details = el('details', 'npcink-toolbox__result-details');
			details.appendChild(el('summary', '', siteKnowledgeAutoSyncCronSummary(health, status, queueCount)));
			if (health.cron_command) {
				details.appendChild(el('p', 'description', 'Use this when your host supports URL-based scheduled tasks.'));
				const curl = el('pre', 'npcink-toolbox__result-raw');
				curl.textContent = String(health.cron_command);
				details.appendChild(curl);
			}
			if (health.wp_cli_command) {
				details.appendChild(el('p', 'description', 'Use this when your server supports WP-CLI.'));
				const cli = el('pre', 'npcink-toolbox__result-raw');
				cli.textContent = String(health.wp_cli_command);
				details.appendChild(cli);
			}
			container.appendChild(details);
		}
	}

	function siteKnowledgeAutoSyncNotice(health, status, queueCount) {
		if (status === 'disabled') {
			return {
				kind: 'warning',
				message: health.message || 'Cloud Addon change bridge is disabled until Cloud settings are verified.',
			};
		}
		if (status === 'delayed' || health.wp_cron_disabled === true || siteKnowledgeAutoSyncDue(health, queueCount)) {
			return {
				kind: 'warning',
				message: 'Cloud Addon has buffered Site Knowledge changes that are due for WP-Cron. If this stays buffered, run WP-Cron or configure the server cron command below.',
			};
		}
		if (queueCount > 0) {
			return {
				kind: '',
				message: 'Cloud Addon is waiting for the debounce window. The current index remains usable; buffered changes will refresh on the next WP-Cron run.',
			};
		}
		return {
			kind: 'ok',
			message: 'Cloud Addon change bridge is idle. No public-content changes are waiting.',
		};
	}

	function siteKnowledgeAutoSyncQueueMeaning(health, status, queueCount) {
		if (status === 'disabled') {
			return 'Disabled until Cloud Addon is installed and verified';
		}
		if (queueCount <= 0) {
			return 'No buffered changes';
		}
		if (status === 'delayed' || health.wp_cron_disabled === true || siteKnowledgeAutoSyncDue(health, queueCount)) {
			return 'Buffered changes are due for WP-Cron';
		}
		return 'Buffered changes waiting for the next WP-Cron run';
	}

	function siteKnowledgeAutoSyncCronSummary(health, status, queueCount) {
		if (status === 'delayed' || health.wp_cron_disabled === true || siteKnowledgeAutoSyncDue(health, queueCount)) {
			return 'Server cron action';
		}
		return 'Server cron suggestion';
	}

	function siteKnowledgeAutoSyncDue(health, queueCount) {
		const nextValue = health.next_flush_at || health.next_queue_run_at;
		if (queueCount <= 0 || !nextValue) {
			return false;
		}
		const nextRun = Date.parse(String(nextValue));
		return Number.isFinite(nextRun) && nextRun <= Date.now();
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

		const panel = el('div', 'npcink-toolbox__knowledge-summary');
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
				? 'Cloud accepted the index refresh. Toolbox is watching status; search results may remain stale until the index is ready.'
				: 'Cloud returned a site knowledge sync response.'
		);
		if (!result) {
			return;
		}

		const meta = el('div', 'npcink-toolbox__result-meta');
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
			result.appendChild(el('div', 'npcink-toolbox__result-notice is-pending', payload.message));
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

		const meta = el('div', 'npcink-toolbox__result-meta');
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
			result.appendChild(el('div', 'npcink-toolbox__result-notice is-pending', 'Cloud rerank failed; vector order was used as the fallback.'));
		}
		if (hiddenSemanticCount > 0) {
			result.appendChild(el('div', 'npcink-toolbox__result-notice is-pending', String(hiddenSemanticCount) + (hiddenSemanticCount === 1 ? t(' semantic-only result hidden because exact query matches were found. Expand Search payload to inspect them.') : t(' semantic-only results hidden because exact query matches were found. Expand Search payload to inspect them.'))));
		}
		renderHandoff(result, payload.handoff || payload.agent_handoff);

		if (visibleResults.length) {
			const section = createSection('Results');
			const list = el('div', 'npcink-toolbox__result-list');
			visibleResults.forEach((item) => {
				const row = el('article', 'npcink-toolbox__result-item');
				row.appendChild(el('h4', '', item.title || 'Indexed source'));
				if (item.url) {
					row.appendChild(createLink(item.url, item.url));
				}
				const context = item.match_context || item.chunk || '';
				const contextNode = el('p', '');
				appendHighlightedText(contextNode, truncate(context, 420), item.exact_query_match ? query : '');
				row.appendChild(contextNode);
				const rowMeta = el('div', 'npcink-toolbox__result-meta');
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
		const shellPayload = Object.assign({}, payload, { provider_label: 'cloud_web_search' });
		const result = renderShell(
			form,
			shellPayload,
			'Cloud web search',
			results.length
				? results.length + ' external search results returned from Cloud.'
				: 'Cloud search completed without usable external results.'
		);
		if (!result) {
			return;
		}

		const meta = el('div', 'npcink-toolbox__result-meta');
		appendMeta(meta, 'Status', payload.status ? formatLabel(payload.status) : '');
		appendMeta(meta, 'Intent', payload.intent ? formatLabel(payload.intent) : '');
		appendMeta(meta, 'Cloud provider mode', payload.provider_mode ? formatLabel(payload.provider_mode) : 'Cloud Managed');
		appendMeta(meta, 'Actual channel', payload.provider ? formatLabel(payload.provider) : '');
		appendMeta(meta, 'Provider calls', payload.provider_call_count);
		appendMeta(meta, 'Run', payload.run_id);
		if (payload.usage_summary && typeof payload.usage_summary === 'object') {
			appendMeta(meta, 'Failure', payload.usage_summary.failure_reason ? formatLabel(payload.usage_summary.failure_reason) : '');
		}
		if (payload.evidence_gate && typeof payload.evidence_gate === 'object') {
			appendMeta(meta, 'Evidence', payload.evidence_gate.status ? formatLabel(payload.evidence_gate.status) : '');
			appendMeta(meta, 'Sources', payload.evidence_gate.source_count);
		}
		if (payload.evidence_pack && typeof payload.evidence_pack === 'object') {
			appendMeta(meta, 'Pack', payload.evidence_pack.pack_type ? formatLabel(payload.evidence_pack.pack_type) : '');
			appendMeta(meta, 'Pack contract', payload.evidence_pack.contract_version || payload.output_contract || 'search_evidence_pack.v1');
			appendMeta(meta, 'Source priority', payload.source_priority || payload.evidence_pack.source_priority ? formatLabel(payload.source_priority || payload.evidence_pack.source_priority) : '');
		}
		if (meta.childNodes.length) {
			result.appendChild(meta);
		}

		if (payload.evidence_pack && typeof payload.evidence_pack === 'object' && payload.evidence_pack.guidance) {
			result.appendChild(el('div', 'npcink-toolbox__result-notice is-pending', String(payload.evidence_pack.guidance)));
		}

		if (results.length) {
			const section = createSection('Results');
			const list = el('div', 'npcink-toolbox__result-list');
			results.forEach((item) => {
				const row = el('article', 'npcink-toolbox__result-item');
				row.appendChild(el('h4', '', item.title || item.url || 'Search result'));
				if (item.url) {
					row.appendChild(createLink(item.url, item.url));
				}
				row.appendChild(el('p', '', truncate(item.snippet || '', 360)));
				const rowMeta = el('div', 'npcink-toolbox__result-meta');
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

		const meta = el('div', 'npcink-toolbox__result-meta');
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
			result.appendChild(el('div', 'npcink-toolbox__result-notice is-warning', 'Check Cloud connection before relying on external evidence.'));
		}

		if (Array.isArray(search.sources) && search.sources.length) {
			const section = createSection('Attached sources');
			const list = el('div', 'npcink-toolbox__result-list');
			search.sources.forEach((item) => {
				const row = el('article', 'npcink-toolbox__result-item');
				row.appendChild(el('h4', '', item.title || item.url || 'Attached source'));
				if (item.url) {
					row.appendChild(createLink(item.url, item.url));
				}
				row.appendChild(el('p', '', truncate(item.summary || item.snippet || '', 280)));
				const rowMeta = el('div', 'npcink-toolbox__result-meta');
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
			const notice = el('div', 'npcink-toolbox__result-notice is-warning', payload.research.error);
			notice.appendChild(createLink(
				toolboxAdminUrl({
					toolbox_tab: 'cloud-checks',
					toolbox_cloud_check: 'search',
					toolbox_cloud_check_group: 'search-test',
					toolbox_tool: null,
				}),
				'Open Cloud search check'
			));
			result.appendChild(notice);
		} else if (payload.research) {
			const section = createSection('External search');
			section.appendChild(el('div', 'npcink-toolbox__result-notice is-pending', 'Live Cloud web search verification belongs in Cloud Checks. Use this bundle for combined fallback planning and handoff context.'));
			section.appendChild(createLink(
				toolboxAdminUrl({
					toolbox_tab: 'cloud-checks',
					toolbox_cloud_check: 'search',
					toolbox_cloud_check_group: 'search-test',
					toolbox_tool: null,
				}),
				'Open Cloud search check'
			));
			result.appendChild(section);
		}

		if (payload.images && payload.images.error) {
			result.appendChild(el('div', 'npcink-toolbox__result-notice is-warning', payload.images.error));
		} else if (payload.images) {
			renderImageList(result, payload.images.images);
		}

		if (payload.knowledge && payload.knowledge.error) {
			result.appendChild(el('div', 'npcink-toolbox__result-notice is-warning', payload.knowledge.error));
		} else if (payload.knowledge) {
			renderPointList(result, payload.knowledge.points);
		}

		renderHandoff(result, payload.handoff);
		result.appendChild(createRawDetails(payload, 'Complete payload'));
	}

	function renderHostedAiQualityGuardrails(container, payload) {
		const checklist = Array.isArray(payload.review_checklist) ? payload.review_checklist : [];
		const rejectIf = Array.isArray(payload.reject_if) ? payload.reject_if : [];
		const outputShape = payload.output_shape && typeof payload.output_shape === 'object' ? payload.output_shape : {};
		if (!checklist.length && !rejectIf.length && !Object.keys(outputShape).length) {
			return;
		}

		const section = createSection('Review checklist');
		section.appendChild(el('div', 'npcink-toolbox__result-notice is-pending', 'Use this short checklist before copying any AI suggestion into a Core proposal or draft.'));

		if (checklist.length) {
			const list = el('ul', 'npcink-toolbox__step-list');
			checklist.slice(0, 5).forEach((item) => {
				list.appendChild(el('li', '', item));
			});
			section.appendChild(list);
		}

		if (rejectIf.length) {
			const warning = el('div', 'npcink-toolbox__result-notice is-warning', 'Reject or revise the result if any of these are true:');
			const list = el('ul', 'npcink-toolbox__step-list');
			rejectIf.slice(0, 5).forEach((item) => {
				list.appendChild(el('li', '', item));
			});
			warning.appendChild(list);
			section.appendChild(warning);
		}

		if (Object.keys(outputShape).length) {
			section.appendChild(createRawDetails(outputShape, 'Expected output shape'));
		}

		container.appendChild(section);
	}

	function renderHostedAiContentSupport(form, payload) {
		const intent = String(payload.intent || '');
		const titleByIntent = {
			title_summary: 'Title and summary suggestions',
			article_outline: 'Outline suggestions',
			polish_notes: 'Polish suggestions'
		};
		const summaryByIntent = {
			title_summary: 'Review concise title, excerpt, SEO, and answer-summary options before using them anywhere.',
			article_outline: 'Use this as a working structure for a human-written article, not as generated body copy.',
			polish_notes: 'Review the revised wording and keep the original meaning under editor control.'
		};
		const result = renderShell(
			form,
			payload,
			titleByIntent[intent] || 'AI suggestions',
			summaryByIntent[intent] || 'Review the hosted suggestions before moving anything into a Core proposal.'
		);
		if (!result) {
			return;
		}

		const meta = el('div', 'npcink-toolbox__result-meta');
		appendMeta(meta, 'Profile', payload.hosted_profile || 'text.ai');
		appendMeta(meta, 'Model', payload.model_id || '');
		appendMeta(meta, 'Intent', payload.intent ? formatLabel(payload.intent) : '');
		appendMeta(meta, 'Status', payload.status ? formatLabel(payload.status) : '');
		appendMeta(meta, 'Run', payload.run_id || '');
		result.appendChild(meta);

		renderHostedAiQualityGuardrails(result, payload);

		if (payload.output_text) {
			const pre = el('pre', 'npcink-toolbox__result-raw');
			pre.textContent = String(payload.output_text);
			result.appendChild(pre);
		}

		result.appendChild(el('div', 'npcink-toolbox__result-notice is-pending', 'Core proposal approval is required before any WordPress write.'));
		result.appendChild(createRawDetails(payload, 'Complete payload'));
	}

	function renderHostedAiSiteHelper(form, payload) {
		const intent = String(payload.intent || '');
		const titleByIntent = {
			media_alt_suggestions: 'Media ALT suggestions',
			content_snapshot_suggestions: 'Content snapshot suggestions'
		};
		const summaryByIntent = {
			media_alt_suggestions: 'Review ALT and caption ideas for images used by the selected article before any media-library edit.',
			content_snapshot_suggestions: 'Use these as content opportunities from a bounded sample, not as a full site audit.'
		};
		const result = renderShell(
			form,
			payload,
			titleByIntent[intent] || 'AI site-helper suggestions',
			summaryByIntent[intent] || 'Review the hosted suggestions before moving anything into a Core proposal.'
		);
		if (!result) {
			return;
		}

		const meta = el('div', 'npcink-toolbox__result-meta');
		appendMeta(meta, 'Profile', payload.hosted_profile || 'text.ai');
		appendMeta(meta, 'Model', payload.model_id || '');
		appendMeta(meta, 'Intent', payload.intent ? formatLabel(payload.intent) : '');
		appendMeta(meta, 'Status', payload.status ? formatLabel(payload.status) : '');
		appendMeta(meta, 'Run', payload.run_id || '');
		result.appendChild(meta);

		renderHostedAiQualityGuardrails(result, payload);

		renderMediaAltCaptionReviewSet(result, payload.media_alt_caption_review_set);

		if (payload.output_text) {
			const pre = el('pre', 'npcink-toolbox__result-raw');
			pre.textContent = String(payload.output_text);
			result.appendChild(pre);
		}

		result.appendChild(el('div', 'npcink-toolbox__result-notice is-pending', 'Suggestions only. No media or WordPress content was changed.'));
		result.appendChild(createRawDetails(payload, 'Complete payload'));
	}

	function renderMediaAltCaptionReviewSet(container, reviewSet) {
		reviewSet = asObject(reviewSet);
		if (!reviewSet.contract_version) {
			return;
		}

		const section = createSection('Media ALT/caption review set');
		const eligibility = asObject(reviewSet.eligibility_summary);
		const selectedItems = asArray(reviewSet.selected_items);
		const blockedItems = asArray(reviewSet.blocked_items);
		const imageContextRequest = asObject(reviewSet.image_context_evidence_request);
		const imageContextRequestItems = asArray(imageContextRequest.items);
		const postContext = asObject(reviewSet.post_context);
		const meta = el('div', 'npcink-toolbox__result-meta');
		appendMeta(meta, 'Eligible', eligibility.eligible_count);
		appendMeta(meta, 'Selected', eligibility.selected_count || selectedItems.length);
		appendMeta(meta, 'Blocked', eligibility.blocked_count || blockedItems.length);
		appendMeta(meta, 'Scanned', eligibility.scanned_count);
		appendMeta(meta, 'Contract', reviewSet.contract_version);
		appendMeta(meta, 'Source', reviewSet.source_policy ? formatLabel(reviewSet.source_policy) : '');
		appendMeta(meta, 'Scope', reviewSet.media_scope ? formatLabel(reviewSet.media_scope) : '');
		appendMeta(meta, 'Post', postContext.post_id || '');
		appendMeta(meta, 'Retryable', reviewSet.retryable === true ? 'Yes' : 'No');
		if (meta.childNodes.length) {
			section.appendChild(meta);
		}

		section.appendChild(el('div', 'npcink-toolbox__result-notice is-ok', 'Review-only. No media metadata was changed.'));
		if (reviewSet.operator_next_action) {
			section.appendChild(el('div', 'npcink-toolbox__result-notice is-pending', 'Next action: ' + formatLabel(reviewSet.operator_next_action)));
		}
		if (imageContextRequest.contract_version && imageContextRequestItems.length) {
			section.appendChild(el('div', 'npcink-toolbox__result-notice is-pending', 'Weak metadata found. Cloud-owned image context evidence can be requested for ' + String(imageContextRequestItems.length) + ' item(s); no local vision model or media write is created.'));
		}

		if (!selectedItems.length) {
			section.appendChild(el('div', 'npcink-toolbox__result-notice is-warning', 'No media items were selected for this review set. Check blocked reasons or rebuild with a different sample.'));
		} else {
			const list = el('div', 'npcink-toolbox__batch-list');
			selectedItems.slice(0, 10).forEach((item, index) => {
				const row = el('div', 'npcink-toolbox__batch-row');
				const body = el('span', 'npcink-toolbox__batch-row-body');
				const title = item.title || item.filename || 'Media item ' + (index + 1);
				body.appendChild(el('strong', '', '#' + String(item.attachment_id || '') + ' ' + String(title)));
				const detail = [
					item.current_alt_status ? 'ALT ' + formatLabel(item.current_alt_status) : '',
					item.current_caption_status ? 'Caption ' + formatLabel(item.current_caption_status) : '',
					Array.isArray(item.review_reasons) && item.review_reasons.length ? 'Reasons: ' + item.review_reasons.map(formatLabel).join(', ') : '',
				].filter(Boolean).join(' - ');
				if (detail) {
					body.appendChild(el('small', '', detail));
				}
				const altCandidates = asArray(item.alt_candidates).filter(Boolean);
				if (altCandidates.length) {
					body.appendChild(el('small', '', 'ALT candidates: ' + altCandidates.slice(0, 2).join(' / ')));
				}
				if (item.caption_candidate) {
					body.appendChild(el('small', '', 'Caption candidate: ' + String(item.caption_candidate)));
				}
				const evidence = asObject(item.image_context_evidence);
				if (evidence.visual_summary) {
					body.appendChild(el('small', '', 'Image context evidence: ' + String(evidence.visual_summary)));
				}
				const itemMeta = el('div', 'npcink-toolbox__result-meta');
				appendMeta(itemMeta, 'Visual check', item.needs_human_visual_check ? 'Required' : '');
				appendMeta(itemMeta, 'Evidence', evidence.contract_version ? formatLabel(evidence.source || 'cloud_or_host_runtime') : '');
				appendMeta(itemMeta, 'Final path', item.target_write_path || reviewSet.final_write_path);
				appendMeta(itemMeta, 'Direct write', item.direct_wordpress_write === false ? 'Disabled' : '');
				if (itemMeta.childNodes.length) {
					body.appendChild(itemMeta);
				}
				row.appendChild(body);
				list.appendChild(row);
			});
			section.appendChild(list);
		}

		if (blockedItems.length) {
			const details = document.createElement('details');
			details.className = 'npcink-toolbox__result-details';
			details.appendChild(el('summary', '', 'Blocked items'));
			const blockedList = el('div', 'npcink-toolbox__batch-list');
			blockedItems.slice(0, 10).forEach((item) => {
				const row = el('div', 'npcink-toolbox__batch-row is-skipped');
				const body = el('span', 'npcink-toolbox__batch-row-body');
				body.appendChild(el('strong', '', '#' + String(item.attachment_id || '') + ' blocked'));
				body.appendChild(el('small', '', formatLabel(item.blocked_reason || 'not_selected')));
				row.appendChild(body);
				blockedList.appendChild(row);
			});
			details.appendChild(blockedList);
			section.appendChild(details);
		}

		if (imageContextRequest.contract_version && imageContextRequestItems.length) {
			section.appendChild(createRawDetails(imageContextRequest, 'Image context evidence request'));
		}

		container.appendChild(section);
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
			section.appendChild(el('div', 'npcink-toolbox__result-notice is-warning', emptyMessage || 'No suggestions returned.'));
			container.appendChild(section);
			return;
		}

		const list = el('div', 'npcink-toolbox__result-list');
		items.slice(0, 10).forEach((item, index) => {
			const row = el('article', 'npcink-toolbox__result-item');
			const titleText = item.name || item.title || item.label || item.source_title || item.url || item.id || 'Candidate ' + (index + 1);
			row.appendChild(el('h4', '', titleText));
			const detail = [
				item.value || '',
				item.reason || item.detail || item.excerpt || item.snippet || item.source_url || item.status || '',
				Array.isArray(item.matched_tokens) && item.matched_tokens.length ? 'Matched: ' + item.matched_tokens.slice(0, 6).join(', ') : '',
			].filter(Boolean).join(' · ');
			if (detail) {
				row.appendChild(el('p', '', truncate(detail, 260)));
			}
			if (item.url) {
				row.appendChild(createLink(item.url, item.url));
			}
			const meta = el('div', 'npcink-toolbox__result-meta');
			appendMeta(meta, 'Score', item.score);
			appendMeta(meta, 'Taxonomy', item.taxonomy ? formatLabel(item.taxonomy) : '');
			appendMeta(meta, 'Vocabulary', item.controlled_vocabulary_status ? formatLabel(item.controlled_vocabulary_status) : '');
			appendMeta(meta, 'Normalize', item.normalization_key);
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

	function discoverabilitySuggestionItems(section) {
		const suggestions = section && section.candidate_suggestions && typeof section.candidate_suggestions === 'object' ? section.candidate_suggestions : {};
		return Object.keys(suggestions).map((field) => ({
			name: formatLabel(field),
			detail: String(suggestions[field] || ''),
		}));
	}

	function metadataDeltaSummaryItems(delta) {
		if (!delta || typeof delta !== 'object') {
			return [];
		}
		const issue = delta.issue_record && typeof delta.issue_record === 'object' ? delta.issue_record : {};
		const diagnosis = delta.diagnosis && typeof delta.diagnosis === 'object' ? delta.diagnosis : {};
		const authorization = delta.authorization && typeof delta.authorization === 'object' ? delta.authorization : {};
		return [
			{
				name: 'Issue',
				detail: issue.user_expression || 'Current post metadata can be reviewed for discoverability.',
			},
			{
				name: 'Diagnosis',
				detail: [
					diagnosis.summary_quality ? 'Summary: ' + formatLabel(diagnosis.summary_quality) : '',
					diagnosis.taxonomy_quality ? 'Taxonomy: ' + formatLabel(diagnosis.taxonomy_quality) : '',
					diagnosis.evidence_strength ? 'Evidence: ' + formatLabel(diagnosis.evidence_strength) : '',
				].filter(Boolean).join(' · '),
			},
			{
				name: 'Authorization',
				detail: [
					authorization.classification ? formatLabel(authorization.classification) : 'Suggestion only',
					authorization.handoff_preview_ref || '',
					authorization.reason || '',
				].filter(Boolean).join(' · '),
			},
		];
	}

	function metadataDeltaExcerptItems(delta) {
		const excerpt = delta && delta.delta && delta.delta.excerpt && typeof delta.delta.excerpt === 'object' ? delta.delta.excerpt : null;
		if (!excerpt || !excerpt.recommended) {
			return [];
		}
		return [{
			name: 'Recommended excerpt',
			value: excerpt.recommended,
			reason: excerpt.reason || '',
		}];
	}

	function metadataDeltaCheckItems(delta) {
		const checks = delta && delta.outcome_contract && Array.isArray(delta.outcome_contract.checks) ? delta.outcome_contract.checks : [];
		return checks.map((check) => ({
			name: formatLabel(check),
		}));
	}

	function renderContentMetadataDelta(container, delta) {
		if (!delta || typeof delta !== 'object') {
			return;
		}

		const shell = createSection('Content Metadata Delta');
		const meta = el('div', 'npcink-toolbox__result-meta');
		appendMeta(meta, 'Artifact', delta.artifact_type ? formatLabel(delta.artifact_type) : '');
		appendMeta(meta, 'Post', delta.target_post_id || '');
		appendMeta(meta, 'Write posture', delta.write_posture || 'suggestion_only');
		appendMeta(meta, 'Final path', delta.final_write_path || 'core_proposal_required');
		appendMeta(meta, 'Direct write', delta.direct_wordpress_write === false ? 'disabled' : '');
		if (meta.childNodes.length) {
			shell.appendChild(meta);
		}
		container.appendChild(shell);

		renderSupportItems(container, 'Delta diagnosis', metadataDeltaSummaryItems(delta), 'No metadata diagnosis returned.');
		renderSupportItems(container, 'Delta excerpt', metadataDeltaExcerptItems(delta), 'No excerpt delta returned.');
		renderSupportItems(container, 'Delta categories', delta.delta && Array.isArray(delta.delta.categories) ? delta.delta.categories : [], 'No category delta returned.');
		renderSupportItems(container, 'Delta tags', delta.delta && Array.isArray(delta.delta.tags) ? delta.delta.tags : [], 'No tag delta returned.');
		renderSupportItems(container, 'Outcome checks', metadataDeltaCheckItems(delta), 'No outcome checks returned.');
	}

	function renderSummaryTermsOptimization(container, section) {
		if (!section || typeof section !== 'object') {
			return;
		}

		const summary = section.summary_candidates && typeof section.summary_candidates === 'object' ? section.summary_candidates : {};
		const shell = createSection('Summary and terms optimization');
		const meta = el('div', 'npcink-toolbox__result-meta');
		appendMeta(meta, 'Artifact', section.artifact_type ? formatLabel(section.artifact_type) : '');
		appendMeta(meta, 'Write posture', section.write_posture || 'suggestion_only');
		appendMeta(meta, 'Final path', section.final_write_path || 'core_proposal_required');
		if (section.input_scope) {
			appendMeta(meta, 'Input scope', section.input_scope.label || (section.input_scope.id ? formatLabel(section.input_scope.id) : ''));
			appendMeta(meta, 'Scope mode', section.input_scope.operator_selected_mode ? formatLabel(section.input_scope.operator_selected_mode) : '');
		}
		if (meta.childNodes.length) {
			shell.appendChild(meta);
		}
		if (summary.status === 'error') {
			shell.appendChild(el('div', 'npcink-toolbox__result-notice is-warning', summary.message || 'AI summary candidates were unavailable.'));
		} else if (summary.output_text) {
			const pre = el('pre', 'npcink-toolbox__result-pre');
			pre.textContent = truncate(summary.output_text, 900);
			shell.appendChild(pre);
		}
		container.appendChild(shell);

		if (section.summary_layers) {
			renderSupportItems(container, 'Summary layers', supportItems(section.summary_layers), 'No summary layer candidates returned.');
		}
		if (section.content_metadata_delta) {
			renderContentMetadataDelta(container, section.content_metadata_delta);
		}
		renderSupportItems(container, 'Category candidates', section.category_candidates || [], 'No matching existing categories found.');
		renderSupportItems(container, 'Tag candidates', section.tag_candidates || [], 'No matching existing tags found.');
		if (section.proposed_new_terms) {
			renderSupportItems(container, 'Proposed new terms', supportItems(section.proposed_new_terms), section.proposed_new_terms.empty_message || 'No proposed new terms returned.');
		}
		if (section.optimization_strategy && Array.isArray(section.optimization_strategy.ranking_signals)) {
			renderSupportItems(container, 'Ranking and dedupe strategy', section.optimization_strategy.ranking_signals, 'No ranking strategy returned.');
		}
		if (section.discoverability) {
			renderSupportItems(container, 'Discoverability suggestions', discoverabilitySuggestionItems(section.discoverability), 'No discoverability suggestions returned.');
		}
		if (section.related_content) {
			renderSupportItems(container, 'Related Site Knowledge', supportItems(section.related_content), 'No related public content returned.');
		}
		if (Array.isArray(section.risk_notes)) {
			renderSupportItems(container, 'Review notes', section.risk_notes.map((note) => ({ name: note })), 'No review notes returned.');
		}
			if (section.review_metrics) {
				renderSupportItems(container, 'Review metrics', supportItems(section.review_metrics), 'No review metrics returned.');
			}
			if (section.handoff_preview) {
				if (Array.isArray(section.handoff_preview.core_handoff_candidates)) {
					renderSupportItems(container, 'Core handoff candidates', section.handoff_preview.core_handoff_candidates, 'No Core handoff candidates returned.');
				}
				renderSupportItems(container, 'Handoff preview', (section.handoff_preview.next_steps || []).map((step) => ({ name: step })), 'No handoff preview returned.');
				container.appendChild(createRawDetails(section.handoff_preview, 'Handoff preview packet'));
		}
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

		const meta = el('div', 'npcink-toolbox__result-meta');
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
		if (sections.summary_terms_optimization) {
			renderSummaryTermsOptimization(result, sections.summary_terms_optimization);
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
				result.appendChild(el('div', 'npcink-toolbox__result-notice is-warning', sections.image_candidates.message || 'Image candidate search failed.'));
			} else {
				renderImageList(result, sections.image_candidates.images || sections.image_candidates.image_candidates || sections.image_candidates.candidates);
			}
		}
		if (sections.discoverability && sections.discoverability.candidate_suggestions) {
			renderSupportItems(result, 'Discoverability suggestions', discoverabilitySuggestionItems(sections.discoverability), 'No discoverability suggestions returned.');
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

		const meta = el('div', 'npcink-toolbox__result-meta');
		appendMeta(meta, 'Artifact', payload.artifact_type);
		appendMeta(meta, 'Batch', payload.batch_id);
		appendMeta(meta, 'Risk', risk.risk_level ? formatLabel(risk.risk_level) : '');
		appendMeta(meta, 'Ready', ready ? 'Yes' : 'No');
		appendMeta(meta, 'Final ability', action.target_ability_id);
		appendMeta(meta, 'Post status', actionInput.status);
		result.appendChild(meta);

		if (Array.isArray(risk.blocked_claims) && risk.blocked_claims.length) {
			result.appendChild(el('div', 'npcink-toolbox__result-notice is-error', 'Blocked claims must be resolved before Core handoff.'));
		}
		if (risk.risk_level === 'high') {
			result.appendChild(el('div', 'npcink-toolbox__result-notice is-warning', 'High-risk plans are expected to fail Core proposal intake until revised.'));
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

		const meta = el('div', 'npcink-toolbox__result-meta');
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
			result.appendChild(el('div', 'npcink-toolbox__result-notice is-warning', t('Review required: ') + risk.needs_review.join(', ')));
		}
		if (Array.isArray(risk.blocked_claims) && risk.blocked_claims.length) {
			result.appendChild(el('div', 'npcink-toolbox__result-notice is-error', 'Blocked claims must be removed before Core handoff.'));
		}

		renderArtifactSummary(result, 'Goal brief', payload.article_goal_brief);
		renderArtifactSummary(result, 'Evidence pack', payload.research_evidence_pack);
		if (payload.image_candidates && payload.image_candidates.error) {
			result.appendChild(el('div', 'npcink-toolbox__result-notice is-warning', payload.image_candidates.error));
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

		const meta = el('div', 'npcink-toolbox__result-meta');
		appendMeta(meta, 'Attachment', payload.attachment_id);
		appendMeta(meta, 'Ability', payload.ability_id);
		appendMeta(meta, 'Format', abilityInput.preferred_format ? String(abilityInput.preferred_format).toUpperCase() : '');
		appendMeta(meta, 'Max width', abilityInput.target_max_width ? abilityInput.target_max_width + 'px' : '');
		appendMeta(meta, 'Quality', abilityInput.quality);
		appendMeta(meta, 'Crop', mediaDerivativeCropLabel(abilityInput));
		appendMeta(meta, 'Watermark', mediaDerivativeWatermarkLabel(abilityInput));
		appendMeta(meta, 'Toolbox policy', payload.toolbox_policy_available ? 'Available' : 'Defaults');
		result.appendChild(meta);

		if (Array.isArray(payload.warnings) && payload.warnings.length) {
			payload.warnings.forEach((warning) => {
				result.appendChild(el('div', 'npcink-toolbox__result-notice is-warning', warning));
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

		const meta = el('div', 'npcink-toolbox__result-meta');
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
			image.className = 'npcink-toolbox__image-preview';
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
			result.appendChild(el('div', 'npcink-toolbox__result-notice is-ok', t('Attribution: ') + (candidate.attribution || preview.attribution)));
		}
		if (candidate.requires_human_license_review) {
			result.appendChild(el('div', 'npcink-toolbox__result-notice is-warning', 'License or source review is required before approval.'));
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
		Object.assign(input, mediaDerivativeCropInput(raw));
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

	function mediaDerivativeCropInput(raw) {
		raw = raw || {};
		const aspectRatio = String(raw.crop_aspect_ratio || '').trim();
		if (!aspectRatio) {
			return {};
		}
		const allowedRatios = ['16:9', '4:3', '1:1', '3:4', '9:16'];
		const ratio = allowedRatios.includes(aspectRatio) ? aspectRatio : '16:9';
		const allowedPositions = ['top_left', 'top', 'top_right', 'left', 'center', 'right', 'bottom_left', 'bottom', 'bottom_right'];
		const position = allowedPositions.includes(String(raw.crop_position || 'center')) ? String(raw.crop_position || 'center') : 'center';
		return {
			crop: {
				type: 'aspect_ratio',
				aspect_ratio: ratio,
				position,
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
		return 'Toolbox default';
	}

	function mediaDerivativeCropLabel(input) {
		if (!input || typeof input !== 'object' || !input.crop || typeof input.crop !== 'object') {
			return '';
		}
		const crop = input.crop;
		return [
			crop.aspect_ratio ? String(crop.aspect_ratio) : '',
			crop.position ? formatLabel(crop.position) : '',
		].filter(Boolean).join(' · ');
	}

	function dimensionsFromText(value, fallbackWidth, fallbackHeight) {
		const parts = String(value || '').toLowerCase().split('x');
		const width = parseInt(parts[0] || String(fallbackWidth || 0), 10) || fallbackWidth || 0;
		const height = parseInt(parts[1] || parts[0] || String(fallbackHeight || 0), 10) || fallbackHeight || 0;
		return { width, height };
	}

	function localDateString(date) {
		const pad = (number) => String(number).padStart(2, '0');
		return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
	}

	function monthBounds(offset) {
		const now = new Date();
		const start = new Date(now.getFullYear(), now.getMonth() + offset, 1);
		const end = new Date(now.getFullYear(), now.getMonth() + offset + 1, 0);
		return {
			date_from: localDateString(start),
			date_to: localDateString(end),
		};
	}

	function resolveMediaBatchScopePreset(raw) {
		raw = raw || {};
		const preset = String(raw.batch_scope_preset || 'current_month');
		let scope = {};
		if (preset === 'current_month') {
			scope = monthBounds(0);
		} else if (preset === 'previous_month') {
			scope = monthBounds(-1);
		} else if (preset === 'custom') {
			scope = {};
		}

		if (raw.batch_date_from) {
			scope.date_from = String(raw.batch_date_from);
		}
		if (raw.batch_date_to) {
			scope.date_to = String(raw.batch_date_to);
		}
		return scope;
	}

	function resolveMediaBatchRecipeDefaults(raw) {
		raw = raw || {};
		const recipe = String(raw.batch_recipe || 'smart_optimize');
		const selectedFormat = String(raw.batch_target_format || 'webp');
		if (recipe === 'resize_only') {
			return {
				recipe,
				target_format: 'original',
				exclude_formats: 'gif,svg',
				min_dimensions: '800x800',
			};
		}
		if (recipe === 'convert_format') {
			return {
				recipe,
				target_format: selectedFormat,
				exclude_formats: selectedFormat + ',gif,svg',
				min_dimensions: '0x0',
			};
		}
		if (recipe === 'watermark') {
			return {
				recipe,
				target_format: selectedFormat,
				exclude_formats: 'gif,svg',
				min_dimensions: '0x0',
			};
		}
		return {
			recipe,
			target_format: selectedFormat || 'webp',
			exclude_formats: 'webp,gif,svg',
			min_dimensions: '800x800',
		};
	}

	function syncMediaBatchFixedFlow(form) {
		const recipeField = form.querySelector('[name="batch_recipe"]');
		const formatField = form.querySelector('[name="batch_target_format"]');
		if (recipeField instanceof HTMLSelectElement && formatField instanceof HTMLSelectElement) {
			if (recipeField.value === 'resize_only') {
				formatField.value = 'original';
			} else if (recipeField.value === 'smart_optimize' && formatField.value === 'original') {
				formatField.value = 'webp';
			}
		}

		const scopeField = form.querySelector('[name="batch_scope_preset"]');
		const advanced = form.querySelector('.npcink-toolbox__advanced-filters');
		if (scopeField instanceof HTMLSelectElement && advanced instanceof HTMLDetailsElement && scopeField.value === 'custom') {
			advanced.open = true;
		}
	}

	function mediaDerivativeBatchPlanInput(form) {
		const raw = serialize(form);
		const scope = resolveMediaBatchScopePreset(raw);
		const recipe = resolveMediaBatchRecipeDefaults(raw);
		const rawDimensions = String(raw.batch_min_dimensions || '').trim();
		const dimensionsValue = rawDimensions && !(rawDimensions === '800x800' && recipe.recipe !== 'smart_optimize') ? rawDimensions : recipe.min_dimensions || '0x0';
		const dimensions = dimensionsFromText(dimensionsValue, 0, 0);
		const targetFormat = String(recipe.target_format || raw.batch_target_format || 'webp');
		const rawExcludeFormats = String(raw.batch_exclude_formats || '').trim();
		const excludeFormatsValue = rawExcludeFormats && !(rawExcludeFormats === 'webp,gif,svg' && recipe.recipe !== 'smart_optimize') ? rawExcludeFormats : recipe.exclude_formats || targetFormat;
		const input = {
			mime_type: 'image',
			target_format: targetFormat,
			exclude_formats: commaList(excludeFormatsValue),
			min_width: dimensions.width,
			min_height: dimensions.height,
			max_items: parseInt(raw.batch_max_items || '5', 10) || 5,
		};
		if (scope.date_from) {
			input.date_from = String(scope.date_from);
		}
		if (scope.date_to) {
			input.date_to = String(scope.date_to) + ' 23:59:59';
		}
		if (raw.max_width) {
			input.target_max_width = raw.max_width;
		}
		if (raw.quality) {
			input.quality = raw.quality;
		}
		const cropInput = mediaDerivativeCropInput(raw);
		if (cropInput.crop) {
			input.crop = cropInput.crop;
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
			backup_suffix: 'npcink-cloud-backup',
			dry_run: true,
			commit: false,
			idempotency_key: 'media-derivative-' + String(artifact.artifact_id || artifact.id || state.runId || Date.now()),
		};
	}

	function preflightInputFromState(state) {
		const proposalInput = proposalInputFromState(state);
		return {
			attachment_id: proposalInput.attachment_id,
			derivative_artifact: proposalInput.derivative_artifact,
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

	function asArray(value) {
		return Array.isArray(value) ? value : [];
	}

	function asObject(value) {
		return value && typeof value === 'object' && !Array.isArray(value) ? value : {};
	}

	function firstFilled(values, fallback) {
		for (let index = 0; index < values.length; index += 1) {
			const value = values[index];
			if (value !== undefined && value !== null && value !== '') {
				return value;
			}
		}
		return fallback;
	}

	function integerOr(value, fallback) {
		const numeric = parseInt(value, 10);
		return Number.isFinite(numeric) ? numeric : fallback;
	}

	function mediaBatchBlockedReason(item) {
		return firstFilled([
			item && item.blocked_reason,
			item && item.reason,
			item && item.message,
			item && item.status,
		], 'blocked');
	}

	function mediaBatchRetryGuidanceText(value) {
		const guidance = asObject(value);
		return firstFilled([
			guidance.operator_next_action,
			guidance.reason,
			typeof value === 'string' ? value : '',
		], '');
	}

	function buildLocalAutomationMediaConversionReviewSet(plan, candidates, blockedItems) {
		const filters = asObject(plan.filters);
		const eligibility = asObject(plan.eligibility_summary);
		const targetFormat = firstFilled([
			filters.target_format,
			candidates.length ? candidates[0].target_format : '',
			candidates.length ? asObject(candidates[0].cloud_request_input).preferred_format : '',
		], 'webp');
		const selectedItems = candidates.map((candidate) => {
			const attachmentId = integerOr(candidate.attachment_id || candidate.id, 0);
			return {
				attachment_id: attachmentId,
				source_mime_type: firstFilled([candidate.mime_type, candidate.source_mime_type], 'image/unknown'),
				target_format: firstFilled([candidate.target_format, asObject(candidate.cloud_request_input).preferred_format], targetFormat),
				preview_required: true,
				target_ability_id: 'npcink-abilities-toolkit/build-media-derivative-cloud-request',
				proposal_path: 'core_proposal_required',
				result_ref: firstFilled([candidate.result_ref, candidate.result_reference], 'attachment:' + String(attachmentId)),
				direct_wordpress_write: false,
			};
		});
		const normalizedBlocked = blockedItems.map((item) => ({
			attachment_id: integerOr(item.attachment_id || item.id, 0),
			source_mime_type: firstFilled([item.mime_type, item.source_mime_type], 'image/unknown'),
			blocked_reason: mediaBatchBlockedReason(item),
			operator_next_action: firstFilled([item.operator_next_action], 'adjust_filters_or_skip'),
			retryable: false,
		}));
		const retryable = Boolean(plan.retryable);
		const selectedIds = selectedItems.concat(normalizedBlocked)
			.map((item) => integerOr(item.attachment_id, 0))
			.filter(Boolean)
			.filter((value, index, list) => list.indexOf(value) === index);

		return {
			contract_version: 'npcink_local_automation_media_conversion_review_set.v1',
			runtime_owner: 'npcink-local-automation-runtime',
			operation_family: 'media_conversion',
			mode: 'governed_review_set',
			trigger: 'operator_manual_review',
			scope: {
				object_type: 'attachment',
				source: 'media_library',
				target_format: targetFormat,
				max_items: integerOr(filters.max_items, candidates.length),
				selected_attachment_ids: selectedIds,
			},
			eligibility_summary: {
				items_total: integerOr(firstFilled([eligibility.total_count, eligibility.items_total], selectedItems.length + normalizedBlocked.length), selectedItems.length + normalizedBlocked.length),
				eligible_count: integerOr(eligibility.eligible_count, selectedItems.length),
				selected_count: selectedItems.length,
				blocked_count: normalizedBlocked.length,
				needs_input_count: integerOr(eligibility.needs_input_count, 0),
				risk_level: 'medium',
				target_ability_ids: ['npcink-abilities-toolkit/build-media-derivative-cloud-request'],
			},
			selected_items: selectedItems,
			blocked_items: normalizedBlocked,
			operator_next_action: plan.operator_next_action,
			retryable: retryable,
			retry_guidance: {
				retryable: retryable,
				reason: retryable ? 'review_set_can_be_rebuilt' : 'review_set_not_execution_state',
				operator_next_action: mediaBatchRetryGuidanceText(plan.retry_guidance) || (retryable ? 'adjust_filters_or_selection_then_rebuild' : 'adjust_selection_or_generate_selected_previews'),
			},
			safety: {
				dry_run: true,
				direct_wordpress_write: false,
				core_proposal_created: false,
				approval_performed: false,
				preflight_performed: false,
				execution_performed: false,
				action_scheduler_used: false,
				custom_tables_created: false,
				local_queue_created: false,
				lease_store_created: false,
				retry_worker_created: false,
				dead_letter_created: false,
				cloud_scheduler_truth: false,
			},
		};
	}

	function normalizeMediaDerivativeBatchPlan(rawPlan) {
		const plan = Object.assign({}, asObject(rawPlan));
		const sourceSummary = asObject(plan.summary);
		const sourceEligibility = asObject(plan.eligibility_summary);
		const candidates = asArray(firstFilled([plan.candidates, plan.eligible_items], []));
		const skipped = asArray(plan.skipped);
		const blockedItems = asArray(firstFilled([plan.blocked_items, plan.blocked, skipped], []));
		const eligibleCount = integerOr(
			firstFilled([
				sourceEligibility.eligible_count,
				sourceEligibility.candidate_count,
				sourceSummary.eligible_count,
				sourceSummary.candidate_count,
			], candidates.length),
			candidates.length
		);
		const blockedCount = integerOr(
			firstFilled([
				sourceEligibility.blocked_count,
				sourceEligibility.skipped_count,
				sourceSummary.blocked_count,
				sourceSummary.skipped_count,
			], blockedItems.length),
			blockedItems.length
		);
		const totalCount = integerOr(
			firstFilled([
				sourceEligibility.total_count,
				sourceEligibility.total_matched,
				sourceSummary.total_count,
				sourceSummary.total_matched,
			], eligibleCount + blockedCount),
			eligibleCount + blockedCount
		);

		plan.candidates = candidates;
		plan.skipped = skipped;
		plan.blocked_items = blockedItems;
		plan.summary = sourceSummary;
		plan.eligibility_summary = Object.assign({
			total_count: totalCount,
			eligible_count: eligibleCount,
			blocked_count: blockedCount,
			selected_count: eligibleCount,
		}, sourceEligibility);
		plan.retryable = Boolean(plan.retryable);
		plan.retry_guidance = firstFilled([
			plan.retry_guidance,
			plan.retryGuidance,
		], candidates.length ? 'Change the selected items or rebuild the plan after adjusting filters.' : 'Adjust scope, filters, or blocked media details, then rebuild the plan.');
		plan.operator_next_action = firstFilled([
			plan.operator_next_action,
			plan.operatorNextAction,
		], candidates.length ? 'Review eligible items, then generate selected previews.' : 'Review blocked reasons or adjust filters before rebuilding the plan.');
		plan.local_automation_review_set = buildLocalAutomationMediaConversionReviewSet(plan, candidates, blockedItems);
		return plan;
	}

	function proposalIdFromResponse(payload) {
		const proposal = asObject(payload);
		const data = asObject(proposal.data);
		return firstFilled([
			proposal.proposal_id,
			proposal.id,
			data.proposal_id,
			data.id,
		], '');
	}

	function collectCoreHandoffProposalIds(value, ids, depth) {
		ids = ids || [];
		if (!value || depth > 6 || typeof value !== 'object') {
			return ids;
		}
		const proposalId = proposalIdFromResponse(value);
		if (proposalId && ids.indexOf(proposalId) < 0) {
			ids.push(proposalId);
		}
		if (Array.isArray(value)) {
			value.forEach((item) => collectCoreHandoffProposalIds(item, ids, depth + 1));
			return ids;
		}
		Object.keys(value).forEach((key) => {
			collectCoreHandoffProposalIds(value[key], ids, depth + 1);
		});
		return ids;
	}

	function firstCoreHandoffAbilityId(value, depth) {
		if (!value || depth > 6 || typeof value !== 'object') {
			return '';
		}
		if (Array.isArray(value)) {
			for (let index = 0; index < value.length; index += 1) {
				const abilityId = firstCoreHandoffAbilityId(value[index], depth + 1);
				if (abilityId) {
					return abilityId;
				}
			}
			return '';
		}
		if (value.ability_id || value.target_ability_id) {
			return String(value.ability_id || value.target_ability_id);
		}
		const keys = Object.keys(value);
		for (let index = 0; index < keys.length; index += 1) {
			const abilityId = firstCoreHandoffAbilityId(value[keys[index]], depth + 1);
			if (abilityId) {
				return abilityId;
			}
		}
		return '';
	}

	function coreHandoffProposalUrl(proposalId) {
		if (!proposalId || !config.coreAdminUrl) {
			return '';
		}
		return config.coreAdminUrl + '&proposal_id=' + encodeURIComponent(proposalId);
	}

	function coreHandoffReceipt(payload, options) {
		options = options || {};
		const proposal = asObject(options.proposal || proposalFromPlanResponse(payload));
		const proposalIds = collectCoreHandoffProposalIds(payload || proposal, [], 0);
		const proposalId = firstFilled([
			options.proposalId,
			options.proposal_id,
			proposalIdFromResponse(proposal),
			proposalIds.length ? proposalIds[0] : '',
		], '');
		if (proposalId && proposalIds.indexOf(proposalId) < 0) {
			proposalIds.unshift(proposalId);
		}
		return {
			contract_version: 'toolbox_core_handoff_receipt.v1',
			receipt_owner: 'wordpress_toolbox_local',
			storage: 'ephemeral_response_only',
			approval_owner: 'npcink-governance-core',
			proposal_id: proposalId,
			proposal_ids: proposalIds,
			status: firstFilled([options.status, proposal.status, proposal.proposal_status], 'submitted'),
			target_ability_id: firstFilled([options.targetAbilityId, options.target_ability_id, proposal.ability_id, firstCoreHandoffAbilityId(payload, 0)], ''),
			source_item_id: firstFilled([options.sourceItemId, options.source_item_id], ''),
			source_label: firstFilled([options.sourceLabel, options.source_label], ''),
			handoff_type: firstFilled([options.handoffType, options.handoff_type], ''),
			operator_next_action: firstFilled([options.operatorNextAction, options.operator_next_action], 'review_in_core'),
			core_url: coreHandoffProposalUrl(proposalId),
			direct_wordpress_write: false,
			canonical_truth: 'core_governance_record',
		};
	}

	function renderCoreHandoffReceipt(receipt) {
		if (!receipt || typeof receipt !== 'object') {
			return null;
		}
		const section = el('div', 'npcink-toolbox__handoff-receipt');
		section.appendChild(el('h4', '', 'Core handoff receipt'));
		const meta = el('div', 'npcink-toolbox__result-meta');
		appendMeta(meta, 'Receipt', receipt.contract_version);
		appendMeta(meta, 'Proposal', receipt.proposal_id);
		appendMeta(meta, 'Status', receipt.status ? formatLabel(receipt.status) : '');
		appendMeta(meta, 'Ability', receipt.target_ability_id);
		appendMeta(meta, 'Source item', receipt.source_item_id || receipt.source_label);
		appendMeta(meta, 'Next action', receipt.operator_next_action ? formatLabel(receipt.operator_next_action) : '');
		appendMeta(meta, 'Storage', receipt.storage);
		section.appendChild(meta);
		if (receipt.core_url) {
			const actions = el('div', 'npcink-toolbox__result-actions');
			actions.appendChild(createLink(receipt.core_url, 'Open in Core review'));
			section.appendChild(actions);
		}
		section.appendChild(el('p', '', 'Toolbox keeps this as an ephemeral local receipt. Core remains the canonical approval, preflight, execution, and audit record.'));
		return section;
	}

	function mediaBatchResultStatus(state) {
		if (state && state.batchExecutionError) {
			return 'execution_failed';
		}
		if (state && state.batchExecutionResult) {
			return 'executed';
		}
		if (state && state.batchProposalError) {
			return 'proposal_failed';
		}
		if (state && state.batchProposalResult) {
			return 'submitted';
		}
		if (state && state.derivative) {
			return state.batchStatus || 'preview_ready';
		}
		return state && state.batchStatus ? state.batchStatus : 'pending';
	}

	function mediaBatchExecutionPayload(state) {
		return asObject(state && state.batchExecutionResult ? state.batchExecutionResult : null);
	}

	function mediaBatchExecutionErrorPayload(state) {
		return asObject(state && state.batchExecutionError ? state.batchExecutionError : null);
	}

	function mediaBatchFirstExecutionAction(state) {
		const payload = mediaBatchExecutionPayload(state);
		const execution = asObject(payload.execution);
		const results = asArray(firstFilled([payload.results, execution.results], []));
		if (results.length) {
			return asObject(results[0]);
		}
		return asObject(firstFilled([payload.result, execution.result], {}));
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

		const meta = el('div', 'npcink-toolbox__result-meta');
		appendMeta(meta, 'Run', state.runId);
		appendMeta(meta, 'Artifact', derivative.artifact_id || derivative.id);
		appendMeta(meta, 'Format', derivative.format ? String(derivative.format).toUpperCase() : '');
		appendMeta(meta, 'MIME', derivative.mime_type);
		appendMeta(meta, 'Size', derivative.width && derivative.height ? derivative.width + ' x ' + derivative.height : '');
		appendMeta(meta, 'Bytes', derivative.filesize_bytes);
		appendMeta(meta, 'Expires', formatDateTime(derivative.expires_at));
		appendMeta(meta, 'Crop', mediaDerivativeCropLabel(state.abilityInput));
		appendMeta(meta, 'Watermark', mediaDerivativeWatermarkLabel(state.abilityInput));
		result.appendChild(meta);

		if (Array.isArray(derivative.processing_warnings) && derivative.processing_warnings.length) {
			derivative.processing_warnings.forEach((warning) => {
				result.appendChild(el('div', 'npcink-toolbox__result-notice is-warning', warning));
			});
		}

		const previewUrl = withRestNonce(derivative.preview_url || '');
		if (previewUrl) {
			const preview = el('figure', 'npcink-toolbox__derivative-preview');
			const image = el('img');
			image.src = previewUrl;
			image.alt = 'Generated derivative preview';
			image.loading = 'lazy';
			preview.appendChild(image);
			preview.appendChild(el('figcaption', '', 'Same-origin signed preview proxy. This is not a public Cloud URL or a WordPress media write.'));
			result.appendChild(preview);
			result.appendChild(el('div', 'npcink-toolbox__result-notice is-ok', 'Preview is served through Adapter and Cloud Addon with local authorization.'));
		} else {
			result.appendChild(el('div', 'npcink-toolbox__result-notice is-warning', 'Preview uses artifact evidence only. The local signed preview proxy did not return a display URL.'));
		}
		renderArtifactSummary(result, 'Derivative artifact', derivative);
		if (state.fromPlanRequest) {
			result.appendChild(el('div', 'npcink-toolbox__result-notice is-ok', 'Optimization plan is ready for one Core proposal approval.'));
			renderArtifactSummary(result, 'Media optimization plan', state.fromPlanRequest.plan || {});
		} else if (state.proposalEnvelope) {
			const guard = state.proposalEnvelope.ability_guard || {};
			const nextStep = state.proposalEnvelope.next_step || 'Add reviewed media details, then generate the preview again before Core proposal submission.';
			result.appendChild(el('div', 'npcink-toolbox__result-notice is-warning', nextStep));
			if (guard.missing_capability_behavior) {
				result.appendChild(el('div', 'npcink-toolbox__result-notice is-warning', 'If Core lacks the media optimization plan ability, update Core and Abilities before continuing. Do not split this optimization into two proposals.'));
			}
		}
		if (state.proposalPayload) {
			renderArtifactSummary(result, 'Derivative-only payload', state.proposalPayload);
		}
		if (state.preflightEnvelope) {
			const preflight = planDataFromEnvelope(state.preflightEnvelope);
			if (preflight && preflight.artifact_type === 'media_adoption_preflight_summary') {
				const ready = preflight.readiness && preflight.readiness.can_submit_core_proposal;
				result.appendChild(el('div', ready ? 'npcink-toolbox__result-notice is-ok' : 'npcink-toolbox__result-notice is-warning', ready ? '采用预检通过。提交 Core 提案前请确认摘要。' : '采用预检需要处理后再提交 Core 提案。'));
				const preflightMeta = el('div', 'npcink-toolbox__result-meta');
				appendMeta(preflightMeta, '提案就绪', ready ? '是' : '否');
				appendMeta(preflightMeta, '内容引用文章', preflight.content_reference_summary ? preflight.content_reference_summary.post_count : '');
				appendMeta(preflightMeta, 'URL 替换数', preflight.content_reference_summary ? preflight.content_reference_summary.replacement_count : '');
				appendMeta(preflightMeta, '设置引用扫描', preflight.settings_reference_summary && preflight.settings_reference_summary.scan_available ? '可单独扫描' : '不可用');
				result.appendChild(preflightMeta);
				if (preflight.settings_reference_summary && preflight.settings_reference_summary.scan_available) {
					result.appendChild(el('div', 'npcink-toolbox__result-notice is-warning', '后台设置、主题或其他插件里的旧图片 URL 不会自动随媒体采用一起替换；需要时请使用“提交设置 URL 修复”。'));
				}
				renderArtifactSummary(result, '采用预检', preflight);
			} else if (state.preflightEnvelope.error) {
				result.appendChild(el('div', 'npcink-toolbox__result-notice is-warning', '采用预检不可用：' + state.preflightEnvelope.error));
			}
		}
		result.appendChild(createRawDetails(payload, 'Cloud result payload'));
	}

	function renderMediaDerivativeBatchPlan(form, planEnvelope, plan) {
		const panel = form.querySelector('[data-toolbox-media-batch-plan]');
		if (!panel) {
			return;
		}
		const candidates = asArray(plan.candidates);
		const skipped = asArray(plan.skipped);
		const reviewSet = asObject(plan.local_automation_review_set);
		const reviewSetEligibility = asObject(reviewSet.eligibility_summary);
		const reviewSetScope = asObject(reviewSet.scope);
		const blockedItems = asArray(firstFilled([reviewSet.blocked_items, plan.blocked_items], []));
		const summary = asObject(plan.summary);
		const eligibility = Object.assign({}, asObject(plan.eligibility_summary), reviewSetEligibility);
		panel.hidden = false;
		panel.innerHTML = '';

		const heading = el('div', 'npcink-toolbox__batch-heading');
		heading.appendChild(el('h4', '', 'Media conversion review set'));
		const meta = el('div', 'npcink-toolbox__result-meta');
		appendMeta(meta, 'Eligible', eligibility.eligible_count || summary.candidate_count || candidates.length);
		appendMeta(meta, 'Blocked', eligibility.blocked_count || summary.skipped_count || blockedItems.length || skipped.length);
		appendMeta(meta, 'Matched', eligibility.items_total || eligibility.total_count || summary.total_matched);
		const selectedMetaItem = el('span', 'npcink-toolbox__result-meta-item');
		selectedMetaItem.appendChild(el('span', 'npcink-toolbox__result-meta-label', 'Selected'));
		selectedMetaItem.appendChild(el('span', 'npcink-toolbox__result-meta-value', eligibility.selected_count || candidates.length));
		selectedMetaItem.setAttribute('data-toolbox-media-batch-selected-meta', '');
		meta.appendChild(selectedMetaItem);
		appendMeta(meta, 'Retryable', reviewSet.retryable || plan.retryable ? 'Yes' : 'No');
		appendMeta(meta, 'Mode', reviewSet.mode || plan.plan_mode || 'dry_run');
		appendMeta(meta, 'Contract', reviewSet.contract_version);
		appendMeta(meta, 'Target', reviewSetScope.target_format ? String(reviewSetScope.target_format).toUpperCase() : '');
		appendMeta(meta, 'Runtime owner', reviewSet.runtime_owner);
		heading.appendChild(meta);
		panel.appendChild(heading);

		if (reviewSet.operator_next_action || plan.operator_next_action) {
			panel.appendChild(el('div', 'npcink-toolbox__result-notice is-ok', 'Next action: ' + String(reviewSet.operator_next_action || plan.operator_next_action)));
		}
		const retryGuidanceText = mediaBatchRetryGuidanceText(reviewSet.retry_guidance || plan.retry_guidance);
		if (retryGuidanceText) {
			panel.appendChild(el('div', 'npcink-toolbox__result-notice is-warning', 'Retry guidance: ' + retryGuidanceText));
		}
		panel.appendChild(el('div', 'npcink-toolbox__result-notice is-ok', 'Toolbox is rendering a governed review set only. Previews and Core proposal submission still require selected operator action.'));

		if (!candidates.length) {
			panel.appendChild(el('div', 'npcink-toolbox__result-notice is-warning', 'No candidates are ready for derivative previews. Review skipped reasons or adjust filters.'));
		}

		const list = el('div', 'npcink-toolbox__batch-list');
		const selectedItems = asArray(reviewSet.selected_items);
		candidates.forEach((candidate, index) => {
			const reviewItem = selectedItems[index] || {};
			const row = el('label', 'npcink-toolbox__batch-row');
			const checkbox = document.createElement('input');
			checkbox.type = 'checkbox';
			checkbox.checked = true;
			checkbox.setAttribute('data-toolbox-media-batch-candidate', String(candidate.attachment_id || ''));
			checkbox.addEventListener('change', function () {
				updateMediaBatchSelectedCount(form);
			});
			row.appendChild(checkbox);
			const body = el('span', 'npcink-toolbox__batch-row-body');
			body.appendChild(el('strong', '', '#' + String(candidate.attachment_id || '') + ' ' + String(candidate.title || 'Untitled media')));
			const detail = [
				candidate.source_format ? String(candidate.source_format).toUpperCase() : '',
				candidate.target_format ? 'to ' + String(candidate.target_format).toUpperCase() : '',
				candidate.width && candidate.height ? String(candidate.width) + ' x ' + String(candidate.height) : '',
				candidate.filesize_bytes ? String(candidate.filesize_bytes) + ' bytes' : '',
			].filter(Boolean).join(' · ');
			body.appendChild(el('small', '', detail));
			const status = [
				candidate.status ? formatLabel(candidate.status) : 'Eligible',
				candidate.reason || candidate.eligibility_reason || '',
				reviewItem.proposal_path ? formatLabel(reviewItem.proposal_path) : '',
				reviewItem.result_ref || candidate.result_ref || candidate.result_reference || '',
			].filter(Boolean).join(' · ');
			if (status) {
				body.appendChild(el('small', 'npcink-toolbox__batch-status', status));
			}
			row.appendChild(body);
			row.__npcinkMediaBatchCandidate = Object.assign({}, candidate, { batch_index: index });
			list.appendChild(row);
		});
		panel.appendChild(list);

		if (blockedItems.length || skipped.length) {
			const details = el('details', 'npcink-toolbox__result-details');
			details.appendChild(el('summary', '', 'Blocked or skipped media'));
			const skippedList = el('div', 'npcink-toolbox__batch-list');
			(blockedItems.length ? blockedItems : skipped).slice(0, 20).forEach((item) => {
				const row = el('div', 'npcink-toolbox__batch-row is-skipped');
				const body = el('span', 'npcink-toolbox__batch-row-body');
				body.appendChild(el('strong', '', '#' + String(item.attachment_id || '') + ' ' + String(item.title || 'Skipped media')));
				body.appendChild(el('small', '', String(mediaBatchBlockedReason(item))));
				if (item.operator_next_action) {
					body.appendChild(el('small', 'npcink-toolbox__batch-status', 'Next action: ' + String(item.operator_next_action)));
				}
				row.appendChild(body);
				skippedList.appendChild(row);
			});
			details.appendChild(skippedList);
			panel.appendChild(details);
		}

		panel.appendChild(createRawDetails(planEnvelope, 'Batch plan payload'));
		updateMediaBatchSelectedCount(form);
	}

	function renderMediaUrlResolution(form, resolutionEnvelope, resolution) {
		const panel = form.querySelector('[data-toolbox-media-url-resolution]');
		if (!panel) {
			return;
		}

		const candidates = Array.isArray(resolution.candidates) ? resolution.candidates : [];
		panel.hidden = false;
		clearNode(panel);

		const heading = el('div', 'npcink-toolbox__batch-heading');
		heading.appendChild(el('h4', '', 'URL resolution'));
		const meta = el('div', 'npcink-toolbox__result-meta');
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
			panel.appendChild(el('div', 'npcink-toolbox__result-notice is-ok', 'Attachment ID resolved locally. Generate a preview before submitting a Core proposal.'));
		} else if (!candidates.length) {
			panel.appendChild(el('div', 'npcink-toolbox__result-notice is-warning', 'No attachment candidate matched this local uploads URL.'));
		} else {
			panel.appendChild(el('div', 'npcink-toolbox__result-notice is-warning', 'Review candidate evidence before choosing one attachment.'));
		}

		if (Array.isArray(resolution.warnings) && resolution.warnings.length) {
			resolution.warnings.forEach((warning) => {
				panel.appendChild(el('div', 'npcink-toolbox__result-notice is-warning', warning));
			});
		}

		if (candidates.length) {
			const list = el('div', 'npcink-toolbox__batch-list');
			candidates.forEach((candidate) => {
				const row = el('div', 'npcink-toolbox__batch-row');
				row.setAttribute('data-toolbox-media-resolution-candidate', String(candidate.attachment_id || ''));
				row.__npcinkMediaResolutionCandidate = candidate;
				const button = el('button', 'button button-small', 'Use attachment');
				button.type = 'button';
				button.setAttribute('data-toolbox-use-media-resolution-candidate', String(candidate.attachment_id || ''));
				row.appendChild(button);

				const body = el('span', 'npcink-toolbox__batch-row-body');
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
				const row = checkbox.closest('.npcink-toolbox__batch-row');
				return row && row.__npcinkMediaBatchCandidate ? row.__npcinkMediaBatchCandidate : null;
			})
			.filter(Boolean);
	}

	function updateMediaBatchSelectedCount(form) {
		const selectedCount = selectedMediaBatchCandidates(form).length;
		const selectedValue = form.querySelector('[data-toolbox-media-batch-selected-meta] .npcink-toolbox__result-meta-value');
		const previewButton = form.querySelector('[data-toolbox-run-media-batch-previews]');
		if (selectedValue) {
			selectedValue.textContent = String(selectedCount);
		}
		if (previewButton instanceof HTMLButtonElement) {
			previewButton.disabled = selectedCount < 1;
		}
	}

	function renderMediaDerivativeBatchResults(form, states, title, summary, batchContext) {
		batchContext = asObject(batchContext);
		const selectedCount = integerOr(batchContext.selected_count || batchContext.selectedCount, states.length);
		const submittedCount = integerOr(
			batchContext.submitted_count || batchContext.submittedCount,
			states.filter((state) => state && state.batchProposalResult).length
		);
		const failedCount = integerOr(
			batchContext.failed_count || batchContext.failedCount,
			states.filter((state) => state && (state.batchProposalError || state.batchExecutionError)).length
		);
		const executedCount = integerOr(
			batchContext.executed_count || batchContext.executedCount,
			states.filter((state) => state && state.batchExecutionResult).length
		);
		const blockedCount = integerOr(
			batchContext.blocked_count || batchContext.blockedCount,
			0
		);
		const result = renderShell(
			form,
			{ provider: 'core governance' },
			title || 'Batch media derivative previews',
			summary || 'Selected media now have short-lived derivative artifact evidence. Submit Core proposals before artifact expiry.'
		);
		if (!result) {
			return;
		}

		const meta = el('div', 'npcink-toolbox__result-meta');
		appendMeta(meta, 'Selected', selectedCount);
		appendMeta(meta, 'Previewed', states.length);
		appendMeta(meta, 'Submitted', submittedCount);
		appendMeta(meta, 'Executed', executedCount);
		appendMeta(meta, 'Failed', failedCount);
		appendMeta(meta, 'Blocked', blockedCount);
		appendMeta(meta, 'Partial success', batchContext.partial_success ? 'Yes' : 'No');
		appendMeta(meta, 'Retryable', batchContext.retryable ? 'Yes' : 'No');
		appendMeta(meta, 'Proposal path', 'Core/Adapter governed execution');
		appendMeta(meta, 'Crop', states.length ? mediaDerivativeCropLabel(states[0].abilityInput) : '');
		appendMeta(meta, 'Watermark', states.length ? mediaDerivativeWatermarkLabel(states[0].abilityInput) : '');
		result.appendChild(meta);

		if (batchContext.operator_next_action) {
			result.appendChild(el('div', 'npcink-toolbox__result-notice is-ok', 'Next action: ' + String(batchContext.operator_next_action)));
		}
		if (batchContext.retry_guidance) {
			result.appendChild(el('div', 'npcink-toolbox__result-notice is-warning', 'Retry guidance: ' + String(batchContext.retry_guidance)));
		}
		if (batchContext.core_preflight_evidence) {
			result.appendChild(createRawDetails(batchContext.core_preflight_evidence, 'Core preflight evidence'));
		}

		const list = el('div', 'npcink-toolbox__result-list');
		states.forEach((state) => {
			const derivative = state.derivative || {};
			const candidate = asObject(state.batchCandidate);
			const executionPayload = mediaBatchExecutionPayload(state);
			const executionError = mediaBatchExecutionErrorPayload(state);
			const executionAction = mediaBatchFirstExecutionAction(state);
			const row = el('article', 'npcink-toolbox__result-item');
			row.appendChild(el('h4', '', '#' + String(state.abilityInput && state.abilityInput.attachment_id ? state.abilityInput.attachment_id : '') + ' ' + String(candidate.title || (derivative.format ? String(derivative.format).toUpperCase() : 'Derivative'))));
			const itemMeta = el('div', 'npcink-toolbox__result-meta');
			appendMeta(itemMeta, 'Status', formatLabel(mediaBatchResultStatus(state)));
			appendMeta(itemMeta, 'Artifact', derivative.artifact_id || derivative.id);
			appendMeta(itemMeta, 'Proposal', proposalIdFromResponse(state.batchProposalResult));
			appendMeta(itemMeta, 'Execution profile', executionAction.execution_profile || executionPayload.execution_profile || executionError.failed_execution_profile);
			appendMeta(itemMeta, 'Idempotency', executionAction.idempotency_key || executionPayload.idempotency_key || executionError.failed_idempotency_key);
			appendMeta(itemMeta, 'Executed actions', executionPayload.executed_count);
			appendMeta(itemMeta, 'Operator action', executionPayload.operator_next_action || executionError.operator_next_action);
			appendMeta(itemMeta, 'Size', derivative.width && derivative.height ? derivative.width + ' x ' + derivative.height : '');
			appendMeta(itemMeta, 'Expires', formatDateTime(derivative.expires_at));
			appendMeta(itemMeta, 'Crop', mediaDerivativeCropLabel(state.abilityInput));
			appendMeta(itemMeta, 'Watermark', mediaDerivativeWatermarkLabel(state.abilityInput));
			row.appendChild(itemMeta);
			if (candidate.reason || candidate.eligibility_reason || candidate.result_ref || candidate.result_reference) {
				row.appendChild(el('p', '', [
					candidate.reason || candidate.eligibility_reason || '',
					candidate.result_ref || candidate.result_reference || '',
				].filter(Boolean).join(' · ')));
			}
			if (state.batchProposalError) {
				row.appendChild(el('div', 'npcink-toolbox__result-notice is-warning', formatErrorMessage(state.batchProposalError)));
			}
			if (state.batchExecutionError) {
				row.appendChild(el('div', 'npcink-toolbox__result-notice is-warning', formatErrorMessage(state.batchExecutionError)));
			}
			const previewUrl = withRestNonce(derivative.preview_url || '');
			if (previewUrl) {
				const link = createLink(previewUrl, 'Open preview');
				row.appendChild(link);
			}
			if (Object.keys(executionPayload).length) {
				row.appendChild(createRawDetails(executionPayload, 'Adapter execution result'));
			}
			list.appendChild(row);
		});
		result.appendChild(list);
	}

	function renderProposalCreated(form, proposal, options) {
		options = options || {};
		const proposalId = proposalIdFromResponse(proposal);
		const receipt = options.receipt || coreHandoffReceipt(proposal, Object.assign({}, options.receiptContext || {}, {
			proposal,
			proposalId,
		}));
		const result = renderShell(
			form,
			{ provider: 'core governance' },
			options.title || 'Core proposal submitted',
			options.summary || 'The derivative artifact is now in Core review as a local media replacement proposal. WordPress writes still require Core approval and preflight.'
		);
		if (!result) {
			return;
		}

		const meta = el('div', 'npcink-toolbox__result-meta');
		appendMeta(meta, 'Proposal', proposalId);
		appendMeta(meta, 'Status', proposal && proposal.status ? formatLabel(proposal.status) : '');
		appendMeta(meta, 'Ability', proposal && proposal.ability_id);
		result.appendChild(meta);
		if (proposalId && config.coreAdminUrl) {
			const actions = el('div', 'npcink-toolbox__result-actions');
			actions.appendChild(createLink(config.coreAdminUrl + '&proposal_id=' + encodeURIComponent(proposalId), 'Open in Core review'));
			result.appendChild(actions);
		}
		const receiptNode = renderCoreHandoffReceipt(receipt);
		if (receiptNode) {
			result.appendChild(receiptNode);
		}
		result.appendChild(createRawDetails(proposal, options.rawTitle || 'Core proposal'));
	}

	function unwrapStructuredPayload(payload) {
		if (!payload || typeof payload !== 'object') {
			return payload;
		}

		const candidates = [
			payload.result,
			payload.output,
			payload.result_json,
			payload.data && payload.data.result,
			payload.data && payload.data.output,
			payload.data && payload.data.result_json,
			payload.data && payload.data.run && payload.data.run.result,
			payload.data && payload.data.run && payload.data.run.result_json,
			payload.data,
		];

		for (let i = 0; i < candidates.length; i += 1) {
			const candidate = candidates[i];
			if (!candidate || typeof candidate !== 'object' || candidate === payload) {
				continue;
			}
			if (
				candidate.artifact_type ||
				candidate.output_contract ||
				candidate.evidence_pack ||
				Array.isArray(candidate.results) ||
				Array.isArray(candidate.candidates) ||
				Array.isArray(candidate.images) ||
				candidate.coverage ||
				candidate.sync
			) {
				return candidate;
			}
		}

		return payload;
	}

	function isWebSearchPayload(payload) {
		if (!payload || typeof payload !== 'object') {
			return false;
		}

		const evidencePack = payload.evidence_pack && typeof payload.evidence_pack === 'object' ? payload.evidence_pack : {};
		return payload.artifact_type === 'web_search_results'
			|| payload.output_contract === 'search_evidence_pack.v1'
			|| evidencePack.contract_version === 'search_evidence_pack.v1'
			|| (payload.cloud_ability === 'npcink-cloud/web-search' && Array.isArray(payload.results));
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

		payload = unwrapStructuredPayload(payload);

		if (renderOperatorFeedback(form, payload)) {
			return;
		}

		if (payload.artifact_type === 'image_source_candidates') {
			renderImageSourceCandidates(
				form,
				payload,
				payload.provider_mode === 'ai_generated' ? 'AI-generated image candidates' : ''
			);
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

		if (isWebSearchPayload(payload)) {
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

		if (payload.artifact_type === 'hosted_ai_content_support') {
			renderHostedAiContentSupport(form, payload);
			return;
		}

		if (payload.artifact_type === 'hosted_ai_site_helper') {
			renderHostedAiSiteHelper(form, payload);
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

	function applyWebSearchPreset(select, force) {
		if (!(select instanceof HTMLSelectElement)) {
			return;
		}
		const form = select.closest('form');
		if (!(form instanceof HTMLFormElement)) {
			return;
		}
		const option = select.selectedOptions && select.selectedOptions.length ? select.selectedOptions[0] : null;
		if (!option) {
			return;
		}
		const queryInput = form.querySelector('input[name="query"]');
		const recencyInput = form.querySelector('input[name="recency_days"]');
		const maxResultsInput = form.querySelector('input[name="max_results"]');
		const managedSourceInput = form.querySelector('[name="managed_source"]');
		const presetQuery = option.getAttribute('data-toolbox-query') || '';
		const previousPreset = form.getAttribute('data-toolbox-last-preset-query') || '';
		if (queryInput instanceof HTMLInputElement && presetQuery) {
			const currentQuery = String(queryInput.value || '').trim();
			if (force || !currentQuery || currentQuery === previousPreset) {
				queryInput.value = presetQuery;
				form.setAttribute('data-toolbox-last-preset-query', presetQuery);
			}
		}
		const presetRecency = option.getAttribute('data-toolbox-recency');
		if (recencyInput instanceof HTMLInputElement && presetRecency !== null) {
			recencyInput.value = presetRecency;
		}
		const presetMaxResults = option.getAttribute('data-toolbox-max-results');
		if (maxResultsInput instanceof HTMLInputElement && presetMaxResults !== null) {
			maxResultsInput.value = presetMaxResults;
		}
		const presetManagedSource = option.getAttribute('data-toolbox-managed-source');
		if ((managedSourceInput instanceof HTMLInputElement || managedSourceInput instanceof HTMLSelectElement) && presetManagedSource !== null) {
			managedSourceInput.value = presetManagedSource;
		}
	}

	function initWebSearchPresets() {
		document.querySelectorAll('form[data-toolbox-endpoint="web-search/test"] select[name="intent"]').forEach((select) => {
			if (!(select instanceof HTMLSelectElement)) {
				return;
			}
			applyWebSearchPreset(select, true);
			select.addEventListener('change', () => applyWebSearchPreset(select, false));
		});
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
		const preflightState = {
			abilityInput: input,
			runId,
			derivative,
		};
		let preflightEnvelope = null;
		try {
			preflightEnvelope = await postJson(config.adapterRestUrl, 'run-read-ability', {
				ability_id: 'npcink-abilities-toolkit/build-media-adoption-preflight-summary',
				input: preflightInputFromState(preflightState),
			});
		} catch (error) {
			preflightEnvelope = {
				error: formatErrorMessage(error, 'Media adoption preflight is unavailable.'),
			};
		}

		if (previewOnly) {
			return {
				abilityInput: input,
				mediaDetailsInput: mediaDetails || {},
				create: createPayload,
				result: resultPayload,
				runId,
				derivative,
				preflightEnvelope,
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
			preflightEnvelope,
		};
	}

	async function runMediaDerivative(form) {
		if (!config.adapterRestUrl) {
			throw { message: 'Npcink Adapter REST URL is unavailable.' };
		}

		const input = mediaDerivativeInput(form);
		const mediaDetails = mediaDetailsInput(form);
		const previewOnly = form.hasAttribute('data-toolbox-media-derivative-preview-only');
		renderTextResult(form, 'Submitting media derivative run...', 'pending');
		const state = await createMediaDerivativePreview(input, mediaDetails, previewOnly);
		form.__npcinkMediaDerivativeState = state;
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
			throw { message: 'Npcink Adapter REST URL is unavailable.' };
		}
		const url = mediaUrlValue(form);
		if (!url) {
			throw { message: 'Paste a local uploads URL before resolving an attachment.' };
		}

		renderTextResult(form, 'Resolving media URL...', 'pending');
		const resolutionEnvelope = await postJson(config.adapterRestUrl, 'run-read-ability', {
			ability_id: 'npcink-abilities-toolkit/resolve-media-attachment-by-url',
			input: {
				url,
				max_candidates: 10,
			},
		});
		const resolution = planDataFromEnvelope(resolutionEnvelope) || {};
		renderMediaUrlResolution(form, resolutionEnvelope, resolution);
		if (resolution.attachment_id) {
			renderTextResult(form, t('Media URL resolved to attachment #') + String(resolution.attachment_id) + t('. Generate a preview to continue.'), 'ok');
			return;
		}
		renderTextResult(form, 'Media URL resolution returned candidates. Choose one attachment before generating a preview.', 'warning');
	}

	async function buildMediaDerivativeBatchPlan(form) {
		if (!config.adapterRestUrl) {
			throw { message: 'Npcink Adapter REST URL is unavailable.' };
		}

		const input = mediaDerivativeBatchPlanInput(form);
		renderTextResult(form, 'Building media derivative batch plan...', 'pending');
		const planEnvelope = await postJson(config.adapterRestUrl, 'run-read-ability', {
			ability_id: 'npcink-abilities-toolkit/build-media-derivative-batch-plan',
			input,
		});
		const plan = normalizeMediaDerivativeBatchPlan(planDataFromEnvelope(planEnvelope) || {});
		form.__npcinkMediaDerivativeBatchPlan = plan;
		form.__npcinkMediaDerivativeBatchStates = [];
		renderMediaDerivativeBatchPlan(form, planEnvelope, plan);
		renderTextResult(form, plan.operator_next_action || 'Batch plan ready. Review candidates and generate selected previews.', 'ok');
		const runButton = form.querySelector('[data-toolbox-run-media-batch-previews]');
		const submitButton = form.querySelector('[data-toolbox-submit-media-batch-proposals]');
		const executeButton = form.querySelector('[data-toolbox-execute-media-batch-replacements]');
		if (runButton instanceof HTMLButtonElement) {
			runButton.disabled = !(asArray(plan.candidates).length > 0);
		}
		if (submitButton instanceof HTMLButtonElement) {
			submitButton.disabled = true;
		}
		if (executeButton instanceof HTMLButtonElement) {
			executeButton.disabled = true;
		}
	}

	async function runMediaDerivativeBatchPreviews(form) {
		if (!config.adapterRestUrl) {
			throw { message: 'Npcink Adapter REST URL is unavailable.' };
		}

		const candidates = selectedMediaBatchCandidates(form);
		if (!candidates.length) {
			throw { message: 'Select at least one batch candidate before generating previews.' };
		}
		const raw = serialize(form);
		const cropInput = mediaDerivativeCropInput(raw);
		const watermarkInput = mediaDerivativeWatermarkInput(raw);
		const states = [];
		for (let index = 0; index < candidates.length; index += 1) {
			const candidate = candidates[index] || {};
			const input = Object.assign({}, candidate.cloud_request_input || {}, cropInput, watermarkInput);
			if (!input.attachment_id && candidate.attachment_id) {
				input.attachment_id = candidate.attachment_id;
			}
			renderTextResult(form, 'Generating preview ' + String(index + 1) + ' of ' + String(candidates.length) + '...', 'pending');
			const state = await createMediaDerivativePreview(input);
			state.batchCandidate = candidate;
			state.batchStatus = 'preview_ready';
			states.push(state);
		}

		form.__npcinkMediaDerivativeBatchStates = states;
		const submitButton = form.querySelector('[data-toolbox-submit-media-batch-proposals]');
		const executeButton = form.querySelector('[data-toolbox-execute-media-batch-replacements]');
		if (submitButton instanceof HTMLButtonElement) {
			submitButton.disabled = states.length <= 0;
		}
		if (executeButton instanceof HTMLButtonElement) {
			executeButton.disabled = true;
		}
		renderMediaDerivativeBatchResults(form, states, '', '', {
			selected_count: candidates.length,
			submitted_count: 0,
			failed_count: 0,
			retryable: false,
			operator_next_action: 'Review selected previews, then submit Core proposals for governed review.',
			retry_guidance: 'Change selected media or rebuild the plan before generating previews again.',
		});
	}

	async function submitMediaDerivativeBatchProposals(form) {
		if (!config.adapterRestUrl) {
			throw { message: 'Npcink Adapter REST URL is unavailable.' };
		}

		const states = Array.isArray(form.__npcinkMediaDerivativeBatchStates) ? form.__npcinkMediaDerivativeBatchStates : [];
		if (!states.length) {
			throw { message: 'Generate selected batch previews before submitting Core proposals.' };
		}

		const proposals = [];
		let failed = null;
		for (let index = 0; index < states.length; index += 1) {
			const state = states[index];
			renderTextResult(form, t('Submitting Core proposal ') + String(index + 1) + t(' of ') + String(states.length) + '...', 'pending');
			try {
				const proposal = await postJson(config.adapterRestUrl, 'proposals', {
					ability_id: 'npcink-abilities-toolkit/adopt-cloud-media-derivative',
					title: 'Replace media file with Cloud derivative',
					summary: 'Review one short-lived Cloud derivative artifact before local WordPress media replacement. Final writes require Core approval and preflight.',
					input: proposalInputFromState(state),
					preview: state.proposalPayload,
				});
				state.batchProposalResult = proposal;
				state.batchStatus = 'submitted';
				proposals.push(proposal);
			} catch (error) {
				state.batchProposalError = error;
				state.batchStatus = 'proposal_failed';
				failed = error;
				break;
			}
		}
		renderMediaDerivativeBatchResults(
			form,
			states,
			failed ? 'Batch proposal submission stopped' : 'Batch proposals submitted',
			failed ? 'One selected derivative failed before all proposals were submitted. Review the failed item and retry after revision.' : 'Selected derivative artifacts are now in Core review. WordPress writes still require Core approval and preflight.',
			{
				selected_count: states.length,
				submitted_count: proposals.length,
				failed_count: failed ? 1 : 0,
				retryable: Boolean(failed),
				operator_next_action: failed ? 'Resolve the failed item before submitting the remaining previews.' : 'Continue review, approval, and preflight in Core.',
				retry_guidance: failed ? 'Retry after revising the failed preview, authorization, or Core proposal input.' : 'If more media are needed, rebuild the batch plan rather than reusing stale artifacts.',
			}
		);
		const result = form.querySelector('.npcink-toolbox__result');
		if (result) {
			result.appendChild(createRawDetails({ proposals, failed: failed ? formatErrorMessage(failed) : null }, 'Core proposals'));
		}
		const executeButton = form.querySelector('[data-toolbox-execute-media-batch-replacements]');
		if (executeButton instanceof HTMLButtonElement) {
			executeButton.disabled = proposals.length <= 0;
		}
	}

	async function executeMediaDerivativeBatchReplacements(form) {
		if (!config.adapterRestUrl) {
			throw { message: 'Npcink Adapter REST URL is unavailable.' };
		}

		const states = Array.isArray(form.__npcinkMediaDerivativeBatchStates) ? form.__npcinkMediaDerivativeBatchStates : [];
		const executableStates = states.filter((state) => proposalIdFromResponse(state && state.batchProposalResult));
		if (!executableStates.length) {
			throw { message: 'Submit selected Core reviews before approving and executing replacements.' };
		}

		const responses = [];
		let failed = null;
		for (let index = 0; index < executableStates.length; index += 1) {
			const state = executableStates[index];
			const proposalId = proposalIdFromResponse(state.batchProposalResult);
			renderTextResult(form, t('Approving and executing replacement ') + String(index + 1) + t(' of ') + String(executableStates.length) + '...', 'pending');
			try {
				const response = await postJson(
					config.adapterRestUrl,
					'proposals/' + encodeURIComponent(proposalId) + '/approve-and-execute',
					{
						note: 'Approved from Toolbox fixed media batch replacement flow.',
					}
				);
				state.batchExecutionResult = response;
				state.batchExecutionError = null;
				state.batchStatus = 'executed';
				responses.push(response);
			} catch (error) {
				state.batchExecutionError = error;
				state.batchStatus = 'execution_failed';
				failed = error;
				break;
			}
		}

		const failedCount = failed ? 1 : 0;
		const executedCount = responses.length;
		const blockedCount = Math.max(0, executableStates.length - executedCount - failedCount);
		const firstResponse = responses.length ? responses[responses.length - 1] : {};
		const context = {
			selected_count: executableStates.length,
			submitted_count: executableStates.length,
			executed_count: executedCount,
			failed_count: failedCount,
			blocked_count: blockedCount,
			partial_success: executedCount > 0 && failedCount > 0,
			retryable: false,
			operator_next_action: failed
				? (executedCount > 0 ? 'review_partial_failure_and_create_revised_proposal' : 'review_failed_execution_and_create_revised_proposal')
				: (firstResponse.operator_next_action || 'review_execution_result'),
			retry_guidance: failed ? 'Review the failed Adapter/Core evidence and create a revised proposal for remaining items.' : 'Use Core or Adapter execution records for audit and rollback evidence.',
			core_preflight_evidence: firstResponse.core_preflight_evidence || null,
		};
		renderMediaDerivativeBatchResults(
			form,
			states,
			failed ? 'Batch replacement execution stopped' : 'Batch replacements executed',
			failed ? 'Adapter stopped after the first failed replacement. Completed items keep their Core/Adapter execution records.' : 'Adapter approved, preflighted, and executed the selected media replacement proposals through governed abilities.',
			context
		);
		const result = form.querySelector('.npcink-toolbox__result');
		if (result) {
			result.appendChild(createRawDetails({ executions: responses, failed: failed ? failed : null }, 'Adapter approve-and-execute responses'));
		}
	}

	async function submitMediaDerivativeProposal(form) {
		if (!config.adapterRestUrl) {
			throw { message: 'Npcink Adapter REST URL is unavailable.' };
		}

		const state = form.__npcinkMediaDerivativeState;
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
			summary.appendChild(el('div', 'npcink-toolbox__result-notice is-pending', 'Checking Cloud index status...'));
		}
		const payload = await getJson(config.restUrl, 'site-knowledge/status');
		if (summary) {
			renderSiteKnowledgeStatusNode(summary, payload);
			summary.appendChild(createRawDetails(payload, 'Status payload'));
		}
		refreshAgentFeedbackSummary(root).catch((error) => {
			const feedbackSummary = root.querySelector('[data-toolbox-agent-feedback-summary]');
			if (feedbackSummary) {
				clearNode(feedbackSummary);
				feedbackSummary.appendChild(el('div', 'npcink-toolbox__result-notice is-warning', error.message || 'Agent feedback summary is unavailable.'));
				feedbackSummary.appendChild(createRawDetails(error, 'Agent feedback summary error'));
			}
		});
		updateSiteKnowledgeActionState(root, payload);
		return payload;
	}

	async function refreshAgentFeedbackSummary(root) {
		const summary = root.querySelector('[data-toolbox-agent-feedback-summary]');
		if (!summary) {
			return null;
		}
		clearNode(summary);
		summary.appendChild(el('div', 'npcink-toolbox__result-notice is-pending', 'Loading Agent feedback summary...'));
		const payload = await postJson(config.restUrl, 'agent-feedback/summary', { window_hours: 24 });
		renderAgentFeedbackSummaryNode(summary, payload);
		summary.appendChild(createRawDetails(payload, 'Agent feedback summary payload'));
		return payload;
	}

	async function runSiteKnowledgeForm(form, endpoint) {
		renderTextResult(form, config.labels && config.labels.running ? config.labels.running : 'Running...', 'pending');
		const payload = await postJson(config.restUrl, endpoint, serialize(form));
		renderStructuredResult(form, payload);
		return payload;
	}

	function nightlyCloudSettingValue(name, fallback) {
		const input = document.querySelector('[name="' + name + '"]');
		if (!input) {
			return fallback;
		}
		if (input.type === 'checkbox') {
			return input.checked ? input.value : '';
		}
		return input.value;
	}

	function nightlyCloudRunIdFromPayload(payload) {
		if (!payload || typeof payload !== 'object') {
			return '';
		}
		if (payload.run_id) {
			return String(payload.run_id);
		}
		if (payload.cloud_run && payload.cloud_run.run_id) {
			return String(payload.cloud_run.run_id);
		}
		if (payload.result && payload.result.run_id) {
			return String(payload.result.run_id);
		}
		return '';
	}

	function nightlyCloudRunId(root) {
		const input = root.querySelector('[data-toolbox-nightly-cloud-run-id]');
		return String(input ? input.value : root.dataset.toolboxNightlyCloudRunId || '').trim();
	}

	function setNightlyCloudRunId(root, runId) {
		const normalized = String(runId || '').trim();
		if (!normalized) {
			return;
		}
		root.dataset.toolboxNightlyCloudRunId = normalized;
		const input = root.querySelector('[data-toolbox-nightly-cloud-run-id]');
		if (input) {
			input.value = normalized;
		}
	}

	function nightlyCloudLocalMorningBrief(root) {
		const encoded = root.dataset.toolboxNightlyLocalBrief || '';
		const marker = root.querySelector('[data-toolbox-nightly-local-brief]');
		const serialized = encoded || (marker ? marker.textContent : '');
		if (!String(serialized || '').trim()) {
			return {};
		}
		try {
			const parsed = JSON.parse(serialized);
			return parsed && typeof parsed === 'object' ? parsed : {};
		} catch (error) {
			return {};
		}
	}

	function nightlyCloudControlsEnabled(root) {
		return root && root.dataset.toolboxNightlyCloudEnabled === '1';
	}

	function nightlyCloudReady(root) {
		return root && root.dataset.toolboxNightlyCloudReady === '1';
	}

	function nightlyCloudSubmitAllowed(root) {
		return nightlyCloudControlsEnabled(root) && root.dataset.toolboxNightlyCloudQuotaExhausted !== '1';
	}

	function nightlyCloudLifecycle(payload) {
		const cloudRun = payload && payload.cloud_run && typeof payload.cloud_run === 'object' ? payload.cloud_run : {};
		return cloudRun.run_lifecycle && typeof cloudRun.run_lifecycle === 'object' ? cloudRun.run_lifecycle : {};
	}

	function nightlyCloudStatus(payload) {
		const lifecycle = nightlyCloudLifecycle(payload);
		const cloudRun = payload && payload.cloud_run && typeof payload.cloud_run === 'object' ? payload.cloud_run : {};
		return String(
			(payload && payload.status) ||
			cloudRun.status ||
			lifecycle.terminal_status ||
			lifecycle.phase ||
			''
		).toLowerCase();
	}

	function nightlyCloudTerminal(payload) {
		const lifecycle = nightlyCloudLifecycle(payload);
		const status = nightlyCloudStatus(payload);
		return ['succeeded', 'failed', 'canceled', 'cancelled'].indexOf(status) >= 0 ||
			['succeeded', 'failed', 'canceled', 'cancelled'].indexOf(String(lifecycle.terminal_status || '').toLowerCase()) >= 0;
	}

	function nightlyCloudSucceeded(payload) {
		const lifecycle = nightlyCloudLifecycle(payload);
		return nightlyCloudStatus(payload) === 'succeeded' || String(lifecycle.terminal_status || '').toLowerCase() === 'succeeded';
	}

	function nightlyCloudRunPhase(payload) {
		const lifecycle = nightlyCloudLifecycle(payload);
		if (lifecycle.phase) {
			return String(lifecycle.phase);
		}
		if (nightlyCloudTerminal(payload)) {
			return 'terminal';
		}
		return nightlyCloudStatus(payload) || 'submitted';
	}

	function nightlyCloudCounts(payload) {
		const patch = payload && payload.morning_brief_patch && typeof payload.morning_brief_patch === 'object' ? payload.morning_brief_patch : {};
		const merged = payload && payload.merged_morning_brief && typeof payload.merged_morning_brief === 'object' ? payload.merged_morning_brief : {};
		return {
			actionCount: Number.isFinite(Number(patch.action_count)) ? Number(patch.action_count) : null,
			mergedPriorityCount: merged.cloud_runtime && Number.isFinite(Number(merged.cloud_runtime.merged_priority_count)) ? Number(merged.cloud_runtime.merged_priority_count) : null,
			merged: !!merged.cloud_runtime
		};
	}

	function nightlyCloudResultStatus(payload) {
		const result = payload && payload.result && typeof payload.result === 'object' ? payload.result : {};
		return String((payload && payload.result_status) || result.status || '').toLowerCase();
	}

	function nightlyCloudRunState(payload) {
		const cloudRun = payload && payload.cloud_run && typeof payload.cloud_run === 'object' ? payload.cloud_run : {};
		if (cloudRun.run_state && typeof cloudRun.run_state === 'object') {
			return cloudRun.run_state;
		}
		if (payload && payload.run_state && typeof payload.run_state === 'object') {
			return payload.run_state;
		}
		return {};
	}

	function nightlyCloudRetryGuidance(payload) {
		const result = payload && payload.result && typeof payload.result === 'object' ? payload.result : {};
		const runState = nightlyCloudRunState(payload);
		if (result.retry_guidance && typeof result.retry_guidance === 'object') {
			return result.retry_guidance;
		}
		if (payload && payload.retry_guidance && typeof payload.retry_guidance === 'object') {
			return payload.retry_guidance;
		}
		if (runState.retry && typeof runState.retry === 'object') {
			return runState.retry;
		}
		return {};
	}

	function nightlyCloudRetryable(payload) {
		const guidance = nightlyCloudRetryGuidance(payload);
		return guidance.retryable === true || guidance.available === true;
	}

	function nightlyCloudPayloadFromRecentCard(card) {
		const item = card && typeof card === 'object' ? card : {};
		const summary = item.summary && typeof item.summary === 'object' ? item.summary : {};
		return {
			provider: 'npcink_cloud',
			provider_mode: 'cloud_managed',
			contract_version: 'nightly_site_inspection_recent_run_card.v1',
			status: String(item.status || item.result_status || ''),
			result_status: String(item.result_status || ''),
			cloud_runtime: 'npcink_cloud_addon',
			cloud_run: {
				run_id: String(item.run_id || ''),
				status: String(item.status || ''),
				trace_id: String(item.trace_id || ''),
				run_lifecycle: item.run_lifecycle && typeof item.run_lifecycle === 'object' ? item.run_lifecycle : {},
				run_state: item.run_state && typeof item.run_state === 'object' ? item.run_state : {}
			},
			morning_brief_patch: {
				action_count: Number.isFinite(Number(summary.reviewable_count)) ? Number(summary.reviewable_count) : null
			},
			retry_guidance: item.retry_guidance && typeof item.retry_guidance === 'object' ? item.retry_guidance : {},
			safety: {
				direct_wordpress_write: false,
				cloud_scheduler_truth: false,
				requires_local_review: true
			}
		};
	}

	function nightlyCloudStoredRun() {
		try {
			const parsed = JSON.parse(window.localStorage.getItem(NIGHTLY_CLOUD_RECENT_KEY) || '{}');
			return parsed && typeof parsed === 'object' ? parsed : {};
		} catch (error) {
			return {};
		}
	}

	function storeNightlyCloudRun(root, payload, label) {
		const runId = nightlyCloudRunIdFromPayload(payload) || nightlyCloudRunId(root);
		if (!runId) {
			return;
		}

		const counts = nightlyCloudCounts(payload);
		const record = {
			run_id: runId,
			status: nightlyCloudStatus(payload) || 'submitted',
			result_status: nightlyCloudResultStatus(payload),
			phase: nightlyCloudRunPhase(payload),
			merged: counts.merged,
			retryable: nightlyCloudRetryable(payload),
			action_count: counts.actionCount,
			merged_priority_count: counts.mergedPriorityCount,
			label: label || '',
			updated_at: new Date().toISOString()
		};
		try {
			window.localStorage.setItem(NIGHTLY_CLOUD_RECENT_KEY, JSON.stringify(record));
		} catch (error) {
			// Browser storage is convenience-only; Cloud remains the run-state truth.
		}
		renderNightlyCloudRecentRun(root);
	}

	function renderNightlyCloudRecentRun(root) {
		const container = root.querySelector('[data-toolbox-nightly-cloud-recent-run]');
		if (!container) {
			return;
		}

		const record = nightlyCloudStoredRun();
		clearNode(container);
		if (!record.run_id) {
			container.hidden = true;
			return;
		}

		const label = record.merged
			? 'Merged preview'
			: record.status
				? formatLabel(record.status)
				: 'Recorded';
		const count = Number.isFinite(Number(record.merged_priority_count))
			? String(record.merged_priority_count) + ' local match(es)'
			: Number.isFinite(Number(record.action_count))
				? String(record.action_count) + ' Cloud action(s)'
				: 'Cloud run detail';
		container.appendChild(nightlyCloudSummaryItem(
			'Recent run',
			record.merged ? 'ok' : nightlyCloudTerminal({ status: record.status }) ? 'warning' : 'pending',
			label,
			String(record.run_id)
		));
		container.appendChild(nightlyCloudSummaryItem(
			'Review handoff',
			record.merged ? 'ok' : 'warning',
			record.merged ? 'Local review required' : 'Result not merged',
			count
		));
		if (record.result_status === 'partially_succeeded' || record.retryable) {
			container.appendChild(nightlyCloudSummaryItem(
				'Retry',
				'warning',
				record.retryable ? 'Available' : 'Review guidance',
				record.result_status === 'partially_succeeded' ? 'Cloud reported partial success.' : 'Cloud returned retry guidance.'
			));
		}

		const actions = el('div', 'npcink-toolbox__result-actions');
		const useButton = el('button', 'button', 'Use run');
		useButton.type = 'button';
		useButton.addEventListener('click', () => {
			setNightlyCloudRunId(root, record.run_id);
			renderTextResult(root, 'Recent Cloud run loaded. Use Recent run actions or Advanced details to refresh from Cloud.', 'pending');
		});
		actions.appendChild(useButton);

		const statusButton = el('button', 'button', 'Refresh status');
		statusButton.type = 'button';
		statusButton.disabled = !nightlyCloudControlsEnabled(root);
		statusButton.addEventListener('click', () => {
			setNightlyCloudRunId(root, record.run_id);
			refreshNightlyCloudBatchStatus(root, statusButton);
		});
		actions.appendChild(statusButton);

		const resultButton = el('button', 'button', 'Load result');
		resultButton.type = 'button';
		resultButton.disabled = !nightlyCloudControlsEnabled(root);
		resultButton.addEventListener('click', () => {
			setNightlyCloudRunId(root, record.run_id);
			readNightlyCloudBatchResult(root, resultButton);
		});
		actions.appendChild(resultButton);
		const retryButton = el('button', 'button', 'Retry run');
		retryButton.type = 'button';
		retryButton.disabled = !nightlyCloudControlsEnabled(root);
		retryButton.addEventListener('click', () => {
			setNightlyCloudRunId(root, record.run_id);
			retryNightlyCloudBatch(root, retryButton);
		});
		actions.appendChild(retryButton);
		container.appendChild(actions);
		container.hidden = false;
	}

	function updateNightlyCloudButtonState(root, busy) {
		const controlsEnabled = nightlyCloudControlsEnabled(root);
		root.querySelectorAll('[data-toolbox-nightly-cloud-entitlement]').forEach((button) => {
			button.disabled = busy || !nightlyCloudReady(root);
		});
		root.querySelectorAll('[data-toolbox-nightly-cloud-submit]').forEach((button) => {
			button.disabled = busy || !nightlyCloudSubmitAllowed(root);
		});
		root.querySelectorAll('[data-toolbox-nightly-cloud-status], [data-toolbox-nightly-cloud-result-read], [data-toolbox-nightly-cloud-recent], [data-toolbox-nightly-cloud-retry]').forEach((button) => {
			button.disabled = busy || !controlsEnabled;
		});
	}

	function setNightlyCloudBusy(root, busy, activeButton) {
		root.querySelectorAll('[data-toolbox-nightly-cloud-entitlement], [data-toolbox-nightly-cloud-submit], [data-toolbox-nightly-cloud-status], [data-toolbox-nightly-cloud-result-read], [data-toolbox-nightly-cloud-recent], [data-toolbox-nightly-cloud-retry]').forEach((button) => {
			if (!button.__npcinkOriginalText) {
				button.__npcinkOriginalText = button.textContent;
			}
			button.setAttribute('aria-busy', busy ? 'true' : 'false');
			if (button === activeButton) {
				button.textContent = busy ? 'Working...' : button.__npcinkOriginalText;
			} else if (!busy) {
				button.textContent = button.__npcinkOriginalText;
			}
		});
		updateNightlyCloudButtonState(root, busy);
	}

	function nightlyCloudRequestPayload() {
		const postLimit = Number(nightlyCloudSettingValue('npcink_toolbox_settings[nightly_inspection_post_limit]', 12));
		const mediaLimit = Number(nightlyCloudSettingValue('npcink_toolbox_settings[nightly_inspection_media_limit]', 12));
		const retentionDays = Number(nightlyCloudSettingValue('npcink_toolbox_settings[nightly_inspection_cloud_retention_days]', 14));
		const payloadMode = nightlyCloudSettingValue('npcink_toolbox_settings[nightly_inspection_cloud_payload_mode]', 'metadata_only');
		return {
			post_limit: Number.isFinite(postLimit) && postLimit > 0 ? postLimit : 12,
			media_limit: Number.isFinite(mediaLimit) && mediaLimit > 0 ? mediaLimit : 12,
			payload_mode: payloadMode || 'metadata_only',
			retention_ttl: (Number.isFinite(retentionDays) && retentionDays > 0 ? retentionDays : 14) * 86400,
			idempotency_key: 'nightly-cloud-batch-' + Date.now()
		};
	}

	function renderNightlyCloudEntitlement(root, payload) {
		const runtime = payload && payload.pro_cloud_runtime && typeof payload.pro_cloud_runtime === 'object' ? payload.pro_cloud_runtime : {};
		root.dataset.toolboxNightlyCloudQuotaExhausted = runtime.quota_exhausted ? '1' : '0';
		updateNightlyCloudButtonState(root, false);

		const title = runtime.quota_exhausted ? 'Cloud quota exhausted' : 'Cloud quota refreshed';
		const summary = runtime.quota_exhausted
			? 'This billing period has no remaining Nightly Site Inspection runs. Existing run status and results can still be reviewed.'
			: 'Current Pro Cloud Runtime entitlement was read from Cloud as a local display snapshot.';
		const result = renderShell(root, payload || { provider: 'Cloud entitlement' }, title, summary);
		if (!result) {
			return;
		}

		const meta = el('div', 'npcink-toolbox__result-meta');
		appendMeta(meta, 'Package', payload && payload.package_label ? payload.package_label : '');
		appendMeta(meta, 'Status', payload && payload.status ? formatLabel(payload.status) : '');
		appendMeta(meta, 'Used', runtime.used_nightly_inspection_runs);
		appendMeta(meta, 'Remaining', runtime.remaining_nightly_inspection_runs);
		appendMeta(meta, 'Run limit', runtime.max_nightly_inspection_runs_per_period);
		appendMeta(meta, 'Batch limit', runtime.max_batch_items);
		appendMeta(meta, 'Retention', runtime.result_retention_days ? runtime.result_retention_days + ' days' : '');
		appendMeta(meta, 'Payload modes', Array.isArray(runtime.payload_modes) ? runtime.payload_modes.map(formatLabel).join(', ') : '');
		appendMeta(meta, 'Cloud role', runtime.cloud_role ? formatLabel(runtime.cloud_role) : '');
		if (meta.childNodes.length) {
			result.appendChild(meta);
		}

		if (runtime.quota_exhausted) {
			result.appendChild(el('div', 'npcink-toolbox__result-notice is-warning', 'New runs are disabled until Cloud reports remaining quota. Existing run IDs can still be refreshed or loaded.'));
		}
		result.appendChild(createRawDetails(payload, 'Advanced details: Cloud entitlement payload'));
	}

	function renderNightlyCloudRecentRuns(root, payload) {
		const items = Array.isArray(payload && payload.items) ? payload.items : [];
		const latest = payload && payload.latest && typeof payload.latest === 'object' ? payload.latest : (items[0] || {});
		const latestFailure = payload && payload.latest_failure && typeof payload.latest_failure === 'object' ? payload.latest_failure : {};
		if (latest.run_id) {
			setNightlyCloudRunId(root, latest.run_id);
			storeNightlyCloudRun(root, nightlyCloudPayloadFromRecentCard(latest), 'Cloud recent run');
		}

		const result = renderShell(
			root,
			payload || { provider: 'Cloud recent runs' },
			'Cloud recent runs',
			items.length ? 'Cloud returned recent Nightly Inspection run cards for this site. Toolbox displays them as review-only run detail.' : 'Cloud did not return recent Nightly Inspection runs for this site.'
		);
		if (!result) {
			return;
		}

		const guidance = payload && payload.toolbox_guidance && typeof payload.toolbox_guidance === 'object' ? payload.toolbox_guidance : {};
		const meta = el('div', 'npcink-toolbox__result-meta');
		appendMeta(meta, 'Runs', items.length);
		appendMeta(meta, 'Next action', guidance.primary_next_action ? formatLabel(guidance.primary_next_action) : '');
		appendMeta(meta, 'Cloud scheduler truth', guidance.cloud_scheduler_truth === false ? 'No' : '');
		appendMeta(meta, 'Direct writes', payload && payload.safety && payload.safety.direct_wordpress_write === false ? 'No' : '');
		if (meta.childNodes.length) {
			result.appendChild(meta);
		}

		if (latestFailure.run_id) {
			const failurePayload = nightlyCloudPayloadFromRecentCard(latestFailure);
			const retry = nightlyCloudRetryGuidance(failurePayload);
			result.appendChild(el('div', 'npcink-toolbox__result-notice is-warning', 'Latest retry candidate: ' + latestFailure.run_id + '. Retry guidance remains Cloud-owned and final writes remain local.'));
			const retryMeta = el('div', 'npcink-toolbox__result-meta');
			appendMeta(retryMeta, 'Retryable', nightlyCloudRetryable(failurePayload) ? 'Yes' : '');
			appendMeta(retryMeta, 'Failed actions', Array.isArray(retry.failed_action_ids) ? retry.failed_action_ids.join(', ') : '');
			appendMeta(retryMeta, 'Next action', retry.operator_next_action ? formatLabel(retry.operator_next_action) : '');
			if (retryMeta.childNodes.length) {
				result.appendChild(retryMeta);
			}
		}

		if (items.length) {
			const section = createSection('Recent Cloud run cards');
			items.slice(0, 5).forEach((item) => {
				const card = nightlyCloudPayloadFromRecentCard(item);
				const row = el('article', 'npcink-toolbox__result-item');
				row.appendChild(el('h4', '', String(item.run_id || 'Cloud run')));
				const rowMeta = el('div', 'npcink-toolbox__result-meta');
				appendMeta(rowMeta, 'Run status', item.status ? formatLabel(item.status) : '');
				appendMeta(rowMeta, 'Result', item.result_status ? formatLabel(item.result_status) : '');
				appendMeta(rowMeta, 'Reviewable', item.summary && item.summary.reviewable_count);
				appendMeta(rowMeta, 'Retryable', nightlyCloudRetryable(card) ? 'Yes' : '');
				row.appendChild(rowMeta);
				const actions = el('div', 'npcink-toolbox__result-actions');
				const use = el('button', 'button button-small', 'Use run');
				use.type = 'button';
				use.addEventListener('click', () => {
					setNightlyCloudRunId(root, item.run_id);
					storeNightlyCloudRun(root, card, 'Cloud recent run');
					renderTextResult(root, 'Cloud recent run loaded. Refresh status, load result, or retry from Advanced details.', 'pending');
				});
				actions.appendChild(use);
				row.appendChild(actions);
				section.appendChild(row);
			});
			result.appendChild(section);
		}

		result.appendChild(createRawDetails(payload, 'Advanced details: Cloud recent runs'));
	}

	function renderNightlyCloudActions(result, patch) {
		const actions = patch && Array.isArray(patch.actions) ? patch.actions : [];
		if (!actions.length) {
			return;
		}

		const section = createSection('Cloud review details');
		actions.slice(0, 12).forEach((action) => {
			const item = el('article', 'npcink-toolbox__result-item');
			const title = [
				formatLabel(action && action.type ? action.type : 'Review item'),
				action && action.object_type ? formatLabel(action.object_type) : '',
				action && action.object_id ? '#' + action.object_id : ''
			].filter(Boolean).join(' ');
			item.appendChild(el('h4', '', title || 'Review item'));
			if (action && action.recommendation) {
				item.appendChild(el('p', '', action.recommendation));
			}
			const meta = el('div', 'npcink-toolbox__result-meta');
			appendMeta(meta, 'Score', action && action.score);
			appendMeta(meta, 'Severity', action && action.severity ? formatLabel(action.severity) : '');
			appendMeta(meta, 'Writes', action && action.write_path ? action.write_path : 'None');
			if (meta.childNodes.length) {
				item.appendChild(meta);
			}
			section.appendChild(item);
		});
		result.appendChild(section);
	}

	function nightlyCloudResultPayload(payload) {
		return payload && payload.result && typeof payload.result === 'object' ? payload.result : {};
	}

	function nightlyCloudCoreIntakePackage(payload) {
		const cloudResult = nightlyCloudResultPayload(payload);
		const merged = payload && payload.merged_morning_brief && typeof payload.merged_morning_brief === 'object' ? payload.merged_morning_brief : {};
		const runtime = merged.cloud_runtime && typeof merged.cloud_runtime === 'object' ? merged.cloud_runtime : {};
		if (cloudResult.core_intake_package && typeof cloudResult.core_intake_package === 'object') {
			return cloudResult.core_intake_package;
		}
		if (runtime.core_intake_package && typeof runtime.core_intake_package === 'object') {
			return runtime.core_intake_package;
		}
		if (payload && payload.core_intake_package && typeof payload.core_intake_package === 'object') {
			return payload.core_intake_package;
		}
		return {};
	}

	function nightlyCloudMorningBriefV2(payload) {
		const cloudResult = nightlyCloudResultPayload(payload);
		return cloudResult.morning_brief && typeof cloudResult.morning_brief === 'object' ? cloudResult.morning_brief : {};
	}

	function nightlyCloudFeedbackPayload(payload, item, outcome, labels) {
		const cloudResult = nightlyCloudResultPayload(payload);
		const runId = nightlyCloudRunIdFromPayload(payload) || String(cloudResult.run_id || '');
		const reasonCodes = Array.isArray(item && item.reason_codes) ? item.reason_codes.map((reason) => String(reason || '')).filter(Boolean).slice(0, 12) : [];
		const actionId = String((item && item.action_id) || '').slice(0, 191);
		const objectType = String((item && item.object_type) || '').slice(0, 64);
		const objectId = String((item && item.object_id) || '').slice(0, 191);
		return {
			contract_version: 'cloud_agent_feedback.v1',
			agent_id: 'nightly_site_inspection_cloud_runtime',
			agent_version: String(cloudResult.agent_version || 'nightly_inspection_cloud_runtime.v1'),
			source_runtime: 'nightly_site_inspection',
			source_run_id: runId,
			handoff_id: actionId || ['nightly_site_inspection', objectType, objectId].filter(Boolean).join(':') || 'nightly_site_inspection',
			handoff_type: 'morning_brief_priority',
			local_surface: 'toolbox_nightly_inspection_morning_brief',
			local_outcome: outcome,
			feedback_labels: labels,
			operator_note: '',
			local_proposal_id: '',
			evidence_ref_ids: [actionId, objectType && objectId ? objectType + ':' + objectId : '', runId ? 'run:' + runId : ''].filter(Boolean).slice(0, 24),
			source_action_id: actionId,
			source_object_type: objectType,
			source_object_id: objectId,
			source_reason_codes: reasonCodes,
			source_score: Number.isFinite(Number(item && item.score)) ? Number(item.score) : null,
			source_severity: String((item && item.severity) || ''),
			redaction_status: 'metadata_only',
			retention_class: 'quality_eval',
			created_at: new Date().toISOString()
		};
	}

	async function submitNightlyCloudFeedback(statusNode, payload, item, button, outcome, labels) {
		const originalText = button ? button.textContent : '';
		if (button) {
			button.disabled = true;
			button.textContent = 'Sending...';
		}
		statusNode.className = 'npcink-toolbox__result-notice is-pending';
		statusNode.textContent = 'Sending Morning Brief feedback to Cloud eval...';
		try {
			const receipt = await postJson(config.restUrl, 'agent-feedback', nightlyCloudFeedbackPayload(payload, item, outcome, labels));
			statusNode.className = 'npcink-toolbox__result-notice is-ok';
			statusNode.textContent = receipt && receipt.accepted_for_eval
				? 'Morning Brief feedback accepted for Cloud eval. WordPress approval and writes remain local.'
				: 'Morning Brief feedback sent. WordPress approval and writes remain local.';
			refreshVisibleAgentFeedbackSummaries();
		} catch (error) {
			statusNode.className = 'npcink-toolbox__result-notice is-error';
			statusNode.textContent = (error && error.message ? error.message : 'Could not send Morning Brief feedback.') + ' WordPress approval and writes remain local.';
		} finally {
			if (button) {
				button.disabled = false;
				button.textContent = originalText;
			}
		}
	}

	function appendNightlyCloudFeedbackControls(container, payload, item) {
		const feedback = el('div', 'npcink-toolbox__result-feedback');
		feedback.setAttribute('data-toolbox-nightly-agent-feedback', 'true');
		feedback.setAttribute('data-toolbox-agent-feedback-quick', 'true');
		feedback.appendChild(el('h4', '', 'Morning Brief feedback'));
		const actions = el('div', 'npcink-toolbox__result-actions');
		const status = el('div', 'npcink-toolbox__result-notice is-pending', 'Feedback updates Cloud eval only. Core approval, preflight, and final WordPress writes stay local.');
		const options = [
			{ label: 'Useful', outcome: 'accepted', labels: ['evidence_useful', 'operator_confidence_high'] },
			{ label: 'Wrong priority', outcome: 'rejected', labels: ['wrong_priority', 'operator_confidence_low'] },
			{ label: 'Already handled', outcome: 'rejected', labels: ['already_handled'] },
			{ label: 'Evidence weak', outcome: 'rejected', labels: ['evidence_weak', 'operator_confidence_low'] },
			{ label: 'Wrong next step', outcome: 'rejected', labels: ['wrong_next_step'] }
		];
		options.forEach((option) => {
			const button = el('button', 'button button-small', option.label);
			button.type = 'button';
			button.title = 'Send metadata-only Morning Brief feedback to Cloud eval.';
			button.setAttribute('data-toolbox-nightly-feedback-outcome', option.outcome);
			button.setAttribute('data-toolbox-nightly-feedback-labels', option.labels.join(','));
			button.addEventListener('click', () => submitNightlyCloudFeedback(status, payload, item, button, option.outcome, option.labels));
			actions.appendChild(button);
		});
		feedback.appendChild(actions);
		feedback.appendChild(status);
		container.appendChild(feedback);
	}

	function nightlyCloudSelectedReviewItems(section) {
		return Array.from(section.querySelectorAll('[data-toolbox-nightly-core-review-item]'))
			.filter((checkbox) => checkbox instanceof HTMLInputElement && checkbox.checked)
			.map((checkbox) => {
				const row = checkbox.closest('.npcink-toolbox__result-item');
				return row && row.__npcinkNightlyReviewItem ? row.__npcinkNightlyReviewItem : null;
			})
			.filter(Boolean);
	}

	function nightlyCloudSelectedReviewItemIds(items) {
		return items
			.map((item, index) => String((item && item.action_id) || 'morning_brief_review_' + String(index + 1)).trim())
			.filter(Boolean)
			.slice(0, 10);
	}

	function nightlyCloudStableHash(value) {
		const source = String(value || '');
		let hash = 5381;
		for (let index = 0; index < source.length; index += 1) {
			hash = ((hash << 5) + hash) ^ source.charCodeAt(index);
		}
		return (hash >>> 0).toString(16).padStart(8, '0');
	}

	function nightlyCloudCompletedDraftFields(section) {
		const title = section.querySelector('[data-toolbox-nightly-complete-title]');
		const content = section.querySelector('[data-toolbox-nightly-complete-content]');
		return {
			title: title instanceof HTMLInputElement ? title.value.trim() : '',
			content: content instanceof HTMLTextAreaElement ? content.value.trim() : ''
		};
	}

	function updateNightlyCloudCoreReviewAction(section) {
		const selected = nightlyCloudSelectedReviewItems(section).length;
		const count = section.querySelector('[data-toolbox-nightly-core-review-count]');
		const button = section.querySelector('[data-toolbox-nightly-core-review-submit]');
		const completedButton = section.querySelector('[data-toolbox-nightly-complete-submit]');
		if (count) {
			count.textContent = String(selected);
		}
		if (button instanceof HTMLButtonElement) {
			button.disabled = selected < 1 || !config.adapterRestUrl;
		}
		if (completedButton instanceof HTMLButtonElement) {
			completedButton.disabled = selected < 1 || !config.adapterRestUrl;
		}
	}

	function nightlyCloudCompletedDraftProposalPayload(payload, selectedItems, fields) {
		const cloudResult = nightlyCloudResultPayload(payload);
		const runId = nightlyCloudRunIdFromPayload(payload) || String(cloudResult.run_id || '');
		const coreIntakePackage = nightlyCloudCoreIntakePackage(payload);
		const selectedIds = nightlyCloudSelectedReviewItemIds(selectedItems);
		const evidenceRefs = selectedItems.map((item, index) => ({
			action_id: selectedIds[index] || '',
			title: String((item && item.title) || 'Morning Brief review item').slice(0, 180),
			object_type: String((item && item.object_type) || '').slice(0, 64),
			object_id: String((item && item.object_id) || '').slice(0, 128),
			score: Number.isFinite(Number(item && item.score)) ? Number(item.score) : null,
			severity: String((item && item.severity) || '').slice(0, 64),
			reason_codes: Array.isArray(item && item.reason_codes) ? item.reason_codes.map((reason) => String(reason || '')).filter(Boolean).slice(0, 12) : [],
			recommended_next_action: String((item && item.recommended_next_action) || 'operator_review').slice(0, 128),
			evidence_summary: String((item && item.evidence_summary) || '').slice(0, 600),
			suggested_use: 'morning_brief_review_evidence'
		}));
		const idempotencyBasis = [runId || 'nightly-inspection', selectedIds.join(','), fields.title].join('|');
		return {
			ability_id: 'npcink-abilities-toolkit/create-draft',
			title: 'Create draft from Nightly Morning Brief',
			summary: 'Review a completed draft prepared from selected Nightly Intelligence evidence. Core approval and execution remain local.',
			input: {
				title: fields.title,
				content: fields.content,
				content_format: 'html',
				status: 'draft',
				meta: {
					nightly_inspection_cloud_run_id: runId,
					nightly_inspection_selected_review_item_ids: selectedIds.join(','),
					nightly_inspection_selected_review_item_count: selectedIds.length,
					nightly_inspection_evidence_ref_count: evidenceRefs.length,
					nightly_inspection_source_surface: 'toolbox_morning_brief_completed_draft'
				},
				dry_run: true,
				commit: false,
				idempotency_key: 'nightly-inspection-complete-draft-' + nightlyCloudStableHash(idempotencyBasis)
			},
			preview: {
				proposal_ready: true,
				needs_input: [],
				blocked_items: [],
				nightly_inspection_review: {
					contract_version: 'nightly_site_inspection_completed_draft_handoff.v1',
					source_surface: 'toolbox_morning_brief_completed_draft',
					source_status: 'operator_completed_input',
					cloud_run_id: runId,
					selected_review_item_ids: selectedIds,
					selected_review_items: selectedItems,
					evidence_refs: evidenceRefs,
					evidence_ref_count: evidenceRefs.length,
					core_intake_package: coreIntakePackage,
					proposal_ready: true,
					completed_draft_submitted: true,
					completed_draft: {
						title_length: fields.title.length,
						content_length: fields.content.length,
						content_format: 'html',
						submitted_from: 'toolbox_morning_brief'
					},
					operator_next_action: 'review_completed_draft_in_core',
					needs_input_resolution_owner: 'toolbox_morning_brief_operator',
					resubmission_required: false,
					core_amendment_supported: false,
					final_write_path: 'core_proposal_required',
					direct_wordpress_write: false,
					cloud_scheduler_truth: false
				}
			},
			caller: {
				surface: 'toolbox_nightly_inspection_morning_brief',
				external_thread_id: 'toolbox-nightly-inspection-completed-draft',
				source: 'cloud_nightly_inspection'
			}
		};
	}

	async function submitNightlyCloudCoreReviewProposal(root, payload, section, statusNode, button) {
		const selectedItems = nightlyCloudSelectedReviewItems(section);
		const originalText = button ? button.textContent : '';
		if (!selectedItems.length) {
			statusNode.className = 'npcink-toolbox__result-notice is-warning';
			statusNode.textContent = 'Select at least one Morning Brief review item before creating a Core proposal.';
			return;
		}
		if (!config.adapterRestUrl) {
			statusNode.className = 'npcink-toolbox__result-notice is-error';
			statusNode.textContent = 'Npcink Adapter REST URL is unavailable. Core proposal submission is not available from this site.';
			return;
		}

		if (button) {
			button.disabled = true;
			button.textContent = 'Submitting Core review...';
		}
		statusNode.className = 'npcink-toolbox__result-notice is-pending';
		statusNode.textContent = 'Building Nightly Inspection review plan...';

		try {
			const cloudResult = nightlyCloudResultPayload(payload);
			const plan = await postJson(config.restUrl, 'flows/nightly-inspection-review-plan', {
				cloud_run_id: nightlyCloudRunIdFromPayload(payload) || String(cloudResult.run_id || ''),
				agent_version: String(cloudResult.agent_version || 'nightly_site_inspection_cloud_runtime.v1'),
				selected_items: selectedItems,
				core_intake_package: nightlyCloudCoreIntakePackage(payload)
			});
			statusNode.textContent = 'Submitting selected Morning Brief items to Core proposal intake...';
			const bridge = await postJson(config.adapterRestUrl, 'proposals/from-plan', {
				plan_ability_id: 'npcink-toolbox/build-nightly-inspection-review-plan',
				plan,
				plan_input: {
					source: 'nightly_site_inspection_morning_brief',
					cloud_run_id: nightlyCloudRunIdFromPayload(payload) || String(cloudResult.run_id || ''),
					selected_count: selectedItems.length
				},
				caller: {
					surface: 'toolbox_nightly_inspection_morning_brief',
					external_thread_id: 'toolbox-nightly-inspection-review',
					source: 'cloud_nightly_inspection'
				}
			});
			renderProposalCreated(root, proposalFromPlanResponse(bridge), {
				title: 'Nightly Inspection review proposal submitted',
				summary: 'Core created a blocked review proposal from selected Morning Brief evidence. Human title and content input are required before approval, preflight, or execution can proceed.',
				rawTitle: 'Core Nightly Inspection review response',
				receiptContext: {
					handoffType: 'nightly_inspection_review_plan',
					sourceItemId: 'morning_brief_selected_review_items',
					sourceLabel: 'Morning Brief selected review items',
					targetAbilityId: 'npcink-abilities-toolkit/create-draft',
				},
			});
		} catch (error) {
			renderCoreHandoffStatusError(statusNode, error, 'Could not submit selected Morning Brief items to Core.', {
				handoffType: 'nightly_inspection_review_plan',
				sourceItemId: 'morning_brief_selected_review_items',
				sourceLabel: 'Morning Brief selected review items',
				targetAbilityId: 'npcink-abilities-toolkit/create-draft',
			}, 'Core review submission error');
		} finally {
			if (button) {
				button.disabled = false;
				button.textContent = originalText;
				updateNightlyCloudCoreReviewAction(section);
			}
		}
	}

	async function submitNightlyCloudCompletedDraftProposal(root, payload, section, statusNode, button) {
		const selectedItems = nightlyCloudSelectedReviewItems(section);
		const fields = nightlyCloudCompletedDraftFields(section);
		const originalText = button ? button.textContent : '';
		if (!selectedItems.length) {
			statusNode.className = 'npcink-toolbox__result-notice is-warning';
			statusNode.textContent = 'Select at least one Morning Brief review item before creating a completed Core proposal.';
			return;
		}
		if (!fields.title || !fields.content) {
			statusNode.className = 'npcink-toolbox__result-notice is-warning';
			statusNode.textContent = 'Add a draft title and draft content before creating the completed Core proposal.';
			return;
		}
		if (!config.adapterRestUrl) {
			statusNode.className = 'npcink-toolbox__result-notice is-error';
			statusNode.textContent = 'Npcink Adapter REST URL is unavailable. Core proposal submission is not available from this site.';
			return;
		}

		if (button) {
			button.disabled = true;
			button.textContent = 'Submitting completed draft...';
		}
		statusNode.className = 'npcink-toolbox__result-notice is-pending';
		statusNode.textContent = 'Submitting completed Morning Brief draft to Core proposal intake...';

		try {
			const proposal = await postJson(
				config.adapterRestUrl,
				'proposals',
				nightlyCloudCompletedDraftProposalPayload(payload, selectedItems, fields)
			);
			renderProposalCreated(root, proposalFromPlanResponse(proposal), {
				title: 'Nightly Inspection draft proposal submitted',
				summary: 'Core created a complete draft proposal from selected Morning Brief evidence. Review, approval, preflight, and execution remain in Core/Adapter.',
				rawTitle: 'Core Nightly Inspection completed draft response',
				receiptContext: {
					handoffType: 'nightly_inspection_completed_draft',
					sourceItemId: 'morning_brief_completed_draft',
					sourceLabel: 'Morning Brief completed draft',
					targetAbilityId: 'npcink-abilities-toolkit/create-draft',
				},
			});
		} catch (error) {
			renderCoreHandoffStatusError(statusNode, error, 'Could not submit the completed Morning Brief draft to Core.', {
				handoffType: 'nightly_inspection_completed_draft',
				sourceItemId: 'morning_brief_completed_draft',
				sourceLabel: 'Morning Brief completed draft',
				targetAbilityId: 'npcink-abilities-toolkit/create-draft',
			}, 'Completed draft submission error');
		} finally {
			if (button) {
				button.disabled = false;
				button.textContent = originalText;
				updateNightlyCloudCoreReviewAction(section);
			}
		}
	}

	function renderNightlyCloudMorningBrief(result, payload) {
		const brief = nightlyCloudMorningBriefV2(payload);
		const priorityQueue = Array.isArray(brief.priority_queue) ? brief.priority_queue : [];
		const issueGroups = Array.isArray(brief.issue_groups) ? brief.issue_groups : [];
		if (!priorityQueue.length && !issueGroups.length && !brief.top_summary) {
			return;
		}

		const section = createSection('Morning Brief review queue');
		const topSummary = brief.top_summary && typeof brief.top_summary === 'object' ? brief.top_summary : {};
		const meta = el('div', 'npcink-toolbox__result-meta');
		appendMeta(meta, 'Scanned', topSummary.items_scanned);
		appendMeta(meta, 'Reviewable', topSummary.reviewable_items);
		appendMeta(meta, 'Warnings', topSummary.warnings);
		appendMeta(meta, 'Critical', topSummary.critical);
		appendMeta(meta, 'Average score', topSummary.average_score);
		appendMeta(meta, 'Score version', topSummary.score_version);
		if (meta.childNodes.length) {
			section.appendChild(meta);
		}

		if (issueGroups.length) {
			renderSupportItems(
				section,
				'Issue groups',
				issueGroups.slice(0, 8).map((group) => ({
					name: formatLabel(group.label || group.id || 'Issue group'),
					value: (group.count || 0) + ' item' + (Number(group.count || 0) === 1 ? '' : 's'),
					reason: Array.isArray(group.reason_codes) ? group.reason_codes.map(formatLabel).join(', ') : ''
				})),
				'No issue groups returned.'
			);
		}

		if (priorityQueue.length) {
			const list = el('div', 'npcink-toolbox__result-list');
			priorityQueue.slice(0, 5).forEach((item, index) => {
				const row = el('article', 'npcink-toolbox__result-item');
				row.__npcinkNightlyReviewItem = item;
				const heading = el('label', 'npcink-toolbox__batch-row');
				const checkbox = document.createElement('input');
				checkbox.type = 'checkbox';
				checkbox.setAttribute('data-toolbox-nightly-core-review-item', String(item.action_id || index + 1));
				checkbox.addEventListener('change', () => updateNightlyCloudCoreReviewAction(section));
				heading.appendChild(checkbox);
				const headingText = el('span', 'npcink-toolbox__batch-row-body');
				headingText.appendChild(el('strong', '', [
					item.title || 'Review item',
					item.object_type ? formatLabel(item.object_type) : '',
					item.object_id ? '#' + item.object_id : ''
				].filter(Boolean).join(' ')));
				headingText.appendChild(el('small', '', 'Select for Core review handoff'));
				heading.appendChild(headingText);
				row.appendChild(heading);
				if (item.evidence_summary) {
					row.appendChild(el('p', '', item.evidence_summary));
				}
				const itemMeta = el('div', 'npcink-toolbox__result-meta');
				appendMeta(itemMeta, 'Score', item.score);
				appendMeta(itemMeta, 'Severity', item.severity ? formatLabel(item.severity) : '');
				appendMeta(itemMeta, 'Priority', item.priority_reason ? formatLabel(item.priority_reason) : '');
				appendMeta(itemMeta, 'Groups', Array.isArray(item.group_ids) ? item.group_ids.map(formatLabel).join(', ') : '');
				appendMeta(itemMeta, 'Next action', item.recommended_next_action ? formatLabel(item.recommended_next_action) : '');
				if (itemMeta.childNodes.length) {
					row.appendChild(itemMeta);
				}
				appendNightlyCloudFeedbackControls(row, payload, item);
				list.appendChild(row);
			});
			section.appendChild(list);

			const handoff = el('div', 'npcink-toolbox__result-feedback');
			handoff.setAttribute('data-toolbox-nightly-core-review-handoff', 'true');
			handoff.appendChild(el('h4', '', 'Core proposal handoff'));
			handoff.appendChild(el('p', '', 'Selected items can become a blocked Core review proposal, or a complete draft proposal after you add the draft title and content. Toolbox does not approve, preflight, execute, or write content.'));
			const coreIntakePackage = nightlyCloudCoreIntakePackage(payload);
			if (coreIntakePackage.contract_version) {
				const intakeMeta = el('div', 'npcink-toolbox__result-meta');
				appendMeta(intakeMeta, 'Core intake package', coreIntakePackage.contract_version);
				appendMeta(intakeMeta, 'Target route', coreIntakePackage.target_route);
				appendMeta(intakeMeta, 'Receipt owner', coreIntakePackage.receipt_expectation && coreIntakePackage.receipt_expectation.receipt_owner);
				handoff.appendChild(intakeMeta);
			}
			const completedDraft = el('div', 'npcink-toolbox__result-item');
			completedDraft.appendChild(el('h4', '', 'Completed draft input'));
			const titleLabel = el('label', 'npcink-toolbox__field');
			titleLabel.appendChild(el('span', '', 'Draft title'));
			const titleInput = document.createElement('input');
			titleInput.type = 'text';
			titleInput.setAttribute('data-toolbox-nightly-complete-title', 'true');
			titleInput.placeholder = 'Reviewed draft title';
			titleLabel.appendChild(titleInput);
			completedDraft.appendChild(titleLabel);
			const contentLabel = el('label', 'npcink-toolbox__field');
			contentLabel.appendChild(el('span', '', 'Draft content'));
			const contentInput = document.createElement('textarea');
			contentInput.rows = 6;
			contentInput.setAttribute('data-toolbox-nightly-complete-content', 'true');
			contentInput.placeholder = 'Reviewed draft content for Core proposal review';
			contentLabel.appendChild(contentInput);
			completedDraft.appendChild(contentLabel);
			completedDraft.appendChild(el('p', '', 'Use this only after the selected Morning Brief evidence has been turned into reviewed draft text. Core remains the approval and execution surface.'));
			handoff.appendChild(completedDraft);
			const actions = el('div', 'npcink-toolbox__result-actions');
			const submit = el('button', 'button button-primary', 'Submit selected to Core review');
			submit.type = 'button';
			submit.setAttribute('data-toolbox-nightly-core-review-submit', 'true');
			const completedSubmit = el('button', 'button', 'Submit completed draft to Core');
			completedSubmit.type = 'button';
			completedSubmit.setAttribute('data-toolbox-nightly-complete-submit', 'true');
			const status = el('div', 'npcink-toolbox__result-notice is-pending', 'Selected: ');
			const count = el('strong', '', '0');
			count.setAttribute('data-toolbox-nightly-core-review-count', 'true');
			status.appendChild(count);
			status.appendChild(document.createTextNode('. Core proposal creation requires Adapter/Core availability.'));
			submit.addEventListener('click', () => submitNightlyCloudCoreReviewProposal(result.closest('form') || result, payload, section, status, submit));
			completedSubmit.addEventListener('click', () => submitNightlyCloudCompletedDraftProposal(result.closest('form') || result, payload, section, status, completedSubmit));
			actions.appendChild(submit);
			actions.appendChild(completedSubmit);
			handoff.appendChild(actions);
			handoff.appendChild(status);
			section.appendChild(handoff);
			updateNightlyCloudCoreReviewAction(section);
		}
		result.appendChild(section);
	}

	function renderNightlyCloudScoreBreakdown(result, payload) {
		const cloudResult = nightlyCloudResultPayload(payload);
		const actions = Array.isArray(cloudResult.actions) ? cloudResult.actions : [];
		const scoredActions = actions.filter((action) => action && action.score_breakdown && Array.isArray(action.score_breakdown.dimensions));
		if (!scoredActions.length) {
			return;
		}
		const section = createSection('Score breakdown');
		scoredActions.slice(0, 3).forEach((action) => {
			const item = el('article', 'npcink-toolbox__result-item');
			item.appendChild(el('h4', '', [
				action.title || 'Scored item',
				action.object_type ? formatLabel(action.object_type) : '',
				action.object_id ? '#' + action.object_id : ''
			].filter(Boolean).join(' ')));
			const meta = el('div', 'npcink-toolbox__result-meta');
			appendMeta(meta, 'Overall', action.score);
			appendMeta(meta, 'Severity', action.severity ? formatLabel(action.severity) : '');
			appendMeta(meta, 'Priority', action.priority_reason ? formatLabel(action.priority_reason) : '');
			item.appendChild(meta);
			renderSupportItems(
				item,
				'Dimensions',
				action.score_breakdown.dimensions
					.filter((dimension) => Number(dimension.impact || 0) > 0)
					.slice(0, 6)
					.map((dimension) => ({
						name: formatLabel(dimension.label || dimension.id || 'Dimension'),
						value: 'impact ' + String(dimension.impact || 0),
						reason: Array.isArray(dimension.reason_codes) ? dimension.reason_codes.map(formatLabel).join(', ') : ''
					})),
				'No scoring impacts returned.'
			);
			section.appendChild(item);
		});
		result.appendChild(section);
	}

	function firstNightlyCloudText(source, keys) {
		const item = source && typeof source === 'object' ? source : {};
		for (let index = 0; index < keys.length; index += 1) {
			const value = item[keys[index]];
			if (value !== undefined && value !== null && String(value).trim() !== '') {
				return String(value).trim();
			}
		}
		return '';
	}

	function nightlyCloudOutcomeLabel(payload) {
		if (nightlyCloudResultStatus(payload) === 'partially_succeeded') {
			return 'Partial success';
		}
		if (nightlyCloudSucceeded(payload)) {
			return 'Complete';
		}
		if (nightlyCloudTerminal(payload)) {
			return 'Needs attention';
		}
		return 'Running';
	}

	function nightlyCloudReviewFocus(patch, merged) {
		const cloudRuntime = merged && merged.cloud_runtime && typeof merged.cloud_runtime === 'object' ? merged.cloud_runtime : {};
		const mergedCount = Number.isFinite(Number(cloudRuntime.merged_priority_count)) ? Number(cloudRuntime.merged_priority_count) : null;
		const actionCount = Number.isFinite(Number(patch && patch.action_count)) ? Number(patch.action_count) : null;
		if (mergedCount !== null && mergedCount > 0) {
			return {
				label: String(mergedCount) + ' local priorities',
				description: 'Review the matched Morning Brief priorities before proposal work.'
			};
		}
		if (actionCount !== null && actionCount > 0) {
			return {
				label: String(actionCount) + ' Cloud review items',
				description: 'Load or inspect the result before proposal work.'
			};
		}
		return {
			label: 'No review items',
			description: 'No Core handoff is ready from this inspection result.'
		};
	}

	function renderNightlyCloudRunDetail(result, payload) {
		const cloudRun = payload && payload.cloud_run && typeof payload.cloud_run === 'object' ? payload.cloud_run : {};
		const lifecycle = cloudRun.run_lifecycle && typeof cloudRun.run_lifecycle === 'object' ? cloudRun.run_lifecycle : {};
		const requestSummary = payload && payload.cloud_request_summary && typeof payload.cloud_request_summary === 'object' ? payload.cloud_request_summary : {};
		const retryGuidance = nightlyCloudRetryGuidance(payload);
		const section = createSection('Cloud run detail');
		const meta = el('div', 'npcink-toolbox__result-meta');
		appendMeta(meta, 'Run state', nightlyCloudOutcomeLabel(payload));
		appendMeta(meta, 'Worker phase', formatLabel(nightlyCloudRunPhase(payload)));
		appendMeta(meta, 'Result', nightlyCloudResultStatus(payload) ? formatLabel(nightlyCloudResultStatus(payload)) : '');
		appendMeta(meta, 'Started', formatDateTime(lifecycle.processing_started_at || lifecycle.started_at || cloudRun.started_at));
		appendMeta(meta, 'Finished', formatDateTime(lifecycle.processing_finished_at || lifecycle.completed_at || lifecycle.terminal_at || cloudRun.completed_at));
		appendMeta(meta, 'Failure code', firstNightlyCloudText(lifecycle, ['error_code', 'failure_code', 'reason_code']) || firstNightlyCloudText(cloudRun, ['error_code', 'failure_code', 'reason_code']));
		appendMeta(meta, 'Retryable', nightlyCloudRetryable(payload) ? 'Yes' : '');
		appendPositiveMeta(meta, 'Snapshot items', requestSummary.item_count);
		appendMeta(meta, 'Retention', requestSummary.retention_ttl ? Math.round(Number(requestSummary.retention_ttl) / 86400) + ' days' : '');
		appendMeta(meta, 'Cloud role', requestSummary.cloud_role ? formatLabel(requestSummary.cloud_role) : '');
		if (meta.childNodes.length) {
			section.appendChild(meta);
		}
		if (nightlyCloudTerminal(payload) && !nightlyCloudSucceeded(payload)) {
			section.appendChild(el('div', 'npcink-toolbox__result-notice is-warning', 'Cloud finished without a mergeable result. Review the advanced payload or retry after resolving the Cloud-side reason.'));
		} else if (nightlyCloudResultStatus(payload) === 'partially_succeeded') {
			section.appendChild(el('div', 'npcink-toolbox__result-notice is-warning', 'Cloud returned partial success. Review failed items and retry only after confirming the local bounded snapshot is still current.'));
		} else if (!nightlyCloudTerminal(payload)) {
			section.appendChild(el('div', 'npcink-toolbox__result-notice is-pending', 'Cloud is still the run-state owner. Refresh status later; Toolbox is not running a local queue.'));
		}
		if (nightlyCloudRetryable(payload)) {
			const retryMeta = el('div', 'npcink-toolbox__result-meta');
			appendMeta(retryMeta, 'Retry owner', retryGuidance.retry_owner ? formatLabel(retryGuidance.retry_owner) : 'Cloud Runtime');
			appendMeta(retryMeta, 'Retry action', retryGuidance.operator_next_action ? formatLabel(retryGuidance.operator_next_action) : 'Retry run');
			appendMeta(retryMeta, 'Failed actions', Array.isArray(retryGuidance.failed_action_ids) ? retryGuidance.failed_action_ids.join(', ') : '');
			if (retryMeta.childNodes.length) {
				section.appendChild(retryMeta);
			}
		}
		result.appendChild(section);
	}

	function renderNightlyCloudHandoff(result, payload, patch, merged) {
		const focus = nightlyCloudReviewFocus(patch, merged);
		const hasMerged = !!(merged && merged.cloud_runtime);
		const strip = el('div', 'npcink-toolbox__readiness-strip');
		strip.appendChild(nightlyCloudSummaryItem(
			'Inspection',
			nightlyCloudSucceeded(payload) ? 'ok' : nightlyCloudTerminal(payload) ? 'warning' : 'pending',
			nightlyCloudOutcomeLabel(payload),
			nightlyCloudRunIdFromPayload(payload) ? 'Run ID: ' + nightlyCloudRunIdFromPayload(payload) : 'Waiting for Cloud run id.'
		));
		strip.appendChild(nightlyCloudSummaryItem(
			'Top review',
			focus.label === 'No review items' ? 'warning' : 'ok',
			focus.label,
			focus.description
		));
		strip.appendChild(nightlyCloudSummaryItem(
			'Next step',
			hasMerged ? 'ok' : 'warning',
			hasMerged ? 'Review in Core' : 'Load result first',
			'Toolbox prepares review context only; it does not create proposals or write content.'
		));

		const section = createSection('Core handoff');
		section.appendChild(strip);
		if (hasMerged) {
			section.appendChild(el('div', 'npcink-toolbox__result-notice is-pending', 'Review the merged Morning Brief locally, then open Core for proposal work. Final approval and WordPress writes stay in Core.'));
			const actions = el('div', 'npcink-toolbox__result-actions');
			if (config.coreAdminUrl) {
				actions.appendChild(createLink(config.coreAdminUrl, 'Review in Core'));
			}
			if (actions.childNodes.length) {
				section.appendChild(actions);
			}
		}
		result.appendChild(section);
	}

	function nightlyCloudSummaryItem(title, status, label, description) {
		const item = el('div', 'npcink-toolbox__readiness-item is-' + (status || 'warning'));
		item.appendChild(el('span', '', title));
		item.appendChild(el('strong', '', label || 'Pending'));
		item.appendChild(el('small', '', description || ''));
		return item;
	}

	function updateNightlyCloudRunSummary(root, payload, stageLabel) {
		const summary = root.querySelector('[data-toolbox-nightly-cloud-run-summary]');
		if (!summary) {
			return;
		}

		clearNode(summary);
		const cloudRun = payload && payload.cloud_run && typeof payload.cloud_run === 'object' ? payload.cloud_run : {};
		const lifecycle = cloudRun.run_lifecycle && typeof cloudRun.run_lifecycle === 'object' ? cloudRun.run_lifecycle : {};
		const patch = payload && payload.morning_brief_patch && typeof payload.morning_brief_patch === 'object' ? payload.morning_brief_patch : {};
		const merged = payload && payload.merged_morning_brief && typeof payload.merged_morning_brief === 'object' ? payload.merged_morning_brief : {};
		const runId = nightlyCloudRunIdFromPayload(payload) || nightlyCloudRunId(root);
		const status = payload && payload.status ? String(payload.status) : String(cloudRun.status || '');
		const resultStatus = nightlyCloudResultStatus(payload);
		const succeeded = status === 'succeeded' || lifecycle.terminal_status === 'succeeded';
		const failed = status === 'failed' || status === 'canceled' || lifecycle.terminal_status === 'failed' || lifecycle.terminal_status === 'canceled';
		const phase = lifecycle.phase || (succeeded || failed ? 'terminal' : status || 'submitted');
		const mergedCount = merged.cloud_runtime && Number.isFinite(Number(merged.cloud_runtime.merged_priority_count)) ? Number(merged.cloud_runtime.merged_priority_count) : null;
		const actionCount = Number.isFinite(Number(patch.action_count)) ? Number(patch.action_count) : null;

		summary.appendChild(nightlyCloudSummaryItem(
			'Run status',
			failed || resultStatus === 'partially_succeeded' ? 'warning' : succeeded ? 'ok' : 'warning',
			resultStatus === 'partially_succeeded' ? 'Partial Success' : status ? formatLabel(status) : formatLabel(stageLabel || 'Submitted'),
			runId ? 'Run ID: ' + runId : 'Waiting for Cloud run id.'
		));
		summary.appendChild(nightlyCloudSummaryItem(
			'Worker phase',
			succeeded ? 'ok' : 'warning',
			formatLabel(phase),
			lifecycle.processing_started_at ? 'Started: ' + formatDateTime(lifecycle.processing_started_at) : 'Queue-backed Cloud worker status.'
		));
		summary.appendChild(nightlyCloudSummaryItem(
			'Merge',
			merged.cloud_runtime ? 'ok' : 'warning',
			merged.cloud_runtime ? 'Merged preview' : 'Result not merged yet',
			merged.cloud_runtime ? String(mergedCount === null ? actionCount || 0 : mergedCount) + ' local priority match(es); local review still required.' : 'Load result after the run succeeds.'
		));
		summary.hidden = false;
	}

	function renderNightlyCloudBatchPayload(root, payload, title, summary) {
		const result = renderShell(root, payload || { provider: 'Cloud Batch' }, title, summary);
		if (!result) {
			return;
		}
		updateNightlyCloudRunSummary(root, payload, title);

		const meta = el('div', 'npcink-toolbox__result-meta');
		const cloudRun = payload && payload.cloud_run && typeof payload.cloud_run === 'object' ? payload.cloud_run : {};
		const requestSummary = payload && payload.cloud_request_summary && typeof payload.cloud_request_summary === 'object' ? payload.cloud_request_summary : {};
		const patch = payload && payload.morning_brief_patch && typeof payload.morning_brief_patch === 'object' ? payload.morning_brief_patch : {};
		const merged = payload && payload.merged_morning_brief && typeof payload.merged_morning_brief === 'object' ? payload.merged_morning_brief : {};
		appendMeta(meta, 'Run', nightlyCloudRunIdFromPayload(payload));
		appendMeta(meta, 'Status', payload && payload.status ? formatLabel(payload.status) : (cloudRun.status ? formatLabel(cloudRun.status) : ''));
		appendMeta(meta, 'Result', nightlyCloudResultStatus(payload) ? formatLabel(nightlyCloudResultStatus(payload)) : '');
		appendPositiveMeta(meta, 'Snapshot items', requestSummary.item_count);
		appendMeta(meta, 'Payload', requestSummary.payload_mode ? formatLabel(requestSummary.payload_mode) : '');
		appendMeta(meta, 'Retention', requestSummary.retention_ttl ? Math.round(Number(requestSummary.retention_ttl) / 86400) + ' days' : '');
		appendMeta(meta, 'Patch actions', patch.action_count);
		appendMeta(meta, 'Merged priorities', merged.cloud_runtime && merged.cloud_runtime.merged_priority_count);
		if (meta.childNodes.length) {
			result.appendChild(meta);
		}

		renderNightlyCloudRunDetail(result, payload);
		renderNightlyCloudActions(result, patch);
		renderNightlyCloudMorningBrief(result, payload);
		renderNightlyCloudScoreBreakdown(result, payload);
		renderNightlyCloudHandoff(result, payload, patch, merged);

		if (merged.cloud_runtime) {
			result.appendChild(el('div', 'npcink-toolbox__result-notice is-success', 'Cloud scoring was merged into the local Morning Brief preview for review.'));
			result.appendChild(createRawDetails(merged, 'Advanced details: merged Morning Brief'));
		} else if (!nightlyCloudTerminal(payload)) {
			result.appendChild(el('div', 'npcink-toolbox__result-notice is-pending', 'Cloud run is still processing. This panel will check briefly after submit; manual status and result reads remain available.'));
		} else if (nightlyCloudSucceeded(payload)) {
			result.appendChild(el('div', 'npcink-toolbox__result-notice is-warning', 'Cloud run succeeded, but the result has not been merged yet. Use Load result from Recent run or Advanced details.'));
		} else if (nightlyCloudTerminal(payload)) {
			result.appendChild(el('div', 'npcink-toolbox__result-notice is-error', 'Cloud run ended without a merged result. No local queue, Core proposal, or WordPress write was created; retry after reviewing the advanced payload.'));
		}

		storeNightlyCloudRun(root, payload, title);
		result.appendChild(createRawDetails(payload, 'Advanced details: Cloud inspection payload'));
	}

	async function autoPollNightlyCloudBatch(root, runId) {
		let payload = null;
		for (let attempt = 1; attempt <= 4; attempt += 1) {
			await sleep(attempt === 1 ? 1200 : 2200);
			payload = await getJson(config.restUrl, 'nightly-inspection/cloud-batch/' + encodeURIComponent(runId));
			setNightlyCloudRunId(root, nightlyCloudRunIdFromPayload(payload) || runId);
			renderNightlyCloudBatchPayload(root, payload, 'Cloud inspection status', 'Automatic status check ' + attempt + ' of 4. Cloud remains the run-state owner.');
			if (nightlyCloudTerminal(payload)) {
				break;
			}
		}
		return payload;
	}

	async function autoReadNightlyCloudBatchResult(root, runId) {
		const payload = await postJson(config.restUrl, 'nightly-inspection/cloud-batch/' + encodeURIComponent(runId) + '/result', {
			morning_brief: nightlyCloudLocalMorningBrief(root)
		});
		setNightlyCloudRunId(root, nightlyCloudRunIdFromPayload(payload) || runId);
		renderNightlyCloudBatchPayload(root, payload, 'Cloud inspection result', 'Cloud scoring was automatically merged into the local review-only Morning Brief preview.');
		return payload;
	}

	async function submitNightlyCloudBatch(root, button) {
		if (!nightlyCloudSubmitAllowed(root)) {
			renderTextResult(root, root.dataset.toolboxNightlyCloudQuotaExhausted === '1' ? 'Cloud quota is exhausted for Nightly Site Inspection.' : 'Enable Pro Cloud Runtime and save settings before submitting.', 'warning');
			return null;
		}
		setNightlyCloudBusy(root, true, button);
		try {
			const payload = await postJson(config.restUrl, 'nightly-inspection/cloud-batch', nightlyCloudRequestPayload());
			setNightlyCloudRunId(root, nightlyCloudRunIdFromPayload(payload));
			renderNightlyCloudBatchPayload(root, payload, 'Cloud inspection started', 'Cloud accepted the bounded snapshot for review-only scoring.');
			const runId = nightlyCloudRunIdFromPayload(payload) || nightlyCloudRunId(root);
			if (!runId) {
				return payload;
			}

			let statusPayload = payload;
			try {
				statusPayload = nightlyCloudTerminal(payload) ? payload : await autoPollNightlyCloudBatch(root, runId);
				if (nightlyCloudSucceeded(statusPayload)) {
					return await autoReadNightlyCloudBatchResult(root, runId);
				}
			} catch (followupError) {
				renderNightlyCloudBatchPayload(root, payload, 'Cloud inspection started', 'Cloud accepted the run, but the automatic status/result follow-up did not complete. Use Recent run actions or Advanced details with the retained run ID.');
				const result = root.querySelector('.npcink-toolbox__result');
				if (result) {
					result.appendChild(el('div', 'npcink-toolbox__result-notice is-warning', formatErrorMessage(followupError, 'Automatic Cloud follow-up failed.')));
				}
				return payload;
			}
			if (statusPayload && nightlyCloudTerminal(statusPayload)) {
				renderNightlyCloudBatchPayload(root, statusPayload, 'Cloud inspection terminal status', 'Cloud run reached a terminal state without a mergeable result. Review the advanced status payload before retrying.');
				return statusPayload;
			}
			renderNightlyCloudBatchPayload(root, statusPayload || payload, 'Cloud inspection still running', 'Automatic checks ended before Cloud reached a terminal state. Use Recent run actions or Advanced details later; no local queue was created.');
			return statusPayload || payload;
		} catch (error) {
			renderErrorResult(root, error, 'Cloud inspection failed.');
			return null;
		} finally {
			setNightlyCloudBusy(root, false, button);
		}
	}

	async function refreshNightlyCloudEntitlement(root, button) {
		setNightlyCloudBusy(root, true, button);
		try {
			const payload = await getJson(config.restUrl, 'nightly-inspection/cloud-runtime-entitlement');
			renderNightlyCloudEntitlement(root, payload);
			return payload;
		} catch (error) {
			renderErrorResult(root, error, 'Cloud quota refresh failed.');
			return null;
		} finally {
			setNightlyCloudBusy(root, false, button);
		}
	}

	async function refreshNightlyCloudRecentRuns(root, button) {
		setNightlyCloudBusy(root, true, button);
		try {
			const payload = await getJson(config.restUrl, 'nightly-inspection/cloud-batch/recent?limit=5');
			renderNightlyCloudRecentRuns(root, payload);
			return payload;
		} catch (error) {
			renderErrorResult(root, error, 'Cloud recent runs failed.');
			return null;
		} finally {
			setNightlyCloudBusy(root, false, button);
		}
	}

	async function refreshNightlyCloudBatchStatus(root, button) {
		const runId = nightlyCloudRunId(root);
		if (!runId) {
			renderTextResult(root, 'Enter a Cloud run ID before checking status.', 'warning');
			return null;
		}
		setNightlyCloudBusy(root, true, button);
		try {
			const payload = await getJson(config.restUrl, 'nightly-inspection/cloud-batch/' + encodeURIComponent(runId));
			setNightlyCloudRunId(root, nightlyCloudRunIdFromPayload(payload) || runId);
			renderNightlyCloudBatchPayload(root, payload, 'Cloud inspection status', 'Latest Cloud runtime status for this review-only inspection.');
			if (nightlyCloudSucceeded(payload)) {
				await autoReadNightlyCloudBatchResult(root, runId);
			}
			return payload;
		} catch (error) {
			renderErrorResult(root, error, 'Cloud inspection status failed.');
			return null;
		} finally {
			setNightlyCloudBusy(root, false, button);
		}
	}

	async function readNightlyCloudBatchResult(root, button) {
		const runId = nightlyCloudRunId(root);
		if (!runId) {
			renderTextResult(root, 'Enter a Cloud run ID before reading the result.', 'warning');
			return null;
		}
		setNightlyCloudBusy(root, true, button);
		try {
			const payload = await postJson(config.restUrl, 'nightly-inspection/cloud-batch/' + encodeURIComponent(runId) + '/result', {
				morning_brief: nightlyCloudLocalMorningBrief(root)
			});
			setNightlyCloudRunId(root, nightlyCloudRunIdFromPayload(payload) || runId);
			renderNightlyCloudBatchPayload(root, payload, 'Cloud inspection result', 'Cloud scoring was returned as a review-only Morning Brief patch.');
			return payload;
		} catch (error) {
			if (error && (Number(error.status) === 409 || String(error.code || '').toLowerCase().indexOf('not_terminal') >= 0 || String(error.message || '').toLowerCase().indexOf('not terminal') >= 0)) {
				renderTextResult(root, 'Cloud result is not ready yet. Refresh status after the Cloud worker reaches a terminal state.', 'warning');
			} else {
				renderErrorResult(root, error, 'Cloud inspection result failed.');
			}
			return null;
		} finally {
			setNightlyCloudBusy(root, false, button);
		}
	}

	async function retryNightlyCloudBatch(root, button) {
		const runId = nightlyCloudRunId(root);
		if (!runId) {
			renderTextResult(root, 'Enter a Cloud run ID before retrying the run.', 'warning');
			return null;
		}
		setNightlyCloudBusy(root, true, button);
		try {
			const payload = await postJson(config.restUrl, 'nightly-inspection/cloud-batch/' + encodeURIComponent(runId) + '/retry', nightlyCloudRequestPayload());
			setNightlyCloudRunId(root, nightlyCloudRunIdFromPayload(payload) || runId);
			renderNightlyCloudBatchPayload(root, payload, 'Cloud inspection retry queued', 'Cloud queued a retry with a new idempotency key. Toolbox did not create a local queue, Core proposal, or WordPress write.');
			const retryRunId = nightlyCloudRunIdFromPayload(payload);
			if (retryRunId) {
				const statusPayload = await autoPollNightlyCloudBatch(root, retryRunId);
				if (nightlyCloudSucceeded(statusPayload)) {
					return await autoReadNightlyCloudBatchResult(root, retryRunId);
				}
				return statusPayload || payload;
			}
			return payload;
		} catch (error) {
			renderErrorResult(root, error, 'Cloud inspection retry failed.');
			return null;
		} finally {
			setNightlyCloudBusy(root, false, button);
		}
	}

	function initNightlyCloudBatch() {
		document.querySelectorAll('[data-toolbox-nightly-cloud-batch]').forEach((root) => {
			updateNightlyCloudButtonState(root, false);
			renderNightlyCloudRecentRun(root);
			const entitlementButton = root.querySelector('[data-toolbox-nightly-cloud-entitlement]');
			if (entitlementButton) {
				entitlementButton.addEventListener('click', () => {
					refreshNightlyCloudEntitlement(root, entitlementButton);
				});
			}
			const recentButton = root.querySelector('[data-toolbox-nightly-cloud-recent]');
			if (recentButton) {
				recentButton.addEventListener('click', () => {
					refreshNightlyCloudRecentRuns(root, recentButton);
				});
			}
			const submitButton = root.querySelector('[data-toolbox-nightly-cloud-submit]');
			root.addEventListener('submit', (event) => {
				event.preventDefault();
				if (!nightlyCloudSubmitAllowed(root)) {
					renderTextResult(root, root.dataset.toolboxNightlyCloudQuotaExhausted === '1' ? 'Cloud quota is exhausted for Nightly Site Inspection.' : 'Enable Pro Cloud Runtime and save settings before submitting.', 'warning');
					return;
				}
				submitNightlyCloudBatch(root, submitButton);
			});
			const statusButton = root.querySelector('[data-toolbox-nightly-cloud-status]');
			if (statusButton) {
				statusButton.addEventListener('click', () => {
					refreshNightlyCloudBatchStatus(root, statusButton);
				});
			}
			const resultButton = root.querySelector('[data-toolbox-nightly-cloud-result-read]');
			if (resultButton) {
				resultButton.addEventListener('click', () => {
					readNightlyCloudBatchResult(root, resultButton);
				});
			}
			const retryButton = root.querySelector('[data-toolbox-nightly-cloud-retry]');
			if (retryButton) {
				retryButton.addEventListener('click', () => {
					retryNightlyCloudBatch(root, retryButton);
				});
			}
		});
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
		if (!submitButton.__npcinkOriginalText) {
			submitButton.__npcinkOriginalText = submitButton.textContent;
		}
		submitButton.disabled = busy;
		submitButton.setAttribute('aria-busy', busy ? 'true' : 'false');
		submitButton.textContent = busy ? 'Sending index request...' : submitButton.__npcinkOriginalText;
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
		button.__npcinkOriginalText = hasIndex ? refreshLabel : startLabel;
		if (modeInput) {
			modeInput.value = hasIndex ? 'rebuild' : 'refresh';
		}
		button.disabled = active;
		button.setAttribute('aria-busy', active ? 'true' : 'false');
		button.textContent = active ? siteKnowledgeActiveButtonLabel(payload) : button.__npcinkOriginalText;
	}

	function siteKnowledgeStatusStillActive(payload) {
		const status = String(payload && payload.status ? payload.status : '').toLowerCase();
		return status === 'queued' || status === 'running' || status === 'syncing';
	}

	function siteKnowledgeActiveButtonLabel(payload) {
		const status = String(payload && payload.status ? payload.status : '').toLowerCase();
		return status === 'queued' ? 'Index refresh queued...' : 'Indexing in Cloud...';
	}

	function siteKnowledgeActiveStatusMessage(payload) {
		const status = String(payload && payload.status ? payload.status : '').toLowerCase();
		if (status === 'queued') {
			return 'Index refresh is queued in Cloud. Search results may not include the latest changes yet.';
		}
		return 'Cloud is still indexing. Search results may not include the latest changes until status is ready.';
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
					summary.appendChild(el('div', 'npcink-toolbox__result-notice is-error', error.message || 'Site knowledge status failed.'));
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
					summary.appendChild(el('div', 'npcink-toolbox__result-notice is-error', error.message || 'Site knowledge status failed.'));
					summary.appendChild(createRawDetails(error, 'Status error'));
				}
			});
		});
	}

	async function submitMediaReferenceRepairProposal(form) {
		if (!config.adapterRestUrl) {
			throw { message: 'Npcink Adapter REST URL is unavailable.' };
		}

		const input = referenceRepairInput(form);
		if (!input.attachment_id) {
			throw { message: 'Select or enter an image attachment before building a URL repair proposal.' };
		}

		renderTextResult(form, 'Building media URL repair plan...', 'pending');
		const planEnvelope = await postJson(config.adapterRestUrl, 'run-read-ability', {
			ability_id: 'npcink-abilities-toolkit/build-media-reference-repair-plan',
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
					result.appendChild(el('div', 'npcink-toolbox__result-notice is-warning', 'Manual review found references that are not safe for exact automatic replacement.'));
				}
				result.appendChild(createRawDetails(planEnvelope, 'Reference repair plan'));
			}
			return;
		}

		renderTextResult(form, 'Submitting URL repair proposal...', 'pending');
		const bridge = await postJson(config.adapterRestUrl, 'proposals/from-plan', {
			plan_ability_id: 'npcink-abilities-toolkit/build-media-reference-repair-plan',
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
			throw { message: 'Npcink Adapter REST URL is unavailable.' };
		}

		const input = settingsReferenceRepairInput(form);
		if (!input.attachment_id) {
			throw { message: 'Select or enter an image attachment before building a settings URL repair proposal.' };
		}

		renderTextResult(form, 'Building settings URL repair plan...', 'pending');
		const planEnvelope = await postJson(config.adapterRestUrl, 'run-read-ability', {
			ability_id: 'npcink-abilities-toolkit/build-media-settings-reference-repair-plan',
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
					result.appendChild(el('div', 'npcink-toolbox__result-notice is-warning', 'Manual review found setting references that are not safe for exact automatic replacement.'));
				}
				result.appendChild(createRawDetails(planEnvelope, 'Settings reference repair plan'));
			}
			return;
		}

		renderTextResult(form, 'Submitting settings URL repair proposal...', 'pending');
		const bridge = await postJson(config.adapterRestUrl, 'proposals/from-plan', {
			plan_ability_id: 'npcink-abilities-toolkit/build-media-settings-reference-repair-plan',
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
		const panelRoot = container.matches('[data-toolbox-tabs]') ? (container.closest('.npcink-toolbox') || document) : container;
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

	function toolGroupForTool(workspace, target) {
		let group = '';
		workspace.querySelectorAll('[data-toolbox-tool-target]').forEach((button) => {
			if (!group && button.getAttribute('data-toolbox-tool-target') === target) {
				group = button.getAttribute('data-toolbox-tool-group') || '';
			}
		});
		return group;
	}

	function firstToolInGroup(workspace, group) {
		let target = '';
		workspace.querySelectorAll('[data-toolbox-tool-target]').forEach((button) => {
			if (!target && button.getAttribute('data-toolbox-tool-group') === group) {
				target = button.getAttribute('data-toolbox-tool-target') || '';
			}
		});
		return target;
	}

	function activateToolGroupPanel(workspace, group) {
		if (!workspace || !hasTarget(workspace, '[data-toolbox-tool-group-target]', 'data-toolbox-tool-group-target', group)) {
			return false;
		}

		activateTarget(
			workspace,
			'[data-toolbox-tool-group-target]',
			'[data-toolbox-tool-group-panel]',
			'data-toolbox-tool-group-target',
			'data-toolbox-tool-group-panel',
			group
		);

		workspace.querySelectorAll('[data-toolbox-advanced-workflows]').forEach((details) => {
			if (!(details instanceof HTMLDetailsElement)) {
				return;
			}
			details.open = Array.from(details.querySelectorAll('[data-toolbox-tool-group-target]')).some((button) => {
				return button.getAttribute('data-toolbox-tool-group-target') === group;
			});
		});
		return true;
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

		const group = toolGroupForTool(workspace, target);
		if (group) {
			activateToolGroupPanel(workspace, group);
		}

			activateTarget(
				workspace,
				'[data-toolbox-tool-target]',
				'[data-toolbox-tool-panel]',
				'data-toolbox-tool-target',
				'data-toolbox-tool-panel',
				target
			);

			workspace.querySelectorAll('[data-toolbox-tool-panel-extra]').forEach((panel) => {
				panel.hidden = panel.getAttribute('data-toolbox-tool-panel-extra') !== target;
			});

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

	function activateToolGroup(group, updateUrl) {
		const workspace = document.querySelector('[data-toolbox-tools]');
		if (!activateToolGroupPanel(workspace, group)) {
			return false;
		}

		const target = firstToolInGroup(workspace, group);
		if (!target) {
			return false;
		}

		return activateToolPanel(target, updateUrl);
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
		const requestedConnector = params.get('toolbox_connector') || '';
		let requestedCloudCheck = params.get('toolbox_cloud_check') || '';
		const requestedCloudCheckGroup = params.get('toolbox_cloud_check_group') || '';
		let tab = requestedTab;
		let canonicalizeLegacyConnector = false;

		if (tab === 'connectors' && requestedConnector && hasTarget(document, '[data-toolbox-cloud-check-target]', 'data-toolbox-cloud-check-target', requestedConnector)) {
			tab = 'cloud-checks';
			requestedCloudCheck = requestedConnector;
			canonicalizeLegacyConnector = true;
		}

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
		if (canonicalizeLegacyConnector) {
			updateToolboxUrl({
				toolbox_tab: 'cloud-checks',
				toolbox_connector: null,
				toolbox_cloud_check: requestedCloudCheck,
				toolbox_cloud_check_group: requestedCloudCheckGroup || activeCloudCheckGroup(),
			});
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

				const groupButton = event.target.closest('[data-toolbox-tool-group-target]');
				if (groupButton && workspace.contains(groupButton)) {
					activateToolGroup(groupButton.getAttribute('data-toolbox-tool-group-target'), true);
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

	function initOperationsInsightsTabs() {
		document.querySelectorAll('[data-toolbox-ops-tabs]').forEach((workspace) => {
			workspace.addEventListener('click', (event) => {
				if (!(event.target instanceof Element)) {
					return;
				}

				const button = event.target.closest('[data-toolbox-ops-target]');
				if (!button || !workspace.contains(button)) {
					return;
				}

				activateTarget(
					workspace,
					'[data-toolbox-ops-target]',
					'[data-toolbox-ops-panel]',
					'data-toolbox-ops-target',
					'data-toolbox-ops-panel',
					button.getAttribute('data-toolbox-ops-target')
				);
			});
		});
	}

	function setContextField(form, key, value) {
		const option = config.contextOption || 'npcink_toolbox_content_context';
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
		const option = config.contextOption || 'npcink_toolbox_content_context';
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

		form.querySelectorAll('.npcink-toolbox__disclosure').forEach((details) => {
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
			const image = el('img', 'npcink-toolbox__media-thumb');
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
			syncMediaBatchFixedFlow(form);
			['batch_recipe', 'batch_scope_preset'].forEach((name) => {
				const field = form.querySelector('[name="' + name + '"]');
				if (field instanceof HTMLSelectElement) {
					field.addEventListener('change', () => syncMediaBatchFixedFlow(form));
				}
			});
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
					const candidate = row && row.__npcinkMediaResolutionCandidate ? row.__npcinkMediaResolutionCandidate : {
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

				const batchExecuteButton = event.target.closest('[data-toolbox-execute-media-batch-replacements]');
				if (batchExecuteButton && form.contains(batchExecuteButton)) {
					event.preventDefault();
					executeMediaDerivativeBatchReplacements(form).catch((error) => {
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
	initOperationsInsightsTabs();
	initContextDrafts();
	initWebSearchPresets();
	initNightlyCloudBatch();
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
