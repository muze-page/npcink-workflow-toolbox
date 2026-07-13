import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const sourcePath = new URL('../assets/editor-content-support.js', import.meta.url);
const source = fs.readFileSync(sourcePath, 'utf8');
const expose = `
	window.__NpcinkArticleDraftTest = {
		articleDraftPreviewBlocks,
		articleDraftPreviewPlanInput,
		editorBlockHasMeaningfulContent,
		currentEditorBodyIsEmpty,
	};
})(window.wp || {});`;
const instrumented = source.replace(/\}\)\(window\.wp \|\| \{\}\);\s*$/, expose);
assert.notEqual(instrumented, source, 'Editor script test seam must be injected.');

let currentBlocks = [];
const noop = () => {};
const wp = {
	apiFetch: noop,
	blocks: {
		createBlock: (name, attributes) => ({ name, attributes, innerBlocks: [] }),
	},
	blockEditor: { BlockControls: noop },
	components: {},
	data: {
		dispatch: () => ({}),
		select: () => ({ getBlocks: () => currentBlocks }),
		useSelect: () => ({}),
	},
	editPost: { PluginSidebar: noop },
	editor: { PluginSidebar: noop },
	element: {
		Fragment: 'div',
		createElement: noop,
		useEffect: noop,
		useRef: (value) => ({ current: value }),
		useState: (value) => [typeof value === 'function' ? value() : value, noop],
	},
	hooks: { addFilter: noop },
	i18n: { __: (value) => value, sprintf: (value) => value },
	plugins: { registerPlugin: noop },
};
const context = vm.createContext({
	console,
	setTimeout,
	clearTimeout,
	window: {
		NpcinkToolboxEditorSupport: {},
		addEventListener: noop,
		dispatchEvent: noop,
		location: { href: '' },
		navigator: {},
		removeEventListener: noop,
		wp,
	},
});
vm.runInContext(instrumented, context, { filename: 'editor-content-support.js' });

const helpers = context.window.__NpcinkArticleDraftTest;
assert.ok(helpers, 'Editor draft helpers must be exposed to the smoke test.');

const converted = helpers.articleDraftPreviewBlocks({
	sections: [
		{ heading: 'First <script>', body: 'Body & detail' },
		{ heading: '', body: 'Second paragraph' },
	],
});
assert.equal(converted.length, 3, 'Draft sections become heading and paragraph blocks.');
assert.equal(
	converted.map((block) => block.name).join(','),
	'core/heading,core/paragraph,core/paragraph',
	'Only native heading and paragraph blocks are created.'
);
assert.equal(converted[0].attributes.content, 'First &lt;script&gt;', 'Heading markup is escaped.');
assert.equal(converted[1].attributes.content, 'Body &amp; detail', 'Paragraph markup is escaped.');

const planInput = helpers.articleDraftPreviewPlanInput({
	title: 'Reviewed WordPress draft',
	excerpt: 'A short reviewed excerpt.',
	sections: [
		{ heading: 'First section', body: 'First body.' },
		{ heading: 'Second section', body: 'Second body.' },
	],
	verification_notes: ['Verify one factual claim.'],
	source_attribution_notes: ['Credit the source where required.'],
});
assert.equal(planInput.title, 'Reviewed WordPress draft', 'Core handoff keeps the reviewed draft title.');
assert.equal(planInput.excerpt, 'A short reviewed excerpt.', 'Core handoff keeps the reviewed excerpt.');
assert.equal(planInput.content_markdown, '## First section\n\nFirst body.\n\n## Second section\n\nSecond body.', 'Core handoff maps ordered draft sections to bounded markdown.');
assert.equal(planInput.risk_level, 'medium', 'AI-assisted article drafts keep a review-required risk posture.');
assert.equal(planInput.article_outline.sections.join(','), 'First section,Second section', 'Core handoff keeps the reviewed section order.');
assert.equal(planInput.used_sources[0], 'Credit the source where required.', 'Attribution notes remain plan evidence instead of becoming article body text.');
assert.equal(planInput.unverified_claims[0], 'Verify one factual claim.', 'Verification notes remain human-review evidence.');

currentBlocks = [];
assert.equal(helpers.currentEditorBodyIsEmpty('fallback ignored'), true, 'No Gutenberg blocks is empty.');
currentBlocks = null;
assert.equal(helpers.currentEditorBodyIsEmpty('Existing fallback body'), false, 'Unavailable block state falls back to the serialized body.');
currentBlocks = [{ name: 'core/paragraph', attributes: { content: '&nbsp;<br>' }, innerBlocks: [] }];
assert.equal(helpers.currentEditorBodyIsEmpty(''), true, 'An empty placeholder paragraph remains empty.');
currentBlocks = [{ name: 'core/paragraph', attributes: { content: 'Existing body' }, innerBlocks: [] }];
assert.equal(helpers.currentEditorBodyIsEmpty(''), false, 'Existing paragraph content blocks loading.');
currentBlocks = [{ name: 'core/image', attributes: {}, innerBlocks: [] }];
assert.equal(helpers.currentEditorBodyIsEmpty(''), false, 'Any non-paragraph block blocks loading.');

console.log('Article draft native-editor JavaScript behavior: ok');
