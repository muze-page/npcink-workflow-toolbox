(function (wp) {
	'use strict';

	const config = window.MagickAIToolboxEditorSupport || {};
	const element = wp.element || {};
	const components = wp.components || {};
	const data = wp.data || {};
	const editPost = wp.editPost || {};
	const plugins = wp.plugins || {};
	const i18n = wp.i18n || {};
	const createElement = element.createElement;
	const useState = element.useState;
	const useSelect = data.useSelect;
	const __ = i18n.__ || ((value) => value);

	if (!createElement || !useState || !useSelect || !plugins.registerPlugin || !editPost.PluginDocumentSettingPanel) {
		return;
	}

	const Button = components.Button || 'button';
	const Notice = components.Notice || function NoticeFallback(props) {
		return createElement('div', { className: 'magick-ai-toolbox-editor-support__notice' }, props.children);
	};
	const Spinner = components.Spinner || function SpinnerFallback() {
		return createElement('span', { className: 'magick-ai-toolbox-editor-support__spinner' }, '...');
	};
	const ExternalLink = components.ExternalLink || function ExternalLinkFallback(props) {
		return createElement('a', { href: props.href, target: '_blank', rel: 'noreferrer' }, props.children);
	};

	const flows = [
		{
			intent: 'publish_preflight',
			label: __('Publish preflight', 'magick-ai-toolbox'),
			description: __('Check missing terms, excerpt, image, duplicate risk, and discoverability hints.', 'magick-ai-toolbox'),
		},
		{
			intent: 'taxonomy_tags',
			label: __('Recommend terms', 'magick-ai-toolbox'),
			description: __('Suggest existing categories and tags from the current draft context.', 'magick-ai-toolbox'),
		},
		{
			intent: 'internal_links',
			label: __('Find internal links', 'magick-ai-toolbox'),
			description: __('Use Site Knowledge to find related public content for links.', 'magick-ai-toolbox'),
		},
		{
			intent: 'image_candidates',
			label: __('Find image candidates', 'magick-ai-toolbox'),
			description: __('Search configured image-source providers for featured or inline candidates.', 'magick-ai-toolbox'),
		},
	];

	function normalizeText(value) {
		if (value && typeof value === 'object' && value.raw !== undefined) {
			return String(value.raw || '');
		}
		return String(value || '');
	}

	function joinRestUrl(base, path) {
		return String(base || '').replace(/\/$/, '') + '/' + String(path || '').replace(/^\//, '');
	}

	async function postJson(path, payload) {
		const response = await fetch(joinRestUrl(config.restUrl, path), {
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

	function usePostContext() {
		return useSelect((select) => {
			const editor = select('core/editor');
			if (!editor) {
				return {};
			}

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
			};
		}, []);
	}

	function renderItems(items, emptyLabel) {
		if (!Array.isArray(items) || !items.length) {
			return createElement('p', { className: 'magick-ai-toolbox-editor-support__muted' }, emptyLabel || __('No candidates returned.', 'magick-ai-toolbox'));
		}

		return createElement(
			'ul',
			{ className: 'magick-ai-toolbox-editor-support__list' },
			items.slice(0, 8).map((item, index) => {
				const title = item.name || item.title || item.label || item.source_title || item.url || item.download_url || item.id || __('Candidate', 'magick-ai-toolbox');
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

	function extractKnowledgeItems(section) {
		if (!section || typeof section !== 'object') {
			return [];
		}
		return section.results || section.items || [];
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
				{ key: 'summary', className: 'magick-ai-toolbox-editor-support__summary' },
				createElement('strong', null, payload.intent ? payload.intent.replace(/_/g, ' ') : __('Content support', 'magick-ai-toolbox')),
				createElement('span', null, __('Suggestions only. Final writes require Core approval.', 'magick-ai-toolbox'))
			)
		);

		if (sections.checks) {
			blocks.push(createElement('h4', { key: 'checks-title' }, __('Checks', 'magick-ai-toolbox')));
			blocks.push(renderItems(sections.checks.items || [], __('No checks returned.', 'magick-ai-toolbox')));
		}

		if (sections.taxonomy_terms) {
			blocks.push(createElement('h4', { key: 'terms-title' }, __('Term candidates', 'magick-ai-toolbox')));
			blocks.push(renderItems(sections.taxonomy_terms.items || [], __('No matching existing terms found.', 'magick-ai-toolbox')));
		}

		if (sections.site_knowledge) {
			blocks.push(createElement('h4', { key: 'links-title' }, __('Site Knowledge', 'magick-ai-toolbox')));
			blocks.push(renderItems(extractKnowledgeItems(sections.site_knowledge), __('No related content returned.', 'magick-ai-toolbox')));
		}

		if (sections.duplicate_check) {
			blocks.push(createElement('h4', { key: 'duplicate-title' }, __('Duplicate check', 'magick-ai-toolbox')));
			blocks.push(renderItems(extractKnowledgeItems(sections.duplicate_check), __('No duplicate-risk candidates returned.', 'magick-ai-toolbox')));
		}

		if (sections.image_candidates) {
			blocks.push(createElement('h4', { key: 'images-title' }, __('Image candidates', 'magick-ai-toolbox')));
			blocks.push(renderItems(extractImageItems(sections.image_candidates), __('No image candidates returned.', 'magick-ai-toolbox')));
		}

		if (sections.discoverability && sections.discoverability.candidate_suggestions) {
			const suggestions = Object.keys(sections.discoverability.candidate_suggestions).map((field) => ({
				name: field,
				detail: String(sections.discoverability.candidate_suggestions[field] || ''),
			}));
			blocks.push(createElement('h4', { key: 'discoverability-title' }, __('Discoverability', 'magick-ai-toolbox')));
			blocks.push(renderItems(suggestions, __('No discoverability candidates returned.', 'magick-ai-toolbox')));
		}

		return createElement('div', { className: 'magick-ai-toolbox-editor-support__result' }, blocks);
	}

	function ContentSupportPanel() {
		const postContext = usePostContext();
		const [running, setRunning] = useState('');
		const [result, setResult] = useState(null);
		const [error, setError] = useState('');

		async function runFlow(intent) {
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
				setError(requestError && requestError.message ? requestError.message : __('Request failed.', 'magick-ai-toolbox'));
			} finally {
				setRunning('');
			}
		}

		return createElement(
			editPost.PluginDocumentSettingPanel,
			{
				name: 'magick-ai-content-support',
				title: __('Magick AI Content Support', 'magick-ai-toolbox'),
				className: 'magick-ai-toolbox-editor-support',
			},
			createElement('p', { className: 'magick-ai-toolbox-editor-support__intro' }, __('Run fixed support flows around the current draft. Article text stays with the editor.', 'magick-ai-toolbox')),
			flows.map((flow) =>
				createElement(
					'div',
					{ className: 'magick-ai-toolbox-editor-support__flow', key: flow.intent },
					createElement('div', null, createElement('strong', null, flow.label), createElement('span', null, flow.description)),
					createElement(
						Button,
						{
							variant: 'secondary',
							isBusy: running === flow.intent,
							disabled: Boolean(running),
							onClick: () => runFlow(flow.intent),
						},
						running === flow.intent ? __('Running', 'magick-ai-toolbox') : __('Run', 'magick-ai-toolbox')
					)
				)
			),
			running ? createElement('div', { className: 'magick-ai-toolbox-editor-support__running' }, createElement(Spinner, null), createElement('span', null, __('Running content support flow...', 'magick-ai-toolbox'))) : null,
			error ? createElement(Notice, { status: 'error', isDismissible: false }, error) : null,
			result ? renderResult(result) : null,
			config.adminUrl ? createElement('p', { className: 'magick-ai-toolbox-editor-support__admin-link' }, createElement(ExternalLink, { href: config.adminUrl }, __('Open Toolbox Content Support', 'magick-ai-toolbox'))) : null
		);
	}

	plugins.registerPlugin('magick-ai-toolbox-editor-content-support', {
		render: ContentSupportPanel,
	});
})(window.wp || {});
