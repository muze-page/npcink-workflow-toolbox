(function (wp) {
	'use strict';

	const config = window.NpcinkToolboxEditorSupport || {};
	const element = wp.element || {};
	const components = wp.components || {};
	const data = wp.data || {};
	const blockEditor = wp.blockEditor || {};
	const editPost = wp.editPost || {};
	const editor = wp.editor || {};
	const hooks = wp.hooks || {};
	const plugins = wp.plugins || {};
	const i18n = wp.i18n || {};
	const createElement = element.createElement;
	const Fragment = element.Fragment || 'div';
	const useState = element.useState;
	const useEffect = element.useEffect;
	const useRef = element.useRef;
	const useSelect = data.useSelect;
	const __ = i18n.__ || ((value) => value);
	const sprintf = i18n.sprintf || ((value) => value);
	const SIDEBAR_NAME = 'npcink-content-support-sidebar';
	const PLUGIN_NAME = 'npcink-toolbox-editor-content-support';
	const PARAGRAPH_IMAGE_EVENT = 'npcink-toolbox:paragraph-image-suggestions';
	const PARAGRAPH_REVIEW_EVENT = 'npcink-toolbox:paragraph-review';
	const IMAGE_SOURCE_PICKER_EVENT = 'npcink-toolbox:image-source-picker';
	const IMAGE_SOURCE_PICKER_SELECTED_EVENT = 'npcink-toolbox:image-source-selected';
	const PROGRESSIVE_RECOMMENDATION_TIMEOUT_MS = 2500;
	const PROGRESSIVE_RECOMMENDATION_DEBOUNCE_MS = 1600;
	const IMAGE_RESULT_CACHE_TTL = 5 * 60 * 1000;
	const IMAGE_RESULT_CACHE_MAX_ENTRIES = 20;
	const IMAGE_CANDIDATE_TARGET_COUNT = 9;
	const imageResultCache = {};
	const implicitAgentFeedbackSent = {};
	const PluginSidebarComponent = editor.PluginSidebar || editPost.PluginSidebar;
	const BlockControlsComponent = blockEditor.BlockControls || editor.BlockControls || null;

	if (!createElement || !useState || !useEffect || !useRef || !useSelect || !plugins.registerPlugin || !PluginSidebarComponent) {
		return;
	}

	const Button = components.Button || 'button';
	const PluginSidebar = PluginSidebarComponent;
	const BlockControls = BlockControlsComponent;
	const ToolbarButton = components.ToolbarButton || Button;
	const TextControl = components.TextControl || function TextControlFallback(props) {
		return createElement(
			'label',
			{ className: 'npcink-toolbox-editor-support__field' },
			createElement('span', null, props.label || ''),
			createElement('input', {
				type: 'text',
				value: props.value || '',
				placeholder: props.placeholder || '',
				disabled: Boolean(props.disabled),
				onChange: (event) => props.onChange && props.onChange(event.target.value),
			})
		);
	};
	const TextareaControl = components.TextareaControl || function TextareaControlFallback(props) {
		return createElement(
			'label',
			{ className: 'npcink-toolbox-editor-support__field' },
			createElement('span', null, props.label || ''),
			createElement('textarea', {
				value: props.value || '',
				placeholder: props.placeholder || '',
				disabled: Boolean(props.disabled),
				onChange: (event) => props.onChange && props.onChange(event.target.value),
			})
		);
	};
	const Modal = components.Modal || function ModalFallback(props) {
		return createElement(
			'div',
			{ className: 'npcink-toolbox-editor-support__modal-fallback', role: 'dialog', 'aria-modal': 'true' },
			createElement(
				'div',
				{ className: 'npcink-toolbox-editor-support__modal-fallback-head' },
				createElement('strong', null, props.title || ''),
				createElement('button', { type: 'button', onClick: props.onRequestClose }, __('Close', 'npcink-toolbox'))
			),
			props.children
		);
	};
	const Notice = components.Notice || function NoticeFallback(props) {
		return createElement('div', { className: 'npcink-toolbox-editor-support__notice' }, props.children);
	};
	const Spinner = components.Spinner || function SpinnerFallback() {
		return createElement('span', { className: 'npcink-toolbox-editor-support__spinner' }, '...');
	};
	const sidebarIcon = createElement(
		'svg',
		{
			className: 'npcink-toolbox-editor-support__toolbar-icon',
			viewBox: '0 0 24 24',
			width: 24,
			height: 24,
			'aria-hidden': 'true',
			focusable: 'false',
			role: 'img',
		},
		createElement('rect', { x: 2, y: 2, width: 20, height: 20, rx: 5, fill: '#3858e9' }),
		createElement('text', { x: 12, y: 15, textAnchor: 'middle', fill: '#fff', fontSize: 9, fontWeight: 700, fontFamily: 'Arial, sans-serif' }, 'AI')
	);
	const paragraphImageIcon = createElement('span', { className: 'dashicons dashicons-format-image npcink-toolbox-editor-support__block-toolbar-icon', 'aria-hidden': 'true' });
	const paragraphReviewIcon = createElement('span', { className: 'dashicons dashicons-editor-spellcheck npcink-toolbox-editor-support__block-toolbar-icon', 'aria-hidden': 'true' });

	const imagePickerPresets = {
		featured: {
			mode: 'featured',
			imageUse: 'featured_image',
			adoptionMode: 'featured_image',
			contextScope: 'article',
			initialSearchMode: 'source',
			autoSearch: true,
			allowGeneration: true,
			allowImagePlan: true,
			title: __('AI recommended featured image', 'npcink-toolbox'),
			intro: __('Uses the current article title, excerpt, and body context to recommend or generate a featured image. Paragraph selection is ignored for this entry.', 'npcink-toolbox'),
			emptyTitle: __('Select a featured image candidate', 'npcink-toolbox'),
			sourceModeLabel: __('Image source', 'npcink-toolbox'),
			generateModeLabel: __('AI generated', 'npcink-toolbox'),
			searchPlaceholder: __('Enter a scene, or leave blank to use article context', 'npcink-toolbox'),
			generatePlaceholder: __('Review or enter a featured image prompt', 'npcink-toolbox'),
			searchButtonLabel: __('Refresh recommendations', 'npcink-toolbox'),
			searchBusyLabel: __('Recommending', 'npcink-toolbox'),
			autoButtonLabel: __('Use article context', 'npcink-toolbox'),
			generateButtonLabel: __('Generate AI image', 'npcink-toolbox'),
			briefButtonLabel: __('Generate prompt', 'npcink-toolbox'),
		},
		paragraph: {
			mode: 'paragraph',
			imageUse: 'paragraph_image',
			adoptionMode: 'media_import',
			contextScope: 'paragraph',
			initialSearchMode: 'source',
			autoSearch: true,
			allowGeneration: false,
			allowImagePlan: false,
			title: __('Paragraph image suggestions', 'npcink-toolbox'),
			intro: __('Uses the currently selected paragraph as the primary image context, with nearby article context only for disambiguation.', 'npcink-toolbox'),
			emptyTitle: __('Select an image for this paragraph', 'npcink-toolbox'),
			sourceModeLabel: __('Recommend paragraph image', 'npcink-toolbox'),
			searchPlaceholder: __('Enter a scene, or leave blank to use the selected paragraph', 'npcink-toolbox'),
			searchButtonLabel: __('Refresh recommendations', 'npcink-toolbox'),
			searchBusyLabel: __('Recommending', 'npcink-toolbox'),
			autoButtonLabel: __('Use selected paragraph', 'npcink-toolbox'),
		},
		inline: {
			mode: 'inline',
			imageUse: 'inline_image',
			adoptionMode: 'media_import',
			contextScope: 'paragraph',
			initialSearchMode: 'source',
			autoSearch: true,
			allowGeneration: false,
			allowImagePlan: false,
			title: __('Inline image suggestions', 'npcink-toolbox'),
			intro: __('Search uses the selected text plus article context. Select one image to import it with media details through Adapter/Core.', 'npcink-toolbox'),
			emptyTitle: __('Select an image source', 'npcink-toolbox'),
		},
		setting: {
			mode: 'setting',
			imageUse: 'setting_image',
			adoptionMode: 'select_only',
			contextScope: 'setting',
			initialSearchMode: 'source',
			autoSearch: false,
			allowGeneration: false,
			allowImagePlan: false,
			title: __('Setting image suggestions', 'npcink-toolbox'),
			intro: __('Search uses a short visual query or supplied setting context. Select one image source to return it to the calling field; Toolbox does not write settings directly.', 'npcink-toolbox'),
			emptyTitle: __('Select an image source', 'npcink-toolbox'),
		},
	};

	function normalizeImageBriefButtonLabel(label) {
		const value = String(label || '').trim();
		if (!value || value === 'Generate prompt plan' || value === 'Generate image plan' || value === 'Generate featured image plan' || value === '生成提示词计划' || value === '生成图片计划' || value === '生成特色图计划') {
			return __('Generate prompt', 'npcink-toolbox');
		}
		return value;
	}

	const flows = [
		{
			intent: 'article_checkup',
			label: __('Article checkup', 'npcink-toolbox'),
			description: __('Check the full draft for sentence, fact-gap, tone, and structure issues without rewriting it.', 'npcink-toolbox'),
			group: 'common_recommendations',
		},
		{
			intent: 'title_suggestions',
			label: __('Title suggestions', 'npcink-toolbox'),
			description: __('Generate reviewable title options from the current draft context.', 'npcink-toolbox'),
			group: 'common_recommendations',
		},
		{
			intent: 'summary_suggestions',
			label: __('AI generate summary', 'npcink-toolbox'),
			description: __('Generate reviewed excerpt candidates from the current draft with hosted AI.', 'npcink-toolbox'),
			group: 'common_recommendations',
		},
		{
			intent: 'tag_suggestions',
			label: __('Tag suggestions', 'npcink-toolbox'),
			description: __('Find matching existing tags without creating new vocabulary.', 'npcink-toolbox'),
			group: 'common_recommendations',
		},
		{
			intent: 'category_suggestions',
			label: __('Category suggestions', 'npcink-toolbox'),
			description: __('Find matching existing categories without running the full metadata workflow.', 'npcink-toolbox'),
			group: 'common_recommendations',
		},
		{
			intent: 'image_candidates',
			label: __('AI recommended featured image', 'npcink-toolbox'),
			description: __('Recommend or generate a featured image from the current article context.', 'npcink-toolbox'),
			group: 'common_recommendations',
		},
		{
			intent: 'internal_links',
			label: __('Find internal links', 'npcink-toolbox'),
			description: __('Find related existing posts to cite manually after the draft direction is clear.', 'npcink-toolbox'),
			group: 'common_recommendations',
		},
		{
			intent: 'article_outline',
			label: __('Outline suggestions', 'npcink-toolbox'),
			description: __('Build a compact outline that an editor can expand manually.', 'npcink-toolbox'),
			group: 'writing_assist',
		},
		{
			intent: 'publish_preflight',
			label: __('Publish preflight', 'npcink-toolbox'),
			description: __('Check missing fields and compare related existing posts for duplicate-risk before publishing.', 'npcink-toolbox'),
			group: 'pre_publish',
		},
		{
			intent: 'discoverability',
			label: __('Discoverability suggestions', 'npcink-toolbox'),
			description: __('Review SEO, AEO, GEO, and proposal-field suggestions for the current draft.', 'npcink-toolbox'),
			group: 'pre_publish',
		},
		{
			intent: 'image_alt_suggestions',
			label: __('Image ALT suggestions', 'npcink-toolbox'),
			description: __('Suggest ALT and caption notes for images already used by this draft.', 'npcink-toolbox'),
			group: 'pre_publish',
		},
	];

	const flowGroups = [
		{
			id: 'common_recommendations',
			label: __('Common recommendations', 'npcink-toolbox'),
		},
		{
			id: 'writing_assist',
			label: __('Writing assist', 'npcink-toolbox'),
		},
		{
			id: 'pre_publish',
			label: __('Pre-publish package', 'npcink-toolbox'),
		},
	];

	function normalizeText(value) {
		if (value && typeof value === 'object' && value.raw !== undefined) {
			return String(value.raw || '');
		}
		return String(value || '');
	}

	function plainTextFromHtml(value) {
		const source = String(value || '');
		if (!source) {
			return '';
		}
		if (typeof window !== 'undefined' && window.document) {
			const container = window.document.createElement('div');
			container.innerHTML = source;
			return String(container.textContent || container.innerText || '').replace(/\s+/g, ' ').trim();
		}
		return source.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
	}

	function selectedBlockText(block) {
		if (!block || !block.attributes) {
			return '';
		}
		const attributes = block.attributes;
		const parts = [
			attributes.content,
			attributes.value,
			attributes.text,
			attributes.caption,
			attributes.citation,
			attributes.heading,
		].filter(Boolean);
		return truncateText(plainTextFromHtml(parts.join(' ')), 700);
	}

	function currentArticleMediaItems(blocks) {
		const items = [];
		const seen = {};

		function addItem(source, attrs) {
			const attributes = attrs && typeof attrs === 'object' ? attrs : {};
			const url = String(attributes.url || attributes.src || attributes.poster || '').trim();
			const id = parseInt(attributes.id || attributes.mediaId || attributes.media_id || '0', 10) || 0;
			if (!id && !url) {
				return;
			}
			const key = id ? 'id:' + String(id) : 'url:' + url;
			if (seen[key]) {
				return;
			}
			seen[key] = true;
			items.push({
				source,
				attachment_id: id,
				url,
				title: truncateText(plainTextFromHtml(attributes.title || attributes.name || ''), 120),
				alt: truncateText(plainTextFromHtml(attributes.alt || attributes.altText || ''), 160),
				caption: truncateText(plainTextFromHtml(attributes.caption || attributes.figcaption || ''), 220),
				description: truncateText(plainTextFromHtml(attributes.description || ''), 220),
			});
		}

		function visit(block) {
			if (!block || typeof block !== 'object') {
				return;
			}
			const name = String(block.name || '');
			const attrs = block.attributes || {};
			if (name === 'core/image') {
				addItem('content_image', attrs);
			}
			if (name === 'core/cover') {
				addItem('content_cover', attrs);
			}
			if (name === 'core/gallery' && Array.isArray(attrs.images)) {
				attrs.images.forEach((image) => addItem('content_gallery', image));
			}
			if (Array.isArray(block.innerBlocks)) {
				block.innerBlocks.forEach(visit);
			}
		}

		(Array.isArray(blocks) ? blocks : []).forEach(visit);
		return items.slice(0, 12);
	}

	function browserSelectedText() {
		if (typeof window === 'undefined' || !window.getSelection) {
			return '';
		}
		const selection = window.getSelection();
		return truncateText(selection ? String(selection.toString() || '').replace(/\s+/g, ' ').trim() : '', 700);
	}

	function joinRestUrl(base, path) {
		return String(base || '').replace(/\/$/, '') + '/' + String(path || '').replace(/^\//, '');
	}

	function openContentSupportSidebar() {
		if (!data || typeof data.dispatch !== 'function') {
			return false;
		}

		const target = PLUGIN_NAME + '/' + SIDEBAR_NAME;
		const stores = ['core/edit-post', 'core/interface'];
		for (let index = 0; index < stores.length; index += 1) {
			try {
				const dispatcher = data.dispatch(stores[index]);
				if (dispatcher && typeof dispatcher.openGeneralSidebar === 'function') {
					dispatcher.openGeneralSidebar(target);
					return true;
				}
			} catch (error) {
				// Older editor builds may not expose every store.
			}
		}
		return false;
	}

	async function postJsonToUrl(url, payload) {
		const response = await fetch(url, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce || '',
			},
			body: JSON.stringify(payload || {}),
		});
		const body = await response.json().catch(() => ({}));
		if (!response.ok) {
			throw body;
		}
		return body;
	}

	async function postJson(path, payload) {
		return postJsonToUrl(joinRestUrl(config.restUrl, path), payload);
	}

	async function postJsonWithTimeout(path, payload, timeoutMs) {
		if (!timeoutMs || typeof window === 'undefined' || typeof window.AbortController !== 'function') {
			return postJson(path, payload);
		}
		const controller = new window.AbortController();
		const timeout = window.setTimeout(() => controller.abort(), timeoutMs);
		try {
			const response = await fetch(joinRestUrl(config.restUrl, path), {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': config.nonce || '',
				},
				body: JSON.stringify(payload || {}),
				signal: controller.signal,
			});
			const body = await response.json().catch(() => ({}));
			if (!response.ok) {
				throw body;
			}
			return body;
		} catch (error) {
			if (error && error.name === 'AbortError') {
				throw {
					code: 'npcink_toolbox_progressive_timeout',
					message: __('Local suggestions timed out. Showing cached suggestions if available.', 'npcink-toolbox'),
				};
			}
			throw error;
		} finally {
			window.clearTimeout(timeout);
		}
	}

	function submitImplicitAgentFeedback(payload) {
		if (!payload || typeof payload !== 'object') {
			return;
		}
		const key = [
			payload.source_runtime || '',
			payload.handoff_type || '',
			payload.handoff_id || '',
			payload.local_outcome || '',
		].join('|').slice(0, 500);
		if (implicitAgentFeedbackSent[key]) {
			return;
		}
		implicitAgentFeedbackSent[key] = true;
		postJson('agent-feedback', payload).catch(() => {
			// Keep behavior collection silent; visible flows must not fail because eval is unavailable.
		});
	}

	function adapterRestUrl(path) {
		return joinRestUrl(config.adapterRestUrl || '/wp-json/npcink-openclaw-adapter/v1', path);
	}

	function extractProposalId(value, depth) {
		if (!value || depth > 6 || typeof value !== 'object') {
			return '';
		}
		if (Array.isArray(value)) {
			for (let index = 0; index < value.length; index += 1) {
				const proposalId = extractProposalId(value[index], depth + 1);
				if (proposalId) {
					return proposalId;
				}
			}
			return '';
		}
		const direct = value.proposal_id || value.core_proposal_id || '';
		if (direct) {
			return String(direct);
		}
		if (value.proposal && typeof value.proposal === 'object') {
			const proposalId = value.proposal.proposal_id || value.proposal.id || value.proposal.core_proposal_id || '';
			if (proposalId) {
				return String(proposalId);
			}
		}
		if (Array.isArray(value.proposals)) {
			for (let index = 0; index < value.proposals.length; index += 1) {
				const proposalId = extractProposalId(value.proposals[index], depth + 1);
				if (proposalId) {
					return proposalId;
				}
			}
		}
		const keys = Object.keys(value);
		for (let index = 0; index < keys.length; index += 1) {
			const proposalId = extractProposalId(value[keys[index]], depth + 1);
			if (proposalId) {
				return proposalId;
			}
		}
		return '';
	}

	function metadataTermId(item) {
		return parseInt(item && (item.term_id || item.id) ? (item.term_id || item.id) : 0, 10) || 0;
	}

	function metadataDeltaTermItems(section, key) {
		const delta = section && section.content_metadata_delta && section.content_metadata_delta.delta ? section.content_metadata_delta.delta : {};
		let items = Array.isArray(delta[key]) ? delta[key] : [];
		if (!items.length && key === 'categories' && section && Array.isArray(section.category_candidates)) {
			items = section.category_candidates;
		}
		if (!items.length && key === 'tags' && section && Array.isArray(section.tag_candidates)) {
			items = section.tag_candidates;
		}
		return items.filter((item) => metadataTermId(item) > 0);
	}

	function metadataRecommendedExcerpt(section) {
		const delta = section && section.content_metadata_delta && section.content_metadata_delta.delta ? section.content_metadata_delta.delta : {};
		const excerpt = delta.excerpt && typeof delta.excerpt === 'object' ? delta.excerpt : {};
		return String(excerpt.recommended || '').trim();
	}

	function metadataSelectionHasValue(selection) {
		return Boolean(
			selection && (
				selection.excerpt ||
				(Array.isArray(selection.tag_ids) && selection.tag_ids.length) ||
				(Array.isArray(selection.category_ids) && selection.category_ids.length)
			)
		);
	}

	function selectedMetadataTermIds(items, selectedIds) {
		const allowed = {};
		items.forEach((item) => {
			const termId = metadataTermId(item);
			if (termId > 0) {
				allowed[String(termId)] = true;
			}
		});
		return (Array.isArray(selectedIds) ? selectedIds : [])
			.map((termId) => parseInt(termId, 10) || 0)
			.filter((termId) => termId > 0 && allowed[String(termId)])
			.filter((termId, index, list) => list.indexOf(termId) === index);
	}

	function buildMetadataApplyPlanInput(section, selection, postContext) {
		const postId = parseInt(postContext && postContext.post_id ? postContext.post_id : 0, 10) || 0;
		const excerpt = selection && selection.excerpt ? metadataRecommendedExcerpt(section) : '';
		return {
			post_id: postId,
			excerpt,
			category_ids: selectedMetadataTermIds(metadataDeltaTermItems(section, 'categories'), selection ? selection.category_ids : []),
			tag_ids: selectedMetadataTermIds(metadataDeltaTermItems(section, 'tags'), selection ? selection.tag_ids : []),
			category_mode: 'append',
			tag_mode: 'append',
			content_metadata_delta: section && section.content_metadata_delta ? section.content_metadata_delta : {},
			evidence_refs: section && section.content_metadata_delta && section.content_metadata_delta.issue_record ? (section.content_metadata_delta.issue_record.context_refs || []) : [],
			new_term_candidates: section && section.content_metadata_delta && section.content_metadata_delta.delta ? (section.content_metadata_delta.delta.new_term_candidates || []) : [],
		};
	}

	async function postContentMetadataApplyPlanToAdapter(plan, planInput) {
		return postJsonToUrl(adapterRestUrl('proposals/from-plan'), {
			plan_ability_id: 'npcink-toolbox/build-content-metadata-apply-plan',
			plan,
			plan_input: planInput,
			caller: {
				surface: 'toolbox_editor_content_support',
				external_thread_id: 'toolbox-editor-content-metadata-delta',
				source: 'content_metadata_delta_handoff',
			},
		});
	}

	function seoMetaProposalPayload(section, options) {
		const opts = options || {};
		const commitExecution = Boolean(opts.commitExecution);
		const template = section && section.proposal_payload_template && typeof section.proposal_payload_template === 'object'
			? section.proposal_payload_template
			: {};
		const input = template.input && typeof template.input === 'object' ? template.input : {};
		const preview = template.preview && typeof template.preview === 'object' ? template.preview : {};
		const postId = parseInt(input.post_id || 0, 10) || 0;
		const payloadInput = {
			post_id: postId,
			seo_title: String(input.seo_title || '').trim(),
			seo_description: String(input.seo_description || '').trim(),
			dry_run: !commitExecution,
			commit: commitExecution,
		};
		if (commitExecution) {
			payloadInput.idempotency_key = 'toolbox-editor-seo-meta-' + postId + '-' + Date.now();
		}
		return {
			ability_id: 'npcink-abilities-toolkit/set-post-seo-meta',
			title: template.title || __('Review SEO meta for the current post', 'npcink-toolbox'),
			summary: template.summary || __('Single-post SEO title and description candidate prepared by Toolbox for Core-governed review.', 'npcink-toolbox'),
			input: payloadInput,
			preview: Object.assign({}, preview, {
				dry_run: !commitExecution,
				commit_execution: commitExecution,
			}),
			caller: {
				surface: 'toolbox_editor_content_support',
				external_thread_id: 'toolbox-editor-seo-meta-handoff',
				source: 'seo_meta_handoff_preview',
			},
		};
	}

	async function postSeoMetaProposalToAdapter(section, options) {
		return postJsonToUrl(adapterRestUrl('proposals'), seoMetaProposalPayload(section, options));
	}

	async function executeSeoMetaProposal(proposalId) {
		return postJsonToUrl(
			adapterRestUrl('proposals/' + encodeURIComponent(proposalId) + '/approve-and-execute'),
			{ note: __('Approved from the post editor discoverability optimization action.', 'npcink-toolbox') }
		);
	}

	async function postLocalFeaturedImageConsent(input) {
		return postJson('local-admin-consent/featured-image', input);
	}

	async function postAdapterAdoption(plan, planInput) {
		const bridge = await postJsonToUrl(adapterRestUrl('proposals/from-plan'), {
			plan_ability_id: 'npcink-toolbox/build-image-candidate-adoption-plan',
			plan,
			plan_input: planInput,
			caller: {
				surface: 'toolbox_editor_content_support',
				external_thread_id: 'toolbox-editor-featured-image',
			},
		});
		const proposalId = extractProposalId(bridge, 0);
		if (!proposalId) {
			return { bridge, proposal_id: '', execution_error: {
				message: __('Core did not create an executable adoption proposal from this image plan.', 'npcink-toolbox'),
			} };
		}
		try {
			const execution = await postJsonToUrl(
				adapterRestUrl('proposals/' + encodeURIComponent(proposalId) + '/approve-and-execute'),
				{ note: __('Approved from the post editor image adoption action.', 'npcink-toolbox') }
			);
			return { bridge, proposal_id: proposalId, execution };
		} catch (executionError) {
			return { bridge, proposal_id: proposalId, execution_error: executionError };
		}
	}

	function usePostContext() {
		return useSelect((select) => {
			const editor = select('core/editor');
			const blockEditor = select('core/block-editor');
			if (!editor) {
				return {};
			}
			const selectedBlock = blockEditor && blockEditor.getSelectedBlock ? blockEditor.getSelectedBlock() : null;
			const blocks = blockEditor && blockEditor.getBlocks ? blockEditor.getBlocks() : [];

			return {
				post_id: editor.getCurrentPostId ? editor.getCurrentPostId() : 0,
				post_type: editor.getCurrentPostType ? editor.getCurrentPostType() : 'post',
				post_status: editor.getEditedPostAttribute ? editor.getEditedPostAttribute('status') : '',
				title: normalizeText(editor.getEditedPostAttribute ? editor.getEditedPostAttribute('title') : ''),
				excerpt: normalizeText(editor.getEditedPostAttribute ? editor.getEditedPostAttribute('excerpt') : ''),
				content: normalizeText(editor.getEditedPostAttribute ? editor.getEditedPostAttribute('content') : ''),
				category_ids: (editor.getEditedPostAttribute ? editor.getEditedPostAttribute('categories') : []) || [],
				tag_ids: (editor.getEditedPostAttribute ? editor.getEditedPostAttribute('tags') : []) || [],
				featured_media: editor.getEditedPostAttribute ? editor.getEditedPostAttribute('featured_media') : 0,
				media_items: currentArticleMediaItems(blocks),
				selected_block_name: selectedBlock && selectedBlock.name ? String(selectedBlock.name) : '',
				selected_block_text: selectedBlockText(selectedBlock),
				};
			}, []);
		}

	function wordCount(value) {
		return String(value || '').trim().split(/\s+/).filter(Boolean).length;
	}

	function progressiveRecommendationKey(postContext) {
		const context = postContext && typeof postContext === 'object' ? postContext : {};
		return [
			'progressive_recommendations_v1',
			context.post_id || 0,
			context.post_type || 'post',
			truncateText(context.title, 120),
			truncateText(context.excerpt, 180),
			truncateText(context.content, 900),
			Array.isArray(context.category_ids) ? context.category_ids.join(',') : '',
			Array.isArray(context.tag_ids) ? context.tag_ids.join(',') : '',
			context.featured_media || 0,
		].join('|');
	}

	function progressiveRecommendationDelay(postContext) {
		const words = wordCount(postContext && postContext.content);
		if (words >= 300) {
			return 2000;
		}
		if (String(postContext && postContext.title || '').trim()) {
			return 1200;
		}
		return PROGRESSIVE_RECOMMENDATION_DEBOUNCE_MS;
	}

	function progressiveRecommendationPayload(postContext) {
		const context = postContext && typeof postContext === 'object' ? postContext : {};
		return Object.assign({}, context, {
			intent: 'progressive_recommendations',
			category_ids: Array.isArray(context.category_ids) ? context.category_ids.join(',') : '',
			tag_ids: Array.isArray(context.tag_ids) ? context.tag_ids.join(',') : '',
			media_items: Array.isArray(context.media_items) ? context.media_items : [],
			latency_profile: 'local_300ms',
		});
	}

	function progressiveSuggestionCount(result) {
		const section = result && result.sections ? result.sections.progressive_recommendations : null;
		if (!section || typeof section !== 'object') {
			return 0;
		}
		return Array.isArray(section.recommendation_candidates) ? section.recommendation_candidates.length : 0;
	}

	function shouldShowProgressiveRecommendationPanel(progressiveResult, progressiveStatus, expanded) {
		if (expanded) {
			return true;
		}
		const status = progressiveStatus && progressiveStatus.status ? progressiveStatus.status : '';
		if (status === 'warning' || status === 'error') {
			return true;
		}
		return status === 'success' && progressiveSuggestionCount(progressiveResult) === 0;
	}

	function renderProgressiveRecommendationPanel(progressiveResult, progressiveStatus, expanded, onOpen, onRefresh) {
		if (!shouldShowProgressiveRecommendationPanel(progressiveResult, progressiveStatus, expanded)) {
			return null;
		}
		const section = progressiveResult && progressiveResult.sections ? progressiveResult.sections.progressive_recommendations : null;
		const count = progressiveSuggestionCount(progressiveResult);
		const status = progressiveStatus && progressiveStatus.status ? progressiveStatus.status : '';
		const isLoading = status === 'loading';
		const statusMessage = progressiveStatus && progressiveStatus.message ? progressiveStatus.message : '';
		const panelMessage = isLoading
			? (statusMessage || __('Preparing local suggestions...', 'npcink-toolbox'))
			: (status === 'error' || status === 'warning'
			? (statusMessage || __('Local suggestions are unavailable.', 'npcink-toolbox'))
			: (count ? sprintf(__('%d local suggestions are ready.', 'npcink-toolbox'), count) : __('No local suggestions found for this draft. Add a clearer title, excerpt, or body context, then refresh.', 'npcink-toolbox')));
		return createElement(
			'section',
			{ className: 'npcink-toolbox-editor-support__progressive' },
			createElement(
				'div',
				{ className: 'npcink-toolbox-editor-support__progressive-head' },
				createElement('strong', null, __('Local suggestions', 'npcink-toolbox')),
				createElement('span', null, panelMessage)
			),
			createElement(
				'div',
				{ className: 'npcink-toolbox-editor-support__progressive-actions' },
				section ? createElement(
					Button,
					{
						type: 'button',
						variant: 'secondary',
						onClick: () => onOpen('progressive_recommendations'),
					},
					__('View suggestions', 'npcink-toolbox')
				) : null,
				createElement(
					Button,
					{
						type: 'button',
						variant: 'tertiary',
						isBusy: isLoading,
						disabled: isLoading,
						onClick: onRefresh,
					},
					isLoading ? __('Refreshing', 'npcink-toolbox') : __('Refresh', 'npcink-toolbox')
				)
			)
		);
	}

	function recommendationCandidateSourceLabel(item) {
		const refs = []
			.concat(Array.isArray(item && item.evidence_refs) ? item.evidence_refs : [])
			.concat(item && item.source_candidate_ref ? [item.source_candidate_ref] : [])
			.map((ref) => String(ref || ''));
		if (refs.some((ref) => ref.indexOf('local_preflight:') === 0)) {
			return __('Local preflight', 'npcink-toolbox');
		}
		if (refs.some((ref) => ref.indexOf('attachment:') === 0)) {
			return __('Recent media', 'npcink-toolbox');
		}
		if (
			(item && item.controlled_vocabulary_status === 'existing_wordpress_term') ||
			(item && ['category', 'tag', 'post_tag'].includes(String(item.kind || item.target_field || '')))
		) {
			return __('Existing terms', 'npcink-toolbox');
		}
		if (refs.some((ref) => ref.indexOf('image_provider:') === 0 || ref.indexOf('image_source_type:') === 0)) {
			return __('Image source', 'npcink-toolbox');
		}
		if (refs.some((ref) => ref.indexOf('site_knowledge:') === 0)) {
			return __('Site Knowledge', 'npcink-toolbox');
		}
		return __('Current draft', 'npcink-toolbox');
	}

	function recommendationCandidateActionClassLabel(item) {
		const policy = String(item && item.action_policy ? item.action_policy : 'suggestion_only');
		if (policy === 'core_proposal_required' || policy === 'editor_apply_preview_save_required') {
			return __('Handoffable', 'npcink-toolbox');
		}
		if (policy === 'operator_review_only_no_write' || policy === 'operator_review_only_no_insert') {
			return __('Informational', 'npcink-toolbox');
		}
		return __('Copyable', 'npcink-toolbox');
	}

	function renderItems(items, emptyLabel) {
		if (!Array.isArray(items) || !items.length) {
			return createElement('p', { className: 'npcink-toolbox-editor-support__muted' }, emptyLabel || __('No candidates returned.', 'npcink-toolbox'));
		}

		return createElement(
			'ul',
			{ className: 'npcink-toolbox-editor-support__list' },
			items.slice(0, 8).map((item, index) => {
				const title = readableItemText(item.name || item.title || item.label || item.source_title || item.url || item.download_url || item.id, __('Candidate', 'npcink-toolbox'));
				const detail = [
					readableItemText(item.value, ''),
					readableItemText(item.reason || item.detail || item.excerpt || item.source_url || item.status || item.taxonomy || item.provider, ''),
					Array.isArray(item.matched_tokens) && item.matched_tokens.length ? __('Matched: ', 'npcink-toolbox') + item.matched_tokens.slice(0, 5).join(', ') : '',
				].filter(Boolean).join(' · ');
				return createElement(
					'li',
					{ key: String(index) + '-' + String(title) },
					createElement('strong', null, title),
					detail ? createElement('span', null, detail) : null,
					createElement(
						'div',
						{ className: 'npcink-toolbox-editor-support__candidate-meta' },
						createElement('span', null, __('Source: ', 'npcink-toolbox') + recommendationCandidateSourceLabel(item)),
						createElement('span', null, __('Action: ', 'npcink-toolbox') + recommendationCandidateActionClassLabel(item))
					)
				);
			})
		);
	}

	function extractImageItems(section) {
		if (!section || typeof section !== 'object') {
			return [];
		}
		return section.image_candidates || section.images || section.image_source_candidates || section.source_candidates || section.media_candidates || section.assets || section.candidates || [];
	}

		function extractImageCandidates(payload) {
			if (!payload || typeof payload !== 'object') {
				return [];
			}
		if (payload.sections && payload.sections.image_candidates) {
			return extractImageItems(payload.sections.image_candidates);
		}
			return payload.image_candidates || payload.images || payload.candidates || [];
		}

		function imageCandidateCollectionField(section) {
			if (!section || typeof section !== 'object') {
				return 'candidates';
			}
			const fields = ['image_candidates', 'images', 'image_source_candidates', 'source_candidates', 'media_candidates', 'assets', 'candidates'];
			for (let index = 0; index < fields.length; index += 1) {
				if (Array.isArray(section[fields[index]])) {
					return fields[index];
				}
			}
			return 'candidates';
		}

		function imageCandidateResultWithCandidates(payload, candidates) {
			const normalized = Array.isArray(candidates) ? candidates.slice(0, IMAGE_CANDIDATE_TARGET_COUNT) : [];
			const source = payload && typeof payload === 'object' ? payload : {};
			const next = Object.assign({}, source, {
				candidate_count: normalized.length,
				displayed_count: normalized.length,
			});
			if (source.sections && source.sections.image_candidates && typeof source.sections.image_candidates === 'object') {
				const section = Object.assign({}, source.sections.image_candidates);
				section[imageCandidateCollectionField(section)] = normalized;
				next.sections = Object.assign({}, source.sections, {
					image_candidates: section,
				});
				return next;
			}
			next[imageCandidateCollectionField(source)] = normalized;
			return next;
		}

		function mergeImageCandidateResults(primary, secondary) {
			const mergedCandidates = mergeUniqueByKey(
				extractImageCandidates(primary).concat(extractImageCandidates(secondary)),
				[],
				(image, index) => imageStableKey(image, index)
			);
			return imageCandidateResultWithCandidates(Object.assign({}, primary || {}, secondary || {}), mergedCandidates);
		}

		function imageCandidateResultNeedsCompletion(result) {
			const count = extractImageCandidates(result).length;
			return count < IMAGE_CANDIDATE_TARGET_COUNT;
		}

	function imageTitle(image) {
		return image.title || image.alt_description || image.description || image.prompt || image.id || __('Image candidate', 'npcink-toolbox');
	}

	function imagePreviewUrl(image) {
		return image.thumbnail_url || image.thumb_url || image.small_url || image.preview_url || image.regular_url || image.download_url || image.url || '';
	}

	function imageFullPreviewUrl(image) {
		return image.regular_url || image.download_url || image.url || imagePreviewUrl(image);
	}

	function imageSourceUrl(image) {
		return image.html_url || image.source_url || image.photographer_url || image.regular_url || image.download_url || image.url || '';
	}

	function imageAttributionLabel(image) {
		const raw = String((image && (image.attribution || image.attribution_text)) || '')
			.replace(/https?:\/\/\S+/gi, '')
			.replace(/\s+/g, ' ')
			.trim();
		if (!raw) {
			return '';
		}
		const providerByline = raw.match(/^(.+?)\s+image\s+by\s+(.+?)\.?$/i);
		if (providerByline) {
			return sprintf(
				__('Image by %1$s / %2$s', 'npcink-toolbox'),
				providerByline[2].replace(/\.$/, '').trim(),
				formatMetaLabel(providerByline[1])
			);
		}
		return raw;
	}

	function imageDownloadUrl(image) {
		return image.download_url || image.regular_url || image.small_url || image.url || imagePreviewUrl(image);
	}

	function imageStableKey(image, index) {
		return String(image.id || image.download_url || image.regular_url || imagePreviewUrl(image) || imageTitle(image) || index);
	}

	function imageAgentFeedbackEvidenceRefIds(payload, selectedImage) {
		const images = selectedImage ? [selectedImage] : extractImageCandidates(payload).slice(0, 8);
		const ids = [];
		images.forEach((image, index) => {
			if (!image || typeof image !== 'object') {
				return;
			}
			const provider = String(image.provider || (payload && (payload.provider_mode || payload.resolved_provider)) || 'image').trim();
			const sourceType = String(image.source_type || (payload && payload.provider_mode) || 'candidate').trim();
			const id = String(image.id || image.asset_id || image.suggested_filename || imageStableKey(image, index)).trim();
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

	function editorImageAgentFeedbackPayload(payload, selectedImage, picker, outcome, labels, options) {
		const feedbackOptions = options && typeof options === 'object' ? options : {};
		const activePicker = normalizeImagePickerOptions(picker || {});
		const sourceType = String((selectedImage && selectedImage.source_type) || (payload && payload.provider_mode) || '').toLowerCase();
		const aiGenerated = sourceType === 'ai_generated';
		const sourceRuntime = aiGenerated ? 'ai_image_generation' : 'image_candidates';
		const surface = feedbackOptions.localSurface || (aiGenerated ? 'editor_ai_image_generation_modal' : 'editor_image_candidate_modal');
		const runId = payload && payload.run_id ? String(payload.run_id) : '';
		const handoffId = [
			sourceRuntime,
			feedbackOptions.action || '',
			activePicker.imageUse || activePicker.mode,
			selectedImage ? imageStableKey(selectedImage, 0) : '',
			runId
		].filter(Boolean).join(':') || sourceRuntime;

		return {
			contract_version: 'cloud_agent_feedback.v1',
			agent_id: aiGenerated ? 'ai_image_generation_candidate_agent' : 'image_source_candidate_agent',
			agent_version: payload && payload.candidate_contract_version ? String(payload.candidate_contract_version) : '',
			source_runtime: sourceRuntime,
			source_run_id: runId,
			handoff_id: handoffId.slice(0, 191),
			handoff_type: feedbackOptions.handoffType || 'editor_image_candidate_result',
			local_surface: surface,
			local_outcome: outcome,
			feedback_labels: labels,
			operator_note: '',
			local_proposal_id: '',
			evidence_ref_ids: imageAgentFeedbackEvidenceRefIds(payload || {}, selectedImage),
			redaction_status: 'metadata_only',
			retention_class: 'quality_eval',
			created_at: new Date().toISOString()
		};
	}

	function editorImageImplicitFeedbackPayload(payload, selectedImage, picker, action, outcome, labels) {
		return editorImageAgentFeedbackPayload(payload, selectedImage, picker, outcome, labels, {
			action,
			handoffType: 'editor_image_candidate_' + sanitizeFeedbackAction(action),
			localSurface: 'editor_image_candidate_modal_implicit',
		});
	}

	function contentSupportFeedbackEvidenceRefIds(payload) {
		const ids = [];
		const sections = payload && payload.sections && typeof payload.sections === 'object' ? payload.sections : {};
		const addId = (value) => {
			const normalized = String(value || '').trim().slice(0, 191);
			if (normalized && ids.indexOf(normalized) === -1) {
				ids.push(normalized);
			}
		};
		if (payload && payload.run_id) {
			addId('content_support_run:' + payload.run_id);
		}
		Object.keys(sections).forEach((key) => {
			const section = sections[key];
			addId('content_support_section:' + key);
			if (!section || typeof section !== 'object') {
				return;
			}
			if (section.artifact_type) {
				addId('artifact:' + section.artifact_type);
			}
			if (section.run_id) {
				addId('section_run:' + section.run_id);
			}
			const refs = Array.isArray(section.evidence_refs) ? section.evidence_refs : [];
			refs.forEach((ref, index) => {
				if (ref && typeof ref === 'object') {
					addId(ref.id || ref.ref_id || [key, ref.source_type || 'evidence', ref.source_id || ref.post_id || ref.url || index + 1].join(':'));
				}
			});
		});
		return ids.slice(0, 24);
	}

	function editorContentSupportFeedbackPayload(payload, intent, outcome, labels, localProposalId, options) {
		const feedbackOptions = options && typeof options === 'object' ? options : {};
		const activeIntent = String(intent || (payload && payload.intent) || 'content_support').trim();
		const runId = payload && payload.run_id ? String(payload.run_id) : '';
		const artifactType = payload && payload.artifact_type ? String(payload.artifact_type) : 'editor_content_support_flow';
		const handoffId = [
			'content_support',
			feedbackOptions.action || '',
			activeIntent,
			artifactType,
			runId || String(contentSupportFeedbackEvidenceRefIds(payload || {}).length)
		].filter(Boolean).join(':');

		return {
			contract_version: 'cloud_agent_feedback.v1',
			agent_id: 'editor_content_support_agent',
			agent_version: payload && payload.contract_version ? String(payload.contract_version) : '',
			source_runtime: 'content_support',
			source_run_id: runId,
			handoff_id: handoffId.slice(0, 191),
			handoff_type: feedbackOptions.handoffType || 'editor_content_support_result',
			local_surface: feedbackOptions.localSurface || 'editor_content_support_sidebar',
			local_outcome: outcome,
			feedback_labels: labels,
			operator_note: '',
			local_proposal_id: localProposalId || '',
			evidence_ref_ids: contentSupportFeedbackEvidenceRefIds(payload || {}),
			redaction_status: 'metadata_only',
			retention_class: 'quality_eval',
			created_at: new Date().toISOString()
		};
	}

	function editorContentImplicitFeedbackPayload(payload, intent, action, outcome, labels, localProposalId) {
		return editorContentSupportFeedbackPayload(payload, intent, outcome, labels, localProposalId, {
			action,
			handoffType: 'editor_content_support_' + sanitizeFeedbackAction(action),
			localSurface: 'editor_content_support_sidebar_implicit',
		});
	}

	function sanitizeFeedbackAction(value) {
		return String(value || 'interaction').toLowerCase().replace(/[^a-z0-9_]+/g, '_').replace(/^_+|_+$/g, '') || 'interaction';
	}

	function imageSearchCacheKey(type, picker, query, context) {
		const cachePicker = normalizeImagePickerOptions(picker || {});
		const cacheContext = imageRequestContext(context || {}, cachePicker.context);
		return [
			'image_grid_9_auto_retry',
			type || 'image',
			cachePicker.imageUse,
			String(query || '').trim().toLowerCase(),
			truncateText(cacheContext.title, 90),
			truncateText(cacheContext.excerpt, 120),
			truncateText(cacheContext.selected_text || cacheContext.selected_block_text || cacheContext.content, 220),
		].join('|');
	}

	function imageRefreshVariant() {
		return String(Date.now()) + '-' + String(Math.random()).slice(2, 10);
	}

	function readCachedImageResult(cacheKey) {
		const cached = imageResultCache[cacheKey];
		if (!cached || !cached.timestamp || Date.now() - cached.timestamp > IMAGE_RESULT_CACHE_TTL) {
			return null;
		}
		return cached.result || null;
	}

	function writeCachedImageResult(cacheKey, result) {
		if (!cacheKey || !result) {
			return;
		}
		imageResultCache[cacheKey] = {
			timestamp: Date.now(),
			result,
		};
		const cacheKeys = Object.keys(imageResultCache);
		if (cacheKeys.length > IMAGE_RESULT_CACHE_MAX_ENTRIES) {
			cacheKeys
				.sort((a, b) => (imageResultCache[a].timestamp || 0) - (imageResultCache[b].timestamp || 0))
				.slice(0, cacheKeys.length - IMAGE_RESULT_CACHE_MAX_ENTRIES)
				.forEach((key) => {
					delete imageResultCache[key];
				});
		}
	}

	function truncateText(value, maxLength) {
		const text = String(value || '').trim();
		if (!text || text.length <= maxLength) {
			return text;
		}
		return text.slice(0, maxLength - 1).trim() + '...';
	}

	function filenameExtensionFromUrl(url) {
		const cleanUrl = String(url || '').split('?')[0].split('#')[0];
		const match = cleanUrl.match(/\.([a-z0-9]{3,5})$/i);
		if (!match) {
			return 'jpg';
		}
		const ext = match[1].toLowerCase();
		return ['jpg', 'jpeg', 'png', 'webp', 'gif'].indexOf(ext) >= 0 ? ext : 'jpg';
	}

	function filenameBase(value, fallback) {
		const base = String(value || '')
			.normalize ? String(value || '').normalize('NFKD') : String(value || '');
		const slug = base
			.toLowerCase()
			.replace(/[\u0300-\u036f]/g, '')
			.replace(/[^a-z0-9]+/g, '-')
			.replace(/^-+|-+$/g, '')
			.slice(0, 72);
		return slug || fallback || 'featured-image';
	}

	function buildImageSeoFields(image, postContext) {
		const suggestions = image && typeof image.seo_suggestions === 'object' ? image.seo_suggestions : {};
		const title = truncateText(imageTitle(image), 90) || __('Selected image candidate', 'npcink-toolbox');
		const alt = truncateText(suggestions.alt || suggestions.alt_text || image.alt_description || image.description || postContext.title || title, 140) || title;
		const description = truncateText(suggestions.description || image.description || image.alt_description || postContext.excerpt || title, 220) || alt;
		const attribution = truncateText(image.attribution || image.credit || image.photographer || '', 180);
		const fileBase = filenameBase(postContext.title || title, postContext.post_id ? 'post-' + String(postContext.post_id) + '-featured-image' : 'featured-image');
		return {
			title: truncateText(suggestions.title || title, 90) || title,
			alt,
			description,
			attribution_text: attribution,
			file_name: fileBase + '.' + filenameExtensionFromUrl(imageDownloadUrl(image)),
		};
	}

	function hasDraftImageContext(postContext) {
		return Boolean(String(postContext.selected_text || postContext.selected_block_text || postContext.title || postContext.excerpt || postContext.content || '').trim());
	}

	function hasScopedImageContext(context, picker) {
		const activePicker = normalizeImagePickerOptions(picker || {});
		if (activePicker.contextScope === 'paragraph') {
			return Boolean(String(context.selected_text || context.selected_block_text || '').trim());
		}
		if (activePicker.contextScope === 'setting') {
			return hasDraftImageContext(context);
		}
		return Boolean(String(context.title || context.excerpt || context.content || '').trim());
	}

	function imageMissingContextMessage(picker) {
		const activePicker = normalizeImagePickerOptions(picker || {});
		if (activePicker.contextScope === 'paragraph') {
			return __('Select a paragraph with text before asking for paragraph image recommendations, or enter a manual image search query.', 'npcink-toolbox');
		}
		if (activePicker.contextScope === 'setting') {
			return __('Enter a short image search query or provide setting context before searching.', 'npcink-toolbox');
		}
		return __('Add a title, excerpt, or body text to use article-level featured image recommendations, or enter a manual search query.', 'npcink-toolbox');
	}

	function normalizeImagePickerOptions(options) {
		const source = options && typeof options === 'object' ? options : {};
		const requestedMode = String(source.mode || source.image_mode || '').replace(/_image$/, '') || 'featured';
		const preset = imagePickerPresets[requestedMode] || imagePickerPresets.featured;
		const context = source.context && typeof source.context === 'object' ? source.context : {};
		const imageUse = source.image_use || source.imageUse || preset.imageUse;
		const adoptionMode = source.adoption_mode || source.adoptionMode || preset.adoptionMode;
		const initialSearchMode = source.initial_search_mode || source.initialSearchMode || preset.initialSearchMode || 'source';
		const allowGeneration = source.allow_generation !== undefined ? Boolean(source.allow_generation) : (source.allowGeneration !== undefined ? Boolean(source.allowGeneration) : Boolean(preset.allowGeneration));
		return Object.assign({}, preset, {
			mode: preset.mode,
			imageUse,
			adoptionMode,
			contextScope: source.context_scope || source.contextScope || preset.contextScope || 'article',
			initialSearchMode: allowGeneration && initialSearchMode === 'generate' ? 'generate' : 'source',
			title: source.title || preset.title,
			intro: source.intro || preset.intro,
			emptyTitle: source.empty_title || source.emptyTitle || preset.emptyTitle,
			context,
			initialQuery: source.query || source.manual_query || source.initialQuery || '',
			autoSearch: source.auto_search !== undefined ? Boolean(source.auto_search) : Boolean(preset.autoSearch),
			allowGeneration,
			allowImagePlan: source.allow_image_plan !== undefined ? Boolean(source.allow_image_plan) : (source.allowImagePlan !== undefined ? Boolean(source.allowImagePlan) : Boolean(preset.allowImagePlan)),
			sourceModeLabel: source.source_mode_label || source.sourceModeLabel || preset.sourceModeLabel || __('Recommended image', 'npcink-toolbox'),
			generateModeLabel: source.generate_mode_label || source.generateModeLabel || preset.generateModeLabel || __('Manual prompt', 'npcink-toolbox'),
			searchPlaceholder: source.search_placeholder || source.searchPlaceholder || preset.searchPlaceholder || __('Search or describe image needs', 'npcink-toolbox'),
			generatePlaceholder: source.generate_placeholder || source.generatePlaceholder || preset.generatePlaceholder || __('Review or enter an AI image prompt', 'npcink-toolbox'),
			searchButtonLabel: source.search_button_label || source.searchButtonLabel || preset.searchButtonLabel || __('Recommend images', 'npcink-toolbox'),
			searchBusyLabel: source.search_busy_label || source.searchBusyLabel || preset.searchBusyLabel || __('Recommending', 'npcink-toolbox'),
			autoButtonLabel: source.auto_button_label || source.autoButtonLabel || preset.autoButtonLabel || __('Search from article', 'npcink-toolbox'),
			generateButtonLabel: source.generate_button_label || source.generateButtonLabel || preset.generateButtonLabel || __('Generate AI image', 'npcink-toolbox'),
			briefButtonLabel: normalizeImageBriefButtonLabel(source.brief_button_label || source.briefButtonLabel || preset.briefButtonLabel),
			selectionEvent: source.selection_event || source.selectionEvent || IMAGE_SOURCE_PICKER_SELECTED_EVENT,
			closeOnSelect: source.close_on_select !== undefined ? Boolean(source.close_on_select) : false,
		});
	}

	function imagePickerContextOverride(picker, contextOverride) {
		const activePicker = normalizeImagePickerOptions(picker || {});
		const base = Object.assign({}, activePicker.context || {}, contextOverride && typeof contextOverride === 'object' ? contextOverride : {});
		base.context_scope = activePicker.contextScope;
		if (activePicker.contextScope === 'article') {
			base.ignore_selection = true;
			base.selected_text = '';
			base.selected_block_text = '';
			base.selected_block_name = '';
		}
		return base;
	}

	function imageRequestContext(postContext, contextOverride) {
		const override = contextOverride && typeof contextOverride === 'object' ? contextOverride : {};
		const ignoreSelection = Boolean(override.ignore_selection || override.ignoreSelection || override.context_scope === 'article' || override.contextScope === 'article');
		return Object.assign({}, postContext, override, {
			selected_text: ignoreSelection ? '' : (browserSelectedText() || override.selected_text || postContext.selected_text || ''),
			selected_block_text: ignoreSelection ? '' : (override.selected_block_text || postContext.selected_block_text || ''),
			selected_block_name: ignoreSelection ? '' : (override.selected_block_name || postContext.selected_block_name || ''),
		});
	}

	function imagePickerRequestContext(postContext, picker, contextOverride) {
		return imageRequestContext(postContext || {}, imagePickerContextOverride(picker, contextOverride));
	}

	function buildImageVisualContext(postContext, imageMode, manualQuery, contextOverride, imageUse, refreshVariant) {
		const context = imageRequestContext(postContext, contextOverride);
		const visualContext = {
			image_mode: imageUse || (imageMode === 'paragraph' ? 'paragraph_image' : 'featured_image'),
			manual_query: String(manualQuery || '').trim(),
			title: truncateText(context.title, 160),
			post_id: context.post_id || 0,
			excerpt: truncateText(context.excerpt, 260),
			content_summary: truncateText(plainTextFromHtml(context.content), 600),
			selected_text: truncateText(context.selected_text, 500),
			selected_block_text: truncateText(context.selected_block_text, 500),
			selected_block_name: context.selected_block_name || '',
			avoid_brand_logos: true,
			query_intent: {
				rewrite_abstract_terms: true,
				prefer_concrete_visual_scene: true,
				return_alternate_queries: true,
			},
		};
		if (refreshVariant) {
			visualContext.refresh_variant = refreshVariant;
		}
		return visualContext;
	}

	function imageFastSearchQuery(postContext, manualQuery, contextOverride, picker) {
		const activePicker = normalizeImagePickerOptions(picker || {});
		const context = imagePickerRequestContext(postContext, activePicker, contextOverride);
		const query = String(manualQuery || '').trim();
		if (query) {
			return truncateText(query, 180);
		}

		if (activePicker.contextScope === 'paragraph') {
			const selected = [context.selected_text, context.selected_block_text]
				.map((value) => String(value || '').trim())
				.filter(Boolean)
				.join(' ');
			if (selected) {
				return truncateText(selected, 180);
			}
		}

		const summary = [context.title, context.excerpt]
			.map((value) => String(value || '').trim())
			.filter(Boolean)
			.join(' ');
		if (summary) {
			return truncateText(summary, 180);
		}

		return truncateText(plainTextFromHtml(context.content), 180);
	}

	function formatMetaLabel(value) {
		const raw = String(value || '').trim();
		const key = raw.toLowerCase().replace(/\s+/g, '_').replace(/-+/g, '_');
		const labels = {
			issue: __('Issue', 'npcink-toolbox'),
			diagnosis: __('Diagnosis', 'npcink-toolbox'),
			authorization: __('Authorization boundary', 'npcink-toolbox'),
			summary: __('Summary', 'npcink-toolbox'),
			taxonomy: __('Categories and tags', 'npcink-toolbox'),
			evidence: __('Evidence', 'npcink-toolbox'),
			suggestion_only: __('Suggestion only', 'npcink-toolbox'),
			core_proposal_required: __('Core review required', 'npcink-toolbox'),
			content_metadata_delta_handoff: __('Content metadata handoff', 'npcink-toolbox'),
			summary_suggestions: __('Summary suggestions', 'npcink-toolbox'),
			category_suggestions: __('Category suggestions', 'npcink-toolbox'),
			tag_suggestions: __('Tag suggestions', 'npcink-toolbox'),
			metadata_suggestions: __('Metadata suggestions', 'npcink-toolbox'),
			summary_terms_optimization: __('Metadata optimization', 'npcink-toolbox'),
			article_checkup: __('Article checkup', 'npcink-toolbox'),
			clarity: __('Clarity', 'npcink-toolbox'),
			structure: __('Structure', 'npcink-toolbox'),
			fact_gap: __('Fact gap', 'npcink-toolbox'),
			tone: __('Tone', 'npcink-toolbox'),
			format: __('Format', 'npcink-toolbox'),
			publish_preflight: __('Publish preflight', 'npcink-toolbox'),
			title_suggestions: __('Title suggestions', 'npcink-toolbox'),
			article_outline: __('Outline suggestions', 'npcink-toolbox'),
			polish_notes: __('Paragraph check', 'npcink-toolbox'),
			discoverability: __('Discoverability suggestions', 'npcink-toolbox'),
			image_alt_suggestions: __('Image ALT suggestions', 'npcink-toolbox'),
			categories: __('Categories', 'npcink-toolbox'),
			tags: __('Tags', 'npcink-toolbox'),
			featured_image: __('Featured image', 'npcink-toolbox'),
			image_candidates: __('Image candidates', 'npcink-toolbox'),
			internal_link_candidates: __('Internal link candidates', 'npcink-toolbox'),
			internal_links: __('Internal links', 'npcink-toolbox'),
			ready: __('Ready', 'npcink-toolbox'),
			required: __('Required', 'npcink-toolbox'),
			pexels: __('Pexels', 'npcink-toolbox'),
			pixabay: __('Pixabay', 'npcink-toolbox'),
			unsplash: __('Unsplash', 'npcink-toolbox'),
			pre_publish_review: __('Pre-publish review', 'npcink-toolbox'),
			seo_handoff: __('SEO handoff', 'npcink-toolbox'),
			seo_meta_single_post_handoff: __('SEO handoff', 'npcink-toolbox'),
			seo_meta: __('SEO meta', 'npcink-toolbox'),
			duplicate_check: __('Duplicate check', 'npcink-toolbox'),
			duplicate_risk: __('Duplicate risk', 'npcink-toolbox'),
			recommended_excerpt: __('Recommended excerpt', 'npcink-toolbox'),
			review_only_candidate: __('Review only', 'npcink-toolbox'),
			review_required: __('Review required', 'npcink-toolbox'),
			operator_review_only_no_insert: __('Review only, no insert', 'npcink-toolbox'),
			core_policy_gated_strong_review: __('Core strong review', 'npcink-toolbox'),
			no_direct_term_creation_in_toolbox: __('No direct term creation', 'npcink-toolbox'),
			hosted_summary: __('Hosted summary', 'npcink-toolbox'),
			good: __('Good', 'npcink-toolbox'),
			ok: __('OK', 'npcink-toolbox'),
			review: __('Review', 'npcink-toolbox'),
			warning: __('Warning', 'npcink-toolbox'),
			error: __('Error', 'npcink-toolbox'),
			missing: __('Missing', 'npcink-toolbox'),
			present: __('Present', 'npcink-toolbox'),
			high: __('High', 'npcink-toolbox'),
			medium: __('Medium', 'npcink-toolbox'),
			low: __('Low', 'npcink-toolbox'),
		};
		return labels[key] || raw.replace(/[_-]+/g, ' ');
	}

	function compactLabelParts(parts) {
		const seen = {};
		return (Array.isArray(parts) ? parts : [])
			.map((item) => String(item || '').trim())
			.filter((item) => {
				const key = item.toLowerCase();
				if (!item || item === 'ai_generated' || item === 'magick_ai_cloud' || item === 'grok_imagine' || seen[key]) {
					return false;
				}
				seen[key] = true;
				return true;
			});
	}

	function imageResultProviderDetails(payload) {
		const source = imageResultSource(payload || {});
		if (!source || typeof source !== 'object') {
			return [];
		}
		return compactLabelParts([
			source.hosted_profile,
			source.model_id,
			source.resolved_provider,
		]);
	}

	function imageResultSourceLabel(payload, includeDetails) {
		const source = imageResultSource(payload || {});
		if (!source || typeof source !== 'object') {
			return '';
		}
		if (String(source.provider_mode || '').toLowerCase() === 'ai_generated') {
			const parts = includeDetails ? imageResultProviderDetails(payload) : [];
			return parts.length ? parts.join(' / ') : __('AI generated', 'npcink-toolbox');
		}
		return source.resolved_provider ? formatMetaLabel(source.resolved_provider) : '';
	}

	function imageCandidateProviderDetails(image, payload) {
		if (!image || typeof image !== 'object') {
			return [];
		}
		const source = imageResultSource(payload || {});
		return compactLabelParts([
			image.hosted_profile,
			source && source.hosted_profile,
			image.generation_model || image.model,
			source && source.model_id,
			image.generation_provider || image.provider_name,
		]);
	}

	function imageCandidateSourceLabel(image, payload, includeDetails) {
		if (!image || typeof image !== 'object') {
			return '';
		}
		const sourceType = String(image.source_type || '').toLowerCase();
		const provider = String(image.provider || '').toLowerCase();
		if (sourceType === 'ai_generated' || provider === 'ai_generated') {
			const parts = includeDetails ? imageCandidateProviderDetails(image, payload) : [];
			return parts.length ? parts.join(' / ') : __('AI generated', 'npcink-toolbox');
		}
		return image.provider ? formatMetaLabel(image.provider) : '';
	}

	function formatIntentLabel(value) {
		if (value === 'writing_support') {
			return __('Find related existing posts', 'npcink-toolbox');
		}
		if (value === 'article_checkup') {
			return __('Article checkup', 'npcink-toolbox');
		}
		if (value === 'title_suggestions') {
			return __('Title suggestions', 'npcink-toolbox');
		}
		if (value === 'article_outline') {
			return __('Outline suggestions', 'npcink-toolbox');
		}
		if (value === 'polish_notes') {
			return __('Check selected paragraph', 'npcink-toolbox');
		}
		if (value === 'discoverability') {
			return __('Discoverability suggestions', 'npcink-toolbox');
		}
		if (value === 'image_alt_suggestions') {
			return __('Image ALT suggestions', 'npcink-toolbox');
		}
		if (value === 'summary_terms_optimization') {
			return __('Metadata optimization', 'npcink-toolbox');
		}
		if (value === 'summary_suggestions') {
			return __('AI generate summary', 'npcink-toolbox');
		}
		if (value === 'category_suggestions') {
			return __('Category suggestions', 'npcink-toolbox');
		}
		if (value === 'tag_suggestions') {
			return __('Tag suggestions', 'npcink-toolbox');
		}
		return formatMetaLabel(value);
	}

	function resultScopeLabel(value) {
		if (value === 'writing_support') {
			return __('Finds similar published content first, then helps you decide how this draft should differ.', 'npcink-toolbox');
		}
		if (value === 'article_checkup') {
			return __('Checks the full draft for review items and points you to affected paragraphs. It will not rewrite the article.', 'npcink-toolbox');
		}
		if (value === 'title_suggestions') {
			return __('Review title options before replacing the post title.', 'npcink-toolbox');
		}
		if (value === 'article_outline') {
			return __('Use the outline as planning notes; it does not write the article body.', 'npcink-toolbox');
		}
		if (value === 'polish_notes') {
			return __('Review clarity, fact gaps, and tone notes for the selected paragraph. It will not replace text.', 'npcink-toolbox');
		}
		if (value === 'discoverability') {
			return __('Turn SEO, AEO, GEO, and proposal-field suggestions into reviewable optimization tasks and Core handoff candidates.', 'npcink-toolbox');
		}
		if (value === 'image_alt_suggestions') {
			return __('Review ALT and caption suggestions against the actual image before any media edit.', 'npcink-toolbox');
		}
		if (value === 'summary_suggestions') {
			return __('AI reads the current draft and returns an editor-ready excerpt candidate.', 'npcink-toolbox');
		}
		if (value === 'category_suggestions' || value === 'tag_suggestions') {
			return __('Review suggestions here, then confirm existing terms in the editor.', 'npcink-toolbox');
		}
		if (value === 'summary_terms_optimization') {
			return __('Suggestions only. Final writes require Core approval.', 'npcink-toolbox');
		}
		return __('Review suggestions for the current draft.', 'npcink-toolbox');
	}

	function formatImageErrorMessage(error, fallback) {
		const code = error && error.code ? String(error.code) : '';
		const message = error && error.message ? String(error.message) : '';
		if (message.toLowerCase().indexOf('runtime quota') >= 0 && message.toLowerCase().indexOf('exhausted') >= 0) {
			return __('Cloud runtime quota is exhausted for this request. Check Cloud quota or billing limits, then retry.', 'npcink-toolbox');
		}
		if (code === 'cloud_routing_profile_not_found' || message.indexOf('image-source.managed') >= 0) {
			return __('Npcink Cloud image-source profile is not configured. Configure image-source.managed in Cloud, then search again.', 'npcink-toolbox');
		}
		if (code === 'cloud_routing_execution_kind_mismatch' || message.indexOf('expects') >= 0) {
			return __('Npcink Cloud routed this image-source request to the wrong runtime profile. Verify the Cloud image-source routing profile.', 'npcink-toolbox');
		}
		if (message.toLowerCase().indexOf('connect npcink cloud') >= 0) {
			return __('Npcink Cloud Addon is not connected or not configured for managed image-source search.', 'npcink-toolbox');
		}
		return message || fallback;
	}

	function aiImagePromptSubject(postContext, picker, manualPrompt) {
		const activePicker = normalizeImagePickerOptions(picker || {});
		const context = imagePickerRequestContext(postContext || {}, activePicker);
		const selectedContext = String(context.selected_text || context.selected_block_text || '').trim();
		const operatorPrompt = String(manualPrompt || '').trim();
		const articleContext = String(context.title || context.excerpt || '').trim();
		const subject = activePicker.contextScope === 'paragraph'
			? (selectedContext || operatorPrompt)
			: (operatorPrompt || articleContext || truncateText(plainTextFromHtml(context.content), 260));
		if (!String(subject || '').trim()) {
			return null;
		}
		return {
			subject: String(subject || '').trim(),
			operatorPrompt: selectedContext && operatorPrompt ? operatorPrompt : '',
			source: selectedContext ? 'selected_paragraph' : (operatorPrompt ? 'operator_prompt' : 'article_context'),
		};
	}

	function defaultAiImageGenerationPrompt(postContext, picker, manualPrompt, aspectRatio) {
		const promptSubject = aiImagePromptSubject(postContext, picker, manualPrompt);
		if (!promptSubject) {
			return '';
		}
		const contextLabel = promptSubject.source === 'selected_paragraph'
			? 'Context source: selected paragraph. Use it as semantic context, not as visible copy.'
			: (promptSubject.source === 'operator_prompt' ? 'Context source: operator prompt.' : 'Context source: article context.');
		const lines = [
			'Create an original editorial image for a WordPress article.',
			contextLabel,
			'Source context: ' + truncateText(promptSubject.subject, 260),
		];
		if (promptSubject.operatorPrompt) {
			lines.push('Operator visual direction: ' + truncateText(promptSubject.operatorPrompt, 220));
		}
		return [
			...lines,
			'Visual task: translate the context into a concrete editorial scene or metaphor; do not make a screenshot, poster, document, slide, interface, or text panel.',
			'Composition: ' + (aspectRatio || '16:9') + ' image suitable for a WordPress article.',
			'Style: clean editorial photo illustration, natural light, high quality.',
			'Text rule: no visible text, letters, numbers, labels, logos, watermarks, UI copy, or reproduced article wording.',
			'Avoid distorted hands or faces and copyrighted characters.'
		].join('\n');
	}

	function aiImageRevisionPrompt(postContext, picker, selectedImage, currentPrompt, aspectRatio, revisionMode) {
		const activePicker = normalizeImagePickerOptions(picker || {});
		const basePrompt = String(currentPrompt || selectedImage && (selectedImage.generation_prompt || selectedImage.prompt || selectedImage.description) || '').trim()
			|| defaultAiImageGenerationPrompt(postContext, activePicker, '', aspectRatio);
		const context = imagePickerRequestContext(postContext || {}, activePicker);
		const selectedContext = String(context.selected_text || context.selected_block_text || context.title || context.excerpt || '').trim();
		const mode = String(revisionMode || 'more_editorial');
		const instruction = mode === 'more_specific'
			? 'Revision direction: make the visual concept more specific and concrete while preserving the selected paragraph meaning.'
			: (mode === 'simpler'
				? 'Revision direction: simplify the composition, reduce visual clutter, and keep one clear editorial idea.'
				: 'Revision direction: make it feel more like a polished editorial article image with stronger composition and natural context.');
		return [
			basePrompt,
			'',
			'Regenerate this AI image candidate as a revised alternative.',
			instruction,
			selectedContext ? 'Preserve semantic context: ' + truncateText(selectedContext, 240) : '',
			'Keep composition: ' + (aspectRatio || '16:9') + '.',
			'Do not add visible text, letters, numbers, logos, watermarks, UI screenshots, posters, or copied article wording.'
		].filter(Boolean).join('\n');
	}

	function imageIsAiGenerated(image) {
		if (!image || typeof image !== 'object') {
			return false;
		}
		const sourceType = String(image.source_type || '').toLowerCase();
		const provider = String(image.provider || '').toLowerCase();
		return sourceType === 'ai_generated' || provider === 'ai_generated';
	}

	function imageCandidateTagValues(image) {
		const tags = []
			.concat(image.recommended_use ? [formatMetaLabel(image.recommended_use)] : [])
			.concat(image.license_review_status ? [formatMetaLabel(image.license_review_status)] : [])
			.concat(Array.isArray(image.quality_tags) ? image.quality_tags.map(formatMetaLabel).slice(0, 2) : [])
			.concat(Array.isArray(image.risk_flags) && image.risk_flags.length ? [__('Review risk', 'npcink-toolbox')] : []);
		return tags.filter(Boolean).slice(0, 4);
	}

	function extractImageSearchSuggestions(payload) {
		const source = payload && payload.sections && payload.sections.image_candidates ? payload.sections.image_candidates : (payload || {});
		const brief = source.visual_brief && typeof source.visual_brief === 'object' ? source.visual_brief : {};
		const suggestions = []
			.concat(brief.primary_query ? [brief.primary_query] : [])
			.concat(source.optimized_query ? [source.optimized_query] : [])
			.concat(Array.isArray(brief.alternate_queries) ? brief.alternate_queries : [])
			.concat(Array.isArray(source.alternate_queries) ? source.alternate_queries : [])
			.concat(Array.isArray(source.query_suggestions) ? source.query_suggestions : []);
		const seen = {};
		return suggestions
			.map((item) => String(item || '').trim())
			.filter((item) => {
				const key = item.toLowerCase();
				if (!item || seen[key]) {
					return false;
				}
				seen[key] = true;
				return true;
			})
			.slice(0, 4);
	}

	function fallbackImageSearchSuggestions(picker) {
		const activePicker = normalizeImagePickerOptions(picker || {});
		if (activePicker.mode === 'paragraph') {
			return [
				{ label: __('editorial article illustration', 'npcink-toolbox'), query: 'editorial article illustration' },
				{ label: __('concept photo for article section', 'npcink-toolbox'), query: 'conceptual editorial photo for article section' },
				{ label: __('professional workspace detail', 'npcink-toolbox'), query: 'professional workspace desk detail' },
			];
		}
		if (activePicker.mode === 'setting') {
			return [
				{ label: __('homepage hero image', 'npcink-toolbox'), query: 'clean homepage hero workspace' },
				{ label: __('product workspace', 'npcink-toolbox'), query: 'product workspace desk' },
				{ label: __('clean website banner', 'npcink-toolbox'), query: 'clean website banner workspace' },
			];
		}
		return [
			{ label: __('editorial planning workspace', 'npcink-toolbox'), query: 'editorial planning workspace desk' },
			{ label: __('AI analytics dashboard', 'npcink-toolbox'), query: 'analytics dashboard workspace' },
			{ label: __('search strategy concept', 'npcink-toolbox'), query: 'search strategy planning workspace' },
		];
	}

	function imageSuggestionLabel(suggestion) {
		if (suggestion && typeof suggestion === 'object') {
			return String(suggestion.label || suggestion.query || '').trim();
		}
		return String(suggestion || '').trim();
	}

	function imageSuggestionQuery(suggestion) {
		if (suggestion && typeof suggestion === 'object') {
			return String(suggestion.query || suggestion.label || '').trim();
		}
		return String(suggestion || '').trim();
	}

	function imageAutoFallbackQueries(payload, originalQuery, picker) {
		const original = String(originalQuery || '').trim().toLowerCase();
		const suggestions = extractImageSearchSuggestions(payload)
			.map((suggestion) => ({ label: suggestion, query: suggestion }))
			.concat(fallbackImageSearchSuggestions(picker));
		const seen = {};
		const queries = [];
		for (let index = 0; index < suggestions.length; index += 1) {
			const query = imageSuggestionQuery(suggestions[index]);
			const key = query.toLowerCase();
			if (query && key !== original && !seen[key]) {
				seen[key] = true;
				queries.push(query);
			}
		}
		return queries.slice(0, 4);
	}

	function imageAutoFallbackQuery(payload, originalQuery, picker) {
		return imageAutoFallbackQueries(payload, originalQuery, picker)[0] || '';
	}

	function renderImageSuggestionButtons(suggestions, onUseSuggestion) {
		if (!Array.isArray(suggestions) || !suggestions.length || typeof onUseSuggestion !== 'function') {
			return null;
		}
		return createElement(
			'div',
			{ className: 'npcink-toolbox-editor-support__query-chips' },
			suggestions.map((suggestion, index) => createElement(
				'button',
				{
					key: String(index) + '-' + imageSuggestionQuery(suggestion),
					type: 'button',
					onClick: () => onUseSuggestion(imageSuggestionQuery(suggestion)),
				},
				imageSuggestionLabel(suggestion)
			))
		);
	}

	function imageResultSource(payload) {
		if (!payload || typeof payload !== 'object') {
			return {};
		}
		return payload.sections && payload.sections.image_candidates ? payload.sections.image_candidates : payload;
	}

	function renderImageResultSummary(payload, images, queryLabel, selectedImage) {
		if (!payload || typeof payload !== 'object') {
			return null;
		}
		const source = imageResultSource(payload);
		const imageCount = Array.isArray(images) ? images.length : 0;
		const sourceLabel = imageResultSourceLabel(payload);
		const items = [
			queryLabel ? __('Query: ', 'npcink-toolbox') + queryLabel : '',
			sourceLabel ? __('Source: ', 'npcink-toolbox') + sourceLabel : '',
			imageCount ? __('Candidates: ', 'npcink-toolbox') + String(imageCount) : '',
			selectedImage ? __('Selected: ', 'npcink-toolbox') + truncateText(imageTitle(selectedImage), 52) : '',
		].filter(Boolean);
		if (!items.length) {
			return null;
		}
		return createElement(
			'div',
			{ className: 'npcink-toolbox-editor-support__image-summary' },
			items.map((item, index) => createElement('span', { key: String(index) }, item))
		);
	}

	function renderImageCloudDetails(payload, onUsePrompt, options) {
		const settings = Object.assign({
			mode: 'source',
			hasImages: false,
		}, options || {});
		const visualBrief = settings.mode === 'source' && settings.hasImages
			? null
			: renderImageVisualBrief(payload, onUsePrompt, {
				actionLabel: settings.mode === 'generate' ? __('Generate this direction', 'npcink-toolbox') : __('Search this direction', 'npcink-toolbox'),
				heading: settings.mode === 'generate' ? __('Generation direction reference', 'npcink-toolbox') : __('Image direction reference', 'npcink-toolbox'),
			});
		const diagnostics = renderImageDiagnostics(payload);
		if (!visualBrief && !diagnostics) {
			return null;
		}
		const summaryLabel = visualBrief
			? (settings.mode === 'generate' ? __('Generation directions', 'npcink-toolbox') : __('Try another image direction', 'npcink-toolbox'))
			: __('Cloud details', 'npcink-toolbox');
		return createElement(
			'details',
			{ className: 'npcink-toolbox-editor-support__cloud-details' },
			createElement('summary', null, summaryLabel),
			visualBrief ? createElement(
				'div',
				{ className: 'npcink-toolbox-editor-support__image-cloud-context' },
				visualBrief
			) : null,
			diagnostics
		);
	}

	function firstImagePromptCandidate(payload) {
		const source = imageResultSource(payload || {});
		const handoff = source.ai_generation_handoff && typeof source.ai_generation_handoff === 'object' ? source.ai_generation_handoff : {};
		const candidates = []
			.concat(Array.isArray(source.prompt_candidates) ? source.prompt_candidates : [])
			.concat(Array.isArray(handoff.prompt_candidates) ? handoff.prompt_candidates : []);
		for (let index = 0; index < candidates.length; index += 1) {
			const candidate = candidates[index];
			const prompt = typeof candidate === 'string'
				? candidate
				: (candidate && typeof candidate === 'object' ? candidate.prompt : '');
			const value = String(prompt || '').trim();
			if (value) {
				return value;
			}
		}
		return '';
	}

	function renderImageCandidateCards(images, payload, selectedImage, onSelectImage, onPreviewImage, onUseSuggestion, picker) {
		if (!Array.isArray(images) || !images.length) {
			const source = imageResultSource(payload || {});
			const status = String(source.status || '').toLowerCase();
			const message = String(source.message || '');
			const hasProviderErrors = Array.isArray(source.provider_errors) && source.provider_errors.length > 0;
			const isCloudError = status === 'error' || hasProviderErrors || message.toLowerCase().indexOf('connect npcink cloud') >= 0 || message.toLowerCase().indexOf('cloud') >= 0;
			const cloudSuggestions = extractImageSearchSuggestions(payload);
			const suggestions = cloudSuggestions.length ? cloudSuggestions : fallbackImageSearchSuggestions(picker);
			return createElement(
				'div',
				{ className: 'npcink-toolbox-editor-support__empty' },
				createElement('strong', null, isCloudError ? __('Cloud image search is unavailable.', 'npcink-toolbox') : __('No image-source candidates found.', 'npcink-toolbox')),
				createElement('span', null, isCloudError ? __('Connect or verify Npcink Cloud Addon, then run image-source search again.', 'npcink-toolbox') : __('Try one of these shorter visual searches, or enter another concrete scene.', 'npcink-toolbox')),
				isCloudError ? null : renderImageSuggestionButtons(suggestions, onUseSuggestion)
			);
		}

		return createElement(
			'div',
			{ className: 'npcink-toolbox-editor-support__image-grid' },
				images.slice(0, 9).map((image, index) => {
					const previewUrl = imagePreviewUrl(image);
					const fullPreviewUrl = imageFullPreviewUrl(image);
					const candidateKey = imageStableKey(image, index);
					const selected = selectedImage && imageStableKey(selectedImage, index) === candidateKey;

					return createElement(
						'article',
						{
							className: 'npcink-toolbox-editor-support__image-card' + (selected ? ' is-selected' : ''),
							key: String(index) + '-' + candidateKey,
							role: 'button',
							tabIndex: 0,
							'aria-pressed': selected ? 'true' : 'false',
							'aria-label': __('Select image candidate', 'npcink-toolbox') + ' ' + String(index + 1),
							onClick: () => onSelectImage(image),
							onKeyDown: (event) => {
								if (event.key === 'Enter' || event.key === ' ') {
									event.preventDefault();
									onSelectImage(image);
								}
							},
						},
						selected ? createElement('span', { className: 'npcink-toolbox-editor-support__selected-badge', 'aria-hidden': 'true' }, '✓') : null,
						previewUrl
						? createElement('img', {
							className: 'npcink-toolbox-editor-support__image-thumb',
							src: previewUrl,
							alt: image.alt_description || image.description || '',
							loading: 'lazy',
							})
							: createElement('div', { className: 'npcink-toolbox-editor-support__image-placeholder' }, __('No preview', 'npcink-toolbox')),
						fullPreviewUrl ? createElement(
							'button',
							{
								type: 'button',
								className: 'npcink-toolbox-editor-support__image-zoom',
								'aria-label': __('Preview image larger', 'npcink-toolbox'),
								onClick: (event) => {
									event.preventDefault();
									event.stopPropagation();
									onPreviewImage(image);
								},
							},
							createElement('span', { className: 'dashicons dashicons-search', 'aria-hidden': 'true' })
						) : null
					);
				})
			);
		}

	function renderImagePreviewLightbox(image, onClose) {
		if (!image) {
			return null;
		}
		const previewUrl = imageFullPreviewUrl(image);
		if (!previewUrl) {
			return null;
		}
		return createElement(
			'div',
			{
				className: 'npcink-toolbox-editor-support__image-lightbox',
				role: 'dialog',
				'aria-modal': 'true',
				'aria-label': __('Image preview', 'npcink-toolbox'),
				onClick: onClose,
			},
			createElement(
				'div',
				{
					className: 'npcink-toolbox-editor-support__image-lightbox-frame',
					onClick: (event) => event.stopPropagation(),
				},
				createElement(
					'button',
					{
						type: 'button',
						className: 'npcink-toolbox-editor-support__image-lightbox-close',
						'aria-label': __('Close preview', 'npcink-toolbox'),
						onClick: onClose,
					},
					'×'
				),
				createElement('img', {
					src: previewUrl,
					alt: image.alt_description || image.description || imageTitle(image),
				})
			)
		);
	}

	function renderImagePreviewModal(image, onClose) {
		const lightbox = renderImagePreviewLightbox(image, onClose);
		if (!lightbox) {
			return null;
		}
		return createElement(
			Modal,
			{
				title: __('Image preview', 'npcink-toolbox'),
				onRequestClose: onClose,
				className: 'npcink-toolbox-editor-support__image-preview-modal',
			},
			lightbox
		);
	}

	function adoptionCorePayload(result) {
		if (!result || typeof result !== 'object') {
			return {};
		}
		return result.core && typeof result.core === 'object' ? result.core : {};
	}

	function executionSucceeded(value) {
		if (!value || typeof value !== 'object') {
			return false;
		}
		const status = String(value.status || value.proposal_status || (value.execution && value.execution.status) || '').toLowerCase();
		if (value.success === true || value.executed || value.write_executed || ['executed', 'completed', 'complete', 'succeeded', 'success'].indexOf(status) >= 0) {
			return true;
		}
		const executedCount = parseInt(value.executed_count || '0', 10);
		const failedCount = parseInt(value.failed_count || '0', 10);
		return executedCount > 0 && failedCount === 0;
	}

	function adoptionStatus(result) {
		const core = adoptionCorePayload(result);
		const status = String(core.status || core.proposal_status || (core.proposal && core.proposal.status) || '').toLowerCase();
		if (executionSucceeded(core) || executionSucceeded(core.execution)) {
			return 'adopted';
		}
		if (core.execution_error || result.core_error) {
			return 'needs_review';
		}
		if (extractProposalId(core, 0) || status) {
			return 'submitted';
		}
		return 'prepared';
	}

	function findAttachmentId(value, depth) {
		if (!value || depth > 5) {
			return 0;
		}
		if (typeof value !== 'object') {
			return 0;
		}
		const direct = parseInt(value.attachment_id || value.media_id || value.featured_media || '0', 10);
		if (direct > 0) {
			return direct;
		}
		const keys = Object.keys(value);
		for (let index = 0; index < keys.length; index += 1) {
			const found = findAttachmentId(value[keys[index]], depth + 1);
			if (found > 0) {
				return found;
			}
		}
		return 0;
	}

	function syncFeaturedMediaFromCore(core) {
		const attachmentId = findAttachmentId(core, 0);
		if (attachmentId > 0 && data.dispatch) {
			const editorDispatch = data.dispatch('core/editor');
			if (editorDispatch && editorDispatch.editPost) {
				editorDispatch.editPost({ featured_media: attachmentId });
			}
		}
	}

	function renderAdoptionResult(result) {
		if (!result || typeof result !== 'object') {
			return null;
		}

		const status = adoptionStatus(result);
		const core = adoptionCorePayload(result);
		const proposalId = extractProposalId(core, 0);
		const localConsent = Boolean(result.local_consent);
		const mediaImportOnly = result.adoption_target === 'media_import';
		const title = status === 'adopted'
			? (mediaImportOnly ? __('Media imported', 'npcink-toolbox') : __('Featured image adopted', 'npcink-toolbox'))
			: (status === 'submitted' ? __('Adoption request sent', 'npcink-toolbox') : __('Automatic adoption not completed', 'npcink-toolbox'));
		const summary = status === 'adopted'
			? (localConsent
				? __('Existing media is now the featured image.', 'npcink-toolbox')
				: (mediaImportOnly ? __('Media is imported and ready in the media library.', 'npcink-toolbox') : __('Image is imported and applied as the featured image.', 'npcink-toolbox')))
			: (status === 'submitted'
				? __('Request was sent to Core. Open Core to check the current status.', 'npcink-toolbox')
				: __('Automatic completion was unavailable. Open Core to review the proposal.', 'npcink-toolbox'));
		const coreLink = proposalId && config.coreAdminUrl
			? createElement(
				'a',
				{
					className: 'npcink-toolbox-editor-support__core-record-link',
					href: config.coreAdminUrl + '&proposal_id=' + encodeURIComponent(proposalId),
					target: '_blank',
					rel: 'noreferrer'
				},
				__('Open Core record', 'npcink-toolbox')
			)
			: null;
		return createElement(
			'div',
			{ className: 'npcink-toolbox-editor-support__adoption-result is-' + status },
			createElement('strong', null, title),
			createElement('span', null, summary),
			coreLink ? coreLink : null,
			proposalId && !coreLink ? createElement('small', null, __('Proposal: ', 'npcink-toolbox') + String(proposalId)) : null
		);
	}

	function imageDimensionLabel(image) {
		const width = parseInt(image.width || image.image_width || '0', 10);
		const height = parseInt(image.height || image.image_height || '0', 10);
		if (width > 0 && height > 0) {
			return String(width) + ' × ' + String(height);
		}
		return '';
	}

	function imageFormatLabel(image) {
		const explicit = image.format || image.mime_type || image.mime || '';
		if (explicit) {
			return String(explicit).replace(/^image\//, '').toUpperCase();
		}
		return filenameExtensionFromUrl(imageDownloadUrl(image)).toUpperCase();
	}

	function renderInfoRow(label, value, key) {
		if (!value) {
			return null;
		}
		return createElement(
			'div',
			{ className: 'npcink-toolbox-editor-support__info-row', key },
			createElement('span', null, label),
			createElement('strong', null, value)
		);
	}

	function renderEditorImageFeedbackControls(selectedImage, feedbackRunning, feedbackStatus, onSubmitFeedback) {
		if (!selectedImage || typeof onSubmitFeedback !== 'function') {
			return null;
		}
		const options = [
			{ label: __('Useful', 'npcink-toolbox'), outcome: 'accepted', labels: ['evidence_useful', 'operator_confidence_high'] },
			{ label: __('Adoption planned', 'npcink-toolbox'), outcome: 'accepted', labels: ['evidence_useful', 'good_but_needs_human_draft'] },
			{ label: __('Low quality', 'npcink-toolbox'), outcome: 'rejected', labels: ['visual_quality_low', 'operator_confidence_low'] },
			{ label: __('Source risk', 'npcink-toolbox'), outcome: 'rejected', labels: ['source_or_license_risk', 'operator_confidence_low'] },
			{ label: __('Not relevant', 'npcink-toolbox'), outcome: 'rejected', labels: ['not_relevant_to_site'] },
		];
		return createElement(
			'details',
			{ className: 'npcink-toolbox-editor-support__image-feedback', 'data-toolbox-editor-image-agent-feedback': 'true' },
			createElement('summary', null, __('Report image issue', 'npcink-toolbox')),
			createElement(
				'div',
				{ className: 'npcink-toolbox-editor-support__image-feedback-actions' },
				options.map((option) => createElement(
					Button,
					{
						key: option.label,
						type: 'button',
						variant: 'secondary',
						isSmall: true,
						className: 'npcink-toolbox-editor-support__feedback-chip',
						isBusy: feedbackRunning === option.label,
						disabled: Boolean(feedbackRunning),
						onClick: () => onSubmitFeedback(option),
					},
					option.label
				))
			),
			feedbackStatus ? createElement(Notice, { status: feedbackStatus.status, isDismissible: false }, feedbackStatus.message) : null
		);
	}

	function renderContentSupportFeedbackControls(payload, controls) {
		if (!payload || !controls || typeof controls.submitFeedback !== 'function') {
			return null;
		}
		const options = [
			{ label: __('Useful', 'npcink-toolbox'), outcome: 'accepted', labels: ['evidence_useful', 'operator_confidence_high'] },
			{ label: __('Useful after edits', 'npcink-toolbox'), outcome: 'edited_before_accept', labels: ['evidence_useful', 'good_but_needs_human_draft'] },
			{ label: __('Evidence weak', 'npcink-toolbox'), outcome: 'rejected', labels: ['evidence_weak', 'operator_confidence_low'] },
			{ label: __('Wrong intent', 'npcink-toolbox'), outcome: 'rejected', labels: ['wrong_intent'] },
			{ label: __('Too generic', 'npcink-toolbox'), outcome: 'rejected', labels: ['too_generic'] },
			{ label: __('Missing context', 'npcink-toolbox'), outcome: 'rejected', labels: ['missing_context', 'operator_confidence_low'] },
			{ label: __('Not relevant', 'npcink-toolbox'), outcome: 'rejected', labels: ['not_relevant_to_site'] },
		];
		return createElement(
			'details',
			{ className: 'npcink-toolbox-editor-support__content-feedback', 'data-toolbox-editor-content-agent-feedback': 'true' },
			createElement('summary', null, __('Report issue', 'npcink-toolbox')),
			createElement(
				'div',
				{ className: 'npcink-toolbox-editor-support__content-feedback-actions' },
				options.map((option) => createElement(
					Button,
					{
						key: option.label,
						type: 'button',
						variant: 'secondary',
						isBusy: controls.feedbackRunning === option.label,
						disabled: Boolean(controls.feedbackRunning),
						onClick: () => controls.submitFeedback(option),
					},
					option.label
				))
			),
			createElement('small', null, __('Optional issue report only. Toolbox also sends metadata-only interaction signals; Core approval, media import, SEO fields, and WordPress writes stay local.', 'npcink-toolbox')),
			controls.feedbackStatus ? createElement(Notice, { status: controls.feedbackStatus.status, isDismissible: false }, controls.feedbackStatus.message) : null
		);
	}

	function renderAiImageRegenerationControls(selectedImage, generationRunning, onRegenerate) {
		if (!imageIsAiGenerated(selectedImage) || typeof onRegenerate !== 'function') {
			return null;
		}
		const options = [
			{ mode: 'more_specific', label: __('More specific', 'npcink-toolbox') },
			{ mode: 'simpler', label: __('Simpler', 'npcink-toolbox') },
			{ mode: 'more_editorial', label: __('More editorial', 'npcink-toolbox') },
		];
		return createElement(
			'div',
			{ className: 'npcink-toolbox-editor-support__image-regenerate', 'data-toolbox-editor-ai-image-regenerate': 'true' },
			createElement('h3', null, __('Regenerate AI image', 'npcink-toolbox')),
			createElement('small', null, __('Keeps the selected paragraph meaning and creates a revised candidate through the existing AI image runtime.', 'npcink-toolbox')),
			createElement(
				'div',
				{ className: 'npcink-toolbox-editor-support__image-regenerate-actions' },
				options.map((option) => createElement(
					Button,
					{
						key: option.mode,
						type: 'button',
						variant: 'secondary',
						isBusy: generationRunning === option.mode,
						disabled: Boolean(generationRunning),
						onClick: () => onRegenerate(option.mode),
					},
					option.label
				))
			)
		);
	}

	function readableItemText(value, fallback) {
		if (value === null || value === undefined || value === '') {
			return fallback || '';
		}
		if (Array.isArray(value)) {
			return value
				.map((item) => readableItemText(item, ''))
				.filter(Boolean)
				.join(', ');
		}
		if (typeof value === 'object') {
			const direct = value.name || value.title || value.label || value.question || value.answer || value.text || value.value || value.summary || value.excerpt;
			if (direct) {
				return readableItemText(direct, fallback);
			}
			const pairs = Object.keys(value).slice(0, 6).map((key) => {
				const itemText = readableItemText(value[key], '');
				return itemText ? formatMetaLabel(key) + ': ' + itemText : '';
			}).filter(Boolean);
			return pairs.length ? truncateText(pairs.join(' · '), 180) : (fallback || '');
		}
		const text = String(value);
		return /^[a-z0-9_-]+$/i.test(text) ? formatMetaLabel(text) : text;
	}

	function renderSelectedImagePanel(selectedImage, seoFields, adoptionRunning, adoptionAction, adoptionResult, adoptionError, picker, onSeoFieldChange, onAdoptFeatured, onImportOnly, onSelectOnly, feedbackRunning, feedbackStatus, onSubmitFeedback, regenerationRunning, onRegenerate) {
		const activePicker = normalizeImagePickerOptions(picker || {});
		const paragraphMode = activePicker.mode === 'paragraph';
		const selectOnlyMode = activePicker.adoptionMode === 'select_only';
		if (!selectedImage) {
			return null;
		}

		const seo = seoFields || {};
		const sourceUrl = imageSourceUrl(selectedImage);
		const attributionLabel = imageAttributionLabel(selectedImage);
		const sourceDetailRows = [
			renderInfoRow(__('Source', 'npcink-toolbox'), imageCandidateSourceLabel(selectedImage), 'source'),
			renderInfoRow(__('Review', 'npcink-toolbox'), selectedImage.license_review_status ? formatMetaLabel(selectedImage.license_review_status) : '', 'review'),
		].filter(Boolean);
		return createElement(
			'aside',
			{ className: 'npcink-toolbox-editor-support__selected-image' },
			createElement(
				'div',
				{ className: 'npcink-toolbox-editor-support__selected-actions' + (paragraphMode || selectOnlyMode ? ' is-single' : '') },
				selectOnlyMode ? createElement(
					Button,
					{
						type: 'button',
						variant: 'primary',
						className: 'npcink-toolbox-editor-support__primary-image-action',
						disabled: adoptionRunning,
						onClick: onSelectOnly,
					},
					__('Use selected image', 'npcink-toolbox')
				) : paragraphMode ? createElement(
					Button,
					{
						type: 'button',
						variant: 'primary',
						className: 'npcink-toolbox-editor-support__primary-image-action',
						isBusy: adoptionAction === 'import',
						disabled: adoptionRunning,
						onClick: onImportOnly,
					},
					adoptionAction === 'import' ? __('Importing media', 'npcink-toolbox') : __('Import paragraph image', 'npcink-toolbox')
				) : createElement(
					Button,
					{
						type: 'button',
						variant: 'primary',
						className: 'npcink-toolbox-editor-support__primary-image-action',
						isBusy: adoptionAction === 'adopt',
						disabled: adoptionRunning,
						onClick: onAdoptFeatured,
					},
					adoptionAction === 'adopt' ? __('Adopting image', 'npcink-toolbox') : __('Adopt', 'npcink-toolbox')
				),
				paragraphMode ? null : createElement(
					Button,
					{
						type: 'button',
						variant: 'secondary',
						className: 'npcink-toolbox-editor-support__secondary-image-action',
						isBusy: adoptionAction === 'import',
						disabled: adoptionRunning,
						onClick: onImportOnly,
					},
					adoptionAction === 'import' ? __('Importing media', 'npcink-toolbox') : __('Import only', 'npcink-toolbox')
				)
			),
			adoptionError ? createElement(Notice, { status: 'error', isDismissible: false }, adoptionError) : null,
			renderAdoptionResult(adoptionResult),
			renderAiImageRegenerationControls(selectedImage, regenerationRunning, onRegenerate),
			createElement(
				'div',
				{ className: 'npcink-toolbox-editor-support__seo-fields' },
				createElement(
					'details',
					{ className: 'npcink-toolbox-editor-support__image-details' },
					createElement('summary', null, __('More SEO fields', 'npcink-toolbox')),
					createElement(TextareaControl, {
						label: __('Alt text', 'npcink-toolbox'),
						value: seo.alt || '',
						disabled: adoptionRunning,
						__next40pxDefaultSize: true,
						onChange: (value) => onSeoFieldChange('alt', value),
					}),
					createElement(TextControl, {
						label: __('Title', 'npcink-toolbox'),
						value: seo.title || '',
						disabled: adoptionRunning,
						__next40pxDefaultSize: true,
						onChange: (value) => onSeoFieldChange('title', value),
					}),
					createElement(TextareaControl, {
						label: __('Description', 'npcink-toolbox'),
						value: seo.description || '',
						disabled: adoptionRunning,
						__next40pxDefaultSize: true,
						onChange: (value) => onSeoFieldChange('description', value),
					})
				)
			),
			createElement(
				'details',
				{ className: 'npcink-toolbox-editor-support__image-details' },
				createElement('summary', null, __('Source details', 'npcink-toolbox')),
				sourceDetailRows.length ? createElement(
					'div',
					{ className: 'npcink-toolbox-editor-support__info-list' },
					sourceDetailRows
				) : null,
				sourceUrl ? createElement('a', { href: sourceUrl, target: '_blank', rel: 'noreferrer' }, __('Open source', 'npcink-toolbox')) : null,
				attributionLabel ? createElement('small', null, attributionLabel) : null
			),
			renderEditorImageFeedbackControls(selectedImage, feedbackRunning, feedbackStatus, onSubmitFeedback)
		);
	}

	function renderImageDiagnostics(payload) {
		if (!payload || typeof payload !== 'object') {
			return null;
		}

		const source = payload.sections && payload.sections.image_candidates ? payload.sections.image_candidates : payload;
		const sourceLabel = imageResultSourceLabel(payload, true);
		const cloudMessage = source.message ? String(source.message) : '';
		const displayMessage = cloudMessage.toLowerCase().indexOf('connect npcink cloud') >= 0
			? __('Npcink Cloud Addon is not connected or not configured for managed image-source search.', 'npcink-toolbox')
			: cloudMessage;
		const status = String(source.status || '').toLowerCase();
		const hasProviderErrors = Array.isArray(source.provider_errors) && source.provider_errors.length > 0;
		const isProblem = status === 'error' || status === 'warning' || hasProviderErrors;
		const isEmptyResult = source.result_count === 0 || source.candidate_source_count === 0;
		const meaningfulMessage = displayMessage && displayMessage.toLowerCase() !== 'runtime executed' ? displayMessage : '';
		if (!isProblem && !isEmptyResult && !meaningfulMessage) {
			return null;
		}
		const diagnostics = [
			(isProblem || isEmptyResult) && source.status ? __('Cloud status: ', 'npcink-toolbox') + formatMetaLabel(source.status) : '',
			(isProblem || isEmptyResult) && sourceLabel ? __('Source: ', 'npcink-toolbox') + sourceLabel : '',
			(isProblem || isEmptyResult) && source.result_count !== undefined ? __('Shown: ', 'npcink-toolbox') + String(source.result_count) : '',
			(isProblem || isEmptyResult) && source.candidate_source_count !== undefined ? __('Received: ', 'npcink-toolbox') + String(source.candidate_source_count) : '',
			meaningfulMessage ? __('Cloud note: ', 'npcink-toolbox') + meaningfulMessage : '',
		].filter(Boolean);

		if (!diagnostics.length && (!Array.isArray(source.provider_errors) || !source.provider_errors.length)) {
			return null;
		}

		return createElement(
			'div',
			{ className: 'npcink-toolbox-editor-support__diagnostics' },
			diagnostics.map((item, index) => createElement('span', { key: String(index) }, item)),
			Array.isArray(source.provider_errors) && source.provider_errors.length
				? createElement('span', null, __('Provider errors were reported by Cloud.', 'npcink-toolbox'))
				: null
		);
	}

	function hasCjkText(value) {
		return /[\u3400-\u9fff]/.test(String(value || ''));
	}

	function firstLocalizedText(source, keys) {
		const target = source && typeof source === 'object' ? source : {};
		for (let index = 0; index < keys.length; index += 1) {
			const value = String(target[keys[index]] || '').trim();
			if (value) {
				return value;
			}
		}
		return '';
	}

	function imageDirectionTypeLabel(type) {
		const key = String(type || '').trim().toLowerCase().replace(/\s+/g, '_').replace(/-+/g, '_');
		const labels = {
			editorial_scene: __('Editorial scene direction', 'npcink-toolbox'),
			conceptual_metaphor: __('Conceptual metaphor direction', 'npcink-toolbox'),
			concept_metaphor: __('Conceptual metaphor direction', 'npcink-toolbox'),
			workspace_detail: __('Workspace detail direction', 'npcink-toolbox'),
			workflow_detail: __('Workflow detail direction', 'npcink-toolbox'),
			hero_editorial: __('Featured image direction', 'npcink-toolbox'),
			product_context: __('Product context direction', 'npcink-toolbox'),
			search_workflow: __('Search workflow direction', 'npcink-toolbox'),
		};
		return labels[key] || '';
	}

	function localizedPromptCandidateDisplay(candidate) {
		const label = firstLocalizedText(candidate, ['localized_label', 'localized_title', 'label_zh', 'title_zh', 'zh_label', 'zh_title']);
		const strategy = firstLocalizedText(candidate, ['localized_strategy', 'localized_visual_strategy', 'strategy_zh', 'visual_strategy_zh', 'zh_strategy']);
		const reason = firstLocalizedText(candidate, ['localized_reason', 'reason_zh', 'zh_reason', 'localized_summary', 'summary_zh']);
		const mappedLabel = imageDirectionTypeLabel(candidate.direction_type);
		return {
			label: label || mappedLabel || __('Direction option', 'npcink-toolbox'),
			strategy: strategy || (label ? mappedLabel : ''),
			reason,
		};
	}

	function compactPromptDirectionLabel(label) {
		return String(label || '')
			.replace(/方向$/u, '')
			.replace(/\sdirection$/i, '')
			.trim();
	}

	function localizedVisualBriefIntent(brief) {
		const localized = firstLocalizedText(brief, ['localized_visual_intent', 'visual_intent_zh', 'zh_visual_intent', 'localized_summary', 'summary_zh']);
		if (localized) {
			return localized;
		}
		const intent = String(brief && brief.visual_intent ? brief.visual_intent : '').trim();
		return hasCjkText(intent) ? intent : '';
	}

	function renderImageVisualBrief(payload, onUsePrompt, options) {
		if (!payload || typeof payload !== 'object') {
			return null;
		}
		const settings = Object.assign({
			actionLabel: __('Use direction', 'npcink-toolbox'),
			heading: __('Image direction reference', 'npcink-toolbox'),
			headingAction: null,
			hideContext: false,
			compactSelector: false,
		}, options || {});

		const source = payload.sections && payload.sections.image_candidates ? payload.sections.image_candidates : payload;
		const brief = source.visual_brief && typeof source.visual_brief === 'object' ? source.visual_brief : {};
		const handoff = source.ai_generation_handoff && typeof source.ai_generation_handoff === 'object' ? source.ai_generation_handoff : {};
		const promptCandidates = []
			.concat(Array.isArray(source.prompt_candidates) ? source.prompt_candidates : [])
			.concat(Array.isArray(handoff.prompt_candidates) ? handoff.prompt_candidates : [])
			.map((candidate, index) => {
				if (typeof candidate === 'string') {
					return { id: String(index), label: candidate, prompt: candidate };
				}
				return candidate && typeof candidate === 'object' ? candidate : null;
			})
			.filter((candidate) => candidate && String(candidate.prompt || '').trim())
			.slice(0, 3);
		const visualIntent = localizedVisualBriefIntent(brief);
		const chips = []
			.concat(brief.primary_query ? [brief.primary_query] : [])
			.concat(Array.isArray(brief.alternate_queries) ? brief.alternate_queries.slice(0, 4) : [])
			.filter(Boolean)
			.filter((chip, index, all) => all.indexOf(chip) === index)
			.slice(0, 3);
		if (!chips.length && !promptCandidates.length && !visualIntent) {
			return null;
		}

		return createElement(
			'div',
			{ className: 'npcink-toolbox-editor-support__visual-brief' + (settings.compactSelector ? ' is-compact-selector' : '') },
			createElement(
				'div',
				{ className: 'npcink-toolbox-editor-support__visual-brief-head' },
				createElement('strong', null, settings.heading),
				settings.headingAction
			),
			!settings.hideContext && visualIntent ? createElement('span', null, truncateText(visualIntent, 160)) : null,
			!settings.hideContext && chips.length ? createElement(
				'div',
				{ className: 'npcink-toolbox-editor-support__query-chips' },
				chips.map((chip, index) => createElement('span', { key: String(index) }, chip))
			) : null,
			promptCandidates.length ? createElement(
				'div',
				{ className: 'npcink-toolbox-editor-support__prompt-candidates' },
				promptCandidates.map((candidate, index) => {
					const prompt = String(candidate.prompt || '');
					const usePrompt = (event) => {
						if (event && event.preventDefault) {
							event.preventDefault();
						}
						if (event && event.stopPropagation) {
							event.stopPropagation();
						}
						if (onUsePrompt) {
							onUsePrompt(prompt);
						}
					};
					const display = localizedPromptCandidateDisplay(candidate);
					return createElement(
						'button',
						{
							key: String(candidate.id || index),
							className: 'npcink-toolbox-editor-support__prompt-card',
							type: 'button',
							'data-toolbox-ai-prompt-direction': 'true',
							onClick: usePrompt,
						},
						createElement('strong', null, truncateText(settings.compactSelector ? compactPromptDirectionLabel(display.label) : display.label, 72)),
						!settings.compactSelector && display.strategy ? createElement('span', null, truncateText(display.strategy, 120)) : null,
						!settings.compactSelector && display.reason ? createElement('small', null, truncateText(display.reason, 180)) : null
					);
				})
			) : null
		);
	}

	function extractKnowledgeItems(section) {
		if (!section || typeof section !== 'object') {
			return [];
		}
		return section.results || section.items || [];
	}

	function writingSupportKey(value) {
		return String(value || '').trim().toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
	}

	function writingSupportRoleLabel(value) {
		const labels = {
			background_or_gap_signal: __('Background or gap signal', 'npcink-toolbox'),
			background: __('Background reference', 'npcink-toolbox'),
			gap_signal: __('Content gap signal', 'npcink-toolbox'),
			overlap_signal: __('Overlap signal', 'npcink-toolbox'),
			internal_reference: __('Internal reference', 'npcink-toolbox'),
			source_evidence: __('Source evidence', 'npcink-toolbox'),
		};
		return labels[writingSupportKey(value)] || formatMetaLabel(value);
	}

	function writingSupportTaskLabel(value) {
		const labels = {
			verify_facts_against_source: __('Verify facts against the source', 'npcink-toolbox'),
			decide_expand_existing_or_write_new_coverage: __('Decide whether to expand older coverage or write a new angle', 'npcink-toolbox'),
			collect_internal_link_and_media_candidates: __('Collect internal link and media candidates', 'npcink-toolbox'),
			compare_overlap_before_publishing: __('Compare overlap before publishing', 'npcink-toolbox'),
			add_to_outline_notes: __('Add to outline notes', 'npcink-toolbox'),
		};
		return labels[writingSupportKey(value)] || formatMetaLabel(value);
	}

	function writingSupportReasonText(value) {
		const text = readableItemText(value, '');
		const lower = text.toLowerCase();
		if (lower.indexOf('indexed passage') >= 0 && lower.indexOf('without becoming an article draft') >= 0) {
			return __('This looks relevant to the draft. Use it to avoid repeating old coverage or to add a clearer new angle.', 'npcink-toolbox');
		}
		return text;
	}

	function extractWritingSupportItems(section) {
		return extractKnowledgeItems(section).map((item) => {
			const support = item && item.writing_support && typeof item.writing_support === 'object' ? item.writing_support : {};
			const evidence = support.evidence_source && typeof support.evidence_source === 'object' ? support.evidence_source : {};
			const tasks = Array.isArray(support.pre_draft_tasks) ? support.pre_draft_tasks.map(writingSupportTaskLabel).join('; ') : '';
			const sourceId = item && (item.post_id || item.id || item.source_id) ? String(item.post_id || item.id || item.source_id) : '';
			const title = evidence.title || item.title || __('Untitled existing post', 'npcink-toolbox');
			return {
				name: sprintf(__('Related existing post: %s', 'npcink-toolbox'), title),
				detail: [
					__('What to decide: does this draft repeat it, extend it, or need a different angle?', 'npcink-toolbox'),
					support.source_role ? __('Why it appeared: ', 'npcink-toolbox') + writingSupportRoleLabel(support.source_role) : '',
					tasks ? __('Do next: ', 'npcink-toolbox') + tasks : '',
					__('After drafting: run internal links only if you want to link to this post.', 'npcink-toolbox'),
					writingSupportReasonText(item.reason) ? __('Note: ', 'npcink-toolbox') + writingSupportReasonText(item.reason) : '',
				].filter(Boolean).join(' · '),
				evidence_refs: sourceId ? ['site_knowledge:' + sourceId] : ['site_knowledge:writing_support'],
				action_policy: 'operator_review_only_no_write',
				};
			});
	}

	function hostedWritingSupportItems(section) {
		if (!section || typeof section !== 'object') {
			return [];
		}
		if (Array.isArray(section.items) && section.items.length) {
			return section.items;
		}
		const status = String(section.status || '').toLowerCase();
		const message = readableItemText(section.message || section.error || section.error_message, '');
		if (message && ['error', 'failed', 'unavailable', 'blocked'].indexOf(status) >= 0) {
			return [{
				name: __('Hosted AI did not return suggestions', 'npcink-toolbox'),
				detail: message,
			}];
		}
		const output = hostedOutputObject(section);
		const outlineItems = outlineSuggestionItems(output);
		if (outlineItems.length) {
			return outlineItems;
		}
		const outputKeys = Object.keys(output);
		if (outputKeys.length) {
			return outputKeys.map((key) => ({
				name: formatMetaLabel(key),
				detail: readableItemText(output[key], ''),
			})).filter((item) => item.detail);
		}
		const result = section.result && typeof section.result === 'object' ? section.result : {};
		const outputText = section.output_text || section.text || result.output_text || result.text || result.content || (result.message && result.message.content) || '';
		return outputText ? [{
			name: __('Hosted AI suggestion', 'npcink-toolbox'),
			detail: outputText,
		}] : (message || status ? [{
			name: __('Hosted AI did not return suggestions', 'npcink-toolbox'),
			detail: message || __('Cloud status: ', 'npcink-toolbox') + formatMetaLabel(status),
		}] : []);
	}

	function hostedAiReturnedText(section) {
		if (!section || typeof section !== 'object') {
			return false;
		}
		const output = hostedOutputObject(section);
		if (Object.keys(output).length) {
			return true;
		}
		const result = section.result && typeof section.result === 'object' ? section.result : {};
		return Boolean(String(section.output_text || section.text || result.output_text || result.text || result.content || (result.message && result.message.content) || '').trim());
	}

	function hostedAiNoResultReason(section) {
		if (!section || typeof section !== 'object') {
			return '';
		}
		const fallbackReason = String(section.fallback_reason || '');
		if (fallbackReason === 'local_paragraph_check_after_hosted_ai_empty') {
			return __('Hosted AI reached the runtime but returned no paragraph-check text, so Toolbox showed the local no-rewrite fallback.', 'npcink-toolbox');
		}
		if (String(section.cloud_status || '').toLowerCase() === 'omitted') {
			return __('Cloud marked this runtime response as omitted. Review storage mode, provider call count, and replay status below before rerunning.', 'npcink-toolbox');
		}
		if (Number(section.cloud_provider_call_count || 0) === 0 && section.cloud_status) {
			return __('Cloud returned a runtime envelope, but no provider call was recorded for this request.', 'npcink-toolbox');
		}
		if (section.status || section.cloud_status) {
			return __('The runtime returned a status but no displayable suggestion text. Rerun after adjusting the request, or use the local/full-article checks.', 'npcink-toolbox');
		}
		return '';
	}

	function hostedAiDiagnosticItems(section) {
		if (!section || typeof section !== 'object') {
			return [];
		}
		const items = [];
		const providerExecution = readableItemText(section.provider_execution, '');
		const hostedStatus = readableItemText(section.hosted_ai_status || section.status, '');
		const cloudStatus = readableItemText(section.cloud_status, '');
		const storageMode = readableItemText(section.cloud_storage_mode, '');
		const dataClass = readableItemText(section.cloud_data_classification, '');
		const runId = readableItemText(section.cloud_run_id || section.run_id, '');
		const fallbackReason = readableItemText(section.fallback_reason, '');
		const fallbackSource = readableItemText(section.fallback_source, '');
		const providerCalls = section.cloud_provider_call_count;
		if (providerExecution) {
			items.push({ name: __('Runtime path', 'npcink-toolbox'), detail: formatMetaLabel(providerExecution) });
		}
		if (hostedStatus) {
			items.push({ name: __('Hosted status', 'npcink-toolbox'), detail: formatMetaLabel(hostedStatus) });
		}
		if (cloudStatus && cloudStatus !== hostedStatus) {
			items.push({ name: __('Cloud status', 'npcink-toolbox'), detail: formatMetaLabel(cloudStatus) });
		}
		if (storageMode || dataClass) {
			items.push({ name: __('Cloud storage', 'npcink-toolbox'), detail: [storageMode ? formatMetaLabel(storageMode) : '', dataClass ? formatMetaLabel(dataClass) : ''].filter(Boolean).join(' / ') });
		}
		if (providerCalls !== undefined && providerCalls !== null && providerCalls !== '') {
			items.push({ name: __('Provider calls', 'npcink-toolbox'), detail: String(Number(providerCalls || 0)) });
		}
		if (section.cloud_idempotent_replay) {
			items.push({ name: __('Replay status', 'npcink-toolbox'), detail: __('Idempotent replay', 'npcink-toolbox') });
		}
		if (fallbackReason || fallbackSource) {
			items.push({ name: __('Local fallback', 'npcink-toolbox'), detail: [formatMetaLabel(fallbackReason), formatMetaLabel(fallbackSource)].filter(Boolean).join(' / ') });
		}
		if (runId) {
			items.push({ name: __('Run id', 'npcink-toolbox'), detail: runId });
		}
		return items;
	}

	function renderHostedAiDiagnostics(section) {
		const items = hostedAiDiagnosticItems(section);
		if (!items.length) {
			return null;
		}
		const hasHostedText = hostedAiReturnedText(section);
		const reason = (!hasHostedText || section.fallback_reason || String(section.cloud_status || '').toLowerCase() === 'omitted') ? hostedAiNoResultReason(section) : '';
		const isHighlighted = Boolean(reason || section.fallback_reason || String(section.cloud_status || '').toLowerCase() === 'omitted');
		return createElement(
			'details',
			{ className: 'npcink-toolbox-editor-support__runtime-diagnostics', open: isHighlighted },
			createElement('summary', null, isHighlighted ? __('Why no AI text appeared', 'npcink-toolbox') : __('Runtime diagnostics', 'npcink-toolbox')),
			reason ? createElement('p', { className: 'npcink-toolbox-editor-support__muted' }, reason) : null,
			renderItems(items, __('No runtime diagnostics returned.', 'npcink-toolbox'))
		);
	}

	function outlineSuggestionItems(output) {
		const data = outlineSuggestionDataFromOutput(output);
		const items = [];
		if (data.workingTitle || data.readerPromise) {
			items.push({
				name: data.workingTitle ? __('Working title: ', 'npcink-toolbox') + data.workingTitle : __('Reader promise', 'npcink-toolbox'),
				detail: data.readerPromise,
			});
		}
		data.sections.forEach((section, index) => {
			items.push({
				name: section.heading || __('Section ', 'npcink-toolbox') + String(index + 1),
				detail: section.points.join(' · '),
			});
		});
		if (data.questions.length) {
			items.push({
				name: __('Missing source questions', 'npcink-toolbox'),
				detail: data.questions.join(' · '),
			});
		}
		return items;
	}

	function outlineSuggestionDataFromOutput(output) {
		const data = {
			workingTitle: '',
			readerPromise: '',
			assumptions: [],
			sections: [],
			questions: [],
		};
		if (!output || typeof output !== 'object' || Array.isArray(output)) {
			return data;
		}
		data.workingTitle = stripMarkdownLabel(readableItemText(output.working_title || output.title, ''));
		data.readerPromise = stripMarkdownLabel(readableItemText(output.reader_promise || output.promise, ''));
		data.assumptions = outlineTextList(output.assumptions_to_confirm || output.assumptions_to_verify || output.assumptions);
		const sections = Array.isArray(output.sections) ? output.sections : (Array.isArray(output.outline) ? output.outline : []);
		sections.forEach((section, index) => {
			if (section === null || section === undefined || section === '') {
				return;
			}
			const heading = typeof section === 'object'
				? readableItemText(section.heading || section.title || section.name || section.section, '')
				: readableItemText(section, '');
			const keyPoints = typeof section === 'object'
				? outlineTextList(section.key_points || section.points || section.bullets || section.detail || section.summary)
				: '';
			data.sections.push({
				heading: stripMarkdownLabel(heading) || __('Section ', 'npcink-toolbox') + String(index + 1),
				points: Array.isArray(keyPoints) ? keyPoints : outlineTextList(keyPoints),
			});
		});
		const questions = output.missing_source_questions || output.questions_to_answer || output.assumptions_to_verify;
		data.questions = outlineTextList(questions);
		return data;
	}

	function outlineSuggestionData(section) {
		const output = hostedOutputObject(section);
		const data = outlineSuggestionDataFromOutput(output);
		if (data.workingTitle || data.readerPromise || data.assumptions.length || data.sections.length || data.questions.length) {
			return data;
		}
		const result = section && section.result && typeof section.result === 'object' ? section.result : {};
		const outputText = section && (section.output_text || section.text || result.output_text || result.text || result.content || (result.message && result.message.content));
		return parseOutlineText(outputText);
	}

	function stripMarkdownLabel(value) {
		return String(value || '')
			.replace(/^#{1,6}\s*/, '')
			.replace(/^\*\*(.*?)\*\*$/, '$1')
			.trim();
	}

	function outlineTextList(value) {
		if (value === null || value === undefined || value === '') {
			return [];
		}
		if (Array.isArray(value)) {
			return value
				.map((item) => outlineTextList(item))
				.reduce((items, item) => items.concat(item), [])
				.map(stripMarkdownLabel)
				.filter(Boolean);
		}
		if (typeof value === 'object') {
			const direct = value.question || value.text || value.value || value.summary || value.detail || value.title || value.name;
			if (direct) {
				return outlineTextList(direct);
			}
			return Object.keys(value).map((key) => {
				const itemText = readableItemText(value[key], '');
				return itemText ? formatMetaLabel(key) + ': ' + itemText : '';
			}).filter(Boolean);
		}
		return String(value || '')
			.replace(/\r/g, '\n')
			.split(/\n+\s*(?:[-*•]\s*)?|\s+-\s+/)
			.map(stripMarkdownLabel)
			.filter(Boolean);
	}

	function parseOutlineText(value) {
		const data = {
			workingTitle: '',
			readerPromise: '',
			assumptions: [],
			sections: [],
			questions: [],
		};
		const raw = String(value || '').trim();
		if (!raw) {
			return data;
		}
		const prepared = raw
			.replace(/\r/g, '\n')
			.replace(/(^|\s)(#{2,3}\s+)/g, '\n$2')
			.replace(/\n{2,}/g, '\n')
			.trim();
		const headingPattern = /^(#{2,3})\s*([^\n]+)(?:\n([\s\S]*?))?(?=\n#{2,3}\s+|$)/gm;
		let match = headingPattern.exec(prepared);
		while (match) {
			const depth = match[1].length;
			const label = stripMarkdownLabel(match[2] || '');
			const body = String(match[3] || '').trim();
			const lower = label.toLowerCase();
			const contentAfter = (prefix) => stripMarkdownLabel(label.slice(prefix.length).replace(/^[:：\s-]+/, ''));
			if (depth === 2 && lower.indexOf('working title') === 0) {
				data.workingTitle = contentAfter('working title') || stripMarkdownLabel(body);
			} else if (depth === 2 && lower.indexOf('reader promise') === 0) {
				data.readerPromise = contentAfter('reader promise') || stripMarkdownLabel(body);
			} else if (depth === 2 && lower.indexOf('assumptions') === 0) {
				data.assumptions = outlineTextList(contentAfter('assumptions to confirm') || contentAfter('assumptions to verify') || body);
			} else if (depth === 2 && (lower.indexOf('missing source questions') === 0 || lower.indexOf('questions') === 0)) {
				data.questions = outlineTextList(contentAfter('missing source questions for editor') || contentAfter('missing source questions') || body);
			} else if (depth === 3) {
				const sectionText = stripMarkdownLabel([label, body].filter(Boolean).join(' - ')).replace(/^\d+[\).]\s*/, '');
				const parts = sectionText.split(/\s[-–]\s/).map(stripMarkdownLabel).filter(Boolean);
				data.sections.push({
					heading: parts.shift() || __('Section ', 'npcink-toolbox') + String(data.sections.length + 1),
					points: parts,
				});
			}
			match = headingPattern.exec(prepared);
		}
		if (!data.workingTitle && !data.readerPromise && !data.sections.length && !data.questions.length) {
			data.sections.push({
				heading: __('Outline notes', 'npcink-toolbox'),
				points: outlineTextList(raw),
			});
		}
		return data;
	}

	function renderOutlineSuggestionSection(section) {
		const data = outlineSuggestionData(section);
		const hasOutline = data.workingTitle || data.readerPromise || data.assumptions.length || data.sections.length || data.questions.length;
		if (!hasOutline) {
			return renderItems(hostedWritingSupportItems(section), __('No outline suggestions returned.', 'npcink-toolbox'));
		}
		return createElement(
			'div',
			{ className: 'npcink-toolbox-editor-support__outline' },
			data.workingTitle || data.readerPromise ? createElement(
				'div',
				{ className: 'npcink-toolbox-editor-support__outline-summary' },
				data.workingTitle ? createElement(
					'div',
					null,
					createElement('span', null, __('Working title', 'npcink-toolbox')),
					createElement('strong', null, data.workingTitle)
				) : null,
				data.readerPromise ? createElement(
					'div',
					null,
					createElement('span', null, __('Reader promise', 'npcink-toolbox')),
					createElement('p', null, data.readerPromise)
				) : null
			) : null,
			data.assumptions.length ? createElement(
				'div',
				{ className: 'npcink-toolbox-editor-support__outline-block' },
				createElement('strong', null, __('Assumptions to confirm', 'npcink-toolbox')),
				createElement(
					'ul',
					null,
					data.assumptions.slice(0, 4).map((item, index) => createElement('li', { key: 'assumption-' + String(index) }, item))
				)
			) : null,
			data.sections.length ? createElement(
				'div',
				{ className: 'npcink-toolbox-editor-support__outline-block' },
				createElement('strong', null, __('Suggested sections', 'npcink-toolbox')),
				createElement(
					'ol',
					{ className: 'npcink-toolbox-editor-support__outline-sections' },
					data.sections.slice(0, 8).map((item, index) => createElement(
						'li',
						{ key: 'outline-section-' + String(index) },
						createElement(
							'div',
							{ className: 'npcink-toolbox-editor-support__outline-section-head' },
							createElement('span', null, String(index + 1)),
							createElement('strong', null, item.heading)
						),
						item.points.length ? createElement(
							'ul',
							null,
							item.points.slice(0, 4).map((point, pointIndex) => createElement('li', { key: 'point-' + String(pointIndex) }, point))
						) : null
					))
				)
			) : null,
			data.questions.length ? createElement(
				'div',
				{ className: 'npcink-toolbox-editor-support__outline-block' },
				createElement('strong', null, __('Questions to confirm', 'npcink-toolbox')),
				createElement(
					'ul',
					null,
					data.questions.slice(0, 6).map((item, index) => createElement('li', { key: 'question-' + String(index) }, item))
				)
			) : null,
			createElement(
				'div',
				{ className: 'npcink-toolbox-editor-support__candidate-meta' },
				createElement('span', null, __('Source: ', 'npcink-toolbox') + __('Current draft', 'npcink-toolbox')),
				createElement('span', null, __('Action: ', 'npcink-toolbox') + __('Copyable', 'npcink-toolbox'))
			)
		);
	}

	function parseHostedJsonObject(value) {
		const text = String(value || '').trim();
		if (!text) {
			return {};
		}
		try {
			const direct = JSON.parse(text);
			return direct && typeof direct === 'object' && !Array.isArray(direct) ? direct : {};
		} catch (error) {
			// Continue with fenced or embedded JSON extraction.
		}
		const fenced = text.match(/```(?:json)?\s*(\{[\s\S]*?\})\s*```/i);
		if (fenced && fenced[1]) {
			try {
				const parsed = JSON.parse(fenced[1]);
				return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
			} catch (error) {
				return {};
			}
		}
		const firstBrace = text.indexOf('{');
		const lastBrace = text.lastIndexOf('}');
		if (firstBrace >= 0 && lastBrace > firstBrace) {
			try {
				const embedded = JSON.parse(text.slice(firstBrace, lastBrace + 1));
				return embedded && typeof embedded === 'object' && !Array.isArray(embedded) ? embedded : {};
			} catch (error) {
				return {};
			}
		}
		return {};
	}

	function hostedOutputObject(section) {
		if (!section || typeof section !== 'object') {
			return {};
		}
		if (section.output_json && typeof section.output_json === 'object' && !Array.isArray(section.output_json)) {
			return section.output_json;
		}
		const directOutput = recognizedHostedOutputObject(section);
		if (Object.keys(directOutput).length) {
			return directOutput;
		}
		if (section.result && typeof section.result === 'object' && !Array.isArray(section.result)) {
			if (section.result.output_json && typeof section.result.output_json === 'object' && !Array.isArray(section.result.output_json)) {
				return section.result.output_json;
			}
			const resultOutput = recognizedHostedOutputObject(section.result);
			if (Object.keys(resultOutput).length) {
				return resultOutput;
			}
			const nestedOutput = nestedHostedOutputObject(section.result);
			if (Object.keys(nestedOutput).length) {
				return nestedOutput;
			}
			const resultText = section.result.output_text || section.result.text || section.result.content || (section.result.message && section.result.message.content) || '';
			const parsedResultText = parseHostedJsonObject(resultText);
			if (Object.keys(parsedResultText).length) {
				return parsedResultText;
			}
		}
		return parseHostedJsonObject(section.output_text || section.text || '');
	}

	function recognizedHostedOutputObject(value) {
		if (!value || typeof value !== 'object' || Array.isArray(value)) {
			return {};
		}
		const keys = Object.keys(value);
		const recognizedKeys = [
			'title_options',
			'titles',
			'suggestions',
			'working_title',
			'reader_promise',
			'sections',
			'outline',
			'missing_source_questions',
			'clarity_check',
			'fact_gaps',
			'tone_consistency',
			'editing_suggestions',
			'assumptions_to_verify',
		];
		return recognizedKeys.some((key) => keys.indexOf(key) >= 0) ? value : {};
	}

	function nestedHostedOutputObject(value) {
		if (!value || typeof value !== 'object' || Array.isArray(value)) {
			return {};
		}
		const structured = recognizedHostedOutputObject(value);
		if (Object.keys(structured).length) {
			return structured;
		}
		if (value.output_json && typeof value.output_json === 'object' && !Array.isArray(value.output_json)) {
			return value.output_json;
		}
		if (value.structured_output && typeof value.structured_output === 'object' && !Array.isArray(value.structured_output)) {
			return value.structured_output;
		}
		if (value.json && typeof value.json === 'object' && !Array.isArray(value.json)) {
			return value.json;
		}
		for (const key of ['output', 'data', 'payload', 'result']) {
			const nested = value[key];
			if (nested && typeof nested === 'object' && !Array.isArray(nested)) {
				const output = nestedHostedOutputObject(nested);
				if (Object.keys(output).length) {
					return output;
				}
			}
		}
		return {};
	}

	function paragraphCheckItems(section) {
		const output = hostedOutputObject(section);
		const fields = [
			['clarity_check', __('Clarity check', 'npcink-toolbox')],
			['fact_gaps', __('Fact gaps', 'npcink-toolbox')],
			['tone_consistency', __('Tone consistency', 'npcink-toolbox')],
			['editing_suggestions', __('Editing suggestions', 'npcink-toolbox')],
			['assumptions_to_verify', __('Assumptions to verify', 'npcink-toolbox')],
		];
		const items = fields.map(([key, label]) => ({
			name: label,
			detail: readableItemText(output[key], ''),
			action_policy: 'operator_review_only_no_insert',
		})).filter((item) => item.detail);
		if (items.length) {
			return items;
		}
		const generic = hostedWritingSupportItems(section);
		if (generic.length) {
			return generic;
		}
		const nested = section && section.result && typeof section.result === 'object' ? nestedHostedOutputObject(section.result) : {};
		const nestedText = readableItemText(nested, '');
		return nestedText ? [{
			name: __('Paragraph check note', 'npcink-toolbox'),
			detail: nestedText,
			action_policy: 'operator_review_only_no_insert',
		}] : [];
	}

	function articleCheckupItems(section) {
		if (!section || typeof section !== 'object') {
			return [];
		}
		const sourceItems = Array.isArray(section.items) && section.items.length
			? section.items
			: (section.output_json && Array.isArray(section.output_json.items) ? section.output_json.items : []);
		return sourceItems.map((item, index) => {
			const issue = readableItemText(item.issue || item.name || item.title || item.type, __('Review item', 'npcink-toolbox'));
			const direction = readableItemText(item.edit_direction || item.detail || item.reason || '', '');
			const evidence = readableItemText(item.evidence || '', '');
			const location = readableItemText(item.location || '', '');
			const severity = item.severity ? formatMetaLabel(item.severity) : '';
			const type = item.type ? formatMetaLabel(item.type) : '';
			return {
				name: [location, issue].filter(Boolean).join(' · ') || __('Review item', 'npcink-toolbox'),
				detail: [direction, evidence ? __('Evidence: ', 'npcink-toolbox') + evidence : '', [type, severity].filter(Boolean).join(' / ')].filter(Boolean).join(' · '),
				action_policy: item.action_policy || 'operator_review_only_no_insert',
				evidence_refs: item.evidence_refs || [],
				id: item.id || String(index),
			};
		}).filter((item) => item.name || item.detail);
	}

	function flowAcceptsUserInstruction(intent) {
		return [
			'title_suggestions',
			'summary_suggestions',
			'tag_suggestions',
			'category_suggestions',
				'internal_links',
				'writing_support',
				'article_checkup',
				'article_outline',
				'polish_notes',
				'discoverability',
				'image_alt_suggestions',
			].indexOf(intent) >= 0;
		}

	function flowInstructionPlaceholder(intent) {
		if (intent === 'title_suggestions') {
			return __('Example: shorter, less marketing, include product name.', 'npcink-toolbox');
		}
		if (intent === 'summary_suggestions') {
			return __('Example: emphasize workflow value, avoid audience-label openings.', 'npcink-toolbox');
		}
		if (intent === 'category_suggestions' || intent === 'tag_suggestions') {
			return __('Example: prefer existing product taxonomy and avoid broad generic terms.', 'npcink-toolbox');
		}
		if (intent === 'internal_links') {
			return __('Example: prefer tutorials over announcement posts.', 'npcink-toolbox');
		}
		if (intent === 'article_checkup') {
			return __('Example: focus on structure and factual claims, not style preference.', 'npcink-toolbox');
		}
		if (intent === 'image_alt_suggestions') {
			return __('Example: concise, factual ALT text; no keyword stuffing.', 'npcink-toolbox');
		}
		if (intent === 'writing_support') {
			return __('Example: focus on what is already covered, what angle is missing, and what I should do next.', 'npcink-toolbox');
		}
		return __('Example: more practical, concise, and less promotional.', 'npcink-toolbox');
	}

	function titleSuggestionItems(section) {
		if (!section || typeof section !== 'object') {
			return [];
		}
		if (Array.isArray(section.recommendation_candidates) && section.recommendation_candidates.length) {
			return section.recommendation_candidates.map((item) => ({
				name: readableItemText(item.label || item.name || item.id, __('Title option', 'npcink-toolbox')),
				detail: [
					readableItemText(item.reason || item.detail, ''),
					item.quality_status ? formatMetaLabel(item.quality_status) : '',
					item.quality_score ? __('Quality score: ', 'npcink-toolbox') + String(item.quality_score) : '',
				].filter(Boolean).join(' · '),
				value: readableItemText(item.value || item.title || item.text, ''),
				quality_status: item.quality_status,
				quality_score: item.quality_score,
				quality_issues: item.quality_issues,
				action_policy: item.action_policy,
				target_field: item.target_field,
			})).filter((item) => item.value);
		}
		const output = hostedOutputObject(section);
		const source = Array.isArray(output.title_options) && output.title_options.length
			? output.title_options
			: (Array.isArray(output.titles) && output.titles.length ? output.titles : (Array.isArray(output.suggestions) && output.suggestions.length ? output.suggestions : []));
		if (source.length) {
			return source.map((item, index) => {
				const title = readableItemText(item && (item.title || item.name || item.label || item.value || item.text || item), '');
				const reason = item && typeof item === 'object' ? readableItemText(item.reason || item.rationale || item.detail, '') : '';
				return {
					name: title || __('Title option', 'npcink-toolbox') + ' ' + String(index + 1),
					detail: reason,
					value: title,
				};
			}).filter((item) => item.value);
		}
		const outputText = String(section.output_text || section.text || '').trim();
		if (outputText && Object.keys(parseHostedJsonObject(outputText)).length) {
			return [];
		}
		return hostedWritingSupportItems(section);
	}

	function renderTitleSuggestionSection(items, controls) {
		const candidates = Array.isArray(items) ? items : [];
		const status = controls && controls.titleApplyStatus ? controls.titleApplyStatus : null;
		const activeIntent = controls && controls.intent ? controls.intent : '';
		const showHeading = activeIntent !== 'title_suggestions';
		return createElement(
			'section',
			{ className: 'npcink-toolbox-editor-support__metadata-compact-section' },
			showHeading ? createElement('h4', null, __('Title suggestions', 'npcink-toolbox')) : null,
			candidates.length
				? createElement(
					'ul',
					{ className: 'npcink-toolbox-editor-support__metadata-compact-list' },
					candidates.slice(0, 5).map((item, index) => {
						const titleText = readableItemText(item && (item.value || item.name || item.title || item.label), __('Title option', 'npcink-toolbox'));
						const detailText = truncateText(readableItemText(item && (item.detail || item.reason || item.excerpt), ''), 140);
						return createElement(
							'li',
							{ key: String(index) + '-' + titleText },
							createElement('strong', null, titleText),
							detailText ? createElement('span', null, detailText) : null,
							titleText && controls && controls.applyTitle ? createElement(
								'div',
								{ className: 'npcink-toolbox-editor-support__candidate-actions' },
								createElement(
									Button,
									{
										type: 'button',
										variant: status && status.title === titleText ? 'secondary' : 'primary',
										onClick: () => controls.applyTitle(titleText),
									},
									status && status.title === titleText ? __('Applied', 'npcink-toolbox') : __('Use this title', 'npcink-toolbox')
								)
							) : null
						);
					})
				)
				: createElement('p', { className: 'npcink-toolbox-editor-support__muted' }, __('No title suggestions returned.', 'npcink-toolbox')),
			status ? createElement(Notice, { status: status.status || 'success', isDismissible: false }, status.message) : null
		);
	}

	function discoverabilitySuggestionItems(section) {
		const suggestions = section && section.candidate_suggestions ? section.candidate_suggestions : {};
		return Object.keys(suggestions).map((field) => ({
			field,
			name: discoverabilityFieldLabel(field),
			detail: discoverabilityFieldValue(field, suggestions[field]),
		}));
	}

	function discoverabilityFieldLabel(field) {
		const labels = {
			seo_title: __('SEO title', 'npcink-toolbox'),
			seo_description: __('SEO description', 'npcink-toolbox'),
			slug: __('URL slug', 'npcink-toolbox'),
			excerpt: __('Excerpt', 'npcink-toolbox'),
			faq: __('FAQ', 'npcink-toolbox'),
			answer_summary: __('Answer summary', 'npcink-toolbox'),
			geo_summary: __('GEO summary', 'npcink-toolbox'),
			structured_data_hints: __('Structured data hints', 'npcink-toolbox'),
		};
		return labels[field] || formatMetaLabel(field);
	}

	function discoverabilityFieldValue(field, value) {
		if (field === 'faq' && Array.isArray(value)) {
			return value.slice(0, 2).map((item) => {
				const question = readableItemText(item && (item.question || item.q), '');
				const answer = readableItemText(item && (item.answer || item.answer_guidance || item.a), '');
				return [question ? __('Q: ', 'npcink-toolbox') + question : '', answer ? __('A: ', 'npcink-toolbox') + answer : ''].filter(Boolean).join(' ');
			}).filter(Boolean).join(' · ');
		}
		const raw = readableItemText(value, '');
		if (field !== 'slug' || !raw) {
			return raw;
		}
		try {
			return decodeURIComponent(raw);
		} catch (error) {
			return raw;
		}
	}

	function discoverabilityPrimaryItems(section) {
		return discoverabilitySuggestionItems(section).filter((item) => ['seo_title', 'seo_description'].indexOf(item.field) >= 0 && item.detail);
	}

	function discoverabilitySecondaryItems(section) {
		const seen = {};
		return discoverabilitySuggestionItems(section).filter((item) => {
			if (['seo_title', 'seo_description'].indexOf(item.field) >= 0 || !item.detail) {
				return false;
			}
			const key = String(item.detail || '').toLowerCase().replace(/\s+/g, ' ').trim();
			if (!key || seen[key]) {
				return false;
			}
			seen[key] = true;
			return true;
		});
	}

	function discoverabilityActionHint(field) {
		const hints = {
			seo_title: __('Included in the SEO optimization action.', 'npcink-toolbox'),
			seo_description: __('Included in the SEO optimization action.', 'npcink-toolbox'),
			slug: __('Changing the slug can change the public URL. Confirm before applying it.', 'npcink-toolbox'),
			excerpt: __('Can be applied to the current excerpt after review.', 'npcink-toolbox'),
			faq: __('Use only after the article contains verified question-and-answer facts.', 'npcink-toolbox'),
			answer_summary: __('Use as an answer-engine note only when it is fully supported by the article.', 'npcink-toolbox'),
			geo_summary: __('Use as an AI-crawler summary only when it adds coverage instead of repeating the excerpt.', 'npcink-toolbox'),
			structured_data_hints: __('Treat as schema planning notes; Toolbox does not apply schema.', 'npcink-toolbox'),
		};
		return hints[field] || __('Review before using this suggestion.', 'npcink-toolbox');
	}

	function discoverabilityTaskGroup(field) {
		if (field === 'slug') {
			return 'permalink';
		}
		if (['seo_title', 'seo_description', 'excerpt'].indexOf(field) >= 0) {
			return 'required';
		}
		return 'enhancement';
	}

	function discoverabilityTaskItems(section) {
		return discoverabilitySuggestionItems(section)
			.filter((item) => item.detail)
			.map((item) => Object.assign({}, item, {
				group: discoverabilityTaskGroup(item.field),
				action_hint: discoverabilityActionHint(item.field),
			}));
	}

	function renderDiscoverabilityTaskList(title, items, emptyLabel, controls, options) {
		const normalized = Array.isArray(items) ? items : [];
		const opts = options || {};
		const showStatus = opts.showStatus !== false;
		const allowCopy = opts.allowCopy !== false;
		return createElement(
			'section',
			{ className: 'npcink-toolbox-editor-support__discoverability-group' },
			createElement('h4', null, title),
			opts.description ? createElement('p', { className: 'npcink-toolbox-editor-support__muted' }, opts.description) : null,
			normalized.length ? createElement(
				'ul',
				{ className: 'npcink-toolbox-editor-support__discoverability-list' },
				normalized.map((item) => {
					const key = item.field || item.name;
					const canApplyExcerpt = item.field === 'excerpt' && controls && typeof controls.applyExcerpt === 'function';
					return createElement(
						'li',
						{ key },
						createElement(
							'div',
							{ className: 'npcink-toolbox-editor-support__discoverability-item-head' },
							createElement('strong', null, item.name),
							showStatus ? createElement('span', null, item.group === 'required' ? __('Ready to apply', 'npcink-toolbox') : __('Optional enhancement', 'npcink-toolbox')) : null
						),
						createElement('code', null, item.detail),
						createElement('p', null, item.action_hint),
						createElement(
							'div',
							{ className: 'npcink-toolbox-editor-support__discoverability-actions' },
							canApplyExcerpt ? createElement(
								Button,
								{
									type: 'button',
									variant: 'secondary',
										onClick: () => controls.applyExcerpt(item.detail),
									},
									__('Apply excerpt', 'npcink-toolbox')
								) : null,
								allowCopy ? createElement(
									Button,
									{
										type: 'button',
									variant: 'tertiary',
									onClick: () => copyTextToClipboard(item.detail).catch(() => {}),
									},
									__('Copy', 'npcink-toolbox')
								) : null
							)
						);
					})
			) : createElement('p', { className: 'npcink-toolbox-editor-support__muted' }, emptyLabel)
		);
	}

	function renderDiscoverabilitySlugSection(slugItem, controls) {
		if (!slugItem || !slugItem.detail) {
			return null;
		}
		const canApplySlug = controls && typeof controls.applySlug === 'function';
		const status = controls && controls.slugApplyStatus ? controls.slugApplyStatus : null;
		return createElement(
			'section',
			{ className: 'npcink-toolbox-editor-support__discoverability-slug' },
			createElement('h4', null, __('Permalink suggestion', 'npcink-toolbox')),
			createElement('p', { className: 'npcink-toolbox-editor-support__muted' }, __('Slug changes affect the public URL. Published articles should only change slug when you accept the redirect and indexing risk.', 'npcink-toolbox')),
			createElement('code', null, slugItem.detail),
			createElement(
				'div',
				{ className: 'npcink-toolbox-editor-support__discoverability-actions' },
				canApplySlug ? createElement(
					Button,
					{
						type: 'button',
						variant: 'secondary',
						onClick: () => controls.applySlug(slugItem.detail),
					},
					__('Apply slug', 'npcink-toolbox')
				) : null,
				createElement(
					Button,
					{
						type: 'button',
						variant: 'tertiary',
						onClick: () => copyTextToClipboard(slugItem.detail).catch(() => {}),
					},
					__('Copy', 'npcink-toolbox')
				)
			),
			status ? createElement(Notice, { status: status.status || 'success', isDismissible: false }, status.message) : null
		);
	}

	function renderDiscoverabilityMoreSuggestions(items, controls) {
		const normalized = Array.isArray(items) ? items : [];
		return createElement(
			'details',
			{ className: 'npcink-toolbox-editor-support__discoverability-more' },
			createElement('summary', null, sprintf(__('More AI and crawler suggestions (%d)', 'npcink-toolbox'), normalized.length)),
			renderDiscoverabilityTaskList(
				__('Optional AI-crawler enhancements', 'npcink-toolbox'),
				normalized,
				__('No optional AEO/GEO enhancements returned.', 'npcink-toolbox'),
				controls,
				{
					description: __('Use these only when the draft already supports the answer, FAQ, GEO, or schema claim.', 'npcink-toolbox'),
					allowCopy: true,
				}
			)
		);
	}

	function mediaAltLooksWeak(item) {
		const alt = String(item && item.alt ? item.alt : '').trim();
		if (!alt) {
			return false;
		}
		const filename = String(item && (item.filename || item.url || '') ? (item.filename || item.url) : '').split('/').pop().replace(/\.[a-z0-9]+$/i, '').replace(/[-_]+/g, ' ').trim().toLowerCase();
		const normalizedAlt = alt.replace(/\.[a-z0-9]+$/i, '').replace(/[-_]+/g, ' ').trim().toLowerCase();
		return normalizedAlt.length < 8 || (filename && normalizedAlt === filename);
	}

	function discoverabilityMediaSummary(mediaItems) {
		const items = (Array.isArray(mediaItems) ? mediaItems : []).filter((item) => item && (item.attachment_id || item.url));
		const missingAlt = items.filter((item) => !String(item.alt || '').trim()).length;
		const weakAlt = items.filter((item) => mediaAltLooksWeak(item)).length;
		const missingCaption = items.filter((item) => !String(item.caption || '').trim()).length;
		return {
			items,
			total: items.length,
			missingAlt,
			weakAlt,
			missingCaption,
		};
	}

	function renderDiscoverabilityMediaSection(controls, imageAltSection) {
		const mediaSummary = discoverabilityMediaSummary(controls && controls.mediaItems);
		const canRun = controls && typeof controls.runIntent === 'function';
		const isRunning = controls && controls.runningIntent === 'image_alt_suggestions';
		const suggestionItems = imageAltSuggestionItems(imageAltSection);
		return createElement(
			'section',
			{ className: 'npcink-toolbox-editor-support__discoverability-media' },
			createElement('h4', null, __('Image ALT and caption check', 'npcink-toolbox')),
			mediaSummary.total ? createElement(
				'div',
				{ className: 'npcink-toolbox-editor-support__discoverability-media-counts' },
				createElement('span', null, sprintf(__('%d image(s) in draft', 'npcink-toolbox'), mediaSummary.total)),
				createElement('span', null, sprintf(__('%d missing ALT', 'npcink-toolbox'), mediaSummary.missingAlt)),
				createElement('span', null, sprintf(__('%d weak ALT', 'npcink-toolbox'), mediaSummary.weakAlt)),
				createElement('span', null, sprintf(__('%d missing captions', 'npcink-toolbox'), mediaSummary.missingCaption))
			) : createElement('p', { className: 'npcink-toolbox-editor-support__muted' }, __('No images are currently used by this draft.', 'npcink-toolbox')),
			createElement('p', { className: 'npcink-toolbox-editor-support__muted' }, __('Generate review-only ALT and caption suggestions for images already used by this draft. Toolbox will not write media metadata.', 'npcink-toolbox')),
			createElement(
				Button,
				{
					type: 'button',
					variant: 'secondary',
					isBusy: Boolean(isRunning),
					disabled: !canRun || !mediaSummary.total || Boolean(isRunning),
					onClick: () => controls.runIntent('image_alt_suggestions', {
						preserveResult: true,
						resultIntent: 'discoverability',
					}),
				},
				isRunning ? __('Generating ALT suggestions', 'npcink-toolbox') : __('Generate ALT and caption suggestions', 'npcink-toolbox')
			),
			suggestionItems.length ? createElement(
				'div',
				{ className: 'npcink-toolbox-editor-support__discoverability-media-results' },
				createElement('strong', null, __('Generated suggestions', 'npcink-toolbox')),
				renderItems(suggestionItems, __('No image ALT suggestions returned.', 'npcink-toolbox'))
			) : null
		);
	}

	function renderDiscoverabilityOptimizationSection(section, seoHandoffSection, controls, imageAltSection) {
		const tasks = discoverabilityTaskItems(section);
		const required = tasks.filter((item) => item.group === 'required');
		const slugItem = tasks.find((item) => item.group === 'permalink');
		const enhancements = tasks.filter((item) => item.group === 'enhancement');
		const seoControls = controls && controls.seoHandoff ? controls.seoHandoff : null;
		const seoPayload = seoMetaProposalPayload(seoHandoffSection || {});
		const seoInput = seoPayload.input || {};
		const seoDisabled = !seoControls || !seoHandoffSection || !seoHandoffSection.proposal_ready || !seoInput.post_id || !seoInput.seo_title || !seoInput.seo_description || seoControls.running;
		const actionCount = required.length + (slugItem ? 1 : 0);

		return createElement(
			'div',
			{ className: 'npcink-toolbox-editor-support__discoverability-panel' },
			createElement(
				'section',
				{ className: 'npcink-toolbox-editor-support__discoverability-summary' },
				createElement('strong', null, __('Discoverability optimization', 'npcink-toolbox')),
				createElement('p', null, sprintf(__('%d reviewed suggestion(s) are ready for this article. SEO still records a governed handoff; excerpt and slug update the current editor draft.', 'npcink-toolbox'), actionCount))
			),
			seoHandoffSection ? createElement(
				'section',
				{ className: 'npcink-toolbox-editor-support__discoverability-next-step' },
				createElement('h4', null, __('Main action', 'npcink-toolbox')),
					createElement('p', { className: 'npcink-toolbox-editor-support__muted' }, __('Apply the reviewed SEO title and description through Adapter/Core. If policy blocks automatic execution, the proposal stays available for Core review. Toolbox does not approve, execute, or write SEO fields.', 'npcink-toolbox')),
					createElement(
						Button,
						{
							type: 'button',
							variant: 'primary',
							title: __('Create SEO Core review proposal', 'npcink-toolbox'),
							isBusy: Boolean(seoControls && seoControls.running),
							disabled: seoDisabled,
							onClick: seoControls && seoControls.submit,
					},
						seoControls && seoControls.running ? __('Applying', 'npcink-toolbox') : __('Apply SEO optimization', 'npcink-toolbox')
				),
				seoControls && seoControls.error ? createElement(Notice, { status: 'error', isDismissible: false }, seoControls.error) : null,
				seoControls && seoControls.result ? createElement(
					Notice,
						{ status: seoControls.result.execution_error ? 'warning' : 'success', isDismissible: false },
						seoControls.result.message || __('SEO optimization submitted for Core review.', 'npcink-toolbox')
				) : null
			) : null,
			renderDiscoverabilityTaskList(
				__('Review before applying', 'npcink-toolbox'),
				required,
				__('No required discoverability tasks returned.', 'npcink-toolbox'),
				controls,
				{
					description: __('These are the visible fields the operator can review in this panel.', 'npcink-toolbox'),
					allowCopy: false,
					showStatus: false,
				}
			),
			renderDiscoverabilitySlugSection(slugItem, controls),
			renderDiscoverabilityMediaSection(controls, imageAltSection),
			enhancements.length ? renderDiscoverabilityMoreSuggestions(enhancements, controls) : null,
			controls && controls.excerptApplyStatus ? createElement(Notice, { status: controls.excerptApplyStatus.status || 'success', isDismissible: false }, controls.excerptApplyStatus.message) : null
		);
	}

	function internalLinkReasonText(reason) {
		const text = readableItemText(reason, '');
		if (!text) {
			return __('Related by Site Knowledge. Review as an internal link.', 'npcink-toolbox');
		}
		if (text.toLowerCase().indexOf('the indexed passage is semantically related') >= 0) {
			return __('Related by Site Knowledge. Review as an internal link.', 'npcink-toolbox');
		}
		return text;
	}

	function internalLinkScoreText(score) {
		const value = parseInt(score || '0', 10);
		return value > 0 ? __('Score ', 'npcink-toolbox') + String(value) : '';
	}

	function internalLinkCandidateItems(section) {
		const sourceItems = section && Array.isArray(section.items) ? section.items : [];
		if (section && Array.isArray(section.recommendation_candidates) && section.recommendation_candidates.length) {
			return section.recommendation_candidates.map((item, index) => {
				const source = sourceItems[index] && typeof sourceItems[index] === 'object' ? sourceItems[index] : {};
				const title = readableItemText(item.label || item.name || source.title || item.id, __('Internal link candidate', 'npcink-toolbox'));
				const targetUrl = readableItemText(item.target_url || item.source_candidate_ref || source.target_url || source.url, '');
				const anchorText = readableItemText(item.value || source.suggested_anchor_text || title, '');
				return {
					id: readableItemText(item.id || source.target_post_id || String(index + 1), String(index + 1)),
					title,
					anchorText,
					targetUrl,
					reason: internalLinkReasonText(item.reason || item.detail || source.reason),
					placementHint: readableItemText(source.placement_hint || item.placement_hint, ''),
					score: internalLinkScoreText(item.quality_score),
					quality_status: item.quality_status,
					quality_score: item.quality_score,
					quality_issues: item.quality_issues,
					action_policy: item.action_policy,
					target_field: item.target_field,
				};
			});
		}
		return sourceItems.map((item, index) => {
			const title = readableItemText(item.title || item.target_url, __('Internal link candidate', 'npcink-toolbox'));
			return {
				id: readableItemText(item.target_post_id || String(index + 1), String(index + 1)),
				title,
				anchorText: readableItemText(item.suggested_anchor_text || title, ''),
				targetUrl: readableItemText(item.target_url || item.url, ''),
				reason: internalLinkReasonText(item.reason),
				placementHint: readableItemText(item.placement_hint, ''),
				score: item.score ? internalLinkScoreText(Math.round(Number(item.score) * 100)) : '',
				quality_status: item.status,
				action_policy: 'operator_review_only_no_insert',
				target_field: 'post_content',
			};
		});
	}

	function imageAltSuggestionItems(section) {
		if (!section || typeof section !== 'object') {
			return [];
		}
		const output = hostedOutputObject(section);
		const source = Array.isArray(output.suggestions) && output.suggestions.length
			? output.suggestions
			: (Array.isArray(output.items) && output.items.length ? output.items : (Array.isArray(output.alt_suggestions) && output.alt_suggestions.length ? output.alt_suggestions : []));
		if (source.length) {
			return source.map((item, index) => {
				const attachmentId = item && (item.attachment_id || item.media_id || item.id);
				const altCandidates = []
					.concat(Array.isArray(item && item.alt_candidates) ? item.alt_candidates : [])
					.concat(item && item.alt_candidate ? [item.alt_candidate] : [])
					.concat(item && item.suggested_alt ? [item.suggested_alt] : [])
					.concat(item && item.alt ? [item.alt] : []);
				const altText = altCandidates.map((value) => readableItemText(value, '')).filter(Boolean).slice(0, 3).join(' / ');
				const captionText = readableItemText(item && (item.caption_candidate || item.suggested_caption || item.caption), '');
				const title = readableItemText(item && (item.title || item.name || item.filename), '')
					|| (attachmentId ? __('Attachment ', 'npcink-toolbox') + String(attachmentId) : '')
					|| __('Image ALT suggestion', 'npcink-toolbox') + ' ' + String(index + 1);
				return {
					name: title,
					detail: [
						altText ? __('ALT: ', 'npcink-toolbox') + altText : '',
						captionText ? __('Caption: ', 'npcink-toolbox') + captionText : '',
						item && item.current_alt_status ? formatMetaLabel(item.current_alt_status) : '',
						item && item.needs_human_visual_check ? __('Human visual check required', 'npcink-toolbox') : '',
					].filter(Boolean).join(' · '),
				};
			}).filter((item) => item.name || item.detail);
		}
		const outputText = String(section.output_text || section.text || '').trim();
		if (outputText && Object.keys(parseHostedJsonObject(outputText)).length) {
			return [];
		}
		return hostedWritingSupportItems(section);
	}

	function prePublishReviewItems(section) {
		const items = section && Array.isArray(section.items) ? section.items : [];
		return items.map((item) => ({
			name: formatMetaLabel(item.name || ''),
			id: item.name || '',
			status: item.status ? formatMetaLabel(item.status) : '',
			rawStatus: item.status || '',
			nextAction: item.next_action || '',
			detail: item.detail || '',
			}));
	}

	function duplicateRiskItems(section) {
		return extractKnowledgeItems(section).map((item, index) => {
			const sourceId = item && (item.post_id || item.id || item.source_id) ? String(item.post_id || item.id || item.source_id) : '';
			const title = readableItemText(item && (item.title || item.name || item.source_title), __('Existing post', 'npcink-toolbox') + ' ' + String(index + 1));
			const targetUrl = readableItemText(item && (item.target_url || item.url || item.permalink || item.link), '');
			const detail = readableItemText(item && (item.reason || item.excerpt || item.snippet || item.content_excerpt), '');
			return {
				id: sourceId || String(index + 1),
				name: title,
				title,
				targetUrl,
				detail,
				evidence_refs: sourceId ? ['site_knowledge:' + sourceId] : ['site_knowledge:duplicate_check'],
				action_policy: 'operator_review_only_no_write',
			};
		});
	}

	function preflightActionIntent(action) {
		const key = String(action || '').trim();
		const mapping = {
			title: 'title_suggestions',
			excerpt: 'summary_suggestions',
			summary: 'summary_suggestions',
			categories: 'category_suggestions',
			category_suggestions: 'category_suggestions',
			tags: 'tag_suggestions',
			tag_suggestions: 'tag_suggestions',
			terms: 'category_suggestions',
			featured_image: 'image_candidates',
			featured_media: 'image_candidates',
			image_candidates: 'image_candidates',
			internal_links: 'internal_links',
			seo_meta: 'discoverability',
			seo_meta_single_post_handoff: 'discoverability',
		};
		return mapping[key] || '';
	}

	function preflightActionLabel(intent, fallback) {
		const labels = {
			title_suggestions: __('Open title suggestions', 'npcink-toolbox'),
			summary_suggestions: __('Open summary suggestions', 'npcink-toolbox'),
			category_suggestions: __('Open category suggestions', 'npcink-toolbox'),
			tag_suggestions: __('Open tag suggestions', 'npcink-toolbox'),
			image_candidates: __('Open image candidates', 'npcink-toolbox'),
			internal_links: __('Open internal link candidates', 'npcink-toolbox'),
			discoverability: __('Open discoverability suggestions', 'npcink-toolbox'),
		};
		return labels[intent] || fallback || __('Open tool', 'npcink-toolbox');
	}

	function preflightReviewActionLabel(item, intent) {
		const action = String(item && (item.nextAction || item.id || item.name) ? (item.nextAction || item.id || item.name) : '').toLowerCase();
		if (intent === 'discoverability' && action.indexOf('seo') >= 0) {
			return __('Review SEO candidates', 'npcink-toolbox');
		}
		return preflightActionLabel(intent);
	}

		function preflightActionItems(reviewSection, checksSection) {
			const actions = [];
			const seen = {};
			function pushAction(id, label, status, detail, nextAction) {
			const intent = preflightActionIntent(nextAction || id);
			const key = String(id || nextAction || intent || label || '').trim();
			if (!key || seen[key]) {
				return;
			}
			seen[key] = true;
			actions.push({
				id: key,
				label: label || formatMetaLabel(key),
				status: status || '',
				detail: detail || '',
				intent,
			});
		}

		const checks = checksSection && Array.isArray(checksSection.items) ? checksSection.items : [];
		checks.forEach((item) => {
			const status = String(item.status || '');
			if (status && status !== 'ok') {
				pushAction(item.id, item.label || formatMetaLabel(item.id || ''), status, item.detail || '', item.id);
			}
		});

		const reviewItems = sectionItems(reviewSection);
		reviewItems.forEach((item) => {
			const status = String(item.status || '');
			if (status && status === 'ok') {
				return;
			}
			pushAction(item.name, formatMetaLabel(item.name || ''), status, item.detail || '', item.next_action || item.name);
		});

			return actions;
		}

		function preflightStatusBucket(status) {
			const key = String(status || '').toLowerCase();
			if (['ok', 'good', 'present', 'ready'].indexOf(key) >= 0) {
				return 'ok';
			}
			if (['warning', 'missing', 'error', 'needs_attention'].indexOf(key) >= 0) {
				return 'action';
			}
			if (['review', 'review_required', 'required', 'pending'].indexOf(key) >= 0) {
				return 'review';
			}
			return key ? 'review' : 'review';
		}

		function preflightReviewCounts(reviewSection) {
			const counts = {
				action: 0,
				review: 0,
				ok: 0,
			};
			sectionItems(reviewSection).forEach((item) => {
				const bucket = preflightStatusBucket(item.status);
				if (counts[bucket] !== undefined) {
					counts[bucket] += 1;
				}
			});
			return counts;
		}

		function renderPreflightStatusStrip(counts) {
			const items = [
				{
					key: 'action',
					label: __('Needs action', 'npcink-toolbox'),
					value: counts.action,
					status: counts.action ? 'warning' : 'ok',
				},
				{
					key: 'review',
					label: __('Needs review', 'npcink-toolbox'),
					value: counts.review,
					status: counts.review ? 'review' : 'ok',
				},
				{
					key: 'ok',
					label: __('Passed', 'npcink-toolbox'),
					value: counts.ok,
					status: 'ok',
				},
			];
			return createElement(
				'div',
				{ className: 'npcink-toolbox-editor-support__preflight-status-strip' },
				items.map((item) => createElement(
					'div',
					{ key: item.key, className: 'npcink-toolbox-editor-support__preflight-status is-' + item.status },
					createElement('strong', null, String(item.value)),
					createElement('span', null, item.label)
				))
			);
		}

		function preflightBlockingActions(actions) {
			return actions.filter((item) => preflightStatusBucket(item.status) === 'action');
		}

		function renderPreflightBlockingHint(actions, controls) {
			const blockingActions = preflightBlockingActions(actions);
			const item = blockingActions[0];
			if (!item) {
				return createElement(
					'p',
					{ className: 'npcink-toolbox-editor-support__muted' },
					__('No blocking items were found. Review the full preflight before publishing.', 'npcink-toolbox')
				);
			}
			return createElement(
				'div',
				{ className: 'npcink-toolbox-editor-support__preflight-blocking-hint' },
				createElement(
					'div',
					null,
					createElement('span', null, __('First required item', 'npcink-toolbox')),
					createElement('strong', null, item.label),
					item.detail ? createElement('p', null, item.detail) : null
				),
				item.intent && controls && controls.runIntent ? createElement(
					Button,
					{
						type: 'button',
						variant: 'tertiary',
						disabled: Boolean(controls.runningIntent),
						onClick: () => controls.runIntent(item.intent),
					},
					preflightActionLabel(item.intent)
				) : null
			);
		}

		function renderPreflightSummaryPanel(payload, controls) {
			const sections = payload && payload.sections ? payload.sections : {};
			const counts = preflightReviewCounts(sections.pre_publish_review);
			const actions = preflightActionItems(sections.pre_publish_review, sections.checks)
				.filter((item) => preflightStatusBucket(item.status) !== 'ok')
				.sort((first, second) => {
					const priority = { action: 0, review: 1, ok: 2 };
					return (priority[preflightStatusBucket(first.status)] || 2) - (priority[preflightStatusBucket(second.status)] || 2);
				});
			const summaryText = counts.action
				? __('Handle required items before publishing.', 'npcink-toolbox')
				: (counts.review ? __('Review evidence before publishing.', 'npcink-toolbox') : __('No required preflight fixes were found.', 'npcink-toolbox'));
			return createElement(
				'section',
				{ className: 'npcink-toolbox-editor-support__preflight-summary' },
				createElement(
					'div',
					{ className: 'npcink-toolbox-editor-support__preflight-summary-head' },
					createElement('strong', null, __('Publish preflight summary', 'npcink-toolbox')),
					createElement('span', null, summaryText)
				),
				renderPreflightStatusStrip(counts),
				renderPreflightBlockingHint(actions, controls),
				createElement(
					'div',
					{ className: 'npcink-toolbox-editor-support__preflight-summary-actions' },
					controls && controls.openPreflightModal ? createElement(
						Button,
						{
							type: 'button',
							variant: 'primary',
							onClick: controls.openPreflightModal,
						},
						__('View publish preflight', 'npcink-toolbox')
					) : null
				),
				createElement('p', { className: 'npcink-toolbox-editor-support__muted' }, __('Full duplicate-risk, SEO, and evidence details open in the preflight dialog.', 'npcink-toolbox'))
			);
		}

		function preflightReviewGroups(reviewSection) {
			const groups = {
				action: [],
				review: [],
				ok: [],
			};
			prePublishReviewItems(reviewSection).forEach((item) => {
				const bucket = preflightStatusBucket(item.rawStatus || item.status);
				if (bucket === 'ok') {
					groups.ok.push(item);
					return;
				}
				if (bucket === 'action') {
					groups.action.push(item);
					return;
				}
				groups.review.push(item);
			});
			return groups;
		}

		function preflightItemKey(item) {
			return String(item && (item.nextAction || item.id || item.name) ? (item.nextAction || item.id || item.name) : '').toLowerCase();
		}

		function preflightItemHasKey(item, needle) {
			return preflightItemKey(item).indexOf(needle) >= 0;
		}

		function renderPreflightRowDetail(item, sections, controls) {
			if (preflightItemHasKey(item, 'duplicate')) {
				return sections && sections.duplicate_check ? renderDuplicateRiskInline(sections.duplicate_check, controls) : null;
			}
			if (preflightItemHasKey(item, 'seo')) {
				return renderSeoPreflightInline(sections || {}, controls);
			}
			return null;
		}

		function renderPreflightReviewRow(item, controls, bucket, sections) {
			const intent = preflightActionIntent(item.nextAction || item.id);
			const rowDetail = sections ? renderPreflightRowDetail(item, sections, controls) : null;
			const bucketLabel = bucket === 'action' ? __('Needs action', 'npcink-toolbox') : __('Needs review', 'npcink-toolbox');
			return createElement(
				'li',
				{ key: item.id || item.name, className: 'npcink-toolbox-editor-support__preflight-review-row is-' + bucket },
				createElement('span', { className: 'npcink-toolbox-editor-support__preflight-review-status' }, bucketLabel),
				createElement(
					'div',
					{ className: 'npcink-toolbox-editor-support__preflight-review-item' },
					createElement('strong', null, item.name)
				),
				createElement(
					'div',
					{ className: 'npcink-toolbox-editor-support__preflight-review-decision' },
					item.detail ? createElement('p', null, item.detail) : createElement('p', null, item.status || bucketLabel),
					rowDetail ? createElement(
						'details',
						{ className: 'npcink-toolbox-editor-support__preflight-row-details' },
						createElement('summary', null, __('View details', 'npcink-toolbox')),
						rowDetail
					) : null
				),
				createElement(
					'div',
					{ className: 'npcink-toolbox-editor-support__preflight-review-action' },
					intent && controls && controls.runIntent ? createElement(
						Button,
						{
							type: 'button',
							variant: 'secondary',
							disabled: Boolean(controls.runningIntent),
							onClick: () => controls.runIntent(intent),
						},
						preflightReviewActionLabel(item, intent)
					) : null
				)
			);
		}

		function renderPreflightChecklist(reviewSection, controls, sections) {
			const groups = preflightReviewGroups(reviewSection);
			const rows = groups.action.map((item) => ({ item, bucket: 'action' }))
				.concat(groups.review.map((item) => ({ item, bucket: 'review' })));
			return createElement(
				'section',
				{ className: 'npcink-toolbox-editor-support__preflight-checklist' },
				createElement(
					'div',
					{ className: 'npcink-toolbox-editor-support__preflight-checklist-head' },
					createElement('span', null, __('Status', 'npcink-toolbox')),
					createElement('span', null, __('Check item', 'npcink-toolbox')),
					createElement('span', null, __('Decision', 'npcink-toolbox')),
					createElement('span', null, __('Action', 'npcink-toolbox'))
				),
				rows.length ? createElement(
					'ul',
					{ className: 'npcink-toolbox-editor-support__preflight-review-list' },
					rows.map((row) => renderPreflightReviewRow(row.item, controls, row.bucket, sections))
				) : createElement('p', { className: 'npcink-toolbox-editor-support__muted' }, __('No blocking or review-only checks.', 'npcink-toolbox')),
				renderPreflightPassedSummary(groups.ok)
			);
		}

		function renderPreflightPassedSummary(items) {
			return createElement(
				'section',
				{ className: 'npcink-toolbox-editor-support__preflight-passed-strip' },
				createElement('strong', null, __('Passed checks', 'npcink-toolbox')),
				items.length ? createElement(
					'div',
					{ className: 'npcink-toolbox-editor-support__preflight-passed-chips' },
					items.map((item) => createElement(
						'span',
						{ key: item.id || item.name },
						createElement('span', { className: 'dashicons dashicons-yes-alt', 'aria-hidden': 'true' }),
						item.name
					))
				) : createElement('p', { className: 'npcink-toolbox-editor-support__muted' }, __('No passed checks returned.', 'npcink-toolbox'))
			);
		}

		function renderPreflightReviewSections(reviewSection, controls, sections) {
			return renderPreflightChecklist(reviewSection, controls, sections);
		}

		function renderPreflightEvidenceList(items, emptyLabel) {
			const normalized = Array.isArray(items) ? items : [];
			if (!normalized.length) {
				return createElement('p', { className: 'npcink-toolbox-editor-support__muted' }, emptyLabel);
			}
			return createElement(
				'ul',
				{ className: 'npcink-toolbox-editor-support__preflight-evidence-list' },
				normalized.map((item, index) => {
					const title = readableItemText(item && (item.name || item.title || item.label), '');
					const value = readableItemText(item && item.value, '');
					const detail = readableItemText(item && (item.detail || item.reason || item.status), '');
					return createElement(
						'li',
						{ key: String((item && (item.id || item.name || item.title)) || index) },
						title ? createElement('strong', null, title) : null,
						value ? createElement('code', null, value) : null,
						detail ? createElement('span', null, detail) : null
					);
				})
			);
		}

		function renderDuplicateRiskInline(section, controls) {
			const items = duplicateRiskItems(section);
			const actionControls = controls && controls.internalLinks ? controls.internalLinks : {};
			return createElement(
				'div',
				{ className: 'npcink-toolbox-editor-support__preflight-inline-detail' },
				createElement('p', { className: 'npcink-toolbox-editor-support__muted' }, items.length ? sprintf(__('%d possible overlap(s) found. Review these related posts once before publishing.', 'npcink-toolbox'), items.length) : __('No duplicate-risk candidates returned.', 'npcink-toolbox')),
				items.length ? createElement(
					'ul',
					{ className: 'npcink-toolbox-editor-support__preflight-duplicate-list' },
					items.map((item, index) => {
						const key = String(index) + '-' + readableItemText(item && item.title, __('Existing post', 'npcink-toolbox'));
						const hasUrl = Boolean(item && item.targetUrl);
						return createElement(
							'li',
							{ key },
							createElement('strong', null, item.title),
							item.detail ? createElement('p', null, truncateText(item.detail, 120)) : null,
							createElement(
								'div',
								{ className: 'npcink-toolbox-editor-support__preflight-inline-actions' },
								createElement(
									Button,
									{
										type: 'button',
										variant: 'tertiary',
										disabled: !hasUrl || Boolean(actionControls.running),
										isBusy: actionControls.running === key + ':copy',
										onClick: () => actionControls.copy && actionControls.copy(item, key),
									},
									__('Copy link', 'npcink-toolbox')
								),
								createElement(
									Button,
									{
										type: 'button',
										variant: 'tertiary',
										disabled: !hasUrl,
										onClick: () => actionControls.open && actionControls.open(item),
									},
									__('Open article', 'npcink-toolbox')
								)
							)
						);
					})
				) : null,
				items.length ? createElement('p', { className: 'npcink-toolbox-editor-support__muted' }, __('If the draft repeats an older post, add a new angle or cite the old post instead.', 'npcink-toolbox')) : null
			);
		}

		function renderSeoPreflightInline(sections, controls) {
			const blocks = [];
			if (sections.seo_handoff) {
				const items = seoHandoffItems(sections.seo_handoff).filter((item) => {
					const name = String(item && item.name ? item.name : '').toLowerCase();
					return name.indexOf('target ability') < 0 && name.indexOf('core handoff') < 0 && name.indexOf('目标能力') < 0;
				});
				blocks.push(createElement(
					'div',
					{ key: 'seo-handoff', className: 'npcink-toolbox-editor-support__preflight-inline-detail' },
					createElement('p', { className: 'npcink-toolbox-editor-support__muted' }, __('Review the SEO title and description candidate before applying through Adapter/Core.', 'npcink-toolbox')),
					renderPreflightEvidenceList(items, __('No SEO handoff preview returned.', 'npcink-toolbox')),
					renderSeoHandoffControl(sections.seo_handoff, controls && controls.seoHandoff)
				));
			}
			if (sections.discoverability && sections.discoverability.candidate_suggestions) {
				blocks.push(renderPreflightDiscoverabilitySection(sections.discoverability));
			}
			return blocks.length ? createElement('div', null, blocks) : null;
		}

		function renderPreflightDiscoverabilitySection(section) {
			const secondaryItems = discoverabilitySecondaryItems(section);
			if (!secondaryItems.length) {
				return null;
			}
			return createElement(
				'details',
				{ className: 'npcink-toolbox-editor-support__preflight-discoverability-extra' },
				createElement('summary', null, sprintf(__('More discoverability candidates (%d)', 'npcink-toolbox'), secondaryItems.length)),
				createElement('p', { className: 'npcink-toolbox-editor-support__muted' }, __('SEO title and description stay in the Core handoff section. Other SEO/AEO/GEO ideas are review notes only.', 'npcink-toolbox')),
				renderPreflightEvidenceList(secondaryItems, __('No discoverability candidates returned.', 'npcink-toolbox'))
			);
		}

		function renderPreflightDetailPanel(payload, controls) {
			const sections = payload && payload.sections ? payload.sections : {};
			const blocks = [
				renderPreflightReviewSections(sections.pre_publish_review, controls, sections),
			];
			return createElement('div', { className: 'npcink-toolbox-editor-support__preflight-detail' }, blocks);
		}

		function sectionItems(section) {
			return section && Array.isArray(section.items) ? section.items : [];
		}

	function seoHandoffItems(section) {
		if (!section || typeof section !== 'object') {
			return [];
		}
		const baseItems = Array.isArray(section.items) ? section.items : [];
		const targetAbility = section.target_ability_id ? [{
			name: __('Target ability', 'npcink-toolbox'),
			detail: section.target_ability_id,
		}] : [];
		return targetAbility.concat(baseItems.map((item) => ({
			name: item.name || item.id || __('SEO handoff item', 'npcink-toolbox'),
			value: item.value || '',
			detail: [
				item.status ? formatMetaLabel(item.status) : '',
				item.detail || '',
			].filter(Boolean).join(' · '),
		})));
	}

	function metadataDeltaItems(delta) {
		if (!delta || typeof delta !== 'object') {
			return [];
		}
		const diagnosis = delta.diagnosis && typeof delta.diagnosis === 'object' ? delta.diagnosis : {};
		const authorization = delta.authorization && typeof delta.authorization === 'object' ? delta.authorization : {};
		const issue = delta.issue_record && typeof delta.issue_record === 'object' ? delta.issue_record : {};
		return [
			{
				name: __('Issue', 'npcink-toolbox'),
				detail: issue.user_expression || __('Current post metadata can be reviewed for discoverability.', 'npcink-toolbox'),
			},
			{
				name: __('Diagnosis', 'npcink-toolbox'),
				detail: [
					diagnosis.summary_quality ? __('Summary: ', 'npcink-toolbox') + formatMetaLabel(diagnosis.summary_quality) : '',
					diagnosis.taxonomy_quality ? __('Taxonomy: ', 'npcink-toolbox') + formatMetaLabel(diagnosis.taxonomy_quality) : '',
					diagnosis.evidence_strength ? __('Evidence: ', 'npcink-toolbox') + formatMetaLabel(diagnosis.evidence_strength) : '',
				].filter(Boolean).join(' · '),
			},
			{
				name: __('Authorization', 'npcink-toolbox'),
				detail: [
					authorization.classification ? formatMetaLabel(authorization.classification) : __('Suggestion only', 'npcink-toolbox'),
					authorization.handoff_preview_ref ? authorization.handoff_preview_ref : '',
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
			name: __('Recommended excerpt', 'npcink-toolbox'),
			value: excerpt.recommended,
			reason: excerpt.reason || '',
		}];
	}

	function metadataDeltaCheckItems(delta) {
		const checks = delta && delta.outcome_contract && Array.isArray(delta.outcome_contract.checks) ? delta.outcome_contract.checks : [];
		return checks.map((check) => ({
			name: formatMetaLabel(check),
		}));
	}

	function metadataSummaryItems(section, summaryText) {
		const items = [];
		const seen = {};
		function addSummaryItem(name, detail, reason, sourceItem) {
			const value = readableItemText(detail, '');
			const key = value.trim().toLowerCase();
			if (!value || seen[key]) {
				return;
			}
			seen[key] = true;
			items.push({
				name,
				detail: value,
				reason: reason || '',
				quality_status: sourceItem && sourceItem.quality_status,
				quality_score: sourceItem && sourceItem.quality_score,
				quality_issues: sourceItem && sourceItem.quality_issues,
			});
		}

		const excerpt = metadataRecommendedExcerpt(section);
		const hasLayerItems = Boolean(section && section.summary_layers && Array.isArray(section.summary_layers.items) && section.summary_layers.items.length);
		if (excerpt) {
			addSummaryItem(__('Recommended excerpt', 'npcink-toolbox'), excerpt, '', null);
		}
		if (summaryText && !excerpt && !hasLayerItems) {
			addSummaryItem(__('Hosted summary', 'npcink-toolbox'), summaryText, '', null);
		}
		if (section && section.summary_layers && Array.isArray(section.summary_layers.items)) {
			section.summary_layers.items.forEach((item) => {
				addSummaryItem(
					readableItemText(item && (item.label || item.name || item.id), __('Summary candidate', 'npcink-toolbox')),
					item && item.value,
					item && item.reason,
					item
				);
			});
		}
		return items;
	}

	function summaryQualityItems(section) {
		if (!section || !section.summary_layers || typeof section.summary_layers !== 'object') {
			return [];
		}
		if (Array.isArray(section.summary_layers.quality_notes) && section.summary_layers.quality_notes.length) {
			return section.summary_layers.quality_notes;
		}
		const items = Array.isArray(section.summary_layers.items) ? section.summary_layers.items : [];
		return items
			.filter((item) => item && (item.quality_status || item.quality_score || (Array.isArray(item.quality_issues) && item.quality_issues.length)))
			.map((item) => ({
				name: readableItemText(item.label || item.name || item.id, __('Summary candidate', 'npcink-toolbox')),
				status: item.quality_status ? formatMetaLabel(item.quality_status) : '',
				detail: [
					item.quality_score ? __('Quality score: ', 'npcink-toolbox') + String(item.quality_score) : '',
					Array.isArray(item.quality_issues) ? item.quality_issues.join(' ') : '',
				].filter(Boolean).join(' · '),
			}));
	}

	function renderCompactMetadataSection(title, items, emptyLabel, options) {
		const candidates = Array.isArray(items) ? items : [];
		const showHeading = !(options && options.hideHeading);
		const actionNote = options && options.actionNote ? options.actionNote : '';
		const description = options && options.description ? options.description : '';
		const badgeLabel = options && options.badgeLabel ? options.badgeLabel : '';
		const hideDetails = Boolean(options && options.hideDetails);
		return createElement(
			'section',
			{ className: 'npcink-toolbox-editor-support__metadata-compact-section' },
			showHeading ? createElement('h4', null, title) : null,
			description ? createElement('p', { className: 'npcink-toolbox-editor-support__muted' }, description) : null,
			candidates.length
				? createElement(
					'ul',
					{ className: 'npcink-toolbox-editor-support__metadata-compact-list' },
					candidates.slice(0, 5).map((item, index) => {
						const titleText = readableItemText(item && (item.name || item.title || item.label || item.value || item.term_id || item.id), __('Candidate', 'npcink-toolbox'));
						const detailText = hideDetails ? '' : truncateText(readableItemText(item && (item.detail || item.reason || item.excerpt || item.description || item.taxonomy || item.status), ''), 140);
						return createElement(
							'li',
							{ key: String(index) + '-' + titleText },
							badgeLabel ? createElement('span', { className: 'npcink-toolbox-editor-support__candidate-badge' }, badgeLabel) : null,
							createElement('strong', null, titleText),
							detailText ? createElement('span', null, detailText) : null,
							actionNote ? createElement('small', { className: 'npcink-toolbox-editor-support__candidate-policy' }, actionNote) : null
						);
					})
				)
				: createElement('p', { className: 'npcink-toolbox-editor-support__muted' }, emptyLabel || __('No candidates returned.', 'npcink-toolbox'))
		);
	}

	function renderMetadataUnavailableNote(message) {
		return createElement(
			'p',
			{ className: 'npcink-toolbox-editor-support__muted' },
			message || __('No Core review choices are available.', 'npcink-toolbox')
		);
	}

	function renderSummarySuggestionSection(items, controls) {
		const candidates = Array.isArray(items) ? items : [];
		const status = controls && controls.excerptApplyStatus ? controls.excerptApplyStatus : null;
		const activeIntent = controls && controls.intent ? controls.intent : '';
		const showHeading = activeIntent !== 'summary_suggestions';
		return createElement(
			'section',
			{ className: 'npcink-toolbox-editor-support__metadata-compact-section' },
			showHeading ? createElement('h4', null, __('Summary suggestions', 'npcink-toolbox')) : null,
			candidates.length
					? createElement(
						'ul',
						{ className: 'npcink-toolbox-editor-support__metadata-compact-list npcink-toolbox-editor-support__summary-candidate-list' },
						candidates.slice(0, 3).map((item, index) => {
							const excerptText = readableItemText(item && (item.detail || item.value || item.excerpt), readableItemText(item && (item.name || item.title || item.label), __('Summary candidate', 'npcink-toolbox')));
							return createElement(
								'li',
								{ key: String(index) + '-' + excerptText },
								excerptText ? createElement('span', null, excerptText) : null,
								excerptText && controls && controls.applyExcerpt ? createElement(
								'div',
								{ className: 'npcink-toolbox-editor-support__candidate-actions' },
								createElement(
									Button,
									{
										type: 'button',
										variant: status && status.excerpt === excerptText ? 'secondary' : 'primary',
										onClick: () => controls.applyExcerpt(excerptText),
									},
									status && status.excerpt === excerptText ? __('Applied', 'npcink-toolbox') : __('Use this summary', 'npcink-toolbox')
								)
							) : null
						);
					})
				)
				: createElement('p', { className: 'npcink-toolbox-editor-support__muted' }, __('No summary suggestions returned.', 'npcink-toolbox')),
			status ? createElement(Notice, { status: status.status || 'success', isDismissible: false }, status.message) : null
		);
	}

	function copyTextToClipboard(text) {
		const value = String(text || '');
		if (!value) {
			return Promise.reject(new Error('empty_clipboard_value'));
		}
		if (window.navigator && window.navigator.clipboard && window.navigator.clipboard.writeText) {
			return window.navigator.clipboard.writeText(value);
		}
		return new Promise((resolve, reject) => {
			const textarea = document.createElement('textarea');
			textarea.value = value;
			textarea.setAttribute('readonly', 'readonly');
			textarea.style.position = 'fixed';
			textarea.style.left = '-9999px';
			document.body.appendChild(textarea);
			textarea.select();
			try {
				const copied = document.execCommand && document.execCommand('copy');
				document.body.removeChild(textarea);
				copied ? resolve() : reject(new Error('copy_failed'));
			} catch (error) {
				document.body.removeChild(textarea);
				reject(error);
			}
		});
	}

	function renderInternalLinkCandidateSection(section, controls) {
		const candidates = internalLinkCandidateItems(section);
		const visibleCandidates = candidates.slice(0, 3);
		const actionControls = controls && controls.internalLinks ? controls.internalLinks : {};
		return createElement(
				'section',
				{ className: 'npcink-toolbox-editor-support__metadata-compact-section npcink-toolbox-editor-support__internal-links' },
				createElement('h4', null, __('Recommended internal links', 'npcink-toolbox')),
				createElement('p', { className: 'npcink-toolbox-editor-support__muted' }, __('Use this after the draft direction is clear. These are related existing posts to cite manually, not a duplicate-risk review.', 'npcink-toolbox')),
				visibleCandidates.length
				? createElement(
					'ul',
					{ className: 'npcink-toolbox-editor-support__internal-link-list' },
					visibleCandidates.map((item, index) => {
						const key = String(index) + '-' + readableItemText(item && item.title, __('Internal link candidate', 'npcink-toolbox'));
						const hasUrl = Boolean(item && item.targetUrl);
						return createElement(
						'li',
						{ key, className: 'npcink-toolbox-editor-support__internal-link-card' },
						createElement('strong', null, readableItemText(item && item.title, __('Internal link candidate', 'npcink-toolbox'))),
						createElement(
							'div',
							{ className: 'npcink-toolbox-editor-support__internal-link-meta' },
							item.anchorText ? createElement('span', null, __('Anchor: ', 'npcink-toolbox') + truncateText(item.anchorText, 48)) : null,
							item.score ? createElement('span', null, item.score) : null
						),
						item.reason ? createElement('p', null, truncateText(item.reason, 92)) : null,
						createElement(
							'div',
							{ className: 'npcink-toolbox-editor-support__internal-link-actions' },
							createElement(
								Button,
								{
									type: 'button',
									variant: 'primary',
									disabled: !hasUrl || Boolean(actionControls.running),
									isBusy: actionControls.running === key + ':copy',
									onClick: () => actionControls.copy && actionControls.copy(item, key),
								},
								__('Copy link', 'npcink-toolbox')
							),
							createElement(
								Button,
								{
									type: 'button',
									variant: 'tertiary',
									disabled: !hasUrl,
									onClick: () => actionControls.open && actionControls.open(item),
								},
								__('Open article', 'npcink-toolbox')
							)
						)
					);
					})
					)
						: createElement('p', { className: 'npcink-toolbox-editor-support__muted' }, __('No internal link candidates returned.', 'npcink-toolbox'))
				,
				candidates.length > visibleCandidates.length ? createElement('small', { className: 'npcink-toolbox-editor-support__candidate-policy' }, sprintf(__('Showing top %1$d of %2$d candidates.', 'npcink-toolbox'), visibleCandidates.length, candidates.length)) : null,
				createElement('small', { className: 'npcink-toolbox-editor-support__candidate-policy' }, __('Copy a reviewed link or open the article, then place the link manually in the draft.', 'npcink-toolbox')),
				actionControls.status ? createElement(Notice, { status: actionControls.status.status || 'info', isDismissible: false }, actionControls.status.message) : null
			);
	}

	function renderEvidenceDetails(blocks, controls) {
		if (!Array.isArray(blocks) || !blocks.length) {
			return null;
		}
		if (controls && controls.openEvidence) {
			return createElement(
				'div',
				{ className: 'npcink-toolbox-editor-support__metadata-evidence-entry' },
				createElement(
					Button,
					{
						type: 'button',
						variant: 'link',
						onClick: () => controls.openEvidence(blocks),
					},
					__('View evidence and diagnostics', 'npcink-toolbox')
				)
			);
		}
		return createElement(
			'details',
			{ className: 'npcink-toolbox-editor-support__metadata-evidence' },
			createElement('summary', null, __('Evidence and diagnostics', 'npcink-toolbox')),
			createElement('div', { className: 'npcink-toolbox-editor-support__metadata-evidence-body' }, blocks)
		);
	}

	function renderContentMetadataDelta(delta) {
		if (!delta || typeof delta !== 'object') {
			return null;
		}

		return createElement(
			'div',
			{ className: 'npcink-toolbox-editor-support__metadata-delta' },
			createElement('h4', null, __('Content Metadata Delta', 'npcink-toolbox')),
			renderItems(metadataDeltaItems(delta), __('No metadata diagnosis returned.', 'npcink-toolbox')),
			renderItems(metadataDeltaExcerptItems(delta), __('No excerpt delta returned.', 'npcink-toolbox')),
			createElement('h4', null, __('Delta categories', 'npcink-toolbox')),
			renderItems(delta.delta && Array.isArray(delta.delta.categories) ? delta.delta.categories : [], __('No category delta returned.', 'npcink-toolbox')),
			createElement('h4', null, __('Delta tags', 'npcink-toolbox')),
			renderItems(delta.delta && Array.isArray(delta.delta.tags) ? delta.delta.tags : [], __('No tag delta returned.', 'npcink-toolbox')),
			createElement('h4', null, __('Outcome checks', 'npcink-toolbox')),
			renderItems(metadataDeltaCheckItems(delta), __('No outcome checks returned.', 'npcink-toolbox'))
		);
	}

	function renderMetadataHandoffCheckbox(label, checked, disabled, onChange, detail) {
		return createElement(
			'label',
			{ className: 'npcink-toolbox-editor-support__metadata-choice' },
			createElement('input', {
				type: 'checkbox',
				checked: Boolean(checked),
				disabled: Boolean(disabled),
				onChange: (event) => onChange && onChange(Boolean(event.target.checked)),
			}),
			createElement(
				'span',
				null,
				createElement('strong', null, label),
				detail ? createElement('small', null, detail) : null
			)
		);
	}

	function renderMetadataTermChoices(title, items, selectedIds, disabled, onToggle) {
		if (!items.length) {
			return null;
		}

		return createElement(
			'div',
			{ className: 'npcink-toolbox-editor-support__metadata-choice-group' },
			createElement('h4', null, title),
			items.slice(0, 6).map((item) => {
				const termId = metadataTermId(item);
				return renderMetadataHandoffCheckbox(
					item.name || String(termId),
					(Array.isArray(selectedIds) ? selectedIds : []).indexOf(termId) >= 0,
					disabled,
					(checked) => onToggle(termId, checked),
					item.reason || item.taxonomy || ''
				);
			})
		);
	}

	function renderMetadataHandoffControl(section, controls) {
		if (!section || !controls) {
			return null;
		}
		const selection = controls.selection || {};
		const excerpt = metadataRecommendedExcerpt(section);
		const tags = metadataDeltaTermItems(section, 'tags');
		const categories = metadataDeltaTermItems(section, 'categories');
		const hasSelection = metadataSelectionHasValue(selection);
		const disabled = Boolean(controls.running);

		return createElement(
			'details',
			{ className: 'npcink-toolbox-editor-support__metadata-handoff' },
			createElement('summary', null, __('Core review submission', 'npcink-toolbox')),
			createElement(
				'div',
				{ className: 'npcink-toolbox-editor-support__metadata-handoff-body' },
				createElement('p', { className: 'npcink-toolbox-editor-support__muted' }, __('Select reviewed summary, category, or tag choices, then create Core review proposals. Toolbox does not approve, execute, or write metadata.', 'npcink-toolbox')),
				excerpt ? renderMetadataHandoffCheckbox(
					__('Include reviewed excerpt', 'npcink-toolbox'),
					selection.excerpt,
					disabled,
					(checked) => controls.setSelection((current) => Object.assign({}, current || {}, { excerpt: checked })),
					truncateText(excerpt, 140)
				) : null,
				renderMetadataTermChoices(
					__('Existing tags to append', 'npcink-toolbox'),
					tags,
					selection.tag_ids,
					disabled,
					(termId, checked) => controls.toggleTerm('tag_ids', termId, checked)
				),
				renderMetadataTermChoices(
					__('Existing categories to append', 'npcink-toolbox'),
					categories,
					selection.category_ids,
					disabled,
					(termId, checked) => controls.toggleTerm('category_ids', termId, checked)
				),
				createElement(
					Button,
					{
						type: 'button',
						variant: 'primary',
						isBusy: Boolean(controls.running),
						disabled: disabled || !hasSelection,
						onClick: controls.submit,
					},
					controls.running ? __('Submitting', 'npcink-toolbox') : __('Create Core review proposal', 'npcink-toolbox')
				),
				controls.error ? createElement(Notice, { status: 'error', isDismissible: false }, controls.error) : null,
				controls.result ? createElement(
					Notice,
					{ status: 'success', isDismissible: false },
					controls.result.message || __('Core proposal created. Review it in Governance Core before execution.', 'npcink-toolbox')
				) : null
			)
		);
	}

	function seoExecutionPayload(result) {
		if (!result || typeof result !== 'object') {
			return {};
		}
		const execution = result.execution && typeof result.execution === 'object' ? result.execution : {};
		const candidates = [
			execution.result && execution.result.data,
			execution.data,
			execution.result,
			execution,
		];
		for (let index = 0; index < candidates.length; index += 1) {
			const candidate = candidates[index];
			if (candidate && typeof candidate === 'object' && (candidate.changes || candidate.current || candidate.provider || candidate.updated)) {
				return candidate;
			}
		}
		return {};
	}

	function seoApplyResultItems(result) {
		const payload = seoExecutionPayload(result);
		const changes = payload && payload.changes && typeof payload.changes === 'object' ? payload.changes : {};
		const items = [];
		if (result && result.proposal_id) {
			items.push({ name: __('Core proposal', 'npcink-toolbox'), value: result.proposal_id, status: result.execution_error ? 'review_required' : 'applied' });
		}
		if (payload.provider) {
			items.push({ name: __('SEO provider', 'npcink-toolbox'), value: payload.provider, status: payload.updated ? 'applied' : 'review_required' });
		}
		if (changes.seo_title) {
			items.push({ name: __('Applied SEO title', 'npcink-toolbox'), value: changes.seo_title, status: 'applied' });
		}
		if (changes.seo_description) {
			items.push({ name: __('Applied SEO description', 'npcink-toolbox'), value: changes.seo_description, status: 'applied' });
		}
		if (result && result.execution_error) {
			items.push({ name: __('Execution status', 'npcink-toolbox'), value: result.execution_error.message || result.execution_error.code || __('Blocked by Core policy', 'npcink-toolbox'), status: 'review_required' });
		}
		return items;
	}

	function renderSeoHandoffControl(section, controls) {
		if (!section || !controls) {
			return null;
		}
		const payload = seoMetaProposalPayload(section);
		const input = payload.input || {};
		const disabled = Boolean(controls.running) || !section.proposal_ready || !input.post_id || !input.seo_title || !input.seo_description;

		return createElement(
			'details',
			{ className: 'npcink-toolbox-editor-support__metadata-handoff' },
				createElement('summary', null, __('SEO optimization apply', 'npcink-toolbox')),
				createElement(
					'div',
					{ className: 'npcink-toolbox-editor-support__metadata-handoff-body' },
					createElement('p', { className: 'npcink-toolbox-editor-support__muted' }, __('Apply the reviewed SEO title and description through Adapter/Core. Toolbox creates the governed request; Core keeps approval, preflight, execution, and audit ownership.', 'npcink-toolbox')),
				renderItems(
					[
						{ name: __('SEO title', 'npcink-toolbox'), value: input.seo_title, status: 'review_required' },
						{ name: __('SEO description', 'npcink-toolbox'), value: input.seo_description, status: 'review_required' },
						{ name: __('Target ability', 'npcink-toolbox'), value: payload.ability_id, status: 'core_proposal_required' },
					],
					__('No SEO proposal payload returned.', 'npcink-toolbox')
				),
				createElement(
					Button,
					{
						type: 'button',
						variant: 'primary',
						isBusy: Boolean(controls.running),
						disabled,
						onClick: controls.submit,
					},
						controls.running ? __('Applying', 'npcink-toolbox') : __('Apply SEO optimization', 'npcink-toolbox')
					),
					controls.error ? createElement(Notice, { status: 'error', isDismissible: false }, controls.error) : null,
					controls.result ? createElement(
						Notice,
						{ status: controls.result.execution_error ? 'warning' : 'success', isDismissible: false },
						controls.result.message || __('SEO optimization applied and recorded.', 'npcink-toolbox')
					) : null,
					controls.result ? renderItems(
						seoApplyResultItems(controls.result),
						__('No SEO application details returned.', 'npcink-toolbox')
					) : null
			)
		);
	}

	function metadataSectionSources(section) {
		const sources = section && Array.isArray(section.metadata_sources) ? section.metadata_sources : [];
		const candidateType = section && section.candidate_type ? String(section.candidate_type) : '';
		return sources.concat(candidateType ? [candidateType] : []);
	}

	function metadataSectionHasSource(section, source) {
		return metadataSectionSources(section).indexOf(source) >= 0;
	}

	function metadataHandoffHasChoices(section) {
		return Boolean(
			metadataRecommendedExcerpt(section)
			|| metadataDeltaTermItems(section, 'tags').length
			|| metadataDeltaTermItems(section, 'categories').length
		);
	}

	function renderSummaryOptimization(section, metadataHandoffControls) {
		if (!section || typeof section !== 'object') {
			return null;
		}

		const blocks = [];
		const evidenceBlocks = [];
		const summaryItems = metadataSummaryItems(section, (section.summary_candidates && section.summary_candidates.output_text) || '');
		const categoryItems = Array.isArray(section.category_candidates) && section.category_candidates.length ? section.category_candidates : metadataDeltaTermItems(section, 'categories');
		const tagItems = Array.isArray(section.tag_candidates) && section.tag_candidates.length ? section.tag_candidates : metadataDeltaTermItems(section, 'tags');
		const fullMetadataRun = metadataSectionHasSource(section, 'summary_terms_optimization');
		const mergedMetadataRun = metadataSectionHasSource(section, 'metadata_suggestions');
		const summary = section.summary_candidates && typeof section.summary_candidates === 'object' ? section.summary_candidates : {};
		const summaryText = summary.output_text || '';
		const activeIntent = metadataHandoffControls && metadataHandoffControls.intent ? metadataHandoffControls.intent : '';
		const summaryOnlyRun = activeIntent === 'summary_suggestions' || section.candidate_type === 'summary_suggestions';
		const categoryOnlyRun = activeIntent === 'category_suggestions' || section.candidate_type === 'category_suggestions';
		const tagOnlyRun = activeIntent === 'tag_suggestions' || section.candidate_type === 'tag_suggestions';
		const showFullMetadataSurface = activeIntent === 'summary_terms_optimization' || (!activeIntent && fullMetadataRun);
		if (showFullMetadataSurface) {
			blocks.push(createElement('h4', { key: 'summary-optimization-title' }, __('Metadata optimization', 'npcink-toolbox')));
		}
		if (!summaryOnlyRun && section.input_scope) {
			evidenceBlocks.push(createElement('h4', { key: 'summary-input-scope-title' }, __('Input scope', 'npcink-toolbox')));
			evidenceBlocks.push(renderItems([section.input_scope], __('No input scope returned.', 'npcink-toolbox')));
		}
		if (summary.status === 'error') {
			blocks.push(createElement('p', { key: 'summary-ai-error', className: 'npcink-toolbox-editor-support__muted' }, summary.message || __('AI summary candidates were unavailable.', 'npcink-toolbox')));
		}

		if (!categoryOnlyRun && !tagOnlyRun && (summaryItems.length || metadataSectionHasSource(section, 'summary_suggestions') || fullMetadataRun)) {
			blocks.push(renderSummarySuggestionSection(summaryItems, metadataHandoffControls));
		}

		if (summaryOnlyRun) {
			return createElement('div', { className: 'npcink-toolbox-editor-support__optimization' }, blocks);
		}
		if (categoryOnlyRun) {
			blocks.push(renderCompactMetadataSection(
				__('Recommended existing categories', 'npcink-toolbox'),
				categoryItems,
				__('No matching existing categories found.', 'npcink-toolbox'),
				{
					description: __('Existing WordPress categories appear here and can be selected for a Core review proposal.', 'npcink-toolbox'),
				}
			));
			if (metadataHandoffControls && metadataHandoffControls.showHandoff && metadataHandoffHasChoices(section)) {
				blocks.push(renderMetadataHandoffControl(section, metadataHandoffControls));
			}
			return createElement('div', { className: 'npcink-toolbox-editor-support__optimization' }, blocks);
		}
		if (tagOnlyRun) {
			blocks.push(renderCompactMetadataSection(
				__('Recommended existing tags', 'npcink-toolbox'),
				tagItems,
				__('No matching existing tags found.', 'npcink-toolbox'),
				{
					description: __('Existing WordPress tags appear here and can be selected for a Core review proposal.', 'npcink-toolbox'),
				}
			));
			if (metadataHandoffControls && metadataHandoffControls.showHandoff && metadataHandoffHasChoices(section)) {
				blocks.push(renderMetadataHandoffControl(section, metadataHandoffControls));
			} else {
				blocks.push(renderMetadataUnavailableNote(__('No existing tag choices are available for Core review.', 'npcink-toolbox')));
			}
			return createElement('div', { className: 'npcink-toolbox-editor-support__optimization' }, blocks);
		}

		const summaryQuality = summaryQualityItems(section);
		if (summaryQuality.length) {
			evidenceBlocks.push(createElement('h4', { key: 'summary-quality-title' }, __('Summary quality', 'npcink-toolbox')));
			evidenceBlocks.push(renderItems(summaryQuality, __('No summary quality notes returned.', 'npcink-toolbox')));
		}

		if (section.content_metadata_delta) {
			evidenceBlocks.push(renderContentMetadataDelta(section.content_metadata_delta));
		}

		if (categoryItems.length || metadataSectionHasSource(section, 'category_suggestions') || fullMetadataRun) {
			blocks.push(renderCompactMetadataSection(
				__('Recommended existing categories', 'npcink-toolbox'),
				categoryItems,
				__('No matching existing categories found.', 'npcink-toolbox'),
				{
					hideHeading: activeIntent === 'category_suggestions',
					description: __('Existing WordPress categories appear here and can be selected for a Core review proposal.', 'npcink-toolbox'),
				}
			));
		}
		if (tagItems.length || metadataSectionHasSource(section, 'tag_suggestions') || fullMetadataRun) {
			blocks.push(renderCompactMetadataSection(
				__('Recommended existing tags', 'npcink-toolbox'),
				tagItems,
				__('No matching existing tags found.', 'npcink-toolbox'),
				{
					hideHeading: activeIntent === 'tag_suggestions',
					description: __('Existing WordPress tags appear here and can be selected for a Core review proposal.', 'npcink-toolbox'),
				}
			));
		}
		if (section.optimization_strategy && Array.isArray(section.optimization_strategy.ranking_signals)) {
			evidenceBlocks.push(createElement('h4', { key: 'summary-strategy-title' }, __('Ranking and dedupe strategy', 'npcink-toolbox')));
			evidenceBlocks.push(renderItems(section.optimization_strategy.ranking_signals, __('No ranking strategy returned.', 'npcink-toolbox')));
		}

		if (section.discoverability) {
			evidenceBlocks.push(createElement('h4', { key: 'summary-discoverability-title' }, __('Discoverability suggestions', 'npcink-toolbox')));
			evidenceBlocks.push(renderItems(discoverabilitySuggestionItems(section.discoverability), __('No discoverability candidates returned.', 'npcink-toolbox')));
		}

		if (section.related_content) {
			evidenceBlocks.push(createElement('h4', { key: 'summary-related-title' }, __('Related Site Knowledge', 'npcink-toolbox')));
			evidenceBlocks.push(renderItems(extractKnowledgeItems(section.related_content), __('No related content returned.', 'npcink-toolbox')));
		}

		if (Array.isArray(section.risk_notes) && section.risk_notes.length) {
			evidenceBlocks.push(createElement('h4', { key: 'summary-risk-title' }, __('Review notes', 'npcink-toolbox')));
			evidenceBlocks.push(renderItems(section.risk_notes.map((note) => ({ name: note })), __('No review notes returned.', 'npcink-toolbox')));
		}

		if (section.review_metrics) {
			evidenceBlocks.push(createElement('h4', { key: 'summary-metrics-title' }, __('Review metrics', 'npcink-toolbox')));
			evidenceBlocks.push(renderItems(section.review_metrics.items || [], __('No review metrics returned.', 'npcink-toolbox')));
		}

			if (section.handoff_preview) {
				if (Array.isArray(section.handoff_preview.core_handoff_candidates)) {
					evidenceBlocks.push(createElement('h4', { key: 'summary-core-handoff-candidates-title' }, __('Core handoff candidates', 'npcink-toolbox')));
					evidenceBlocks.push(renderItems(section.handoff_preview.core_handoff_candidates, __('No Core handoff candidates returned.', 'npcink-toolbox')));
				}
				evidenceBlocks.push(createElement('h4', { key: 'summary-handoff-preview-title' }, __('Handoff preview', 'npcink-toolbox')));
				evidenceBlocks.push(renderItems((section.handoff_preview.next_steps || []).map((step) => ({ name: step })), __('No handoff preview returned.', 'npcink-toolbox')));
		}

		if (metadataHandoffControls && metadataHandoffControls.showHandoff && (metadataHandoffHasChoices(section) || mergedMetadataRun)) {
			blocks.push(renderMetadataHandoffControl(section, metadataHandoffControls));
		}
		blocks.push(renderEvidenceDetails(evidenceBlocks, metadataHandoffControls));

		return createElement('div', { className: 'npcink-toolbox-editor-support__optimization' }, blocks);
	}

	function renderResult(payload, metadataHandoffControls) {
		if (!payload || typeof payload !== 'object') {
			return null;
		}

			const sections = payload.sections || {};
			const blocks = [];

			if (sections.progressive_recommendations) {
				const section = sections.progressive_recommendations;
				blocks.push(createElement('h4', { key: 'progressive-title' }, __('Fast local recommendations', 'npcink-toolbox')));
				blocks.push(renderItems(section.recommendation_candidates || [], __('No local recommendation candidates returned.', 'npcink-toolbox')));
				if (section.preflight_checks) {
					blocks.push(createElement('h4', { key: 'progressive-checks-title' }, __('Local preflight snapshot', 'npcink-toolbox')));
					blocks.push(renderItems(section.preflight_checks.items || [], __('No local preflight checks returned.', 'npcink-toolbox')));
				}
			}

			if (sections.writing_support) {
				blocks.push(createElement('h4', { key: 'writing-support-title' }, __('Related existing posts: decide what to do next', 'npcink-toolbox')));
				blocks.push(createElement('p', { key: 'writing-support-help', className: 'npcink-toolbox-editor-support__muted' }, __('This is not the internal-link tool yet. First compare these older posts with the draft: avoid repeating them, add a new angle, borrow facts manually, or run internal links later if you decide to cite one.', 'npcink-toolbox')));
				blocks.push(renderItems(extractWritingSupportItems(sections.writing_support), __('No related existing posts were found for this draft.', 'npcink-toolbox')));
			}

			if (sections.article_checkup) {
				blocks.push(createElement('h4', { key: 'article-checkup-title' }, __('Article checkup', 'npcink-toolbox')));
				blocks.push(createElement('p', { key: 'article-checkup-help', className: 'npcink-toolbox-editor-support__muted' }, __('Review these full-draft issues manually. Toolbox points to paragraphs and editing direction, but does not rewrite or insert text.', 'npcink-toolbox')));
				blocks.push(renderItems(articleCheckupItems(sections.article_checkup), __('No high-confidence local article checkup issues were found.', 'npcink-toolbox')));
			}

			if (sections.title_suggestions) {
				blocks.push(renderTitleSuggestionSection(titleSuggestionItems(sections.title_suggestions), metadataHandoffControls));
				blocks.push(renderHostedAiDiagnostics(sections.title_suggestions));
			}

		if (sections.article_outline) {
			blocks.push(createElement('h4', { key: 'article-outline-title' }, __('Outline suggestions', 'npcink-toolbox')));
			blocks.push(renderOutlineSuggestionSection(sections.article_outline));
			blocks.push(renderHostedAiDiagnostics(sections.article_outline));
		}

			if (sections.polish_notes) {
				blocks.push(createElement('h4', { key: 'polish-notes-title' }, __('Paragraph check', 'npcink-toolbox')));
				blocks.push(renderItems(paragraphCheckItems(sections.polish_notes), __('No paragraph check notes returned.', 'npcink-toolbox')));
				blocks.push(renderHostedAiDiagnostics(sections.polish_notes));
			}

				const hasPreflightReview = Boolean(sections.pre_publish_review);
				const showImageAltInsideDiscoverability = Boolean(sections.image_alt_suggestions && sections.discoverability && sections.discoverability.candidate_suggestions && metadataHandoffControls && metadataHandoffControls.intent === 'discoverability');

				if (sections.image_alt_suggestions && !showImageAltInsideDiscoverability) {
					if (metadataHandoffControls && metadataHandoffControls.intent !== 'image_alt_suggestions') {
						blocks.push(createElement('h4', { key: 'image-alt-suggestions-title' }, __('Image ALT suggestions', 'npcink-toolbox')));
					}
					blocks.push(renderItems(imageAltSuggestionItems(sections.image_alt_suggestions), __('No image ALT suggestions returned.', 'npcink-toolbox')));
					blocks.push(renderHostedAiDiagnostics(sections.image_alt_suggestions));
				}

				if (hasPreflightReview) {
					blocks.push(renderPreflightSummaryPanel(payload, metadataHandoffControls));
				}

			if (sections.checks && !hasPreflightReview) {
				blocks.push(createElement('h4', { key: 'checks-title' }, __('Checks', 'npcink-toolbox')));
				blocks.push(renderItems(sections.checks.items || [], __('No checks returned.', 'npcink-toolbox')));
			}

		if (sections.summary_terms_optimization) {
			blocks.push(renderSummaryOptimization(sections.summary_terms_optimization, metadataHandoffControls));
		}

		if (sections.taxonomy_terms) {
			blocks.push(createElement('h4', { key: 'terms-title' }, __('Term candidates', 'npcink-toolbox')));
			blocks.push(renderItems(sections.taxonomy_terms.items || [], __('No matching existing terms found.', 'npcink-toolbox')));
		}

		if (sections.internal_links) {
			blocks.push(renderInternalLinkCandidateSection(sections.internal_links, metadataHandoffControls));
		}

		if (sections.site_knowledge && !sections.internal_links) {
			blocks.push(createElement('h4', { key: 'links-title' }, __('Site Knowledge', 'npcink-toolbox')));
			blocks.push(renderItems(extractKnowledgeItems(sections.site_knowledge), __('No related content returned.', 'npcink-toolbox')));
		}

				if (sections.duplicate_check && !hasPreflightReview) {
					blocks.push(createElement('h4', { key: 'duplicate-title' }, __('Duplicate check', 'npcink-toolbox')));
					blocks.push(createElement('p', { key: 'duplicate-help', className: 'npcink-toolbox-editor-support__muted' }, __('This replaces the old separate related-post check: review overlap here, then use internal links only when you decide to cite an existing post.', 'npcink-toolbox')));
					blocks.push(renderItems(duplicateRiskItems(sections.duplicate_check), __('No duplicate-risk candidates returned.', 'npcink-toolbox')));
				}

		if (sections.image_candidates) {
			blocks.push(createElement('h4', { key: 'images-title' }, __('Image candidates', 'npcink-toolbox')));
			blocks.push(renderItems(extractImageItems(sections.image_candidates), __('No image candidates returned.', 'npcink-toolbox')));
		}

				if (sections.discoverability && sections.discoverability.candidate_suggestions && !hasPreflightReview) {
					blocks.push(renderDiscoverabilityOptimizationSection(sections.discoverability, sections.seo_handoff, metadataHandoffControls, sections.image_alt_suggestions));
				}

			if (sections.seo_handoff && !hasPreflightReview) {
				const hasDiscoverabilityPanel = Boolean(sections.discoverability && sections.discoverability.candidate_suggestions);
				if (!hasDiscoverabilityPanel) {
					blocks.push(createElement('h4', { key: 'seo-handoff-title' }, __('SEO handoff', 'npcink-toolbox')));
					blocks.push(renderItems(seoHandoffItems(sections.seo_handoff), __('No SEO handoff preview returned.', 'npcink-toolbox')));
					blocks.push(renderSeoHandoffControl(sections.seo_handoff, metadataHandoffControls && metadataHandoffControls.seoHandoff));
				}
			}

		blocks.push(renderContentSupportFeedbackControls(payload, metadataHandoffControls));

		return createElement('div', { className: 'npcink-toolbox-editor-support__result' }, blocks);
	}

	function isMetadataIntent(intent) {
		return ['summary_suggestions', 'category_suggestions', 'tag_suggestions', 'summary_terms_optimization'].indexOf(intent) >= 0;
	}

	function hasMergeableMetadataResult(result) {
		const sections = result && result.sections && typeof result.sections === 'object' ? result.sections : null;
		if (!sections || !sections.summary_terms_optimization) {
			return false;
		}
		return Object.keys(sections).every((key) => key === 'summary_terms_optimization');
	}

	function mergeUniqueByKey(currentItems, incomingItems, keyFn) {
		const seen = {};
		const merged = [];
		(Array.isArray(currentItems) ? currentItems : []).concat(Array.isArray(incomingItems) ? incomingItems : []).forEach((item, index) => {
			const key = keyFn(item, index);
			if (!key || seen[key]) {
				return;
			}
			seen[key] = true;
			merged.push(item);
		});
		return merged;
	}

	function mergeMetadataDelta(currentDelta, incomingDelta) {
		const current = currentDelta && typeof currentDelta === 'object' ? currentDelta : {};
		const incoming = incomingDelta && typeof incomingDelta === 'object' ? incomingDelta : {};
		const currentChange = current.delta && typeof current.delta === 'object' ? current.delta : {};
		const incomingChange = incoming.delta && typeof incoming.delta === 'object' ? incoming.delta : {};
		return Object.assign({}, current, incoming, {
			delta: Object.assign({}, currentChange, incomingChange, {
				excerpt: incomingChange.excerpt && incomingChange.excerpt.recommended ? incomingChange.excerpt : (currentChange.excerpt || incomingChange.excerpt || {}),
				categories: mergeUniqueByKey(currentChange.categories, incomingChange.categories, (item) => 'category:' + metadataTermId(item)),
				tags: mergeUniqueByKey(currentChange.tags, incomingChange.tags, (item) => 'tag:' + metadataTermId(item)),
				new_term_candidates: mergeUniqueByKey(currentChange.new_term_candidates, incomingChange.new_term_candidates, (item, index) => 'new:' + String((item && item.name) || index).toLowerCase()),
			}),
		});
	}

	function mergeMetadataSection(currentSection, incomingSection) {
		const current = currentSection && typeof currentSection === 'object' ? currentSection : {};
		const incoming = incomingSection && typeof incomingSection === 'object' ? incomingSection : {};
		const currentSummary = current.summary_layers && typeof current.summary_layers === 'object' ? current.summary_layers : {};
		const incomingSummary = incoming.summary_layers && typeof incoming.summary_layers === 'object' ? incoming.summary_layers : {};
		const currentNewTerms = current.proposed_new_terms && typeof current.proposed_new_terms === 'object' ? current.proposed_new_terms : {};
		const incomingNewTerms = incoming.proposed_new_terms && typeof incoming.proposed_new_terms === 'object' ? incoming.proposed_new_terms : {};
		const currentType = current.candidate_type ? String(current.candidate_type) : '';
		const incomingType = incoming.candidate_type ? String(incoming.candidate_type) : '';
		const sources = mergeUniqueByKey(
			(Array.isArray(current.metadata_sources) ? current.metadata_sources : []).concat(currentType ? [currentType] : []),
			incomingType ? [incomingType] : [],
			(item) => String(item)
		);
		return Object.assign({}, current, incoming, {
			candidate_type: currentType && incomingType && currentType !== incomingType ? 'metadata_suggestions' : (incomingType || currentType || 'metadata_suggestions'),
			metadata_sources: sources,
			summary_layers: Object.assign({}, currentSummary, incomingSummary, {
				items: mergeUniqueByKey(currentSummary.items, incomingSummary.items, (item, index) => 'summary:' + String((item && (item.id || item.label || item.value)) || index)),
			}),
			category_candidates: mergeUniqueByKey(current.category_candidates, incoming.category_candidates, (item) => 'category:' + metadataTermId(item)),
			tag_candidates: mergeUniqueByKey(current.tag_candidates, incoming.tag_candidates, (item) => 'tag:' + metadataTermId(item)),
			proposed_new_terms: Object.assign({}, currentNewTerms, incomingNewTerms, {
				items: mergeUniqueByKey(currentNewTerms.items, incomingNewTerms.items, (item, index) => 'new:' + String((item && item.name) || index).toLowerCase()),
			}),
			content_metadata_delta: mergeMetadataDelta(current.content_metadata_delta, incoming.content_metadata_delta),
		});
	}

	function mergeContentSupportResult(currentResult, incomingResult, intent) {
		if (!isMetadataIntent(intent)) {
			return incomingResult;
		}
		const incomingSection = incomingResult && incomingResult.sections ? incomingResult.sections.summary_terms_optimization : null;
		if (!incomingSection) {
			return incomingResult;
		}
		const currentSections = currentResult && currentResult.sections ? currentResult.sections : {};
		const currentSection = currentSections.summary_terms_optimization || {};
		return Object.assign({}, incomingResult, {
			intent: 'summary_terms_optimization',
			sections: Object.assign({}, incomingResult.sections || {}, {
				summary_terms_optimization: mergeMetadataSection(currentSection, incomingSection),
			}),
		});
	}

	function ContentSupportControls() {
		const postContext = usePostContext();
		const [running, setRunning] = useState('');
		const [result, setResult] = useState(null);
		const [error, setError] = useState('');
		const [supportView, setSupportView] = useState('menu');
		const [contextualResult, setContextualResult] = useState(false);
		const [activeFlowIntent, setActiveFlowIntent] = useState('');
		const [imageModalOpen, setImageModalOpen] = useState(false);
			const [imageRunning, setImageRunning] = useState('');
			const [imageCompletionRunning, setImageCompletionRunning] = useState(false);
			const [imageResult, setImageResult] = useState(null);
			const [imageSourceResult, setImageSourceResult] = useState(null);
			const [imageGenerationResult, setImageGenerationResult] = useState(null);
			const [imageError, setImageError] = useState('');
		const [imageGuidance, setImageGuidance] = useState('');
			const [imageQuery, setImageQuery] = useState('');
			const [imageSearchMode, setImageSearchMode] = useState('source');
			const imageSearchModeRef = useRef('source');
			const imageSourceRequestSeqRef = useRef(0);
			imageSearchModeRef.current = imageSearchMode;
			const [imageMode, setImageMode] = useState('featured');
		const [aiImageAspectRatio, setAiImageAspectRatio] = useState('16:9');
		const [aiImageResolution, setAiImageResolution] = useState('high');
		const [aiImageCandidateCount, setAiImageCandidateCount] = useState('1');
		const [imagePicker, setImagePicker] = useState(() => normalizeImagePickerOptions({ mode: 'featured' }));
		const [selectedImage, setSelectedImage] = useState(null);
		const [selectedImageSeo, setSelectedImageSeo] = useState(null);
		const [imagePreviewLightbox, setImagePreviewLightbox] = useState(null);
		const [imageAdoptionRunning, setImageAdoptionRunning] = useState(false);
		const [imageAdoptionAction, setImageAdoptionAction] = useState('');
		const [imageAdoptionResult, setImageAdoptionResult] = useState(null);
		const [imageAdoptionError, setImageAdoptionError] = useState('');
		const [imageFeedbackRunning, setImageFeedbackRunning] = useState('');
		const [imageFeedbackStatus, setImageFeedbackStatus] = useState(null);
		const [imageRegenerationRunning, setImageRegenerationRunning] = useState('');
		const [metadataHandoffSelection, setMetadataHandoffSelection] = useState({});
		const [metadataHandoffRunning, setMetadataHandoffRunning] = useState(false);
		const [metadataHandoffResult, setMetadataHandoffResult] = useState(null);
		const [metadataHandoffError, setMetadataHandoffError] = useState('');
		const [seoHandoffRunning, setSeoHandoffRunning] = useState(false);
		const [seoHandoffResult, setSeoHandoffResult] = useState(null);
		const [seoHandoffError, setSeoHandoffError] = useState('');
		const [contentFeedbackRunning, setContentFeedbackRunning] = useState('');
		const [contentFeedbackStatus, setContentFeedbackStatus] = useState(null);
		const [internalLinkRunning, setInternalLinkRunning] = useState('');
		const [internalLinkStatus, setInternalLinkStatus] = useState(null);
				const [flowInstructions, setFlowInstructions] = useState({});
				const [titleApplyStatus, setTitleApplyStatus] = useState(null);
				const [excerptApplyStatus, setExcerptApplyStatus] = useState(null);
				const [slugApplyStatus, setSlugApplyStatus] = useState(null);
				const [evidenceModalBlocks, setEvidenceModalBlocks] = useState(null);
				const [preflightModalOpen, setPreflightModalOpen] = useState(false);
				const [progressiveResult, setProgressiveResult] = useState(null);
				const [progressiveStatus, setProgressiveStatus] = useState(null);
			const [progressiveLoadedKey, setProgressiveLoadedKey] = useState('');
			const [progressivePanelOpen, setProgressivePanelOpen] = useState(false);
			const [paragraphReviewContext, setParagraphReviewContext] = useState(null);
			const progressiveKey = progressiveRecommendationKey(postContext);
			const progressiveMountedRef = useRef(false);
			const progressiveRequestSeqRef = useRef(0);
			const progressiveCurrentKeyRef = useRef(progressiveKey);
			progressiveCurrentKeyRef.current = progressiveKey;

			useEffect(() => {
				progressiveMountedRef.current = true;
				return () => {
					progressiveMountedRef.current = false;
					progressiveRequestSeqRef.current += 1;
				};
			}, []);

			useEffect(() => {
				if (!progressiveKey || progressiveLoadedKey === progressiveKey) {
					return undefined;
				}
				const timer = window.setTimeout(() => {
					runProgressivePrefetch(progressiveKey, false);
				}, progressiveRecommendationDelay(postContext));
				return () => window.clearTimeout(timer);
			}, [progressiveKey, progressiveLoadedKey]);

			useEffect(() => {
				setProgressivePanelOpen(false);
			}, [progressiveKey]);

			useEffect(() => {
				const images = extractImageCandidates(imageResult);
				if (!imageModalOpen || imageRunning || !images.length) {
					return;
				}
				const currentKey = selectedImage ? imageStableKey(selectedImage, 0) : '';
				const currentStillVisible = currentKey && images.some((image, index) => imageStableKey(image, index) === currentKey);
				if (currentStillVisible) {
					return;
				}
				const firstImage = images[0];
				const activePicker = normalizeImagePickerOptions(imagePicker || { mode: imageMode });
				const seoContext = imagePickerRequestContext(postContext, activePicker);
				setSelectedImage(firstImage);
				setSelectedImageSeo(buildImageSeoFields(firstImage, seoContext));
				setImageAdoptionResult(null);
				setImageAdoptionError('');
				resetImageFeedbackState();
			}, [imageResult, imageModalOpen, imageRunning, selectedImage, imagePicker, imageMode, postContext]);

				useEffect(() => {
					function handleParagraphImageRequest(event) {
						const detail = event && event.detail && typeof event.detail === 'object' ? event.detail : {};
						openImageSourcePicker({ mode: 'paragraph', context: detail });
					}

					function handleParagraphReviewRequest(event) {
						const detail = event && event.detail && typeof event.detail === 'object' ? event.detail : {};
						openContentSupportSidebar();
						if (typeof window !== 'undefined') {
							window.NpcinkToolboxPendingParagraphReview = null;
						}
						const selected = String(detail.selected_text || detail.selected_block_text || '').trim();
						if (!selected) {
							setSupportView('result');
							setContextualResult(true);
							setActiveFlowIntent('polish_notes');
							setResult(null);
							setError(__('Select paragraph text before running a paragraph check.', 'npcink-toolbox'));
							return;
						}
						const reviewContext = {
							context_scope: 'selected_text',
							selected_text: truncateText(String(detail.selected_text || selected), 700),
							selected_block_text: truncateText(String(detail.selected_block_text || selected), 700),
							selected_block_name: detail.selected_block_name || '',
						};
						setParagraphReviewContext(reviewContext);
						runFlow('polish_notes', {
							forceRegenerate: true,
							contextualResult: true,
							contextOverride: reviewContext,
						});
					}

					function handleImageSourcePickerRequest(event) {
						const detail = event && event.detail && typeof event.detail === 'object' ? event.detail : {};
						openImageSourcePicker(detail);
					}

				if (typeof window === 'undefined' || !window.addEventListener) {
					return undefined;
				}

					window.addEventListener(PARAGRAPH_IMAGE_EVENT, handleParagraphImageRequest);
					window.addEventListener(PARAGRAPH_REVIEW_EVENT, handleParagraphReviewRequest);
					window.addEventListener(IMAGE_SOURCE_PICKER_EVENT, handleImageSourcePickerRequest);
					if (window.NpcinkToolboxPendingParagraphReview) {
						const pendingParagraphReview = window.NpcinkToolboxPendingParagraphReview;
						window.setTimeout(() => handleParagraphReviewRequest({ detail: pendingParagraphReview }), 0);
					}
					return () => {
						window.removeEventListener(PARAGRAPH_IMAGE_EVENT, handleParagraphImageRequest);
						window.removeEventListener(PARAGRAPH_REVIEW_EVENT, handleParagraphReviewRequest);
						window.removeEventListener(IMAGE_SOURCE_PICKER_EVENT, handleImageSourcePickerRequest);
					};
				});

			async function runProgressivePrefetch(keyOverride, force) {
				const key = keyOverride || progressiveRecommendationKey(postContext);
				if (!force && progressiveLoadedKey === key) {
					return;
				}
				const requestSeq = progressiveRequestSeqRef.current + 1;
				progressiveRequestSeqRef.current = requestSeq;
				const shouldApplyProgressiveResult = () => progressiveMountedRef.current && progressiveRequestSeqRef.current === requestSeq && progressiveCurrentKeyRef.current === key;
				setProgressiveStatus({ status: 'loading', message: __('Preparing local suggestions...', 'npcink-toolbox') });
				try {
					const flowResult = await postJsonWithTimeout(
						'editor/content-support',
						progressiveRecommendationPayload(postContext),
						PROGRESSIVE_RECOMMENDATION_TIMEOUT_MS
					);
					if (!shouldApplyProgressiveResult()) {
						return;
					}
					setProgressiveResult(flowResult);
					setProgressiveLoadedKey(key);
					setProgressiveStatus({
						status: 'success',
						message: __('Local suggestions are ready.', 'npcink-toolbox'),
					});
				} catch (requestError) {
					if (!shouldApplyProgressiveResult()) {
						return;
					}
					setProgressiveLoadedKey(key);
					setProgressiveStatus({
						status: requestError && requestError.code === 'npcink_toolbox_progressive_timeout' ? 'warning' : 'error',
						message: requestError && requestError.message ? requestError.message : __('Local suggestions are unavailable.', 'npcink-toolbox'),
					});
				}
			}

			function openProgressiveRecommendation(intent) {
				if (intent === 'progressive_recommendations') {
					if (progressiveResult) {
						setResult(progressiveResult);
						setActiveFlowIntent('progressive_recommendations');
						setSupportView('result');
						setContextualResult(false);
						setError('');
						return;
					}
					runFlow('progressive_recommendations', { timeoutMs: PROGRESSIVE_RECOMMENDATION_TIMEOUT_MS });
					return;
				}
				runFlow(intent);
			}

			function openProgressivePanel() {
				setProgressivePanelOpen(true);
				if (!progressiveResult && (!progressiveStatus || progressiveStatus.status !== 'loading')) {
					runProgressivePrefetch(progressiveRecommendationKey(postContext), true);
				}
			}

				async function runFlow(intent, options) {
					const runOptions = options && typeof options === 'object' ? options : {};
					if (intent === 'image_candidates') {
						openImageSourcePicker({ mode: 'featured' });
						return;
					}
					const displayIntent = runOptions.resultIntent || intent;

					setSupportView('result');
					setContextualResult(Boolean(runOptions.contextualResult) || (intent === 'polish_notes' && Boolean(paragraphReviewContext)));
					setActiveFlowIntent(displayIntent);
					setRunning(intent);
						setError('');
						setPreflightModalOpen(false);
						if (runOptions.preserveResult) {
							setResult((current) => current);
						} else if (isMetadataIntent(intent)) {
							setResult((current) => hasMergeableMetadataResult(current) ? current : null);
						} else {
						setResult(null);
					setMetadataHandoffSelection({});
				}
				setMetadataHandoffResult(null);
				setMetadataHandoffError('');
				setSeoHandoffResult(null);
				setSeoHandoffError('');
				setContentFeedbackRunning('');
				setContentFeedbackStatus(null);
				setInternalLinkRunning('');
				setInternalLinkStatus(null);
					setTitleApplyStatus(null);
					setExcerptApplyStatus(null);
					setSlugApplyStatus(null);
					try {
						const userInstruction = flowAcceptsUserInstruction(intent) ? String(flowInstructions[intent] || '').trim() : '';
						const shouldForceRegenerate = Boolean(runOptions.forceRegenerate);
						const fallbackContextOverride = intent === 'polish_notes' && paragraphReviewContext && typeof paragraphReviewContext === 'object' ? paragraphReviewContext : {};
						const runContext = Object.assign(
							{},
							postContext,
							fallbackContextOverride,
							runOptions.contextOverride && typeof runOptions.contextOverride === 'object' ? runOptions.contextOverride : {}
						);
						const payload = Object.assign({}, runContext, {
							intent,
							category_ids: Array.isArray(runContext.category_ids) ? runContext.category_ids.join(',') : '',
							tag_ids: Array.isArray(runContext.tag_ids) ? runContext.tag_ids.join(',') : '',
							media_items: Array.isArray(runContext.media_items) ? runContext.media_items : [],
							generation_variant: ['title_suggestions', 'article_outline', 'polish_notes'].indexOf(intent) >= 0 || shouldForceRegenerate ? String(Date.now()) : '',
							force_regenerate: shouldForceRegenerate,
							user_instruction: userInstruction,
					});
					if (intent === 'summary_suggestions') {
						payload.summary_generation_mode = runOptions.summaryGenerationMode === 'full_context' ? 'full_context' : 'fast_brief';
					}
					if (intent === 'title_suggestions') {
						payload.context_scope = 'full_article';
						payload.selected_text = '';
						payload.selected_block_text = '';
						payload.selected_block_name = '';
					}
						const flowResult = runOptions.timeoutMs
							? await postJsonWithTimeout('editor/content-support', payload, runOptions.timeoutMs)
							: await postJson('editor/content-support', payload);
						setResult((current) => {
							const mergedFlowResult = mergeContentSupportResult(current, flowResult, intent);
							if (!runOptions.preserveResult || !current || !current.sections) {
								return displayIntent === intent ? mergedFlowResult : Object.assign({}, mergedFlowResult, { intent: displayIntent });
							}
							return Object.assign({}, current, mergedFlowResult, {
								intent: displayIntent,
								sections: Object.assign({}, current.sections || {}, mergedFlowResult.sections || {}),
							});
						});
					if (intent === 'publish_preflight' && runOptions.reopenPreflightModal) {
						setPreflightModalOpen(true);
					}
					if (intent === 'progressive_recommendations') {
						setProgressiveResult(flowResult);
						setProgressiveLoadedKey(progressiveRecommendationKey(postContext));
						setProgressiveStatus({ status: 'success', message: __('Local suggestions are ready.', 'npcink-toolbox') });
					}
				} catch (requestError) {
					setError(requestError && requestError.message ? requestError.message : __('Request failed.', 'npcink-toolbox'));
					setSupportView('result');
				} finally {
					setRunning('');
				}
			}

			async function submitContentSupportFeedback(option) {
			if (!result) {
				setContentFeedbackStatus({ status: 'error', message: __('Run a content support flow first.', 'npcink-toolbox') });
				return;
			}
			const localProposalId = extractProposalId([metadataHandoffResult, seoHandoffResult, result], 0);
			const intent = activeFlowIntent || (result && result.intent) || '';
			setContentFeedbackRunning(option.label);
			setContentFeedbackStatus(null);
			try {
				const receipt = await postJson(
					'agent-feedback',
					editorContentSupportFeedbackPayload(result, intent, option.outcome, option.labels, localProposalId)
				);
				setContentFeedbackStatus({
					status: 'success',
					message: receipt && receipt.accepted_for_eval
						? __('Feedback accepted for Cloud eval. Core approval and WordPress writes remain local.', 'npcink-toolbox')
						: __('Feedback sent. Core approval and WordPress writes remain local.', 'npcink-toolbox'),
				});
			} catch (requestError) {
				setContentFeedbackStatus({
					status: 'error',
					message: (requestError && requestError.message ? requestError.message : __('Could not send content feedback.', 'npcink-toolbox')) + ' ' + __('Core approval and WordPress writes remain local.', 'npcink-toolbox'),
				});
			} finally {
				setContentFeedbackRunning('');
			}
		}

		function submitContentImplicitFeedback(action, outcome, labels) {
			if (!result) {
				return;
			}
			const localProposalId = extractProposalId([metadataHandoffResult, seoHandoffResult, result], 0);
			const intent = activeFlowIntent || (result && result.intent) || '';
			submitImplicitAgentFeedback(
				editorContentImplicitFeedbackPayload(result, intent, action, outcome, labels, localProposalId)
			);
		}

		async function copyInternalLinkCandidate(candidate, key) {
			const url = String(candidate && candidate.targetUrl ? candidate.targetUrl : '').trim();
			if (!url) {
				setInternalLinkStatus({ status: 'error', message: __('This candidate has no URL to copy.', 'npcink-toolbox') });
				return;
			}
			setInternalLinkRunning(String(key || 'link') + ':copy');
			try {
				await copyTextToClipboard(url);
				submitContentImplicitFeedback('internal_link_copy', 'accepted', ['evidence_useful', 'operator_confidence_high']);
				setInternalLinkStatus({ status: 'success', message: __('Link copied. Paste it where the reviewed anchor belongs.', 'npcink-toolbox') });
			} catch (copyError) {
				setInternalLinkStatus({ status: 'error', message: __('Could not copy the link. Open the article and copy it manually.', 'npcink-toolbox') });
			} finally {
				setInternalLinkRunning('');
			}
		}

		function openInternalLinkCandidate(candidate) {
			const url = String(candidate && candidate.targetUrl ? candidate.targetUrl : '').trim();
			if (!url) {
				setInternalLinkStatus({ status: 'error', message: __('This candidate has no article URL to open.', 'npcink-toolbox') });
				return;
			}
			submitContentImplicitFeedback('internal_link_open', 'accepted', ['evidence_useful', 'operator_confidence_high']);
			window.open(url, '_blank', 'noopener,noreferrer');
		}

			function resetImageFeedbackState() {
				setImageFeedbackRunning('');
				setImageFeedbackStatus(null);
			}

			function setImageResultForSearchMode(mode, resultValue, forceVisible) {
				const normalizedMode = mode === 'generate' ? 'generate' : 'source';
				if (normalizedMode === 'generate') {
					setImageGenerationResult(resultValue || null);
				} else {
					setImageSourceResult(resultValue || null);
				}
				if (forceVisible || imageSearchModeRef.current === normalizedMode) {
					setImageResult(resultValue || null);
				}
			}

				function imageResultForSearchMode(mode) {
					return mode === 'generate' ? imageGenerationResult : imageSourceResult;
				}

				function completeImageSourceCandidates(requestPayload, cacheKey, initialResult, requestSeq) {
					if (!imageCandidateResultNeedsCompletion(initialResult)) {
						if (extractImageCandidates(initialResult).length >= IMAGE_CANDIDATE_TARGET_COUNT) {
							writeCachedImageResult(cacheKey, initialResult);
						}
						setImageCompletionRunning(false);
						return;
					}
					setImageCompletionRunning(true);
					postJson('image-candidates', Object.assign({}, requestPayload, {
						latency_mode: 'complete',
					})).then((completeResult) => {
						if (imageSourceRequestSeqRef.current !== requestSeq) {
							return;
						}
						const mergedResult = mergeImageCandidateResults(initialResult, completeResult);
						writeCachedImageResult(cacheKey, mergedResult);
						setImageResultForSearchMode('source', mergedResult);
					}).catch(() => {
						if (imageSourceRequestSeqRef.current === requestSeq) {
							writeCachedImageResult(cacheKey, initialResult);
						}
					}).finally(() => {
						if (imageSourceRequestSeqRef.current === requestSeq) {
							setImageCompletionRunning(false);
						}
					});
				}

		async function runAutoImageRecommendations(modeOverride, contextOverride, pickerOverride, forceRefresh) {
			const activePicker = normalizeImagePickerOptions(pickerOverride || imagePicker || { mode: imageMode });
			const activeImageMode = modeOverride || activePicker.mode;
			const imageContext = imagePickerRequestContext(postContext, activePicker, contextOverride || activePicker.context);
				const operatorInstruction = String(imageQuery || '').trim();
				const cacheKey = imageSearchCacheKey('auto', activePicker, operatorInstruction, imageContext);
				const cachedResult = forceRefresh ? null : readCachedImageResult(cacheKey);
					if (!hasScopedImageContext(imageContext, activePicker)) {
						imageSourceRequestSeqRef.current += 1;
						setImageRunning('');
						setImageCompletionRunning(false);
						setImageResultForSearchMode('source', null, true);
						setImageError('');
						setImageGuidance(imageMissingContextMessage(activePicker));
						return;
					}
					if (cachedResult) {
						setImageRunning('');
						setImageCompletionRunning(false);
						setImageError('');
						setImageGuidance('');
						setImageResultForSearchMode('source', cachedResult, true);
					setSelectedImage(null);
					setSelectedImageSeo(null);
					setImagePreviewLightbox(null);
					setImageAdoptionResult(null);
					setImageAdoptionError('');
					resetImageFeedbackState();
						return;
					}
					const requestSeq = imageSourceRequestSeqRef.current + 1;
					imageSourceRequestSeqRef.current = requestSeq;
					setImageRunning('auto');
					setImageCompletionRunning(false);
					setImageError('');
					setImageGuidance('');
					setImageResultForSearchMode('source', null, true);
				setSelectedImage(null);
				setSelectedImageSeo(null);
			setImagePreviewLightbox(null);
			setImageAdoptionResult(null);
			setImageAdoptionError('');
			resetImageFeedbackState();
				try {
					const query = imageFastSearchQuery(postContext, operatorInstruction, activePicker.context, activePicker);
					const refreshVariant = forceRefresh ? imageRefreshVariant() : '';
					let requestPayload = {
						query,
						provider: 'auto',
						per_page: 9,
					latency_mode: 'fast_first',
					image_mode: activePicker.imageUse,
						user_instruction: operatorInstruction,
						refresh_variant: refreshVariant,
						visual_context: buildImageVisualContext(postContext, activeImageMode, operatorInstruction, imagePickerContextOverride(activePicker), activePicker.imageUse, refreshVariant),
					};
					let result = await postJson('image-candidates', requestPayload);
					const fallbackQueries = !extractImageCandidates(result).length ? imageAutoFallbackQueries(result, query, activePicker) : [];
					for (let fallbackIndex = 0; fallbackIndex < fallbackQueries.length; fallbackIndex += 1) {
						const fallbackQuery = fallbackQueries[fallbackIndex];
						if (!fallbackQuery) {
							continue;
						}
						const fallbackPayload = {
							query: fallbackQuery,
							provider: 'auto',
							per_page: 9,
						latency_mode: 'fast_first',
						image_mode: activePicker.imageUse,
							user_instruction: operatorInstruction,
							refresh_variant: refreshVariant,
							visual_context: buildImageVisualContext(postContext, activeImageMode, fallbackQuery, imagePickerContextOverride(activePicker), activePicker.imageUse, refreshVariant),
						};
						const fallbackResult = await postJson('image-candidates', fallbackPayload);
						if (extractImageCandidates(fallbackResult).length) {
							result = fallbackResult;
							requestPayload = fallbackPayload;
							break;
						}
						}
						if (imageSourceRequestSeqRef.current !== requestSeq) {
							return;
						}
						setImageResultForSearchMode('source', result);
						completeImageSourceCandidates(requestPayload, cacheKey, result, requestSeq);
					} catch (requestError) {
						if (imageSourceRequestSeqRef.current === requestSeq) {
							setImageError(formatImageErrorMessage(requestError, __('Cloud image recommendation failed.', 'npcink-toolbox')));
						}
					} finally {
						if (imageSourceRequestSeqRef.current === requestSeq) {
							setImageRunning('');
						}
				}
			}

		async function runImageSearch(event, suggestedQuery, forceRefresh) {
			if (event && event.preventDefault) {
				event.preventDefault();
			}
			const activePicker = normalizeImagePickerOptions(imagePicker || { mode: imageMode });
			const query = String(suggestedQuery || imageQuery || '').trim();
			if (!query) {
				runAutoImageRecommendations(activePicker.mode, activePicker.context, activePicker, forceRefresh);
				return;
			}
			const imageContext = imagePickerRequestContext(postContext, activePicker);
			const cacheKey = imageSearchCacheKey('manual', activePicker, query, imageContext);
			const cachedResult = forceRefresh ? null : readCachedImageResult(cacheKey);
			setImageQuery(query);
					if (cachedResult) {
						setImageRunning('');
						setImageCompletionRunning(false);
						setImageError('');
						setImageGuidance('');
						setImageResultForSearchMode('source', cachedResult, true);
					setSelectedImage(null);
					setSelectedImageSeo(null);
					setImagePreviewLightbox(null);
					setImageAdoptionResult(null);
				setImageAdoptionError('');
					resetImageFeedbackState();
					return;
				}
					const requestSeq = imageSourceRequestSeqRef.current + 1;
					imageSourceRequestSeqRef.current = requestSeq;
					setImageRunning('search');
					setImageCompletionRunning(false);
					setImageError('');
					setImageGuidance('');
					setImageResultForSearchMode('source', null, true);
				setSelectedImage(null);
			setSelectedImageSeo(null);
			setImagePreviewLightbox(null);
			setImageAdoptionResult(null);
			setImageAdoptionError('');
			resetImageFeedbackState();
				try {
					const refreshVariant = forceRefresh ? imageRefreshVariant() : '';
					const requestPayload = {
						query,
						provider: 'auto',
						per_page: 9,
					latency_mode: 'fast_first',
						image_mode: activePicker.imageUse,
						refresh_variant: refreshVariant,
						visual_context: buildImageVisualContext(postContext, activePicker.mode, query, imagePickerContextOverride(activePicker), activePicker.imageUse, refreshVariant),
					};
					const result = await postJson('image-candidates', requestPayload);
					if (imageSourceRequestSeqRef.current !== requestSeq) {
						return;
					}
					setImageResultForSearchMode('source', result);
					completeImageSourceCandidates(requestPayload, cacheKey, result, requestSeq);
					} catch (requestError) {
						if (imageSourceRequestSeqRef.current === requestSeq) {
							setImageError(formatImageErrorMessage(requestError, __('Cloud image search failed.', 'npcink-toolbox')));
						}
					} finally {
						if (imageSourceRequestSeqRef.current === requestSeq) {
							setImageRunning('');
						}
				}
			}

		async function runMediaBrief() {
			const postId = parseInt(postContext.post_id || '0', 10) || 0;
			const targetSearchMode = activeSearchMode;
			if (!postId) {
				setImageError(__('Save the draft before generating an image plan.', 'npcink-toolbox'));
				return;
			}
				setImageRunning('brief');
				setImageError('');
				setImageGuidance('');
				setImageResultForSearchMode(targetSearchMode, null, true);
				setSelectedImage(null);
			setSelectedImageSeo(null);
			setImagePreviewLightbox(null);
			setImageAdoptionResult(null);
			setImageAdoptionError('');
			resetImageFeedbackState();
				try {
					const result = await postJson('flows/media-brief', { post_id: postId });
					setImageResultForSearchMode(targetSearchMode, result);
					if (targetSearchMode === 'generate') {
					const prompt = firstImagePromptCandidate(result) || (result && result.query ? String(result.query) : '');
					setImageSearchMode('generate');
					setImageQuery(prompt);
					setImageGuidance(prompt ? __('Generated a prompt from the article context. Review it before creating an AI image candidate.', 'npcink-toolbox') : __('Generated prompt directions from the article context. Choose one direction before creating an AI image candidate.', 'npcink-toolbox'));
				} else {
					setImageSearchMode('source');
					setImageQuery(result && result.query ? String(result.query) : '');
					setImageGuidance(__('Generated an article image plan from the saved post context. Review candidates before search, generation, import, or featured-image adoption.', 'npcink-toolbox'));
				}
			} catch (requestError) {
				setImageError(requestError && requestError.message ? requestError.message : __('Image plan generation failed.', 'npcink-toolbox'));
			} finally {
				setImageRunning('');
			}
		}

		async function runAiImageGeneration(event, promptOverride) {
			if (event && event.preventDefault) {
				event.preventDefault();
			}
			const activePicker = normalizeImagePickerOptions(imagePicker || { mode: imageMode });
			if (!activePicker.allowGeneration) {
				setImageSearchMode('source');
				setImageError(__('AI image generation is available from the featured image entry. Paragraph image suggestions use the selected paragraph to find source images.', 'npcink-toolbox'));
				return;
			}
			const context = imagePickerRequestContext(postContext || {}, activePicker);
			const override = promptOverride && typeof promptOverride === 'object' ? promptOverride : {};
			const prompt = String(override.prompt || imageQuery || '').trim();
			if (!prompt) {
				setImageError(__('Enter an AI image prompt, or generate one from the article first.', 'npcink-toolbox'));
				return;
			}
				setImageRunning('generate');
				setImageError('');
				setImageGuidance(override.guidance || '');
				setImageResultForSearchMode('generate', null, true);
				setSelectedImage(null);
			setSelectedImageSeo(null);
			setImagePreviewLightbox(null);
			setImageAdoptionResult(null);
			setImageAdoptionError('');
			resetImageFeedbackState();
			try {
				const candidateCount = Math.max(1, Math.min(4, parseInt(aiImageCandidateCount || '1', 10) || 1));
				const result = await postJson('ai/image-generation', {
					prompt,
					aspect_ratio: aiImageAspectRatio,
					resolution: aiImageResolution,
					response_format: 'url',
					n: candidateCount,
					purpose: 'editor_image_source_modal_generation',
					prompt_reviewed_by_operator: true,
					regeneration_mode: override.regenerationMode || '',
					media_context: {
						title: truncateText(postContext.title || context.title || imageQuery || '', 120),
						alt: '',
						description: '',
					},
					post_context: {
						post_id: postContext.post_id || 0,
						post_type: postContext.post_type || 'post',
						title: truncateText(context.title || postContext.title || '', 160),
						excerpt: truncateText(context.excerpt || postContext.excerpt || '', 260),
						selected_text: truncateText(context.selected_text || '', 500),
						selected_block_text: truncateText(context.selected_block_text || '', 500),
						selected_block_name: context.selected_block_name || '',
						image_use: activePicker.imageUse,
					},
					handoff: {
						trigger: 'manual_user_action',
						action_id: 'ai_generate_image',
						image_use: activePicker.imageUse,
						regeneration_mode: override.regenerationMode || '',
						runtime_request_template: {
							ability_name: 'magick-ai-cloud/generate-image',
						},
					},
					});
					if (result && (result.code || (result.data && result.data.cloud_error_code))) {
						throw result;
					}
					setImageResultForSearchMode('generate', result);
					setImageGuidance(__('Showing AI-generated image candidates. Review and adopt through Core before importing or setting featured media.', 'npcink-toolbox'));
			} catch (requestError) {
				setImageError(formatImageErrorMessage(requestError, __('AI image generation failed.', 'npcink-toolbox')));
			} finally {
				setImageRunning('');
			}
		}

		async function regenerateSelectedImage(revisionMode) {
			if (!selectedImage) {
				return;
			}
			const activePicker = normalizeImagePickerOptions(imagePicker || { mode: imageMode });
			submitImplicitAgentFeedback(
				editorImageImplicitFeedbackPayload(imageResult || {}, selectedImage, activePicker, 'regenerate_' + sanitizeFeedbackAction(revisionMode), 'edited_before_accept', ['good_but_needs_human_draft'])
			);
			const currentPrompt = selectedImage.generation_prompt || selectedImage.prompt || imageQuery || '';
			const prompt = aiImageRevisionPrompt(postContext, activePicker, selectedImage, currentPrompt, aiImageAspectRatio, revisionMode);
			if (!prompt) {
				setImageError(__('No AI image prompt is available to regenerate.', 'npcink-toolbox'));
				return;
			}
			setImageSearchMode('generate');
			setImageQuery(prompt);
			setImageRegenerationRunning(revisionMode);
			setImageGuidance(__('Regenerating a revised AI image while preserving the selected paragraph meaning.', 'npcink-toolbox'));
			try {
				await runAiImageGeneration(null, {
					prompt,
					regenerationMode: revisionMode,
					guidance: __('Regenerating a revised AI image while preserving the selected paragraph meaning.', 'npcink-toolbox'),
				});
			} finally {
				setImageRegenerationRunning('');
			}
		}

		function useSuggestedImageQuery(query) {
			submitImplicitAgentFeedback(
				editorImageImplicitFeedbackPayload(imageResult || {}, selectedImage, imagePicker || { mode: imageMode }, 'suggested_query_click', 'edited_before_accept', ['good_but_needs_human_draft'])
			);
			runImageSearch(null, query, true);
		}

		function useAiPromptCandidate(prompt) {
			const reviewedPrompt = String(prompt || '').trim();
			if (!reviewedPrompt) {
				return;
			}
			setImageSearchMode('generate');
			setImageQuery(reviewedPrompt);
			setSelectedImage(null);
			setSelectedImageSeo(null);
			setImagePreviewLightbox(null);
			setImageAdoptionResult(null);
			setImageAdoptionError('');
			resetImageFeedbackState();
			setImageGuidance('');
		}

			function switchImageSearchMode(mode) {
				const nextMode = mode === 'generate' ? 'generate' : 'source';
				const currentMode = imageSearchMode === 'generate' ? 'generate' : 'source';
				imageSearchModeRef.current = nextMode;
				if (imageResult) {
					if (currentMode === 'generate') {
						setImageGenerationResult(imageResult);
					} else {
						setImageSourceResult(imageResult);
					}
				}
				setImageSearchMode(nextMode);
				setImageResult(imageResultForSearchMode(nextMode) || null);
				setSelectedImage(null);
				setSelectedImageSeo(null);
			setImagePreviewLightbox(null);
			setImageAdoptionResult(null);
			setImageAdoptionError('');
			resetImageFeedbackState();
		}

		function renderAiImageOption(label, value, onChange, options) {
			return createElement(
				'label',
				{ className: 'npcink-toolbox-editor-support__image-option' },
				createElement('span', null, label),
				createElement(
					'select',
					{
						value,
						disabled: Boolean(imageRunning),
						onChange: (event) => onChange(event.target.value),
					},
					options.map((option) => createElement('option', { key: option.value, value: option.value }, option.label))
				)
			);
		}

		function openImageSourcePicker(options) {
					const activePicker = normalizeImagePickerOptions(options || { mode: 'featured' });
					const initialSearchMode = activePicker.allowGeneration ? (activePicker.initialSearchMode || 'source') : 'source';
					imageSearchModeRef.current = initialSearchMode;
					imageSourceRequestSeqRef.current += 1;
				setImagePicker(activePicker);
				setImageMode(activePicker.mode);
				setImageSearchMode(initialSearchMode);
				setImageModalOpen(true);
					setImageQuery(activePicker.initialQuery || '');
					setImageGuidance('');
					setImageCompletionRunning(false);
					setImageSourceResult(null);
					setImageGenerationResult(null);
				setSelectedImage(null);
			setSelectedImageSeo(null);
			setImagePreviewLightbox(null);
			setImageAdoptionResult(null);
			setImageAdoptionError('');
			resetImageFeedbackState();
			if (activePicker.autoSearch && initialSearchMode !== 'generate') {
				runAutoImageRecommendations(activePicker.mode, activePicker.context, activePicker);
					} else {
						setImageRunning('');
						setImageCompletionRunning(false);
						setImageResult(null);
						setImageError('');
					}
		}

		function openImageRecommendations(mode, contextOverride) {
			openImageSourcePicker({ mode: mode === 'paragraph' ? 'paragraph' : 'featured', context: contextOverride || {} });
		}

		function selectImageCandidate(image) {
			const activePicker = normalizeImagePickerOptions(imagePicker || { mode: imageMode });
			const seoContext = imagePickerRequestContext(postContext, activePicker);
			setSelectedImage(image);
			setSelectedImageSeo(buildImageSeoFields(image, seoContext));
			setImageAdoptionResult(null);
			setImageAdoptionError('');
			resetImageFeedbackState();
			submitImplicitAgentFeedback(
				editorImageImplicitFeedbackPayload(imageResult || {}, image, activePicker, 'candidate_select', 'accepted', ['evidence_useful', 'operator_confidence_high'])
			);
		}

		function updateSelectedImageSeo(field, value) {
			setSelectedImageSeo((current) => Object.assign({}, current || {}, { [field]: value }));
			setImageAdoptionResult(null);
			setImageAdoptionError('');
		}

		function dispatchSelectedImageToCaller() {
			if (!selectedImage) {
				setImageAdoptionError(__('Select an image candidate first.', 'npcink-toolbox'));
				return;
			}

			const activePicker = normalizeImagePickerOptions(imagePicker || { mode: imageMode });
			const seoContext = imagePickerRequestContext(postContext, activePicker);
			const seo = Object.assign({}, buildImageSeoFields(selectedImage, seoContext), selectedImageSeo || {});
			const detail = {
				image_candidate: selectedImage,
				media_seo: seo,
				image_mode: activePicker.imageUse,
				picker_mode: activePicker.mode,
				context: activePicker.context || {},
			};
			if (typeof window !== 'undefined' && window.dispatchEvent) {
				const eventName = activePicker.selectionEvent || IMAGE_SOURCE_PICKER_SELECTED_EVENT;
				if (typeof window.CustomEvent === 'function') {
					window.dispatchEvent(new window.CustomEvent(eventName, { detail }));
				} else if (window.document && window.document.createEvent) {
					const event = window.document.createEvent('CustomEvent');
					event.initCustomEvent(eventName, false, false, detail);
					window.dispatchEvent(event);
				}
			}

			submitImplicitAgentFeedback(
				editorImageImplicitFeedbackPayload(imageResult || {}, selectedImage, activePicker, 'select_only', 'accepted', ['evidence_useful', 'operator_confidence_high'])
			);
			setImageGuidance(__('Image source selected for the calling field.', 'npcink-toolbox'));
			setImageAdoptionError('');
			if (activePicker.closeOnSelect) {
				setImageModalOpen(false);
			}
		}

		function toggleMetadataTermSelection(field, termId, checked) {
			setMetadataHandoffSelection((current) => {
				const next = Object.assign({}, current || {});
				const values = Array.isArray(next[field]) ? next[field].slice() : [];
				const normalized = parseInt(termId, 10) || 0;
				if (!normalized) {
					return next;
				}
				if (checked && values.indexOf(normalized) < 0) {
					values.push(normalized);
				}
				if (!checked) {
					const index = values.indexOf(normalized);
					if (index >= 0) {
						values.splice(index, 1);
					}
				}
				next[field] = values;
				return next;
			});
			setMetadataHandoffResult(null);
			setMetadataHandoffError('');
		}

		function updateFlowInstruction(intent, value) {
			const key = String(intent || '');
			if (!key) {
				return;
			}
			setFlowInstructions((current) => Object.assign({}, current || {}, {
				[key]: String(value || '').slice(0, 320),
			}));
		}

		function applyRecommendedTitle(title) {
			const value = String(title || '').trim();
			if (!value) {
				setTitleApplyStatus({
					status: 'error',
					title: '',
					message: __('No title text is available to apply.', 'npcink-toolbox'),
				});
				return;
			}
			const editorDispatch = data.dispatch ? data.dispatch('core/editor') : null;
			if (!editorDispatch || !editorDispatch.editPost) {
				setTitleApplyStatus({
					status: 'error',
					title: value,
					message: __('Could not update the current title in this editor.', 'npcink-toolbox'),
				});
				return;
			}
			editorDispatch.editPost({ title: value });
			submitContentImplicitFeedback('title_apply', 'accepted', ['evidence_useful', 'operator_confidence_high']);
			setTitleApplyStatus({
				status: 'success',
				title: value,
				message: __('Applied to the current title. Save the draft to persist it.', 'npcink-toolbox'),
			});
		}

		function applyRecommendedExcerpt(excerpt) {
			const value = String(excerpt || '').trim();
			if (!value) {
				setExcerptApplyStatus({
					status: 'error',
					excerpt: '',
					message: __('No summary text is available to apply.', 'npcink-toolbox'),
				});
				return;
			}
			const editorDispatch = data.dispatch ? data.dispatch('core/editor') : null;
			if (!editorDispatch || !editorDispatch.editPost) {
				setExcerptApplyStatus({
					status: 'error',
					excerpt: value,
					message: __('Could not update the current excerpt in this editor.', 'npcink-toolbox'),
				});
				return;
			}
			editorDispatch.editPost({ excerpt: value });
			submitContentImplicitFeedback('excerpt_apply', 'accepted', ['evidence_useful', 'operator_confidence_high']);
			setExcerptApplyStatus({
				status: 'success',
				excerpt: value,
				message: __('Applied to the current excerpt. Save the draft to persist it.', 'npcink-toolbox'),
			});
		}

		function applyRecommendedSlug(slug) {
			const value = String(slug || '').trim();
			if (!value) {
				setSlugApplyStatus({
					status: 'error',
					slug: '',
					message: __('No slug text is available to apply.', 'npcink-toolbox'),
				});
				return;
			}
			const postStatus = String((postContext && postContext.post_status) || '').toLowerCase();
			const confirmation = postStatus === 'publish'
				? __('This article is already published. Changing the slug can change the public URL and affect redirects, backlinks, and indexing. Apply this slug anyway?', 'npcink-toolbox')
				: __('Apply this slug to the current draft permalink? Review it before saving the draft.', 'npcink-toolbox');
			if (typeof window !== 'undefined' && window.confirm && !window.confirm(confirmation)) {
				setSlugApplyStatus({
					status: 'warning',
					slug: value,
					message: __('Slug was not changed.', 'npcink-toolbox'),
				});
				return;
			}
			const editorDispatch = data.dispatch ? data.dispatch('core/editor') : null;
			if (!editorDispatch || !editorDispatch.editPost) {
				setSlugApplyStatus({
					status: 'error',
					slug: value,
					message: __('Could not update the current slug in this editor.', 'npcink-toolbox'),
				});
				return;
			}
			editorDispatch.editPost({ slug: value });
			submitContentImplicitFeedback('slug_apply', 'accepted', ['operator_confirmed_url_risk', 'operator_confidence_high']);
			setSlugApplyStatus({
				status: 'success',
				slug: value,
				message: postStatus === 'publish'
					? __('Applied to the editor slug after confirmation. Save the article only if the URL change is intended.', 'npcink-toolbox')
					: __('Applied to the current slug. Save the draft to persist it.', 'npcink-toolbox'),
			});
		}

			function renderEvidenceModal() {
				if (!Array.isArray(evidenceModalBlocks) || !evidenceModalBlocks.length) {
					return null;
				}
			return createElement(
				Modal,
				{
					title: __('Evidence and diagnostics', 'npcink-toolbox'),
					onRequestClose: () => setEvidenceModalBlocks(null),
					className: 'npcink-toolbox-editor-support__evidence-modal',
				},
				createElement('p', { className: 'npcink-toolbox-editor-support__muted' }, __('Use this to verify why a suggestion was produced, what related content was checked, and whether any review risk exists. It does not write to the article.', 'npcink-toolbox')),
					createElement('div', { className: 'npcink-toolbox-editor-support__metadata-evidence-body' }, evidenceModalBlocks)
				);
			}

			function renderPreflightModal() {
				if (!preflightModalOpen || !result || !result.sections || !result.sections.pre_publish_review) {
					return null;
				}
				return createElement(
					Modal,
					{
						title: __('Publish preflight', 'npcink-toolbox'),
						onRequestClose: () => setPreflightModalOpen(false),
						className: 'npcink-toolbox-editor-support__preflight-modal',
					},
					createElement(
						'div',
						{ className: 'npcink-toolbox-editor-support__preflight-modal-head' },
						createElement('p', { className: 'npcink-toolbox-editor-support__muted' }, __('Only for publish decisions; Toolbox does not write WordPress fields.', 'npcink-toolbox')),
						createElement(
							Button,
							{
								type: 'button',
								variant: 'secondary',
								disabled: Boolean(running),
								isBusy: running === 'publish_preflight',
								onClick: () => runFlow('publish_preflight', { reopenPreflightModal: true }),
							},
							running === 'publish_preflight' ? __('Running', 'npcink-toolbox') : __('Rerun preflight', 'npcink-toolbox')
						)
					),
					renderPreflightDetailPanel(result, resultControls)
				);
			}

		async function submitMetadataHandoff() {
			const section = result && result.sections && result.sections.summary_terms_optimization ? result.sections.summary_terms_optimization : null;
			const planInput = buildMetadataApplyPlanInput(section, metadataHandoffSelection, postContext || {});
			if (!planInput.post_id || (!planInput.excerpt && !planInput.category_ids.length && !planInput.tag_ids.length)) {
				setMetadataHandoffError(__('Select at least one reviewed excerpt, tag, or category before creating a Core proposal.', 'npcink-toolbox'));
				return;
			}

			setMetadataHandoffRunning(true);
			setMetadataHandoffError('');
			setMetadataHandoffResult(null);
			try {
				const plan = await postJson('flows/content-metadata-apply-plan', planInput);
				const bridge = await postContentMetadataApplyPlanToAdapter(plan, planInput);
				const proposalIds = [];
				if (Array.isArray(bridge && bridge.proposals)) {
					bridge.proposals.forEach((proposal) => {
						const proposalId = extractProposalId(proposal, 0);
						if (proposalId) {
							proposalIds.push(proposalId);
						}
					});
				}
				const topLevelProposalId = extractProposalId(bridge, 0);
				if (topLevelProposalId && proposalIds.indexOf(topLevelProposalId) < 0) {
					proposalIds.push(topLevelProposalId);
				}
				setMetadataHandoffResult({
					plan,
					bridge,
					proposal_ids: proposalIds,
					message: proposalIds.length
						? __('Created Core review proposal(s): ', 'npcink-toolbox') + proposalIds.join(', ')
						: __('Created Core review proposal(s). Review them in Governance Core before execution.', 'npcink-toolbox'),
				});
			} catch (requestError) {
				setMetadataHandoffError(requestError && requestError.message ? requestError.message : __('Could not create the Core metadata proposal.', 'npcink-toolbox'));
			} finally {
				setMetadataHandoffRunning(false);
			}
		}

		async function submitSeoHandoff() {
			const section = result && result.sections && result.sections.seo_handoff ? result.sections.seo_handoff : null;
			const payload = seoMetaProposalPayload(section, { commitExecution: true });
			const input = payload.input || {};
			if (!section || !section.proposal_ready || !input.post_id || !input.seo_title || !input.seo_description) {
				setSeoHandoffError(__('Run discoverability optimization and review complete SEO title and description candidates before applying.', 'npcink-toolbox'));
				return;
			}

			setSeoHandoffRunning(true);
			setSeoHandoffError('');
			setSeoHandoffResult(null);
			try {
				const bridge = await postSeoMetaProposalToAdapter(section, { commitExecution: true });
				const proposalId = extractProposalId(bridge, 0);
				if (!proposalId) {
					setSeoHandoffResult({
						bridge,
						proposal_id: '',
						message: __('SEO optimization was submitted for Core review, but automatic application could not start.', 'npcink-toolbox'),
					});
					return;
				}
				try {
					const execution = await executeSeoMetaProposal(proposalId);
					setSeoHandoffResult({
						bridge,
						proposal_id: proposalId,
						execution,
						message: __('SEO optimization applied and recorded.', 'npcink-toolbox'),
					});
				} catch (executionError) {
					setSeoHandoffResult({
						bridge,
						proposal_id: proposalId,
						execution_error: executionError,
						message: __('SEO optimization was submitted for Core review, but automatic application was blocked: ', 'npcink-toolbox') + (executionError && executionError.message ? executionError.message : __('review in Core before execution.', 'npcink-toolbox')),
					});
				}
			} catch (requestError) {
				setSeoHandoffError(requestError && requestError.message ? requestError.message : __('Could not apply the SEO optimization.', 'npcink-toolbox'));
			} finally {
				setSeoHandoffRunning(false);
			}
		}

		function imageAdoptionPlanInput(seo, setFeaturedImage) {
			return Object.assign({}, seo, {
				post_id: postContext.post_id || 0,
				post_type: postContext.post_type || 'post',
				image_candidate: selectedImage,
				set_featured_image: Boolean(setFeaturedImage),
			});
		}

		function localFeaturedImageConsentInput() {
			const attachmentId = findAttachmentId(selectedImage, 0);
			return {
				post_id: postContext.post_id || 0,
				attachment_id: attachmentId,
				candidate: {
					title: imageTitle(selectedImage),
					source: imageCandidateSourceLabel(selectedImage),
					url: imagePreviewUrl(selectedImage) || imageSourceUrl(selectedImage),
				},
			};
		}

		async function adoptSelectedImage(setFeaturedImage) {
			if (!selectedImage) {
				setImageAdoptionError(__('Select an image candidate first.', 'npcink-toolbox'));
				return;
			}

			setImageAdoptionRunning(true);
			setImageAdoptionAction(setFeaturedImage ? 'adopt' : 'import');
			setImageAdoptionError('');
			setImageAdoptionResult(null);
			try {
				if (setFeaturedImage && findAttachmentId(selectedImage, 0) > 0) {
					const local = await postLocalFeaturedImageConsent(localFeaturedImageConsentInput());
					syncFeaturedMediaFromCore(local);
					setImageAdoptionResult({ core: local, local_consent: true, adoption_target: 'featured_image' });
					submitImplicitAgentFeedback(
						editorImageImplicitFeedbackPayload(imageResult || {}, selectedImage, imagePicker || { mode: imageMode }, 'local_featured_image_adopt', 'accepted', ['evidence_useful', 'operator_confidence_high'])
					);
					return;
				}

				const activePicker = normalizeImagePickerOptions(imagePicker || { mode: imageMode });
				const seoContext = imagePickerRequestContext(postContext, activePicker);
				const seo = Object.assign({}, buildImageSeoFields(selectedImage, seoContext), selectedImageSeo || {});
				const planInput = imageAdoptionPlanInput(seo, setFeaturedImage);
				const plan = await postJson('flows/image-candidate-adoption-plan', planInput);
				try {
					const core = await postAdapterAdoption(plan, planInput);
					if (setFeaturedImage) {
						syncFeaturedMediaFromCore(core);
					}
					setImageAdoptionResult({ plan, core, adoption_target: setFeaturedImage ? 'featured_image' : 'media_import' });
					submitImplicitAgentFeedback(
						editorImageImplicitFeedbackPayload(imageResult || {}, selectedImage, activePicker, setFeaturedImage ? 'featured_image_adopt' : 'media_import', 'accepted', ['evidence_useful', 'operator_confidence_high'])
					);
				} catch (coreError) {
					setImageAdoptionResult({ plan, core_error: coreError, adoption_target: setFeaturedImage ? 'featured_image' : 'media_import' });
				}
			} catch (requestError) {
				setImageAdoptionError(requestError && requestError.message ? requestError.message : __('Could not adopt the selected image.', 'npcink-toolbox'));
			} finally {
				setImageAdoptionRunning(false);
				setImageAdoptionAction('');
			}
		}

		async function submitSelectedImageFeedback(option) {
			if (!selectedImage) {
				setImageFeedbackStatus({ status: 'error', message: __('Select an image candidate first.', 'npcink-toolbox') });
				return;
			}
			setImageFeedbackRunning(option.label);
			setImageFeedbackStatus(null);
			try {
				await postJson(
					'agent-feedback',
					editorImageAgentFeedbackPayload(imageResult || {}, selectedImage, imagePicker || { mode: imageMode }, option.outcome, option.labels)
				);
				setImageFeedbackStatus({
					status: 'success',
					message: __('Feedback recorded.', 'npcink-toolbox'),
				});
			} catch (requestError) {
				setImageFeedbackStatus({
					status: 'error',
					message: (requestError && requestError.message ? requestError.message : __('Could not send image feedback.', 'npcink-toolbox')) + ' ' + __('Media import and WordPress writes remain local.', 'npcink-toolbox'),
				});
			} finally {
				setImageFeedbackRunning('');
			}
		}

		function renderImageRecommendationModal() {
			if (!imageModalOpen) {
				return null;
			}
			if (imagePreviewLightbox) {
				return renderImagePreviewModal(imagePreviewLightbox, () => setImagePreviewLightbox(null));
			}

			const images = extractImageCandidates(imageResult);
			const activePicker = normalizeImagePickerOptions(imagePicker || { mode: imageMode });
			const activeSearchMode = activePicker.allowGeneration ? imageSearchMode : 'source';
			const imageQueryText = String(imageQuery || '').trim();
			const sourceSubmitLabel = imageRunning === 'search' || imageRunning === 'auto'
				? activePicker.searchBusyLabel
				: (imageQueryText ? __('Search image sources', 'npcink-toolbox') : activePicker.searchButtonLabel);
			const inspectorSeoContext = imagePickerRequestContext(postContext, activePicker);
			const selectedImageStillVisible = selectedImage && images.some((image, index) => imageStableKey(image, index) === imageStableKey(selectedImage, index));
			const selectedImageForInspector = selectedImageStillVisible ? selectedImage : null;
			const inspectorSeo = selectedImageForInspector ? Object.assign({}, buildImageSeoFields(selectedImageForInspector, inspectorSeoContext), selectedImageSeo || {}) : null;
			const imagePromptId = 'npcink-toolbox-editor-support-image-prompt';
			const canGenerateAiImage = activeSearchMode === 'generate' && Boolean(imageQueryText) && !Boolean(imageRunning);
				const imageRunningLabel = imageRunning === 'generate'
					? __('Generating AI image candidate...', 'npcink-toolbox')
					: (imageRunning === 'brief' ? __('Generating image plan...', 'npcink-toolbox') : __('Loading cloud image candidates...', 'npcink-toolbox'));
				const imageCompletionNotice = activeSearchMode === 'source' && imageCompletionRunning && images.length < IMAGE_CANDIDATE_TARGET_COUNT
					? createElement('div', { className: 'npcink-toolbox-editor-support__running npcink-toolbox-editor-support__image-completion-running' }, createElement(Spinner, null), createElement('span', null, __('Loading more image candidates...', 'npcink-toolbox')))
					: null;
			const imageModeControl = activePicker.allowGeneration ? createElement(
				'div',
				{ className: 'npcink-toolbox-editor-support__image-mode', role: 'group', 'aria-label': __('Image candidate mode', 'npcink-toolbox') },
				createElement(
					'button',
					{
						type: 'button',
						className: imageSearchMode === 'source' ? 'is-active' : '',
						'aria-pressed': imageSearchMode === 'source' ? 'true' : 'false',
						onClick: () => switchImageSearchMode('source'),
					},
					activePicker.sourceModeLabel
				),
				createElement(
					'button',
					{
						type: 'button',
						className: imageSearchMode === 'generate' ? 'is-active' : '',
						'aria-pressed': imageSearchMode === 'generate' ? 'true' : 'false',
						onClick: () => switchImageSearchMode('generate'),
					},
					activePicker.generateModeLabel
				)
			) : null;
			const sourceSearchForm = activeSearchMode === 'source' ? createElement(
				'form',
				{ className: 'npcink-toolbox-editor-support__image-search', onSubmit: (event) => runImageSearch(event, null, true) },
				createElement(
					'div',
					{ className: 'npcink-toolbox-editor-support__image-search-row' },
					createElement('input', {
						type: 'search',
						value: imageQuery,
						placeholder: activePicker.searchPlaceholder,
						onChange: (event) => setImageQuery(event.target.value),
					}),
					createElement(
						Button,
						{
							type: 'submit',
							variant: 'secondary',
							className: 'npcink-toolbox-editor-support__image-search-submit',
							isBusy: imageRunning === 'search' || imageRunning === 'auto',
							disabled: Boolean(imageRunning),
						},
						sourceSubmitLabel
					)
				)
			) : null;
				const generationDirectionPayload = imageGenerationResult || imageSourceResult || imageResult;
				const generatedImages = extractImageCandidates(imageGenerationResult);
				const generationDirectionsPanel = activeSearchMode === 'generate' && !generatedImages.length
					? renderImageVisualBrief(generationDirectionPayload, useAiPromptCandidate, {
						actionLabel: __('Use direction', 'npcink-toolbox'),
						heading: __('Generation direction reference', 'npcink-toolbox'),
						headingAction: activePicker.allowImagePlan ? createElement(
							Button,
							{
								type: 'button',
								variant: 'tertiary',
								className: 'npcink-toolbox-editor-support__direction-refresh',
								isBusy: imageRunning === 'brief',
								disabled: Boolean(imageRunning),
								onClick: runMediaBrief,
							},
							imageRunning === 'brief' ? __('Planning', 'npcink-toolbox') : __('Refresh directions', 'npcink-toolbox')
						) : null,
						hideContext: true,
						compactSelector: true,
					})
				: null;
			const aiGenerationPanel = activeSearchMode === 'generate' ? createElement(
				'form',
				{ className: 'npcink-toolbox-editor-support__image-generate-form', onSubmit: runAiImageGeneration },
				createElement(
					'div',
					{ className: 'npcink-toolbox-editor-support__image-prompt-panel' },
					createElement(
						'label',
						{
							className: 'npcink-toolbox-editor-support__image-prompt-label',
							htmlFor: imagePromptId,
						},
						__('AI image prompt', 'npcink-toolbox')
					),
					createElement('textarea', {
						id: imagePromptId,
						className: 'npcink-toolbox-editor-support__image-prompt-textarea',
						value: imageQuery,
						placeholder: activePicker.generatePlaceholder,
						rows: 4,
						disabled: Boolean(imageRunning),
						onChange: (event) => setImageQuery(event.target.value),
					}),
					generationDirectionsPanel,
					createElement(
						'div',
						{ className: 'npcink-toolbox-editor-support__image-generate-actions npcink-toolbox-editor-support__image-generate-main-actions' },
						activePicker.allowImagePlan && !generationDirectionsPanel ? createElement(
							Button,
							{
								type: 'button',
								variant: 'secondary',
								className: 'npcink-toolbox-editor-support__article-search-button',
								isBusy: imageRunning === 'brief',
								disabled: Boolean(imageRunning),
								onClick: runMediaBrief,
							},
							imageRunning === 'brief' ? __('Planning', 'npcink-toolbox') : __('Refresh directions', 'npcink-toolbox')
						) : null,
						createElement(
							Button,
							{
								type: 'submit',
								variant: 'primary',
								isBusy: imageRunning === 'generate',
								disabled: !canGenerateAiImage,
							},
							imageRunning === 'generate' ? __('Generating', 'npcink-toolbox') : (activePicker.generateButtonLabel || __('Generate AI image', 'npcink-toolbox'))
						)
					),
					!imageQueryText ? createElement('p', { className: 'npcink-toolbox-editor-support__image-prompt-required' }, __('Enter a prompt, or generate one from the article first.', 'npcink-toolbox')) : null,
					createElement(
						'div',
						{ className: 'npcink-toolbox-editor-support__image-options' },
						renderAiImageOption(__('Aspect ratio', 'npcink-toolbox'), aiImageAspectRatio, setAiImageAspectRatio, [
							{ value: '16:9', label: '16:9' },
							{ value: '1:1', label: '1:1' },
							{ value: '4:3', label: '4:3' },
							{ value: '3:4', label: '3:4' },
							{ value: '9:16', label: '9:16' },
						]),
						renderAiImageOption(__('Quality', 'npcink-toolbox'), aiImageResolution, setAiImageResolution, [
							{ value: 'high', label: __('High', 'npcink-toolbox') },
							{ value: 'medium', label: __('Medium', 'npcink-toolbox') },
							{ value: 'low', label: __('Low', 'npcink-toolbox') },
						]),
						renderAiImageOption(__('Candidates', 'npcink-toolbox'), aiImageCandidateCount, setAiImageCandidateCount, [
							{ value: '1', label: '1' },
							{ value: '2', label: '2' },
							{ value: '3', label: '3' },
							{ value: '4', label: '4' },
						])
					)
				)
			) : null;
			return createElement(
				Modal,
				{
					title: activePicker.title,
					onRequestClose: () => {
						setImageModalOpen(false);
						setImagePreviewLightbox(null);
					},
					className: 'npcink-toolbox-editor-support__image-modal',
				},
					createElement(
						'div',
						{ className: 'npcink-toolbox-editor-support__image-modal-body' },
						imageGuidance ? createElement(Notice, { status: 'info', isDismissible: false }, imageGuidance) : null,
						imageError ? createElement(Notice, { status: 'error', isDismissible: false }, imageError) : null,
					createElement(
						'div',
						{ className: 'npcink-toolbox-editor-support__image-workspace' },
						createElement(
							'section',
								{ className: 'npcink-toolbox-editor-support__image-results' },
								imageRunning ? createElement('div', { className: 'npcink-toolbox-editor-support__running' }, createElement(Spinner, null), createElement('span', null, imageRunningLabel)) : null,
								imageResult && !imageRunning ? renderImageCandidateCards(images, imageResult, selectedImage, selectImageCandidate, setImagePreviewLightbox, useSuggestedImageQuery, activePicker) : null,
								!imageRunning ? imageCompletionNotice : null
							),
						createElement(
							'section',
							{ className: 'npcink-toolbox-editor-support__image-inspector' },
							imageModeControl,
							sourceSearchForm,
							aiGenerationPanel,
							renderSelectedImagePanel(
								selectedImageForInspector,
								inspectorSeo,
								imageAdoptionRunning,
								imageAdoptionAction,
								imageAdoptionResult,
								imageAdoptionError,
								activePicker,
								updateSelectedImageSeo,
								() => adoptSelectedImage(true),
								() => adoptSelectedImage(false),
								dispatchSelectedImageToCaller,
								imageFeedbackRunning,
								imageFeedbackStatus,
								submitSelectedImageFeedback,
								imageRegenerationRunning || (imageRunning === 'generate' ? 'generate' : ''),
								regenerateSelectedImage
							),
							activeSearchMode === 'source' ? renderImageCloudDetails(
								imageResult,
								useSuggestedImageQuery,
								{
									mode: activeSearchMode,
									hasImages: Boolean(images.length),
								}
							) : null
						)
					)
				)
			);
		}

		const rerunIntent = running || activeFlowIntent || (result && result.intent) || '';
		const resultTitle = rerunIntent ? formatIntentLabel(rerunIntent) : __('Content support', 'npcink-toolbox');
		const isContextualParagraphResult = contextualResult && rerunIntent === 'polish_notes';
		const resultControls = {
			intent: activeFlowIntent || (result && result.intent) || '',
			showHandoff: ['summary_terms_optimization', 'category_suggestions', 'tag_suggestions'].indexOf(activeFlowIntent) >= 0,
			selection: metadataHandoffSelection,
			setSelection: setMetadataHandoffSelection,
			toggleTerm: toggleMetadataTermSelection,
			submit: submitMetadataHandoff,
			running: metadataHandoffRunning,
			result: metadataHandoffResult,
			error: metadataHandoffError,
				applyTitle: applyRecommendedTitle,
				titleApplyStatus,
				applyExcerpt: applyRecommendedExcerpt,
				excerptApplyStatus,
				applySlug: applyRecommendedSlug,
				slugApplyStatus,
					openEvidence: setEvidenceModalBlocks,
					openPreflightModal: () => setPreflightModalOpen(true),
					runIntent: runFlow,
					runningIntent: running,
					mediaItems: Array.isArray(postContext.media_items) ? postContext.media_items : [],
				feedbackRunning: contentFeedbackRunning,
			feedbackStatus: contentFeedbackStatus,
				submitFeedback: submitContentSupportFeedback,
				internalLinks: {
					running: internalLinkRunning,
					status: internalLinkStatus,
					copy: copyInternalLinkCandidate,
					open: openInternalLinkCandidate,
				},
				seoHandoff: {
				submit: submitSeoHandoff,
				running: seoHandoffRunning,
				result: seoHandoffResult,
				error: seoHandoffError,
			},
		};
			const showResultView = supportView === 'result';
			const rerunInstruction = rerunIntent ? String(flowInstructions[rerunIntent] || '') : '';
			const canAdvancedSummaryRerun = rerunIntent === 'summary_suggestions';

		return createElement(
			Fragment,
			null,
			createElement(
				PluginSidebar,
				{
					name: SIDEBAR_NAME,
					title: __('Npcink Content Support', 'npcink-toolbox'),
					icon: sidebarIcon,
					className: 'npcink-toolbox-editor-support',
				},
				createElement(
					'div',
					{ className: 'npcink-toolbox-editor-support__surface' },
					showResultView ? createElement(
						'div',
						{ className: 'npcink-toolbox-editor-support__result-view' },
						isContextualParagraphResult ? null : createElement(
							'div',
							{ className: 'npcink-toolbox-editor-support__view-header' },
							createElement(
								Button,
								{
									type: 'button',
									variant: 'tertiary',
									className: 'npcink-toolbox-editor-support__back-button',
									'aria-label': __('Return to tool list', 'npcink-toolbox'),
									title: __('Return to tool list', 'npcink-toolbox'),
									onClick: () => {
										setSupportView('menu');
										setContextualResult(false);
									},
								},
								createElement('span', { className: 'dashicons dashicons-arrow-left-alt2', 'aria-hidden': 'true' }),
								createElement('span', null, __('Tool list', 'npcink-toolbox'))
							)
						),
						createElement(
							'div',
							{ className: 'npcink-toolbox-editor-support__view-title' },
							createElement('strong', null, resultTitle),
							createElement('span', null, running ? __('Running content support flow...', 'npcink-toolbox') : resultScopeLabel(rerunIntent))
						),
						rerunIntent && flowAcceptsUserInstruction(rerunIntent) ? createElement(
							'div',
							{ className: 'npcink-toolbox-editor-support__flow-instruction' },
							createElement(TextareaControl, {
								label: __('My request for this suggestion', 'npcink-toolbox'),
								value: rerunInstruction,
								placeholder: flowInstructionPlaceholder(rerunIntent),
								disabled: Boolean(running),
								rows: 3,
								onChange: (value) => updateFlowInstruction(rerunIntent, value),
							})
						) : null,
						rerunIntent ? createElement(
							'div',
							{ className: 'npcink-toolbox-editor-support__view-actions' },
								createElement(
									Button,
									{
										type: 'button',
										variant: 'secondary',
										isBusy: Boolean(running),
										disabled: Boolean(running),
										onClick: () => {
											submitContentImplicitFeedback('run_again', 'edited_before_accept', ['good_but_needs_human_draft']);
											runFlow(rerunIntent, rerunIntent === 'summary_suggestions' ? { forceRegenerate: true } : undefined);
										},
									},
									running ? __('Running', 'npcink-toolbox') : (rerunIntent === 'summary_suggestions' ? __('Regenerate', 'npcink-toolbox') : __('Run again', 'npcink-toolbox'))
								),
								canAdvancedSummaryRerun ? createElement(
									Button,
									{
										type: 'button',
										variant: 'tertiary',
										isBusy: Boolean(running),
										disabled: Boolean(running),
										onClick: () => {
											submitContentImplicitFeedback('advanced_rerun', 'edited_before_accept', ['good_but_needs_human_draft']);
											runFlow(rerunIntent, { summaryGenerationMode: 'full_context', forceRegenerate: true });
										},
									},
									__('Advanced rerun', 'npcink-toolbox')
								) : null
							) : null,
						running ? createElement(
							'div',
							{ className: 'npcink-toolbox-editor-support__running' },
							createElement(Spinner, null),
							createElement('span', null, __('Running content support flow...', 'npcink-toolbox'))
						) : null,
						error ? createElement(Notice, { status: 'error', isDismissible: false }, error) : null,
						result ? renderResult(result, resultControls) : null
					) : createElement(
						'div',
						{ className: 'npcink-toolbox-editor-support__menu-view' },
						renderProgressiveRecommendationPanel(
							progressiveResult,
							progressiveStatus,
							progressivePanelOpen,
							openProgressiveRecommendation,
							() => runProgressivePrefetch(progressiveRecommendationKey(postContext), true)
						),
						createElement(
							'div',
							{ className: 'npcink-toolbox-editor-support__intro-row' },
							createElement('p', { className: 'npcink-toolbox-editor-support__intro' }, __('Run fixed support flows around the current draft. Article text stays with the editor.', 'npcink-toolbox')),
							createElement(
								Button,
								{
									type: 'button',
									variant: 'tertiary',
									isBusy: progressiveStatus && progressiveStatus.status === 'loading',
									onClick: openProgressivePanel,
								},
								__('Local suggestions', 'npcink-toolbox')
							)
						),
						flowGroups.map((group) => createElement(
							'section',
							{ className: 'npcink-toolbox-editor-support__flow-group', key: group.id },
							createElement('h4', null, group.label),
							flows.filter((flow) => flow.group === group.id).map((flow) =>
								{
									const flowActionLabel = flow.intent === 'image_candidates' ? __('Open', 'npcink-toolbox') : (running === flow.intent ? __('Running', 'npcink-toolbox') : __('Run', 'npcink-toolbox'));
									return createElement(
										'div',
										{ className: 'npcink-toolbox-editor-support__flow', key: flow.intent },
										createElement('div', null, createElement('strong', null, flow.label), createElement('span', null, flow.description)),
										createElement(
											Button,
											{
												variant: 'secondary',
												isBusy: running === flow.intent,
												disabled: Boolean(running),
												onClick: () => runFlow(flow.intent),
												'aria-label': flowActionLabel + ' ' + flow.label,
											},
											flowActionLabel
										)
									);
								}
							)
						))
					)
				)
				),
				renderImageRecommendationModal(),
				renderEvidenceModal(),
				renderPreflightModal()
			);
		}

	function dispatchParagraphImageRequest(detail) {
		dispatchImageSourcePickerRequest(Object.assign({ mode: 'paragraph' }, detail && typeof detail === 'object' ? { context: detail } : {}));
	}

	function dispatchParagraphReviewRequest(detail) {
		if (typeof window === 'undefined' || !window.dispatchEvent) {
			return;
		}

		const eventDetail = detail && typeof detail === 'object' ? detail : {};
		openContentSupportSidebar();
		window.NpcinkToolboxPendingParagraphReview = eventDetail;
		if (typeof window.CustomEvent === 'function') {
			window.dispatchEvent(new window.CustomEvent(PARAGRAPH_REVIEW_EVENT, { detail: eventDetail }));
			return;
		}

		if (window.document && window.document.createEvent) {
			const event = window.document.createEvent('CustomEvent');
			event.initCustomEvent(PARAGRAPH_REVIEW_EVENT, false, false, eventDetail);
			window.dispatchEvent(event);
		}
	}

	function dispatchImageSourcePickerRequest(detail) {
		if (typeof window === 'undefined' || !window.dispatchEvent) {
			return;
		}

		const eventDetail = detail && typeof detail === 'object' ? detail : {};
		if (typeof window.CustomEvent === 'function') {
			window.dispatchEvent(new window.CustomEvent(IMAGE_SOURCE_PICKER_EVENT, { detail: eventDetail }));
			return;
		}

		if (window.document && window.document.createEvent) {
			const event = window.document.createEvent('CustomEvent');
			event.initCustomEvent(IMAGE_SOURCE_PICKER_EVENT, false, false, eventDetail);
			window.dispatchEvent(event);
		}
	}

	function registerParagraphImageBlockToolbar() {
		if (!hooks.addFilter || !BlockControls || !ToolbarButton) {
			return;
		}

		hooks.addFilter('editor.BlockEdit', PLUGIN_NAME + '/paragraph-image-toolbar', function addParagraphImageToolbar(BlockEdit) {
			return function NpcinkParagraphImageBlockEdit(props) {
					const blockText = selectedBlockText({ attributes: props && props.attributes ? props.attributes : {} });
					const disabled = !String(blockText || '').trim();
					return createElement(
						Fragment,
					null,
					createElement(BlockEdit, props),
					props && props.isSelected
						? createElement(
							BlockControls,
								{ group: 'inline' },
								createElement(
									ToolbarButton,
									{
										className: 'npcink-toolbox-editor-support__block-toolbar-button',
										icon: paragraphReviewIcon,
										label: __('Check selected paragraph', 'npcink-toolbox'),
										title: __('Check selected paragraph', 'npcink-toolbox'),
										showTooltip: true,
										disabled,
										onClick: () => {
											const selected = browserSelectedText();
											dispatchParagraphReviewRequest({
												selected_block_name: props.name || '',
												selected_text: selected || blockText,
												selected_block_text: selected ? '' : blockText,
											});
										},
									}
								),
								createElement(
									ToolbarButton,
									{
									className: 'npcink-toolbox-editor-support__block-toolbar-button',
									icon: paragraphImageIcon,
									label: __('Find image for selected paragraph', 'npcink-toolbox'),
									title: __('Find image for selected paragraph', 'npcink-toolbox'),
									showTooltip: true,
									disabled,
									onClick: () => dispatchParagraphImageRequest({
										selected_block_name: props.name || '',
										selected_block_text: blockText,
									}),
								}
							)
						)
						: null
				);
			};
		});
	}

	function ContentSupportPlugin() {
		return createElement(ContentSupportControls, null);
	}

	registerParagraphImageBlockToolbar();

	if (typeof window !== 'undefined') {
		window.NpcinkToolboxImageSourcePicker = Object.assign({}, window.NpcinkToolboxImageSourcePicker || {}, {
			open: dispatchImageSourcePickerRequest,
			eventName: IMAGE_SOURCE_PICKER_EVENT,
			selectedEventName: IMAGE_SOURCE_PICKER_SELECTED_EVENT,
		});
	}

	plugins.registerPlugin(PLUGIN_NAME, {
		icon: sidebarIcon,
		render: ContentSupportPlugin,
	});
})(window.wp || {});
