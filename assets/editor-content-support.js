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
	const useSelect = data.useSelect;
	const __ = i18n.__ || ((value) => value);
	const SIDEBAR_NAME = 'npcink-content-support-sidebar';
	const PLUGIN_NAME = 'npcink-toolbox-editor-content-support';
	const PARAGRAPH_IMAGE_EVENT = 'npcink-toolbox:paragraph-image-suggestions';
	const IMAGE_SOURCE_PICKER_EVENT = 'npcink-toolbox:image-source-picker';
	const IMAGE_SOURCE_PICKER_SELECTED_EVENT = 'npcink-toolbox:image-source-selected';
	const IMAGE_RESULT_CACHE_TTL = 5 * 60 * 1000;
	const IMAGE_RESULT_CACHE_MAX_ENTRIES = 20;
	const imageResultCache = {};
	const PluginSidebarComponent = editor.PluginSidebar || editPost.PluginSidebar;
	const BlockControlsComponent = blockEditor.BlockControls || editor.BlockControls || null;

	if (!createElement || !useState || !useEffect || !useSelect || !plugins.registerPlugin || !PluginSidebarComponent) {
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
	const ExternalLink = components.ExternalLink || function ExternalLinkFallback(props) {
		return createElement('a', { href: props.href, target: '_blank', rel: 'noreferrer' }, props.children);
	};
	const sidebarIcon = createElement('span', { className: 'npcink-toolbox-editor-support__toolbar-icon', 'aria-hidden': 'true' }, 'AI');
	const paragraphImageIcon = createElement('span', { className: 'dashicons dashicons-format-image npcink-toolbox-editor-support__block-toolbar-icon', 'aria-hidden': 'true' });

	const imagePickerPresets = {
		featured: {
			mode: 'featured',
			imageUse: 'featured_image',
			adoptionMode: 'featured_image',
			initialSearchMode: 'source',
			autoSearch: true,
			title: __('Image source suggestions', 'npcink-toolbox'),
			intro: __('Search uses the selected paragraph when available, plus article context, or a short visual query. Select one image to import it with media details and set it as the featured image through Adapter/Core.', 'npcink-toolbox'),
			emptyTitle: __('Select an image to adopt', 'npcink-toolbox'),
		},
		paragraph: {
			mode: 'paragraph',
			imageUse: 'paragraph_image',
			adoptionMode: 'media_import',
			initialSearchMode: 'source',
			autoSearch: true,
			title: __('Paragraph image suggestions', 'npcink-toolbox'),
			intro: __('Search uses the selected paragraph plus article context. Select one image to import it with media details through Adapter/Core.', 'npcink-toolbox'),
			emptyTitle: __('Select an image for this paragraph', 'npcink-toolbox'),
		},
		inline: {
			mode: 'inline',
			imageUse: 'inline_image',
			adoptionMode: 'media_import',
			initialSearchMode: 'source',
			autoSearch: true,
			title: __('Inline image suggestions', 'npcink-toolbox'),
			intro: __('Search uses the selected text plus article context. Select one image to import it with media details through Adapter/Core.', 'npcink-toolbox'),
			emptyTitle: __('Select an image source', 'npcink-toolbox'),
		},
		setting: {
			mode: 'setting',
			imageUse: 'setting_image',
			adoptionMode: 'select_only',
			initialSearchMode: 'source',
			autoSearch: false,
			title: __('Setting image suggestions', 'npcink-toolbox'),
			intro: __('Search uses a short visual query or supplied setting context. Select one image source to return it to the calling field; Toolbox does not write settings directly.', 'npcink-toolbox'),
			emptyTitle: __('Select an image source', 'npcink-toolbox'),
		},
	};

	const flows = [
		{
			intent: 'writing_support',
			label: __('Writing preparation', 'npcink-toolbox'),
			description: __('Build a source-backed preparation checklist before drafting the article body.', 'npcink-toolbox'),
		},
		{
			intent: 'publish_preflight',
			label: __('Publish preflight', 'npcink-toolbox'),
			description: __('Check missing terms, excerpt, image, duplicate risk, and discoverability hints.', 'npcink-toolbox'),
		},
		{
			intent: 'summary_terms_optimization',
			label: __('Optimize summary and terms', 'npcink-toolbox'),
			description: __('Review layered summaries, existing terms, proposed new terms, evidence, and handoff preview.', 'npcink-toolbox'),
		},
		{
			intent: 'taxonomy_tags',
			label: __('Recommend existing terms', 'npcink-toolbox'),
			description: __('Suggest only existing categories and tags from the current article or selected text.', 'npcink-toolbox'),
		},
		{
			intent: 'internal_links',
			label: __('Find internal links', 'npcink-toolbox'),
			description: __('Use Site Knowledge to find related public content for links.', 'npcink-toolbox'),
		},
		{
			intent: 'image_candidates',
			label: __('Find image candidates', 'npcink-toolbox'),
			description: __('Search configured image-source providers for featured or inline candidates.', 'npcink-toolbox'),
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
				selected_block_name: selectedBlock && selectedBlock.name ? String(selectedBlock.name) : '',
				selected_block_text: selectedBlockText(selectedBlock),
			};
		}, []);
	}

	function renderItems(items, emptyLabel) {
		if (!Array.isArray(items) || !items.length) {
			return createElement('p', { className: 'npcink-toolbox-editor-support__muted' }, emptyLabel || __('No candidates returned.', 'npcink-toolbox'));
		}

		return createElement(
			'ul',
			{ className: 'npcink-toolbox-editor-support__list' },
			items.slice(0, 8).map((item, index) => {
				const title = item.name || item.title || item.label || item.source_title || item.url || item.download_url || item.id || __('Candidate', 'npcink-toolbox');
				const detail = [
					item.value || '',
					item.reason || item.detail || item.excerpt || item.source_url || item.status || item.taxonomy || item.provider || '',
					Array.isArray(item.matched_tokens) && item.matched_tokens.length ? __('Matched: ', 'npcink-toolbox') + item.matched_tokens.slice(0, 5).join(', ') : '',
				].filter(Boolean).join(' · ');
				return createElement(
					'li',
					{ key: String(index) + '-' + String(title) },
					createElement('strong', null, title),
					detail ? createElement('span', null, detail) : null
				);
			})
		);
	}

	function extractImageItems(section) {
		if (!section || typeof section !== 'object') {
			return [];
		}
		return section.image_candidates || section.images || section.candidates || [];
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

	function imageTitle(image) {
		return image.title || image.alt_description || image.description || image.prompt || image.id || __('Image candidate', 'npcink-toolbox');
	}

	function imagePreviewUrl(image) {
		return image.thumbnail_url || image.thumb_url || image.small_url || image.preview_url || image.regular_url || image.download_url || image.url || '';
	}

	function imageSourceUrl(image) {
		return image.html_url || image.source_url || image.photographer_url || image.regular_url || image.download_url || image.url || '';
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

	function editorImageAgentFeedbackPayload(payload, selectedImage, picker, outcome, labels) {
		const activePicker = normalizeImagePickerOptions(picker || {});
		const sourceType = String((selectedImage && selectedImage.source_type) || (payload && payload.provider_mode) || '').toLowerCase();
		const aiGenerated = sourceType === 'ai_generated';
		const sourceRuntime = aiGenerated ? 'ai_image_generation' : 'image_candidates';
		const surface = aiGenerated ? 'editor_ai_image_generation_modal' : 'editor_image_candidate_modal';
		const runId = payload && payload.run_id ? String(payload.run_id) : '';
		const handoffId = [
			sourceRuntime,
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
			handoff_type: 'editor_image_candidate_result',
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

	function imageSearchCacheKey(type, picker, query, context) {
		const cachePicker = normalizeImagePickerOptions(picker || {});
		const cacheContext = imageRequestContext(context || {}, cachePicker.context);
		return [
			type || 'image',
			cachePicker.imageUse,
			String(query || '').trim().toLowerCase(),
			truncateText(cacheContext.title, 90),
			truncateText(cacheContext.excerpt, 120),
			truncateText(cacheContext.selected_text || cacheContext.selected_block_text || cacheContext.content, 220),
		].join('|');
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

	function normalizeImagePickerOptions(options) {
		const source = options && typeof options === 'object' ? options : {};
		const requestedMode = String(source.mode || source.image_mode || '').replace(/_image$/, '') || 'featured';
		const preset = imagePickerPresets[requestedMode] || imagePickerPresets.featured;
		const context = source.context && typeof source.context === 'object' ? source.context : {};
		const imageUse = source.image_use || source.imageUse || preset.imageUse;
		const adoptionMode = source.adoption_mode || source.adoptionMode || preset.adoptionMode;
		const initialSearchMode = source.initial_search_mode || source.initialSearchMode || preset.initialSearchMode || 'source';
		return Object.assign({}, preset, {
			mode: preset.mode,
			imageUse,
			adoptionMode,
			initialSearchMode: initialSearchMode === 'generate' ? 'generate' : 'source',
			title: source.title || preset.title,
			intro: source.intro || preset.intro,
			emptyTitle: source.empty_title || source.emptyTitle || preset.emptyTitle,
			context,
			initialQuery: source.query || source.manual_query || source.initialQuery || '',
			autoSearch: source.auto_search !== undefined ? Boolean(source.auto_search) : Boolean(preset.autoSearch),
			selectionEvent: source.selection_event || source.selectionEvent || IMAGE_SOURCE_PICKER_SELECTED_EVENT,
			closeOnSelect: source.close_on_select !== undefined ? Boolean(source.close_on_select) : false,
		});
	}

	function imageRequestContext(postContext, contextOverride) {
		const override = contextOverride && typeof contextOverride === 'object' ? contextOverride : {};
		return Object.assign({}, postContext, override, {
			selected_text: browserSelectedText() || override.selected_text || postContext.selected_text || '',
			selected_block_text: override.selected_block_text || postContext.selected_block_text || '',
			selected_block_name: override.selected_block_name || postContext.selected_block_name || '',
		});
	}

	function buildImageVisualContext(postContext, imageMode, manualQuery, contextOverride, imageUse) {
		const context = imageRequestContext(postContext, contextOverride);
		return {
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
	}

	function formatMetaLabel(value) {
		return String(value || '').replace(/[_-]+/g, ' ');
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

	function imageResultSourceLabel(payload) {
		const source = imageResultSource(payload || {});
		if (!source || typeof source !== 'object') {
			return '';
		}
		if (String(source.provider_mode || '').toLowerCase() === 'ai_generated') {
			const parts = compactLabelParts([
				source.hosted_profile,
				source.model_id,
				source.resolved_provider,
			]);
			return parts.length ? parts.join(' / ') : __('AI generated', 'npcink-toolbox');
		}
		return source.resolved_provider ? formatMetaLabel(source.resolved_provider) : '';
	}

	function imageCandidateSourceLabel(image, payload) {
		if (!image || typeof image !== 'object') {
			return '';
		}
		const sourceType = String(image.source_type || '').toLowerCase();
		const provider = String(image.provider || '').toLowerCase();
		if (sourceType === 'ai_generated' || provider === 'ai_generated') {
			const source = imageResultSource(payload || {});
			const parts = compactLabelParts([
				image.hosted_profile,
				source && source.hosted_profile,
				image.generation_model || image.model,
				source && source.model_id,
				image.generation_provider || image.provider_name,
			]);
			return parts.length ? parts.join(' / ') : __('AI generated', 'npcink-toolbox');
		}
		return image.provider ? formatMetaLabel(image.provider) : '';
	}

	function formatIntentLabel(value) {
		if (value === 'writing_support') {
			return __('Writing preparation', 'npcink-toolbox');
		}
		if (value === 'summary_terms_optimization') {
			return __('Summary and terms optimization', 'npcink-toolbox');
		}
		return formatMetaLabel(value);
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
		const context = imageRequestContext(postContext || {}, activePicker.context);
		const selectedContext = String(context.selected_text || context.selected_block_text || '').trim();
		const operatorPrompt = String(manualPrompt || '').trim();
		const articleContext = String(context.title || context.excerpt || '').trim();
		const subject = selectedContext || operatorPrompt || articleContext;
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
				__('editorial article illustration', 'npcink-toolbox'),
				__('concept photo for article section', 'npcink-toolbox'),
				__('professional workspace detail', 'npcink-toolbox'),
			];
		}
		if (activePicker.mode === 'setting') {
			return [
				__('homepage hero image', 'npcink-toolbox'),
				__('product workspace', 'npcink-toolbox'),
				__('clean website banner', 'npcink-toolbox'),
			];
		}
		return [
			__('editorial planning workspace', 'npcink-toolbox'),
			__('AI analytics dashboard', 'npcink-toolbox'),
			__('search strategy concept', 'npcink-toolbox'),
		];
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
					key: String(index) + '-' + suggestion,
					type: 'button',
					onClick: () => onUseSuggestion(suggestion),
				},
				suggestion
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
			source.candidate_source_count !== undefined ? __('Received: ', 'npcink-toolbox') + String(source.candidate_source_count) : '',
			source.status ? __('Status: ', 'npcink-toolbox') + formatMetaLabel(source.status) : '',
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

	function renderImageCloudDetails(payload, onUsePrompt) {
		const visualBrief = renderImageVisualBrief(payload, onUsePrompt);
		const diagnostics = renderImageDiagnostics(payload);
		if (!visualBrief && !diagnostics) {
			return null;
		}
		return createElement(
			'details',
			{ className: 'npcink-toolbox-editor-support__cloud-details' },
			createElement('summary', null, __('Cloud details', 'npcink-toolbox')),
			visualBrief,
			diagnostics
		);
	}

	function renderImageCandidateCards(images, payload, selectedImage, onSelectImage, onUseSuggestion, picker) {
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
			images.slice(0, 8).map((image, index) => {
				const previewUrl = imagePreviewUrl(image);
				const sourceUrl = imageSourceUrl(image);
				const candidateKey = imageStableKey(image, index);
				const selected = selectedImage && imageStableKey(selectedImage, index) === candidateKey;
				const cardTags = [
					imageCandidateSourceLabel(image, payload),
					imageDimensionLabel(image),
				].concat(imageCandidateTagValues(image)).filter(Boolean).slice(0, 4);

				return createElement(
					'article',
					{
						className: 'npcink-toolbox-editor-support__image-card' + (selected ? ' is-selected' : ''),
						key: String(index) + '-' + candidateKey,
						role: 'button',
						tabIndex: 0,
						'aria-pressed': selected ? 'true' : 'false',
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
					createElement(
						'div',
						{ className: 'npcink-toolbox-editor-support__image-card-body' },
						createElement('strong', null, truncateText(imageTitle(image), 64)),
						createElement(
							'div',
							{ className: 'npcink-toolbox-editor-support__image-card-meta' },
							cardTags.map((item, tagIndex) => createElement('span', { key: String(tagIndex) + '-' + item }, item))
						),
						createElement(
							'div',
							{ className: 'npcink-toolbox-editor-support__image-card-actions' },
							sourceUrl
								? createElement('a', { href: sourceUrl, target: '_blank', rel: 'noreferrer', onClick: (event) => event.stopPropagation() }, __('Open source', 'npcink-toolbox'))
								: null
						)
					)
				);
			})
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

	function renderAdvancedAdoptionDetails(result) {
		if (!result || typeof result !== 'object') {
			return null;
		}
		return createElement(
			'details',
			{ className: 'npcink-toolbox-editor-support__adoption-advanced' },
			createElement('summary', null, __('Advanced details', 'npcink-toolbox')),
			createElement('pre', null, JSON.stringify({
				plan: result.plan || null,
				core: result.core || null,
				core_error: result.core_error || null,
			}, null, 2))
		);
	}

	function renderAdoptionResult(result) {
		if (!result || typeof result !== 'object') {
			return null;
		}

		const status = adoptionStatus(result);
		const core = adoptionCorePayload(result);
		const proposalId = extractProposalId(core, 0);
		const mediaImportOnly = result.adoption_target === 'media_import';
		const title = status === 'adopted'
			? (mediaImportOnly ? __('Media imported', 'npcink-toolbox') : __('Featured image adopted', 'npcink-toolbox'))
			: (status === 'submitted' ? __('Adoption request sent', 'npcink-toolbox') : __('Automatic adoption not completed', 'npcink-toolbox'));
		const summary = status === 'adopted'
			? (mediaImportOnly ? __('Adapter approved the Core proposal and executed the media import with SEO fields. Insert or place it from the media library after review.', 'npcink-toolbox') : __('Adapter approved the Core proposal and executed the media import, SEO fields, and featured image action. Refresh the editor if the image does not update immediately.', 'npcink-toolbox'))
			: (status === 'submitted'
				? __('Core created the adoption proposal. Automatic execution did not return a completed result; check Core for status.', 'npcink-toolbox')
				: __('Automatic execution was unavailable or blocked by Adapter/Core policy. The Core proposal remains available for review.', 'npcink-toolbox'));
		return createElement(
			'div',
			{ className: 'npcink-toolbox-editor-support__adoption-result is-' + status },
			createElement('strong', null, title),
			createElement('span', null, summary),
			proposalId ? createElement('small', null, __('Proposal: ', 'npcink-toolbox') + String(proposalId)) : null,
			proposalId && config.coreAdminUrl
				? createElement('a', { href: config.coreAdminUrl + '&proposal_id=' + encodeURIComponent(proposalId), target: '_blank', rel: 'noreferrer' }, __('Open in Core', 'npcink-toolbox'))
				: null,
			renderAdvancedAdoptionDetails(result)
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
			'div',
			{ className: 'npcink-toolbox-editor-support__image-feedback', 'data-toolbox-editor-image-agent-feedback': 'true' },
			createElement('h3', null, __('Quick feedback', 'npcink-toolbox')),
			createElement(
				'div',
				{ className: 'npcink-toolbox-editor-support__image-feedback-actions' },
				options.map((option) => createElement(
					Button,
					{
						key: option.label,
						type: 'button',
						variant: 'secondary',
						isBusy: feedbackRunning === option.label,
						disabled: Boolean(feedbackRunning),
						onClick: () => onSubmitFeedback(option),
					},
					option.label
				))
			),
			createElement('small', null, __('Feedback updates Cloud eval only. Media import and WordPress writes stay local.', 'npcink-toolbox')),
			feedbackStatus ? createElement(Notice, { status: feedbackStatus.status, isDismissible: false }, feedbackStatus.message) : null
		);
	}

	function renderSelectedImagePanel(selectedImage, seoFields, adoptionRunning, adoptionResult, adoptionError, picker, onSeoFieldChange, onAdoptFeatured, onImportOnly, onSelectOnly, feedbackRunning, feedbackStatus, onSubmitFeedback) {
		const activePicker = normalizeImagePickerOptions(picker || {});
		const paragraphMode = activePicker.mode === 'paragraph';
		const selectOnlyMode = activePicker.adoptionMode === 'select_only';
		if (!selectedImage) {
			return createElement(
				'div',
				{ className: 'npcink-toolbox-editor-support__selected-image is-empty' },
				createElement('strong', null, activePicker.emptyTitle),
				createElement('span', null, __('Choose a source image on the left to review media details and SEO fields here.', 'npcink-toolbox'))
			);
		}

		const seo = seoFields || {};
		const previewUrl = imagePreviewUrl(selectedImage);
		const sourceUrl = imageSourceUrl(selectedImage);
		const rows = [
			renderInfoRow(__('Source', 'npcink-toolbox'), imageCandidateSourceLabel(selectedImage), 'source'),
			renderInfoRow(__('Review', 'npcink-toolbox'), selectedImage.license_review_status ? formatMetaLabel(selectedImage.license_review_status) : '', 'review'),
			renderInfoRow(__('Size', 'npcink-toolbox'), imageDimensionLabel(selectedImage), 'size'),
			renderInfoRow(__('Use', 'npcink-toolbox'), selectedImage.recommended_use ? formatMetaLabel(selectedImage.recommended_use) : '', 'use'),
		].filter(Boolean);
		return createElement(
			'aside',
			{ className: 'npcink-toolbox-editor-support__selected-image' },
			createElement(
				'div',
				{ className: 'npcink-toolbox-editor-support__selected-title' },
				createElement('h3', null, __('Selected image', 'npcink-toolbox')),
				createElement('strong', null, truncateText(imageTitle(selectedImage), 90))
			),
			createElement(
				'div',
				{ className: 'npcink-toolbox-editor-support__selected-head' },
				previewUrl ? createElement('img', { src: previewUrl, alt: seo.alt || imageTitle(selectedImage), loading: 'lazy' }) : null
			),
			rows.length ? createElement(
				'div',
				{ className: 'npcink-toolbox-editor-support__info-list' },
				rows
			) : null,
			createElement(
				'div',
				{ className: 'npcink-toolbox-editor-support__selected-actions' },
				selectOnlyMode ? createElement(
					Button,
					{
						type: 'button',
						variant: 'primary',
						disabled: adoptionRunning,
						onClick: onSelectOnly,
					},
					__('Use selected image', 'npcink-toolbox')
				) : paragraphMode ? createElement(
					Button,
					{
						type: 'button',
						variant: 'primary',
						isBusy: adoptionRunning,
						disabled: adoptionRunning,
						onClick: onImportOnly,
					},
					adoptionRunning ? __('Importing media', 'npcink-toolbox') : __('Import media for paragraph', 'npcink-toolbox')
				) : createElement(
					Button,
					{
						type: 'button',
						variant: 'primary',
						isBusy: adoptionRunning,
						disabled: adoptionRunning,
						onClick: onAdoptFeatured,
					},
					adoptionRunning ? __('Adopting image', 'npcink-toolbox') : __('Adopt as featured image', 'npcink-toolbox')
				),
				paragraphMode ? null : createElement(
					Button,
					{
						type: 'button',
						variant: 'secondary',
						disabled: adoptionRunning,
						onClick: onImportOnly,
					},
					__('Import media only', 'npcink-toolbox')
				)
			),
			createElement('span', { className: 'npcink-toolbox-editor-support__selected-note' }, selectOnlyMode ? __('Selection is returned to the calling field. Toolbox does not write settings directly.', 'npcink-toolbox') : paragraphMode ? __('Uses Adapter/Core for media import and media SEO fields. Toolbox does not insert images into the paragraph directly.', 'npcink-toolbox') : __('Uses Adapter/Core for import, media SEO fields, and featured image changes. Toolbox does not write media directly.', 'npcink-toolbox')),
			createElement(
				'div',
				{ className: 'npcink-toolbox-editor-support__seo-fields' },
				createElement('h3', null, __('Media SEO', 'npcink-toolbox')),
				createElement(TextareaControl, {
					label: __('Alt text', 'npcink-toolbox'),
					value: seo.alt || '',
					disabled: adoptionRunning,
					__next40pxDefaultSize: true,
					onChange: (value) => onSeoFieldChange('alt', value),
				}),
				createElement(
					'details',
					{ className: 'npcink-toolbox-editor-support__image-details' },
					createElement('summary', null, __('Title and description', 'npcink-toolbox')),
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
				sourceUrl ? createElement('a', { href: sourceUrl, target: '_blank', rel: 'noreferrer' }, __('Open source', 'npcink-toolbox')) : null,
				selectedImage.source_type ? createElement('small', null, __('Type: ', 'npcink-toolbox') + formatMetaLabel(selectedImage.source_type)) : null,
				imageFormatLabel(selectedImage) ? createElement('small', null, __('Format: ', 'npcink-toolbox') + imageFormatLabel(selectedImage)) : null,
				selectedImage.match_reason ? createElement('small', null, __('Match reason: ', 'npcink-toolbox') + selectedImage.match_reason) : null,
				Array.isArray(selectedImage.visual_keywords) && selectedImage.visual_keywords.length ? createElement('small', null, __('Visual keywords: ', 'npcink-toolbox') + selectedImage.visual_keywords.slice(0, 6).join(', ')) : null,
				selectedImage.attribution ? createElement('small', null, selectedImage.attribution) : null,
				selectedImage.download_location ? createElement('small', null, __('Download tracking preserved', 'npcink-toolbox')) : null,
				createElement('small', null, __('Filename: ', 'npcink-toolbox') + (seo.file_name || ''))
			),
			renderEditorImageFeedbackControls(selectedImage, feedbackRunning, feedbackStatus, onSubmitFeedback),
			adoptionError ? createElement(Notice, { status: 'error', isDismissible: false }, adoptionError) : null,
			renderAdoptionResult(adoptionResult)
		);
	}

	function renderImageDiagnostics(payload) {
		if (!payload || typeof payload !== 'object') {
			return null;
		}

		const source = payload.sections && payload.sections.image_candidates ? payload.sections.image_candidates : payload;
		const sourceLabel = imageResultSourceLabel(payload);
		const cloudMessage = source.message ? String(source.message) : '';
		const displayMessage = cloudMessage.toLowerCase().indexOf('connect npcink cloud') >= 0
			? __('Npcink Cloud Addon is not connected or not configured for managed image-source search.', 'npcink-toolbox')
			: cloudMessage;
		const diagnostics = [
			source.status ? __('Cloud status: ', 'npcink-toolbox') + formatMetaLabel(source.status) : '',
			sourceLabel ? __('Source: ', 'npcink-toolbox') + sourceLabel : '',
			source.result_count !== undefined ? __('Shown: ', 'npcink-toolbox') + String(source.result_count) : '',
			source.candidate_source_count !== undefined ? __('Received: ', 'npcink-toolbox') + String(source.candidate_source_count) : '',
			displayMessage ? __('Cloud note: ', 'npcink-toolbox') + displayMessage : '',
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

	function renderImageVisualBrief(payload, onUsePrompt) {
		if (!payload || typeof payload !== 'object') {
			return null;
		}

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
		const chips = []
			.concat(brief.primary_query ? [brief.primary_query] : [])
			.concat(Array.isArray(brief.alternate_queries) ? brief.alternate_queries.slice(0, 4) : [])
			.filter(Boolean);
		const status = [brief.status, source.rerank_status, source.site_context_status].filter(Boolean).map(formatMetaLabel);
		if (!chips.length && !promptCandidates.length && !brief.visual_intent && !status.length) {
			return null;
		}

		return createElement(
			'div',
			{ className: 'npcink-toolbox-editor-support__visual-brief' },
			createElement('strong', null, __('Cloud visual brief', 'npcink-toolbox')),
			brief.visual_intent ? createElement('span', null, truncateText(brief.visual_intent, 160)) : null,
			chips.length ? createElement(
				'div',
				{ className: 'npcink-toolbox-editor-support__query-chips' },
				chips.map((chip, index) => createElement('span', { key: String(index) }, chip))
			) : null,
			promptCandidates.length ? createElement(
				'div',
				{ className: 'npcink-toolbox-editor-support__prompt-candidates' },
				createElement('small', null, __('AI prompt candidates', 'npcink-toolbox')),
				promptCandidates.map((candidate, index) => createElement(
					'button',
					{
						key: String(candidate.id || index),
						type: 'button',
						onClick: () => onUsePrompt && onUsePrompt(String(candidate.prompt || '')),
					},
					truncateText(candidate.label || candidate.prompt, 72)
				))
			) : null,
			status.length ? createElement('small', null, status.join(' | ')) : null
		);
	}

	function extractKnowledgeItems(section) {
		if (!section || typeof section !== 'object') {
			return [];
		}
		return section.results || section.items || [];
	}

	function extractWritingSupportItems(section) {
		return extractKnowledgeItems(section).map((item) => {
			const support = item && item.writing_support && typeof item.writing_support === 'object' ? item.writing_support : {};
			const evidence = support.evidence_source && typeof support.evidence_source === 'object' ? support.evidence_source : {};
			const tasks = Array.isArray(support.pre_draft_tasks) ? support.pre_draft_tasks.map(formatMetaLabel).join(' | ') : '';
			return {
				name: evidence.title || item.title || __('Writing preparation item', 'npcink-toolbox'),
				detail: [
					support.source_role ? __('Role: ', 'npcink-toolbox') + formatMetaLabel(support.source_role) : '',
					tasks ? __('Next: ', 'npcink-toolbox') + tasks : '',
					item.reason || '',
				].filter(Boolean).join(' · '),
			};
		});
	}

	function discoverabilitySuggestionItems(section) {
		const suggestions = section && section.candidate_suggestions ? section.candidate_suggestions : {};
		return Object.keys(suggestions).map((field) => ({
			name: field,
			detail: String(suggestions[field] || ''),
		}));
	}

	function renderSummaryOptimization(section) {
		if (!section || typeof section !== 'object') {
			return null;
		}

		const blocks = [];
		const summary = section.summary_candidates && typeof section.summary_candidates === 'object' ? section.summary_candidates : {};
		const summaryText = summary.output_text || '';
		blocks.push(createElement('h4', { key: 'summary-optimization-title' }, __('Summary and terms optimization', 'npcink-toolbox')));
		if (section.input_scope) {
			blocks.push(createElement('h4', { key: 'summary-input-scope-title' }, __('Input scope', 'npcink-toolbox')));
			blocks.push(renderItems([section.input_scope], __('No input scope returned.', 'npcink-toolbox')));
		}
		if (summary.status === 'error') {
			blocks.push(createElement('p', { key: 'summary-ai-error', className: 'npcink-toolbox-editor-support__muted' }, summary.message || __('AI summary candidates were unavailable.', 'npcink-toolbox')));
		} else if (summaryText) {
			blocks.push(createElement('p', { key: 'summary-ai-output' }, truncateText(summaryText, 700)));
		}

		if (section.summary_layers) {
			blocks.push(createElement('h4', { key: 'summary-layers-title' }, __('Summary layers', 'npcink-toolbox')));
			blocks.push(renderItems(section.summary_layers.items || [], __('No summary layer candidates returned.', 'npcink-toolbox')));
		}

		blocks.push(createElement('h4', { key: 'summary-category-title' }, __('Category candidates', 'npcink-toolbox')));
		blocks.push(renderItems(section.category_candidates || [], __('No matching existing categories found.', 'npcink-toolbox')));
		blocks.push(createElement('h4', { key: 'summary-tag-title' }, __('Tag candidates', 'npcink-toolbox')));
		blocks.push(renderItems(section.tag_candidates || [], __('No matching existing tags found.', 'npcink-toolbox')));
		if (section.proposed_new_terms) {
			blocks.push(createElement('h4', { key: 'summary-new-terms-title' }, __('Proposed new terms', 'npcink-toolbox')));
			blocks.push(renderItems(section.proposed_new_terms.items || [], section.proposed_new_terms.empty_message || __('No proposed new terms returned.', 'npcink-toolbox')));
		}

		if (section.optimization_strategy && Array.isArray(section.optimization_strategy.ranking_signals)) {
			blocks.push(createElement('h4', { key: 'summary-strategy-title' }, __('Ranking and dedupe strategy', 'npcink-toolbox')));
			blocks.push(renderItems(section.optimization_strategy.ranking_signals, __('No ranking strategy returned.', 'npcink-toolbox')));
		}

		if (section.discoverability) {
			blocks.push(createElement('h4', { key: 'summary-discoverability-title' }, __('Discoverability suggestions', 'npcink-toolbox')));
			blocks.push(renderItems(discoverabilitySuggestionItems(section.discoverability), __('No discoverability candidates returned.', 'npcink-toolbox')));
		}

		if (section.related_content) {
			blocks.push(createElement('h4', { key: 'summary-related-title' }, __('Related Site Knowledge', 'npcink-toolbox')));
			blocks.push(renderItems(extractKnowledgeItems(section.related_content), __('No related content returned.', 'npcink-toolbox')));
		}

		if (Array.isArray(section.risk_notes) && section.risk_notes.length) {
			blocks.push(createElement('h4', { key: 'summary-risk-title' }, __('Review notes', 'npcink-toolbox')));
			blocks.push(renderItems(section.risk_notes.map((note) => ({ name: note })), __('No review notes returned.', 'npcink-toolbox')));
		}

		if (section.review_metrics) {
			blocks.push(createElement('h4', { key: 'summary-metrics-title' }, __('Review metrics', 'npcink-toolbox')));
			blocks.push(renderItems(section.review_metrics.items || [], __('No review metrics returned.', 'npcink-toolbox')));
		}

		if (section.handoff_preview) {
			if (Array.isArray(section.handoff_preview.auto_apply_actions)) {
				blocks.push(createElement('h4', { key: 'summary-auto-apply-title' }, __('Core handoff candidates', 'npcink-toolbox')));
				blocks.push(renderItems(section.handoff_preview.auto_apply_actions, __('No Core handoff candidates returned.', 'npcink-toolbox')));
			}
			blocks.push(createElement('h4', { key: 'summary-handoff-preview-title' }, __('Handoff preview', 'npcink-toolbox')));
			blocks.push(renderItems((section.handoff_preview.next_steps || []).map((step) => ({ name: step })), __('No handoff preview returned.', 'npcink-toolbox')));
		}

		return createElement('div', { className: 'npcink-toolbox-editor-support__optimization' }, blocks);
	}

	function renderResult(payload) {
		if (!payload || typeof payload !== 'object') {
			return null;
		}

		const sections = payload.sections || {};
		const blocks = [];
		blocks.push(
			createElement(
				'div',
				{ key: 'summary', className: 'npcink-toolbox-editor-support__summary' },
				createElement('strong', null, payload.intent ? formatIntentLabel(payload.intent) : __('Content support', 'npcink-toolbox')),
				createElement('span', null, __('Suggestions only. Final writes require Core approval.', 'npcink-toolbox'))
			)
		);

		if (sections.writing_support) {
			blocks.push(createElement('h4', { key: 'writing-support-title' }, __('Writing preparation', 'npcink-toolbox')));
			blocks.push(renderItems(extractWritingSupportItems(sections.writing_support), __('No writing preparation evidence returned.', 'npcink-toolbox')));
		}

		if (sections.checks) {
			blocks.push(createElement('h4', { key: 'checks-title' }, __('Checks', 'npcink-toolbox')));
			blocks.push(renderItems(sections.checks.items || [], __('No checks returned.', 'npcink-toolbox')));
		}

		if (sections.summary_terms_optimization) {
			blocks.push(renderSummaryOptimization(sections.summary_terms_optimization));
		}

		if (sections.taxonomy_terms) {
			blocks.push(createElement('h4', { key: 'terms-title' }, __('Term candidates', 'npcink-toolbox')));
			blocks.push(renderItems(sections.taxonomy_terms.items || [], __('No matching existing terms found.', 'npcink-toolbox')));
		}

		if (sections.site_knowledge) {
			blocks.push(createElement('h4', { key: 'links-title' }, __('Site Knowledge', 'npcink-toolbox')));
			blocks.push(renderItems(extractKnowledgeItems(sections.site_knowledge), __('No related content returned.', 'npcink-toolbox')));
		}

		if (sections.duplicate_check) {
			blocks.push(createElement('h4', { key: 'duplicate-title' }, __('Duplicate check', 'npcink-toolbox')));
			blocks.push(renderItems(extractKnowledgeItems(sections.duplicate_check), __('No duplicate-risk candidates returned.', 'npcink-toolbox')));
		}

		if (sections.image_candidates) {
			blocks.push(createElement('h4', { key: 'images-title' }, __('Image candidates', 'npcink-toolbox')));
			blocks.push(renderItems(extractImageItems(sections.image_candidates), __('No image candidates returned.', 'npcink-toolbox')));
		}

		if (sections.discoverability && sections.discoverability.candidate_suggestions) {
			blocks.push(createElement('h4', { key: 'discoverability-title' }, __('Discoverability', 'npcink-toolbox')));
			blocks.push(renderItems(discoverabilitySuggestionItems(sections.discoverability), __('No discoverability candidates returned.', 'npcink-toolbox')));
		}

		return createElement('div', { className: 'npcink-toolbox-editor-support__result' }, blocks);
	}

	function ContentSupportControls() {
		const postContext = usePostContext();
		const [running, setRunning] = useState('');
		const [result, setResult] = useState(null);
		const [error, setError] = useState('');
		const [imageModalOpen, setImageModalOpen] = useState(false);
		const [imageRunning, setImageRunning] = useState('');
		const [imageResult, setImageResult] = useState(null);
		const [imageError, setImageError] = useState('');
		const [imageGuidance, setImageGuidance] = useState('');
		const [imageQuery, setImageQuery] = useState('');
		const [imageSearchMode, setImageSearchMode] = useState('source');
		const [imageMode, setImageMode] = useState('featured');
		const [aiImageAspectRatio, setAiImageAspectRatio] = useState('16:9');
		const [aiImageResolution, setAiImageResolution] = useState('high');
		const [aiImageCandidateCount, setAiImageCandidateCount] = useState('1');
		const [imagePicker, setImagePicker] = useState(() => normalizeImagePickerOptions({ mode: 'featured' }));
		const [selectedImage, setSelectedImage] = useState(null);
		const [selectedImageSeo, setSelectedImageSeo] = useState(null);
		const [imageAdoptionRunning, setImageAdoptionRunning] = useState(false);
		const [imageAdoptionResult, setImageAdoptionResult] = useState(null);
		const [imageAdoptionError, setImageAdoptionError] = useState('');
		const [imageFeedbackRunning, setImageFeedbackRunning] = useState('');
		const [imageFeedbackStatus, setImageFeedbackStatus] = useState(null);

		useEffect(() => {
			function handleParagraphImageRequest(event) {
				const detail = event && event.detail && typeof event.detail === 'object' ? event.detail : {};
				openImageSourcePicker({ mode: 'paragraph', context: detail });
			}

			function handleImageSourcePickerRequest(event) {
				const detail = event && event.detail && typeof event.detail === 'object' ? event.detail : {};
				openImageSourcePicker(detail);
			}

			if (typeof window === 'undefined' || !window.addEventListener) {
				return undefined;
			}

			window.addEventListener(PARAGRAPH_IMAGE_EVENT, handleParagraphImageRequest);
			window.addEventListener(IMAGE_SOURCE_PICKER_EVENT, handleImageSourcePickerRequest);
			return () => {
				window.removeEventListener(PARAGRAPH_IMAGE_EVENT, handleParagraphImageRequest);
				window.removeEventListener(IMAGE_SOURCE_PICKER_EVENT, handleImageSourcePickerRequest);
			};
		});

		async function runFlow(intent) {
			if (intent === 'image_candidates') {
				openImageSourcePicker({ mode: 'featured' });
				return;
			}

			setRunning(intent);
			setError('');
			setResult(null);
			try {
				const payload = Object.assign({}, postContext, {
					intent,
					category_ids: Array.isArray(postContext.category_ids) ? postContext.category_ids.join(',') : '',
					tag_ids: Array.isArray(postContext.tag_ids) ? postContext.tag_ids.join(',') : '',
				});
				setResult(await postJson('editor/content-support', payload));
			} catch (requestError) {
				setError(requestError && requestError.message ? requestError.message : __('Request failed.', 'npcink-toolbox'));
			} finally {
				setRunning('');
			}
		}

		function resetImageFeedbackState() {
			setImageFeedbackRunning('');
			setImageFeedbackStatus(null);
		}

		async function runAutoImageRecommendations(modeOverride, contextOverride, pickerOverride) {
			const activePicker = normalizeImagePickerOptions(pickerOverride || imagePicker || { mode: imageMode });
			const activeImageMode = modeOverride || activePicker.mode;
			const imageContext = imageRequestContext(postContext, contextOverride || activePicker.context);
			const cacheKey = imageSearchCacheKey('auto', activePicker, '', imageContext);
			const cachedResult = readCachedImageResult(cacheKey);
			if (!hasDraftImageContext(imageContext)) {
				setImageRunning('');
				setImageResult(null);
				setImageError('');
				setImageGuidance(__('Add a title, excerpt, body text, or select a paragraph to use draft-based image recommendations, or enter a manual search query.', 'npcink-toolbox'));
				return;
			}
			if (cachedResult) {
				setImageRunning('');
				setImageError('');
				setImageGuidance(__('Showing recent image-source results for this context.', 'npcink-toolbox'));
				setImageResult(cachedResult);
				setSelectedImage(null);
				setSelectedImageSeo(null);
				setImageAdoptionResult(null);
				setImageAdoptionError('');
				resetImageFeedbackState();
				return;
			}
			setImageRunning('auto');
			setImageError('');
			setImageGuidance('');
			setImageResult(null);
			setSelectedImage(null);
			setSelectedImageSeo(null);
			setImageAdoptionResult(null);
			setImageAdoptionError('');
			resetImageFeedbackState();
			try {
				const payload = Object.assign({}, imageContext, {
					intent: 'image_candidates',
					image_mode: activePicker.imageUse,
					visual_context: buildImageVisualContext(postContext, activeImageMode, '', activePicker.context, activePicker.imageUse),
					category_ids: Array.isArray(imageContext.category_ids) ? imageContext.category_ids.join(',') : '',
					tag_ids: Array.isArray(imageContext.tag_ids) ? imageContext.tag_ids.join(',') : '',
				});
				const result = await postJson('editor/content-support', payload);
				writeCachedImageResult(cacheKey, result);
				setImageResult(result);
			} catch (requestError) {
				setImageError(formatImageErrorMessage(requestError, __('Cloud image recommendation failed.', 'npcink-toolbox')));
			} finally {
				setImageRunning('');
			}
		}

		async function runImageSearch(event, suggestedQuery) {
			if (event && event.preventDefault) {
				event.preventDefault();
			}
			const activePicker = normalizeImagePickerOptions(imagePicker || { mode: imageMode });
			const query = String(suggestedQuery || imageQuery || '').trim();
			if (!query) {
				runAutoImageRecommendations(activePicker.mode, activePicker.context, activePicker);
				return;
			}
			const imageContext = imageRequestContext(postContext, activePicker.context);
			const cacheKey = imageSearchCacheKey('manual', activePicker, query, imageContext);
			const cachedResult = readCachedImageResult(cacheKey);
			setImageQuery(query);
			if (cachedResult) {
				setImageRunning('');
				setImageError('');
				setImageGuidance(__('Showing recent image-source results for this query.', 'npcink-toolbox'));
				setImageResult(cachedResult);
				setSelectedImage(null);
				setSelectedImageSeo(null);
				setImageAdoptionResult(null);
				setImageAdoptionError('');
				resetImageFeedbackState();
				return;
			}
			setImageRunning('search');
			setImageError('');
			setImageGuidance('');
			setImageResult(null);
			setSelectedImage(null);
			setSelectedImageSeo(null);
			setImageAdoptionResult(null);
			setImageAdoptionError('');
			resetImageFeedbackState();
			try {
				const result = await postJson('image-candidates', {
					query,
					provider: 'auto',
					per_page: 8,
					image_mode: activePicker.imageUse,
					visual_context: buildImageVisualContext(postContext, activePicker.mode, query, activePicker.context, activePicker.imageUse),
				});
				writeCachedImageResult(cacheKey, result);
				setImageResult(result);
			} catch (requestError) {
				setImageError(formatImageErrorMessage(requestError, __('Cloud image search failed.', 'npcink-toolbox')));
			} finally {
				setImageRunning('');
			}
		}

		async function runAiImageGeneration(event) {
			if (event && event.preventDefault) {
				event.preventDefault();
			}
			const activePicker = normalizeImagePickerOptions(imagePicker || { mode: imageMode });
			const context = imageRequestContext(postContext || {}, activePicker.context);
			const prompt = defaultAiImageGenerationPrompt(postContext, activePicker, imageQuery, aiImageAspectRatio);
			if (!prompt) {
				setImageError(__('Enter an AI image prompt, article title, or selected paragraph before generating.', 'npcink-toolbox'));
				return;
			}
			setImageRunning('generate');
			setImageError('');
			setImageGuidance('');
			setImageResult(null);
			setSelectedImage(null);
			setSelectedImageSeo(null);
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
					media_context: {
						title: truncateText(postContext.title || context.title || imageQuery || '', 120),
						alt: '',
						description: '',
					},
					handoff: {
						trigger: 'manual_user_action',
						action_id: 'ai_generate_image',
						image_use: activePicker.imageUse,
						runtime_request_template: {
							ability_name: 'magick-ai-cloud/generate-image',
						},
					},
				});
				if (result && (result.code || (result.data && result.data.cloud_error_code))) {
					throw result;
				}
				setImageResult(result);
				setImageGuidance(__('Showing AI-generated image candidates. Review and adopt through Core before importing or setting featured media.', 'npcink-toolbox'));
			} catch (requestError) {
				setImageError(formatImageErrorMessage(requestError, __('AI image generation failed.', 'npcink-toolbox')));
			} finally {
				setImageRunning('');
			}
		}

		function useSuggestedImageQuery(query) {
			runImageSearch(null, query);
		}

		function useAiPromptCandidate(prompt) {
			const reviewedPrompt = String(prompt || '').trim();
			if (!reviewedPrompt) {
				return;
			}
			setImageSearchMode('generate');
			setImageQuery(reviewedPrompt);
			setImageGuidance(__('Review the suggested prompt, then generate an AI image candidate.', 'npcink-toolbox'));
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
			const initialSearchMode = activePicker.initialSearchMode || 'source';
			setImagePicker(activePicker);
			setImageMode(activePicker.mode);
			setImageSearchMode(initialSearchMode);
			setImageModalOpen(true);
			setImageQuery(activePicker.initialQuery || '');
			setImageGuidance('');
			setSelectedImage(null);
			setSelectedImageSeo(null);
			setImageAdoptionResult(null);
			setImageAdoptionError('');
			resetImageFeedbackState();
			if (activePicker.autoSearch && initialSearchMode !== 'generate') {
				runAutoImageRecommendations(activePicker.mode, activePicker.context, activePicker);
			} else {
				setImageRunning('');
				setImageResult(null);
				setImageError('');
			}
		}

		function openImageRecommendations(mode, contextOverride) {
			openImageSourcePicker({ mode: mode === 'paragraph' ? 'paragraph' : 'featured', context: contextOverride || {} });
		}

		function selectImageCandidate(image) {
			const activePicker = normalizeImagePickerOptions(imagePicker || { mode: imageMode });
			const seoContext = imageRequestContext(postContext, activePicker.context);
			setSelectedImage(image);
			setSelectedImageSeo(buildImageSeoFields(image, seoContext));
			setImageAdoptionResult(null);
			setImageAdoptionError('');
			resetImageFeedbackState();
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
			const seoContext = imageRequestContext(postContext, activePicker.context);
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
			setImageGuidance(__('Image source selected for the calling field.', 'npcink-toolbox'));
			setImageAdoptionError('');
			if (activePicker.closeOnSelect) {
				setImageModalOpen(false);
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

		async function adoptSelectedImage(setFeaturedImage) {
			if (!selectedImage) {
				setImageAdoptionError(__('Select an image candidate first.', 'npcink-toolbox'));
				return;
			}

			setImageAdoptionRunning(true);
			setImageAdoptionError('');
			setImageAdoptionResult(null);
			try {
				const activePicker = normalizeImagePickerOptions(imagePicker || { mode: imageMode });
				const seoContext = imageRequestContext(postContext, activePicker.context);
				const seo = Object.assign({}, buildImageSeoFields(selectedImage, seoContext), selectedImageSeo || {});
				const planInput = imageAdoptionPlanInput(seo, setFeaturedImage);
				const plan = await postJson('flows/image-candidate-adoption-plan', planInput);
				try {
					const core = await postAdapterAdoption(plan, planInput);
					syncFeaturedMediaFromCore(core);
					setImageAdoptionResult({ plan, core, adoption_target: setFeaturedImage ? 'featured_image' : 'media_import' });
				} catch (coreError) {
					setImageAdoptionResult({ plan, core_error: coreError, adoption_target: setFeaturedImage ? 'featured_image' : 'media_import' });
				}
			} catch (requestError) {
				setImageAdoptionError(requestError && requestError.message ? requestError.message : __('Could not adopt the selected image.', 'npcink-toolbox'));
			} finally {
				setImageAdoptionRunning(false);
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
				const receipt = await postJson(
					'agent-feedback',
					editorImageAgentFeedbackPayload(imageResult || {}, selectedImage, imagePicker || { mode: imageMode }, option.outcome, option.labels)
				);
				setImageFeedbackStatus({
					status: 'success',
					message: receipt && receipt.accepted_for_eval
						? __('Feedback accepted for Cloud eval. Media import and WordPress writes remain local.', 'npcink-toolbox')
						: __('Feedback sent. Media import and WordPress writes remain local.', 'npcink-toolbox'),
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

			const images = extractImageCandidates(imageResult);
			const queryLabel = imageResult && (imageResult.query || (imageResult.sections && imageResult.sections.image_candidates && imageResult.sections.image_candidates.query)) ? (imageResult.query || imageResult.sections.image_candidates.query) : '';
			const activePicker = normalizeImagePickerOptions(imagePicker || { mode: imageMode });
			const activeInputContext = imageRequestContext(postContext, activePicker.context);
			const selectedContext = imageResult && imageResult.post_context
				? (imageResult.post_context.selected_text || imageResult.post_context.selected_block_text || activeInputContext.selected_text || activeInputContext.selected_block_text || '')
				: (activeInputContext.selected_text || activeInputContext.selected_block_text || '');
			const inspectorSeoContext = imageRequestContext(postContext, activePicker.context);
			const inspectorSeo = selectedImage ? Object.assign({}, buildImageSeoFields(selectedImage, inspectorSeoContext), selectedImageSeo || {}) : null;
			return createElement(
				Modal,
				{
					title: activePicker.title,
					onRequestClose: () => setImageModalOpen(false),
					className: 'npcink-toolbox-editor-support__image-modal',
				},
				createElement(
					'div',
					{ className: 'npcink-toolbox-editor-support__image-modal-body' },
					createElement('p', { className: 'npcink-toolbox-editor-support__intro' }, activePicker.intro),
					createElement(
						'form',
						{ className: 'npcink-toolbox-editor-support__image-search', onSubmit: imageSearchMode === 'generate' ? runAiImageGeneration : runImageSearch },
						createElement(
							'div',
							{ className: 'npcink-toolbox-editor-support__image-mode', role: 'group', 'aria-label': __('Image candidate mode', 'npcink-toolbox') },
							createElement(
								'button',
								{
									type: 'button',
									className: imageSearchMode === 'source' ? 'is-active' : '',
									'aria-pressed': imageSearchMode === 'source' ? 'true' : 'false',
									onClick: () => setImageSearchMode('source'),
								},
								__('Recommended image', 'npcink-toolbox')
							),
							createElement(
								'button',
								{
									type: 'button',
									className: imageSearchMode === 'generate' ? 'is-active' : '',
									'aria-pressed': imageSearchMode === 'generate' ? 'true' : 'false',
									onClick: () => setImageSearchMode('generate'),
								},
								__('Manual prompt', 'npcink-toolbox')
							)
						),
						createElement('input', {
							type: 'search',
							value: imageQuery,
							placeholder: imageSearchMode === 'generate' ? __('Review or enter an AI image prompt', 'npcink-toolbox') : __('Search or describe image needs', 'npcink-toolbox'),
							onChange: (event) => setImageQuery(event.target.value),
						}),
						imageSearchMode === 'generate' ? createElement(
							Button,
							{
								type: 'submit',
								variant: 'primary',
								isBusy: imageRunning === 'generate',
								disabled: Boolean(imageRunning),
							},
							imageRunning === 'generate' ? __('Generating', 'npcink-toolbox') : __('Generate AI image', 'npcink-toolbox')
						) : createElement(
							Button,
							{
								type: 'submit',
								variant: 'primary',
								isBusy: imageRunning === 'search',
								disabled: Boolean(imageRunning),
							},
							imageRunning === 'search' ? __('Recommending', 'npcink-toolbox') : __('Recommend images', 'npcink-toolbox')
						),
						createElement(
							Button,
							{
								type: 'button',
								variant: 'tertiary',
								className: 'npcink-toolbox-editor-support__article-search-button',
								isBusy: imageRunning === 'auto',
								disabled: Boolean(imageRunning),
								onClick: runAutoImageRecommendations,
							},
							__('Search from article', 'npcink-toolbox')
						)
					),
					imageSearchMode === 'generate' ? createElement(
						'div',
						{ className: 'npcink-toolbox-editor-support__image-options' },
						renderAiImageOption(
							__('Aspect ratio', 'npcink-toolbox'),
							aiImageAspectRatio,
							setAiImageAspectRatio,
							[
								{ value: '16:9', label: '16:9' },
								{ value: '1:1', label: '1:1' },
								{ value: '4:3', label: '4:3' },
								{ value: '3:4', label: '3:4' },
								{ value: '9:16', label: '9:16' },
							]
						),
						renderAiImageOption(
							__('Quality', 'npcink-toolbox'),
							aiImageResolution,
							setAiImageResolution,
							[
								{ value: 'high', label: __('High', 'npcink-toolbox') },
								{ value: 'medium', label: __('Medium', 'npcink-toolbox') },
								{ value: 'low', label: __('Low', 'npcink-toolbox') },
							]
						),
						renderAiImageOption(
							__('Candidates', 'npcink-toolbox'),
							aiImageCandidateCount,
							setAiImageCandidateCount,
							[
								{ value: '1', label: '1' },
								{ value: '2', label: '2' },
								{ value: '3', label: '3' },
								{ value: '4', label: '4' },
							]
						)
					) : null,
					imageGuidance ? createElement(Notice, { status: 'info', isDismissible: false }, imageGuidance) : null,
					imageError ? createElement(Notice, { status: 'error', isDismissible: false }, imageError) : null,
					createElement(
						'div',
						{ className: 'npcink-toolbox-editor-support__image-workspace' },
						createElement(
							'section',
							{ className: 'npcink-toolbox-editor-support__image-results' },
							selectedContext ? createElement(
								'details',
								{ className: 'npcink-toolbox-editor-support__selection-context' },
								createElement('summary', null, __('Input context', 'npcink-toolbox')),
								createElement('span', null, truncateText(selectedContext, 180))
							) : null,
							renderImageResultSummary(imageResult, images, queryLabel, selectedImage),
							renderImageCloudDetails(imageResult, useAiPromptCandidate),
								imageRunning ? createElement('div', { className: 'npcink-toolbox-editor-support__running' }, createElement(Spinner, null), createElement('span', null, imageRunning === 'generate' ? __('Generating AI image candidate...', 'npcink-toolbox') : __('Loading cloud image candidates...', 'npcink-toolbox'))) : null,
								imageResult && !imageRunning ? renderImageCandidateCards(images, imageResult, selectedImage, selectImageCandidate, useSuggestedImageQuery, activePicker) : null
						),
						createElement(
							'section',
							{ className: 'npcink-toolbox-editor-support__image-inspector' },
							renderSelectedImagePanel(
								selectedImage,
								inspectorSeo,
								imageAdoptionRunning,
								imageAdoptionResult,
								imageAdoptionError,
								activePicker,
									updateSelectedImageSeo,
									() => adoptSelectedImage(true),
									() => adoptSelectedImage(false),
									dispatchSelectedImageToCaller,
									imageFeedbackRunning,
									imageFeedbackStatus,
									submitSelectedImageFeedback
								)
							)
					)
				)
			);
		}

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
					createElement('p', { className: 'npcink-toolbox-editor-support__intro' }, __('Run fixed support flows around the current draft. Article text stays with the editor.', 'npcink-toolbox')),
					flows.map((flow) =>
						createElement(
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
								},
								flow.intent === 'image_candidates' ? __('Open', 'npcink-toolbox') : (running === flow.intent ? __('Running', 'npcink-toolbox') : __('Run', 'npcink-toolbox'))
							)
						)
					),
					running ? createElement('div', { className: 'npcink-toolbox-editor-support__running' }, createElement(Spinner, null), createElement('span', null, __('Running content support flow...', 'npcink-toolbox'))) : null,
					error ? createElement(Notice, { status: 'error', isDismissible: false }, error) : null,
					result ? renderResult(result) : null,
					config.adminUrl ? createElement('p', { className: 'npcink-toolbox-editor-support__admin-link' }, createElement(ExternalLink, { href: config.adminUrl }, __('Open Toolbox Content Support', 'npcink-toolbox'))) : null
				)
			),
			renderImageRecommendationModal()
		);
	}

	function dispatchParagraphImageRequest(detail) {
		dispatchImageSourcePickerRequest(Object.assign({ mode: 'paragraph' }, detail && typeof detail === 'object' ? { context: detail } : {}));
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
									icon: paragraphImageIcon,
									label: __('Paragraph image suggestions', 'npcink-toolbox'),
									title: __('Paragraph image suggestions', 'npcink-toolbox'),
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
