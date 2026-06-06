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
			autoSearch: true,
			title: __('Image source suggestions', 'npcink-toolbox'),
			intro: __('Search uses the selected paragraph when available, plus article context, or a short visual query. Select one image to import it with media details and set it as the featured image through Adapter/Core.', 'npcink-toolbox'),
			emptyTitle: __('Select an image to adopt', 'npcink-toolbox'),
		},
		paragraph: {
			mode: 'paragraph',
			imageUse: 'paragraph_image',
			adoptionMode: 'media_import',
			autoSearch: true,
			title: __('Paragraph image suggestions', 'npcink-toolbox'),
			intro: __('Search uses the selected paragraph plus article context. Select one image to import it with media details through Adapter/Core.', 'npcink-toolbox'),
			emptyTitle: __('Select an image for this paragraph', 'npcink-toolbox'),
		},
		inline: {
			mode: 'inline',
			imageUse: 'inline_image',
			adoptionMode: 'media_import',
			autoSearch: true,
			title: __('Inline image suggestions', 'npcink-toolbox'),
			intro: __('Search uses the selected text plus article context. Select one image to import it with media details through Adapter/Core.', 'npcink-toolbox'),
			emptyTitle: __('Select an image source', 'npcink-toolbox'),
		},
		setting: {
			mode: 'setting',
			imageUse: 'setting_image',
			adoptionMode: 'select_only',
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
			description: __('Use AI, Site Knowledge, and saved context to suggest excerpt, category, and tag candidates.', 'npcink-toolbox'),
		},
		{
			intent: 'taxonomy_tags',
			label: __('Recommend terms', 'npcink-toolbox'),
			description: __('Suggest existing categories and tags from the current draft context.', 'npcink-toolbox'),
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
				const detail = item.reason || item.detail || item.excerpt || item.source_url || item.status || item.taxonomy || item.provider || '';
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
		return image.description || image.alt_description || image.title || image.prompt || image.id || __('Image candidate', 'npcink-toolbox');
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
		return Object.assign({}, preset, {
			mode: preset.mode,
			imageUse,
			adoptionMode,
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

	function renderImageCandidateCards(images, payload, selectedImage, onSelectImage, onUseSuggestion, picker) {
		if (!Array.isArray(images) || !images.length) {
			const source = payload && payload.sections && payload.sections.image_candidates ? payload.sections.image_candidates : (payload || {});
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
				const sourceMeta = [
					image.provider ? formatMetaLabel(image.provider) : '',
					image.source_type ? formatMetaLabel(image.source_type) : '',
					image.recommended_use ? formatMetaLabel(image.recommended_use) : '',
					image.license_review_status ? formatMetaLabel(image.license_review_status) : '',
					image.download_location ? __('Download tracking preserved', 'npcink-toolbox') : '',
				].filter(Boolean);
				const cardTags = [
					image.provider ? formatMetaLabel(image.provider) : '',
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
						createElement('strong', null, truncateText(imageTitle(image), 86)),
						image.match_reason ? createElement('span', { className: 'npcink-toolbox-editor-support__match-reason' }, truncateText(image.match_reason, 120)) : null,
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
						),
						createElement(
							'details',
							{ className: 'npcink-toolbox-editor-support__image-details', onClick: (event) => event.stopPropagation() },
							createElement('summary', null, __('Source details', 'npcink-toolbox')),
							image.attribution ? createElement('span', null, image.attribution) : null,
							sourceMeta.length ? createElement('small', null, sourceMeta.join(' | ')) : null,
							Array.isArray(image.risk_flags) && image.risk_flags.length ? createElement('small', null, __('Risk flags: ', 'npcink-toolbox') + image.risk_flags.slice(0, 4).map(formatMetaLabel).join(', ')) : null,
							sourceUrl ? createElement('small', null, sourceUrl) : null
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

	function renderSelectedImagePanel(selectedImage, seoFields, adoptionRunning, adoptionResult, adoptionError, picker, onSeoFieldChange, onAdoptFeatured, onImportOnly, onSelectOnly) {
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
			renderInfoRow(__('Title', 'npcink-toolbox'), truncateText(imageTitle(selectedImage), 90), 'title'),
			renderInfoRow(__('Source', 'npcink-toolbox'), selectedImage.provider ? formatMetaLabel(selectedImage.provider) : '', 'source'),
			renderInfoRow(__('Type', 'npcink-toolbox'), selectedImage.source_type ? formatMetaLabel(selectedImage.source_type) : '', 'type'),
			renderInfoRow(__('Size', 'npcink-toolbox'), imageDimensionLabel(selectedImage), 'size'),
			renderInfoRow(__('Format', 'npcink-toolbox'), imageFormatLabel(selectedImage), 'format'),
			renderInfoRow(__('Recommended use', 'npcink-toolbox'), selectedImage.recommended_use ? formatMetaLabel(selectedImage.recommended_use) : '', 'use'),
			renderInfoRow(__('Review', 'npcink-toolbox'), selectedImage.license_review_status ? formatMetaLabel(selectedImage.license_review_status) : '', 'review'),
		].filter(Boolean);
		return createElement(
			'aside',
			{ className: 'npcink-toolbox-editor-support__selected-image' },
			createElement('h3', null, __('Selected image', 'npcink-toolbox')),
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
			sourceUrl ? createElement('a', { href: sourceUrl, target: '_blank', rel: 'noreferrer' }, __('Open source', 'npcink-toolbox')) : null,
			createElement(
				'details',
				{ className: 'npcink-toolbox-editor-support__image-details' },
				createElement('summary', null, __('Source details', 'npcink-toolbox')),
				selectedImage.match_reason ? createElement('small', null, __('Match reason: ', 'npcink-toolbox') + selectedImage.match_reason) : null,
				Array.isArray(selectedImage.visual_keywords) && selectedImage.visual_keywords.length ? createElement('small', null, __('Visual keywords: ', 'npcink-toolbox') + selectedImage.visual_keywords.slice(0, 6).join(', ')) : null,
				selectedImage.attribution ? createElement('small', null, selectedImage.attribution) : null,
				selectedImage.download_location ? createElement('small', null, __('Download tracking preserved', 'npcink-toolbox')) : null,
				createElement('small', null, __('Filename: ', 'npcink-toolbox') + (seo.file_name || ''))
			),
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
			),
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
			adoptionError ? createElement(Notice, { status: 'error', isDismissible: false }, adoptionError) : null,
			renderAdoptionResult(adoptionResult)
		);
	}

	function renderImageDiagnostics(payload) {
		if (!payload || typeof payload !== 'object') {
			return null;
		}

		const source = payload.sections && payload.sections.image_candidates ? payload.sections.image_candidates : payload;
		const cloudMessage = source.message ? String(source.message) : '';
		const displayMessage = cloudMessage.toLowerCase().indexOf('connect npcink cloud') >= 0
			? __('Npcink Cloud Addon is not connected or not configured for managed image-source search.', 'npcink-toolbox')
			: cloudMessage;
		const diagnostics = [
			source.status ? __('Cloud status: ', 'npcink-toolbox') + formatMetaLabel(source.status) : '',
			source.resolved_provider ? __('Source: ', 'npcink-toolbox') + formatMetaLabel(source.resolved_provider) : '',
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

	function renderImageVisualBrief(payload) {
		if (!payload || typeof payload !== 'object') {
			return null;
		}

		const source = payload.sections && payload.sections.image_candidates ? payload.sections.image_candidates : payload;
		const brief = source.visual_brief && typeof source.visual_brief === 'object' ? source.visual_brief : {};
		const chips = []
			.concat(brief.primary_query ? [brief.primary_query] : [])
			.concat(Array.isArray(brief.alternate_queries) ? brief.alternate_queries.slice(0, 4) : [])
			.filter(Boolean);
		const status = [brief.status, source.rerank_status, source.site_context_status].filter(Boolean).map(formatMetaLabel);
		if (!chips.length && !brief.visual_intent && !status.length) {
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
		if (summary.status === 'error') {
			blocks.push(createElement('p', { key: 'summary-ai-error', className: 'npcink-toolbox-editor-support__muted' }, summary.message || __('AI summary candidates were unavailable.', 'npcink-toolbox')));
		} else if (summaryText) {
			blocks.push(createElement('p', { key: 'summary-ai-output' }, truncateText(summaryText, 700)));
		}

		blocks.push(createElement('h4', { key: 'summary-category-title' }, __('Category candidates', 'npcink-toolbox')));
		blocks.push(renderItems(section.category_candidates || [], __('No matching existing categories found.', 'npcink-toolbox')));
		blocks.push(createElement('h4', { key: 'summary-tag-title' }, __('Tag candidates', 'npcink-toolbox')));
		blocks.push(renderItems(section.tag_candidates || [], __('No matching existing tags found.', 'npcink-toolbox')));

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
		const [imageMode, setImageMode] = useState('featured');
		const [imagePicker, setImagePicker] = useState(() => normalizeImagePickerOptions({ mode: 'featured' }));
		const [selectedImage, setSelectedImage] = useState(null);
		const [selectedImageSeo, setSelectedImageSeo] = useState(null);
		const [imageAdoptionRunning, setImageAdoptionRunning] = useState(false);
		const [imageAdoptionResult, setImageAdoptionResult] = useState(null);
		const [imageAdoptionError, setImageAdoptionError] = useState('');

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
			const query = String(suggestedQuery || imageQuery || '').trim();
			if (!query) {
				setImageError(__('Enter a search query for cloud image candidates.', 'npcink-toolbox'));
				return;
			}
			const activePicker = normalizeImagePickerOptions(imagePicker || { mode: imageMode });
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

		function useSuggestedImageQuery(query) {
			runImageSearch(null, query);
		}

		function openImageSourcePicker(options) {
			const activePicker = normalizeImagePickerOptions(options || { mode: 'featured' });
			setImagePicker(activePicker);
			setImageMode(activePicker.mode);
			setImageModalOpen(true);
			setImageQuery(activePicker.initialQuery || '');
			setImageGuidance('');
			setSelectedImage(null);
			setSelectedImageSeo(null);
			setImageAdoptionResult(null);
			setImageAdoptionError('');
			if (activePicker.autoSearch) {
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

		function renderImageRecommendationModal() {
			if (!imageModalOpen) {
				return null;
			}

			const images = extractImageCandidates(imageResult);
			const queryLabel = imageResult && (imageResult.query || (imageResult.sections && imageResult.sections.image_candidates && imageResult.sections.image_candidates.query)) ? (imageResult.query || imageResult.sections.image_candidates.query) : '';
			const selectedContext = imageResult && imageResult.post_context ? (imageResult.post_context.selected_text || imageResult.post_context.selected_block_text || '') : '';
			const activePicker = normalizeImagePickerOptions(imagePicker || { mode: imageMode });
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
						{ className: 'npcink-toolbox-editor-support__image-search', onSubmit: runImageSearch },
						createElement('input', {
							type: 'search',
							value: imageQuery,
							placeholder: __('Search image sources', 'npcink-toolbox'),
							onChange: (event) => setImageQuery(event.target.value),
						}),
						createElement(
							Button,
							{
								type: 'submit',
								variant: 'secondary',
								isBusy: imageRunning === 'search',
								disabled: Boolean(imageRunning),
							},
							imageRunning === 'search' ? __('Searching', 'npcink-toolbox') : __('Search', 'npcink-toolbox')
						),
						createElement(
							Button,
							{
								type: 'button',
								variant: 'tertiary',
								isBusy: imageRunning === 'auto',
								disabled: Boolean(imageRunning),
								onClick: runAutoImageRecommendations,
							},
							__('Use draft', 'npcink-toolbox')
						)
					),
					imageGuidance ? createElement(Notice, { status: 'info', isDismissible: false }, imageGuidance) : null,
					imageError ? createElement(Notice, { status: 'error', isDismissible: false }, imageError) : null,
					createElement(
						'div',
						{ className: 'npcink-toolbox-editor-support__image-workspace' },
						createElement(
							'section',
							{ className: 'npcink-toolbox-editor-support__image-results' },
							selectedContext ? createElement(
								'div',
								{ className: 'npcink-toolbox-editor-support__selection-context' },
								createElement('strong', null, __('Selected paragraph context', 'npcink-toolbox')),
								createElement('span', null, truncateText(selectedContext, 180))
							) : null,
							queryLabel ? createElement('p', { className: 'npcink-toolbox-editor-support__muted' }, __('Query: ', 'npcink-toolbox') + queryLabel) : null,
							renderImageVisualBrief(imageResult),
							renderImageDiagnostics(imageResult),
								imageRunning ? createElement('div', { className: 'npcink-toolbox-editor-support__running' }, createElement(Spinner, null), createElement('span', null, __('Loading cloud image candidates...', 'npcink-toolbox'))) : null,
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
								dispatchSelectedImageToCaller
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
