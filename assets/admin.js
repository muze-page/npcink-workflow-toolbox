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

	function createLink(url, label) {
		const link = el('a', '', label || url);
		link.href = url;
		link.target = '_blank';
		link.rel = 'noreferrer';
		return link;
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
		notice.textContent = String(value || '');
		result.appendChild(notice);
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
			if (image.thumb_url || image.small_url) {
				const preview = el('img', 'magick-ai-toolbox__image-thumb');
				preview.src = image.thumb_url || image.small_url;
				preview.alt = image.alt_description || image.description || '';
				row.appendChild(preview);
			}

			const body = el('div', 'magick-ai-toolbox__image-body');
			body.appendChild(el('h4', '', image.description || image.alt_description || image.id || 'Image candidate'));
			if (image.attribution) {
				body.appendChild(el('p', '', image.attribution));
			}
			const links = el('div', 'magick-ai-toolbox__result-actions');
			if (image.html_url) {
				links.appendChild(createLink(image.html_url, 'Open on Unsplash'));
			}
			if (image.photographer_url) {
				links.appendChild(createLink(image.photographer_url, 'Photographer'));
			}
			if (links.childNodes.length) {
				body.appendChild(links);
			}
			const meta = el('div', 'magick-ai-toolbox__result-meta');
			appendMeta(meta, 'ID', image.id);
			appendMeta(meta, 'Download tracking', image.download_location ? 'Preserved' : '');
			appendMeta(meta, 'Photographer', image.photographer);
			if (meta.childNodes.length) {
				body.appendChild(meta);
			}
			if (image.download_location) {
				const details = el('details', 'magick-ai-toolbox__result-details');
				details.appendChild(el('summary', '', 'Attribution metadata'));
				const pre = el('pre', 'magick-ai-toolbox__result-raw');
				pre.textContent = JSON.stringify({
					attribution: image.attribution || '',
					download_location: image.download_location || '',
					regular_url: image.regular_url || '',
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

	function renderTavily(form, payload) {
		const count = Array.isArray(payload.results) ? payload.results.length : 0;
		const result = renderShell(
			form,
			payload,
			'Web research',
			payload.answer || (count ? count + ' source candidates returned for operator review.' : 'No source candidates were returned.')
		);
		if (!result) {
			return;
		}

		renderSourceList(result, payload.results);
		if (payload.raw) {
			result.appendChild(createRawDetails(payload.raw, 'Provider raw response'));
		}
		result.appendChild(createRawDetails(payload, 'Complete payload'));
	}

	function renderUnsplash(form, payload, title) {
		const count = Array.isArray(payload.images) ? payload.images.length : 0;
		const result = renderShell(
			form,
			payload,
			title || 'Image-source candidates',
			count
				? count + ' candidates returned. Attribution and download tracking metadata are preserved.'
				: 'No image-source candidates were returned.'
		);
		if (!result) {
			return;
		}

		renderImageList(result, payload.images);
		if (payload.raw) {
			result.appendChild(createRawDetails(payload.raw, 'Provider raw response'));
		}
		result.appendChild(createRawDetails(payload, 'Complete payload'));
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

	function renderArticleBrief(form, payload) {
		const result = renderShell(
			form,
			payload,
			'Article brief',
			'Planning artifact only. Review sources, candidates, and handoff notes before creating a Core proposal.'
		);
		if (!result) {
			return;
		}

		if (payload.research && payload.research.error) {
			result.appendChild(el('div', 'magick-ai-toolbox__result-notice is-warning', payload.research.error));
		} else if (payload.research) {
			renderSourceList(result, payload.research.results);
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

		if (payload.provider === 'tavily') {
			renderTavily(form, payload);
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
			throw payload;
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

	function activateTarget(container, buttonSelector, panelSelector, targetAttribute, panelAttribute, target) {
		const buttons = container.querySelectorAll(buttonSelector);
		const root = container.closest('.magick-ai-toolbox') || document;
		const panels = root.querySelectorAll(panelSelector);

		buttons.forEach((button) => {
			const active = button.getAttribute(targetAttribute) === target;
			button.classList.toggle('is-active', active);
			button.setAttribute('aria-selected', active ? 'true' : 'false');
		});

		panels.forEach((panel) => {
			panel.hidden = panel.getAttribute(panelAttribute) !== target;
		});
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

				activateTarget(
					tabs,
					'[data-toolbox-tab-target]',
					'[data-toolbox-tab-panel]',
					'data-toolbox-tab-target',
					'data-toolbox-tab-panel',
					button.getAttribute('data-toolbox-tab-target')
				);
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

				activateTarget(
					workspace,
					'[data-toolbox-tool-target]',
					'[data-toolbox-tool-panel]',
					'data-toolbox-tool-target',
					'data-toolbox-tool-panel',
					button.getAttribute('data-toolbox-tool-target')
				);
			});
		});
	}

	function initConnectorSwitcher() {
		document.querySelectorAll('[data-toolbox-connectors]').forEach((workspace) => {
			workspace.addEventListener('click', (event) => {
				if (!(event.target instanceof Element)) {
					return;
				}

				const button = event.target.closest('[data-toolbox-connector-target]');
				if (!button || !workspace.contains(button)) {
					return;
				}

				activateTarget(
					workspace,
					'[data-toolbox-connector-target]',
					'[data-toolbox-connector-panel]',
					'data-toolbox-connector-target',
					'data-toolbox-connector-panel',
					button.getAttribute('data-toolbox-connector-target')
				);
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

	initTopTabs();
	initToolSwitcher();
	initConnectorSwitcher();
	initContextDrafts();

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
			renderTextResult(form, error && error.message ? error.message : (config.labels && config.labels.error ? config.labels.error : 'Request failed.'), 'error');
		});
	});
}());
