<?php
/**
 * Static contract checks for the first Toolbox release.
 *
 * @package Magick_AI_Toolbox
 */

$root = dirname( __DIR__ );

function toolbox_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}

	echo "PASS: {$message}\n";
}

$main = file_get_contents( $root . '/magick-ai-toolbox.php' );
toolbox_assert( false !== $main && str_contains( $main, 'Plugin Name: Magick AI Toolbox' ), 'Plugin header is present.' );
toolbox_assert( false !== strpos( $main, 'includes/Editor_Content_Support.php' ), 'Plugin bootstrap loads the post editor content support entrypoint.' );

$article_assistant_doc = file_get_contents( $root . '/docs/article-assistant-workbench.md' );
foreach ( array( 'Surface Budget', 'Article Assistant Workbench', 'one article per run', 'Do not present it as an', 'article generator, autonomous writer', 'no Cloud article generation', 'not the default Toolbox product surface', 'no default button that promises to write the article body' ) as $required_article_assistant_doc ) {
	toolbox_assert( false !== strpos( $article_assistant_doc, $required_article_assistant_doc ), 'Article Assistant workbench doc preserves surface budget: ' . $required_article_assistant_doc );
}

$positioning_doc = file_get_contents( $root . '/docs/product-positioning.md' );
foreach ( array( 'Relationship To OpenClaw', 'OpenClaw is the natural-language channel', 'Toolbox is the fixed-button product surface', 'same ability ids, plan artifact shapes', 'Core proposal handoff', 'separate approval path, media registry, prompt/model control plane, or', 'WordPress write executor' ) as $required_positioning_doc ) {
	toolbox_assert( false !== strpos( $positioning_doc, $required_positioning_doc ), 'Product positioning preserves OpenClaw fixed-button mapping: ' . $required_positioning_doc );
}
foreach ( array( 'content-support abilities', 'human-written articles', 'taxonomy/tag candidates', 'internal-link candidates', 'Article Assistant only', 'fallback workbench' ) as $required_support_positioning_doc ) {
	toolbox_assert( false !== strpos( $positioning_doc, $required_support_positioning_doc ), 'Product positioning keeps content-support-first article boundary: ' . $required_support_positioning_doc );
}

$boundary_doc = file_get_contents( $root . '/docs/boundary.md' );
foreach ( array( 'OpenClaw Button Surface Boundary', 'UX projection of the same local ability and Core proposal contracts', 'OpenClaw natural-language request', 'Toolbox fixed button', 'reviewed plan or candidate artifact', 'must not own OpenClaw projection truth', 'approval truth, prompt/model', 'media registry truth, or final WordPress write execution' ) as $required_boundary_doc ) {
	toolbox_assert( false !== strpos( $boundary_doc, $required_boundary_doc ), 'Boundary doc preserves Toolbox/OpenClaw split: ' . $required_boundary_doc );
}

$composition_doc = file_get_contents( $root . '/docs/ai-content-composition-abilities.md' );
foreach ( array( 'Fixed Button Mapping', 'OpenClaw natural-language recipes and Toolbox fixed buttons should compose the', 'same ability contracts', 'Article/media batch fallback', 'Adopt New Image', 'Optimize Existing Image', 'Core proposal for `magick-ai/adopt-cloud-media-derivative`', 'separate workflow runtime, direct write path, or approval store' ) as $required_composition_doc ) {
	toolbox_assert( false !== strpos( $composition_doc, $required_composition_doc ), 'Composition doc preserves fixed-button mapping: ' . $required_composition_doc );
}
foreach ( array( 'Content Support First', 'taxonomy/tag choices', 'publish/readiness checks', 'Article writing packs', 'fallback packaging', 'Publish preflight', 'reviewed content patch must go through Core' ) as $required_support_composition_doc ) {
	toolbox_assert( false !== strpos( $composition_doc, $required_support_composition_doc ), 'Composition doc keeps support-first sequence before article fallback: ' . $required_support_composition_doc );
}

$adr_product_surface = file_get_contents( $root . '/docs/decisions/ADR-001-toolbox-as-product-surface.md' );
foreach ( array( 'click-driven operator surface for the same local ability', 'Core proposal contracts', 'parallel recipe', 'workflow runtime, media registry, prompt/model control', 'WordPress write executor' ) as $required_adr_text ) {
	toolbox_assert( false !== strpos( $adr_product_surface, $required_adr_text ), 'ADR preserves Toolbox as OpenClaw fixed-flow surface: ' . $required_adr_text );
}

$readme = file_get_contents( $root . '/README.md' );
foreach ( array( 'Toolbox fixed buttons are the operator-click surface for repeatable OpenClaw', 'flows. They should reuse the same ability ids', 'same ability ids, plan artifact shapes, Adapter', 'Core proposal handoff', 'separate approval store, media', 'workflow runtime, prompt/model control plane', 'WordPress write' ) as $required_readme_text ) {
	toolbox_assert( false !== strpos( $readme, $required_readme_text ), 'README preserves fixed-button positioning: ' . $required_readme_text );
}

$admin_page = file_get_contents( $root . '/includes/Admin_Page.php' );
toolbox_assert( false !== strpos( $admin_page, "private const PARENT_MENU_SLUG = 'magick-ai';" ), 'Admin page targets the shared Magick AI parent menu.' );
toolbox_assert( false !== strpos( $admin_page, "private const MENU_SLUG        = 'magick-ai-toolbox';" ), 'Admin page uses stable Toolbox menu slug.' );
toolbox_assert( false !== strpos( $admin_page, 'add_submenu_page' ) && false !== strpos( $admin_page, '45' ), 'Admin page registers after Abilities and before Cloud Addon.' );
toolbox_assert( false !== strpos( $admin_page, 'add_management_page' ), 'Admin page keeps a Tools fallback for standalone installs.' );
toolbox_assert( false !== strpos( $admin_page, 'magick-ai-toolbox__status-strip' ), 'Admin page shows a compact connector and content-context status strip.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-tabs' ), 'Admin page separates tools, content context, and connectors into top-level tabs.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-tab-target="context" aria-selected="true"' ), 'Content Context is the default admin tab.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-tab-target="site-knowledge"' ) && false !== strpos( $admin_page, 'data-toolbox-site-knowledge' ), 'Admin page exposes a Site Knowledge operation panel.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-site-knowledge-sync-submit' ) && false !== strpos( $admin_page, 'Start indexing' ) && false !== strpos( $admin_page, 'Refresh index' ), 'Site Knowledge exposes one simple indexing action that changes from start to refresh.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-site-knowledge-status' ) && false === strpos( $admin_page, 'data-toolbox-site-knowledge-action-status' ), 'Site Knowledge keeps status refresh in the status section only.' );
toolbox_assert( false === strpos( $admin_page, '<select name="sync_mode"' ) && false === strpos( $admin_page, 'name="post_ids"' ) && false === strpos( $admin_page, 'Delete index' ), 'Site Knowledge hides rebuild/delete/post ID controls from the default user indexing action.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-context-sections' ) && false !== strpos( $admin_page, 'data-toolbox-context-panel' ), 'Content context uses a focused tabbed workspace.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-context-target="brief" aria-selected="true"' ) && false !== strpos( $admin_page, 'data-toolbox-context-target="boundaries"' ), 'Content context defaults to Brief and keeps Boundaries as a focused section.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-context-groups' ) && false !== strpos( $admin_page, 'data-toolbox-context-group-target="brief-profile"' ) && false !== strpos( $admin_page, 'data-toolbox-context-group-target="boundaries-preview"' ), 'Content context sections use a left field list and right detail panel.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-tab-target="tools" aria-selected="false"' ) && false !== strpos( $admin_page, 'Content Support' ), 'Tool execution is a secondary Content Support tab.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-tab-panel="connectors"' ), 'Connector settings are moved out of the default tools view.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-connectors' ) && false !== strpos( $admin_page, 'data-toolbox-connector-panel' ), 'Connector settings use a single active connector workspace.' );
toolbox_assert( false !== strpos( $admin_page, 'magick-ai-toolbox__connector-tabs' ) && false !== strpos( $admin_page, 'magick-ai-toolbox__connector-tab' ), 'Connector groups use horizontal sub tabs near the connector heading.' );
toolbox_assert( false === strpos( $admin_page, 'data-toolbox-connector-providers' ) && false === strpos( $admin_page, 'data-toolbox-connector-provider-target' ) && false === strpos( $admin_page, 'data-toolbox-connector-provider-panel' ), 'Connector settings no longer expose provider rail configuration locally.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-connector-target="search" aria-selected="true"' ), 'Search is the default connector section for Cloud web search testing.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-endpoint="web-search/test"' ) && false !== strpos( $admin_page, 'Run search test' ), 'Connector settings expose a Cloud-managed Web Search test action.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-endpoint="web-search/diagnostics"' ) && false !== strpos( $admin_page, 'Workflow diagnostic' ), 'Connector settings expose a Cloud web search workflow diagnostic action.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-connector-target="image" aria-selected="false"' ), 'Image connector stays available after the Cloud Search test section.' );
toolbox_assert( false === strpos( $admin_page, 'data-toolbox-connector-target="vector"' ) && false === strpos( $admin_page, 'data-toolbox-connector-panel="vector"' ), 'Connector settings no longer expose a Vector section.' );
toolbox_assert( false !== strpos( $admin_page, 'Cloud image sources' ) && false !== strpos( $admin_page, 'Cloud service' ) && false !== strpos( $admin_page, 'provider keys, quotas, health, and provider selection' ), 'Image connector surface is Cloud-managed and read-only locally.' );
toolbox_assert( false !== strpos( $admin_page, 'magick-ai-toolbox__connector-status' ) && false !== strpos( $admin_page, 'Connector status catalog' ), 'Connector panels expose a read-only status catalog.' );
toolbox_assert( false === strpos( $admin_page, 'Local MVP config' ) && false === strpos( $admin_page, 'Future connector owner' ), 'Connector status catalog no longer presents local or reserved vector provider ownership.' );
toolbox_assert( false === strpos( $admin_page, 'Pinecone' ) && false === strpos( $admin_page, 'Weaviate' ), 'Provider lists do not expose reserved vector slots locally.' );
toolbox_assert( false === strpos( $admin_page, 'search-bocha' ) && false === strpos( $admin_page, 'search-jina-reader' ) && false === strpos( $admin_page, 'tavily_api_key' ), 'Search provider keys and panels are removed from the connector UI.' );
toolbox_assert( false === strpos( $admin_page, 'unsplash_access_key' ) && false === strpos( $admin_page, 'pixabay_api_key' ) && false === strpos( $admin_page, 'pexels_api_key' ), 'Public image-source provider keys are not configurable in local Toolbox.' );
toolbox_assert( false === strpos( $admin_page, 'bocha_api_key' ) && false === strpos( $admin_page, 'enable_jina_reader' ), 'Bocha and Jina Reader search settings are not configurable locally.' );
toolbox_assert( false !== strpos( $admin_page, 'admin.php?page=magick-ai-cloud-addon' ) && false === strpos( $admin_page, 'toolbox_tab=site-knowledge' ) && false === strpos( $admin_page, 'https://qdrant.tech/' ), 'Connector provider rows expose Cloud Addon entries without Site Knowledge or local vector vendor links.' );
toolbox_assert( false !== strpos( $admin_page, 'Cloud owns provider keys' ) && false === strpos( $admin_page, 'Toolbox does not store vector provider keys' ), 'Connector provider rows expose short Cloud ownership descriptions without vector detail.' );
toolbox_assert( false !== strpos( $admin_page, 'Web Search' ) && false !== strpos( $admin_page, 'Cloud managed' ), 'Web Search status is Cloud-managed only.' );
toolbox_assert( false === strpos( $admin_page, 'Cloud owns the vector database' ) && false !== strpos( $admin_page, 'Returned candidates still use image_candidate.v1' ), 'Connector catalog copy preserves image-source boundaries without vector detail.' );
toolbox_assert( false === strpos( $admin_page, 'Jina test setup' ) && false === strpos( $admin_page, 'jina-embeddings-v3' ), 'Vector connector does not include local embedding setup guidance.' );
toolbox_assert( false !== strpos( $admin_page, 'Advanced / Debug' ) && false === strpos( $admin_page, 'Clear stored Jina AI key' ) && false === strpos( $admin_page, 'Clear stored Tavily key' ), 'Connector debug toggles exclude removed provider key clearing.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-tools' ) && false !== strpos( $admin_page, 'data-toolbox-tool-panel' ), 'Tool actions use a single active tool workspace instead of a card matrix.' );
toolbox_assert( false !== strpos( $admin_page, 'Article Assistant Fallback' ) && false !== strpos( $admin_page, 'render_article_assistant_tool' ), 'Tool actions include a dedicated Article Assistant fallback workbench panel.' );
toolbox_assert( false !== strpos( $admin_page, 'reviewed_draft_markdown' ) && false !== strpos( $admin_page, 'Build assistant artifact' ), 'Article Assistant panel collects optional reviewed draft input for Core-ready handoff.' );
toolbox_assert( false !== strpos( $admin_page, 'Reviewed Draft Handoff' ) && false !== strpos( $admin_page, 'render_article_plan_tool' ), 'Tool actions include a dedicated Reviewed Draft Handoff fallback panel.' );
toolbox_assert( false !== strpos( $admin_page, 'content_markdown' ) && false !== strpos( $admin_page, 'Final execution remains magick-ai/create-draft after Core approval.' ), 'Article Write Plan panel collects reviewed draft content and preserves Core handoff copy.' );
toolbox_assert( false !== strpos( $admin_page, 'Adopt New Image' ) && false !== strpos( $admin_page, 'render_image_candidate_adoption_tool' ), 'Tool actions include an Adopt New Image panel.' );
toolbox_assert( false !== strpos( $admin_page, 'Selected image URL' ) && false !== strpos( $admin_page, 'Source type' ) && false !== strpos( $admin_page, 'Advanced candidate details' ) && false !== strpos( $admin_page, 'Build import proposal plan' ), 'Adopt New Image panel hides image_candidate internals behind a simpler button flow.' );
toolbox_assert( false !== strpos( $admin_page, 'Candidate JSON' ) && false !== strpos( $admin_page, 'Toolbox does not import media directly' ), 'Adopt New Image panel keeps advanced candidate JSON optional and preserves no-write copy.' );
toolbox_assert( false !== strpos( $admin_page, 'Cloud smoke test' ) && false !== strpos( $admin_page, 'Test Cloud image source' ) && false !== strpos( $admin_page, 'Candidate count' ), 'Image Source Candidates panel exposes a Cloud provider smoke-test flow.' );
toolbox_assert( false !== strpos( $admin_page, 'Optimize Existing Image' ) && false !== strpos( $admin_page, 'render_media_derivative_tool' ), 'Tool actions include a dedicated Optimize Existing Image panel.' );
toolbox_assert( false !== strpos( $admin_page, 'Core defaults' ) && false !== strpos( $admin_page, 'magick_ai_core_get_media_derivative_settings' ), 'Media Derivative Handoff reads Core media policy defaults when available.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-select-media' ) && false !== strpos( $admin_page, 'Generate preview' ) && false !== strpos( $admin_page, 'Submit replacement review' ) && false !== strpos( $admin_page, 'Repair and handoff actions' ) && false !== strpos( $admin_page, 'data-toolbox-submit-reference-repair' ) && false !== strpos( $admin_page, 'data-toolbox-submit-settings-repair' ), 'Optimize Existing Image supports media selection, Cloud preview generation, Core replacement review, and advanced repair actions.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-media-url' ) && false !== strpos( $admin_page, 'data-toolbox-resolve-media-url' ) && false !== strpos( $admin_page, 'data-toolbox-media-url-resolution' ), 'Media Derivative Preview supports resolving a local uploads URL to an attachment before preview generation.' );
toolbox_assert( false !== strpos( $admin_page, 'Batch conversion plan' ) && false !== strpos( $admin_page, 'data-toolbox-build-media-batch-plan' ) && false !== strpos( $admin_page, 'data-toolbox-run-media-batch-previews' ) && false !== strpos( $admin_page, 'data-toolbox-submit-media-batch-proposals' ), 'Media Derivative Preview supports bounded batch planning, selected previews, and selected proposal submission.' );
toolbox_assert( false !== strpos( $admin_page, 'settings_excluded_formats' ) && false !== strpos( $admin_page, 'settings_min_dimensions' ), 'Media Derivative Preview exposes bounded settings reference repair exclusions.' );
toolbox_assert( false !== strpos( $admin_page, 'Watermark override' ) && false !== strpos( $admin_page, 'name="watermark_mode"' ) && false !== strpos( $admin_page, 'name="watermark_opacity"' ), 'Media Derivative Preview exposes one-run watermark controls without owning stored policy.' );
toolbox_assert( false !== strpos( $admin_page, 'Core remains the policy owner and final WordPress write owner' ), 'Media Derivative Preview copy keeps Core as policy and final write owner.' );
toolbox_assert( false !== strpos( $admin_page, 'magick-ai-toolbox__disclosure' ) && false !== strpos( $admin_page, 'Ability preview' ), 'Lower-frequency details stay out of the default working view.' );
toolbox_assert( false !== strpos( $admin_page, '<div class="magick-ai-toolbox__result is-empty"' ), 'Tool result panels use structured result containers instead of raw preformatted output.' );
toolbox_assert( false !== strpos( $admin_page, 'contextDrafts' ) && false !== strpos( $admin_page, 'get_ai_blog_context_template' ), 'Content context exposes an editable AI technology blog draft template.' );
toolbox_assert( false !== strpos( $admin_page, 'get_site_content_context_suggestion' ) && false !== strpos( $admin_page, 'get_posts(' ) && false !== strpos( $admin_page, 'get_terms(' ), 'Content context can draft suggestions from current public site content signals.' );
toolbox_assert( false !== strpos( $admin_page, "data-toolbox-context-draft=\"aiBlog\"" ) && false !== strpos( $admin_page, "data-toolbox-context-draft=\"site\"" ), 'Content context includes template and current-site draft buttons.' );
toolbox_assert( false !== strpos( $admin_page, 'SEO fields AI may suggest' ) && false !== strpos( $admin_page, 'AEO fields AI may suggest' ) && false !== strpos( $admin_page, 'GEO fields AI may suggest' ), 'Content context groups proposal fields by SEO, AEO, and GEO.' );
toolbox_assert( false !== strpos( $admin_page, 'Drafts are editable suggestions and do not change posts, media, SEO meta, or provider settings.' ), 'Content context draft copy preserves suggestion-only boundaries.' );
toolbox_assert( false !== strpos( $admin_page, 'JSON_UNESCAPED_UNICODE' ), 'Content context ability preview keeps non-Latin text readable.' );
toolbox_assert( false !== strpos( $admin_page, 'wp_enqueue_media' ) && false !== strpos( $admin_page, "'adapterRestUrl'" ) && false !== strpos( $admin_page, "rest_url( 'magick-ai-adapter/v1' )" ), 'Media Derivative Preview loads the WordPress media picker and Adapter REST URL.' );

$admin_js = file_get_contents( $root . '/assets/admin.js' );
toolbox_assert( false !== strpos( $admin_js, 'initTopTabs' ) && false !== strpos( $admin_js, 'initToolSwitcher' ), 'Admin JavaScript initializes section tabs and tool switching.' );
toolbox_assert( false !== strpos( $admin_js, 'initConnectorSwitcher' ), 'Admin JavaScript initializes connector section switching.' );
toolbox_assert( false !== strpos( $admin_js, 'initConnectorProviderSwitcher' ) && false !== strpos( $admin_js, 'data-toolbox-connector-provider-target' ), 'Admin JavaScript initializes connector provider switching.' );
toolbox_assert( false !== strpos( $admin_js, 'initContextSectionSwitcher' ) && false !== strpos( $admin_js, 'initContextGroupSwitcher' ), 'Admin JavaScript initializes content context section and field switching.' );
toolbox_assert( false !== strpos( $admin_js, "container.matches('[data-toolbox-tabs]')" ) && false !== strpos( $admin_js, 'const panelRoot' ), 'Nested switchers keep panel activation scoped to their own workspace.' );
toolbox_assert( false !== strpos( $admin_js, 'initUrlState' ) && false !== strpos( $admin_js, 'history.replaceState' ), 'Admin JavaScript restores tab state from the URL and updates URLs without reloading.' );
toolbox_assert( false !== strpos( $admin_js, 'toolbox_tab' ) && false !== strpos( $admin_js, 'toolbox_tool' ) && false !== strpos( $admin_js, 'toolbox_connector' ), 'Admin tab URL state uses Toolbox-specific query parameters.' );
toolbox_assert( false !== strpos( $admin_js, "result.hidden = false" ), 'Tool results stay hidden until a tool returns output.' );
toolbox_assert( false !== strpos( $admin_js, 'renderStructuredResult' ) && false !== strpos( $admin_js, 'renderShell' ), 'Admin JavaScript renders tool results through a summary-first structured renderer.' );
toolbox_assert( false !== strpos( $admin_js, 'renderWebSearchResults' ) && false !== strpos( $admin_js, "payload.artifact_type === 'web_search_results'" ), 'Admin JavaScript renders Cloud web search test results through a dedicated view.' );
toolbox_assert( false !== strpos( $admin_js, 'renderWebSearchDiagnostics' ) && false !== strpos( $admin_js, "payload.artifact_type === 'web_search_diagnostics'" ), 'Admin JavaScript renders Cloud web search workflow diagnostics through a dedicated view.' );
toolbox_assert( false !== strpos( $admin_js, 'createRawDetails' ) && false !== strpos( $admin_js, 'Complete payload' ), 'Complete payload output is moved behind a result disclosure.' );
toolbox_assert( false !== strpos( $admin_js, 'Provider raw response' ), 'Provider raw responses are rendered only as disclosure details.' );
toolbox_assert( false !== strpos( $admin_js, 'Download tracking' ) && false !== strpos( $admin_js, 'Attribution metadata' ), 'Image candidate rendering preserves Unsplash attribution and download tracking metadata.' );
toolbox_assert( false !== strpos( $admin_js, 'Governed handoff' ) && false !== strpos( $admin_js, 'Core proposal required' ), 'Workflow result rendering keeps governed handoff guidance visible.' );
toolbox_assert( false !== strpos( $admin_js, 'renderArticlePlan' ) && false !== strpos( $admin_js, "payload.artifact_type === 'article_write_plan'" ), 'Admin JavaScript renders article write plans through a dedicated view.' );
toolbox_assert( false !== strpos( $admin_js, 'renderArticleAssistant' ) && false !== strpos( $admin_js, "payload.artifact_type === 'article_assistant_workbench'" ), 'Admin JavaScript renders article assistant workbench artifacts through a dedicated view.' );
toolbox_assert( false !== strpos( $admin_js, 'Write plan' ) && false !== strpos( $admin_js, 'Local workbench' ), 'Article Assistant renderer shows local workbench and write-plan readiness.' );
toolbox_assert( false !== strpos( $admin_js, 'Goal brief' ) && false !== strpos( $admin_js, 'Risk report' ) && false !== strpos( $admin_js, 'Final ability' ), 'Article write plan renderer shows artifacts, risk, and final ability summary.' );
toolbox_assert( false !== strpos( $admin_js, 'renderMediaDerivativeHandoff' ) && false !== strpos( $admin_js, "payload.artifact_type === 'media_derivative_handoff'" ), 'Admin JavaScript renders media derivative handoffs through a dedicated view.' );
toolbox_assert( false !== strpos( $admin_js, 'renderImageCandidateAdoptionPlan' ) && false !== strpos( $admin_js, "payload.artifact_type === 'image_candidate_adoption_plan'" ), 'Admin JavaScript renders image candidate adoption plans through a dedicated view.' );
toolbox_assert( false !== strpos( $admin_js, 'Image import proposal plan' ) && false !== strpos( $admin_js, 'License or source review is required before approval.' ), 'Image candidate adoption renderer keeps next steps and review status visible.' );
toolbox_assert( false !== strpos( $admin_js, 'renderImageSourceCandidates' ) && false !== strpos( $admin_js, "payload.artifact_type === 'image_source_candidates'" ), 'Admin JavaScript renders Cloud image-source candidates through a dedicated view.' );
toolbox_assert( false !== strpos( $admin_js, 'Suggested filename' ) && false !== strpos( $admin_js, 'Cloud returned image candidates only' ), 'Image-source renderer shows filename evidence and no-write guidance.' );
toolbox_assert( false !== strpos( $admin_js, 'One-run planning artifact' ) && false !== strpos( $admin_js, 'Core policy' ), 'Media derivative renderer keeps one-run Core policy handoff visible.' );
toolbox_assert( false !== strpos( $admin_js, 'initMediaDerivativeControls' ) && false !== strpos( $admin_js, 'runMediaDerivative' ) && false !== strpos( $admin_js, 'submitMediaDerivativeProposal' ) && false !== strpos( $admin_js, 'submitMediaReferenceRepairProposal' ) && false !== strpos( $admin_js, 'submitMediaSettingsReferenceRepairProposal' ), 'Admin JavaScript runs the media derivative preview, replacement proposal, post reference repair, and settings reference repair proposal flows through Adapter routes.' );
toolbox_assert( false !== strpos( $admin_js, 'media-derivative-runs' ) && false !== strpos( $admin_js, 'media-derivative-proposal-payload' ) && false !== strpos( $admin_js, "ability_id: 'magick-ai/adopt-cloud-media-derivative'" ), 'Media derivative preview uses Adapter recipe routes and submits a governed local replacement proposal.' );
toolbox_assert( false !== strpos( $admin_js, 'resolveMediaAttachmentUrl' ) && false !== strpos( $admin_js, "ability_id: 'magick-ai/resolve-media-attachment-by-url'" ) && false !== strpos( $admin_js, 'data-toolbox-use-media-resolution-candidate' ), 'Media derivative URL resolution calls the local read-only resolver ability and lets the operator choose a candidate.' );
toolbox_assert( false !== strpos( $admin_js, 'buildMediaDerivativeBatchPlan' ) && false !== strpos( $admin_js, "ability_id: 'magick-ai/build-media-derivative-batch-plan'" ) && false !== strpos( $admin_js, 'runMediaDerivativeBatchPreviews' ) && false !== strpos( $admin_js, 'submitMediaDerivativeBatchProposals' ), 'Media derivative batch flow builds a read-only plan, previews selected candidates, and submits selected Core proposals.' );
toolbox_assert( false !== strpos( $admin_js, 'mediaDerivativeWatermarkInput' ) && false !== strpos( $admin_js, 'watermark_enabled: false' ) && false !== strpos( $admin_js, 'Object.assign({}, candidate.cloud_request_input || {}, watermarkInput)' ), 'Media derivative preview and batch flows preserve Core default watermarks and support explicit one-run watermark overrides.' );
toolbox_assert( false !== strpos( $admin_js, "ability_id: 'magick-ai/build-media-reference-repair-plan'" ) && false !== strpos( $admin_js, "'proposals/from-plan'" ) && false !== strpos( $admin_js, 'patch-post-content actions' ), 'Media reference repair flow builds a read-only plan and submits it to Core from-plan intake.' );
toolbox_assert( false !== strpos( $admin_js, "ability_id: 'magick-ai/build-media-settings-reference-repair-plan'" ) && false !== strpos( $admin_js, 'patch-setting-value actions' ) && false !== strpos( $admin_js, 'excluded_formats' ), 'Media settings reference repair flow builds a filtered read-only plan and submits it to Core from-plan intake.' );
toolbox_assert( false !== strpos( $admin_js, 'derivative_artifact' ) && false !== strpos( $admin_js, 'magick-ai-cloud-backup' ), 'Media derivative replacement proposal carries artifact evidence and backup intent.' );
toolbox_assert( false !== strpos( $admin_js, 'withRestNonce' ) && false !== strpos( $admin_js, 'derivative.preview_url' ) && false !== strpos( $admin_js, 'Same-origin signed preview proxy' ), 'Media derivative preview renders the local signed Adapter proxy URL with a REST nonce.' );
toolbox_assert( false !== strpos( $admin_js, 'renderOperatorFeedback' ) && false !== strpos( $admin_js, 'operator_feedback' ), 'Admin JavaScript renders operator feedback from governed handoffs.' );
toolbox_assert( false !== strpos( $admin_js, 'formatErrorMessage' ) && false !== strpos( $admin_js, 'renderErrorResult' ) && false !== strpos( $admin_js, 'Error payload' ), 'Admin JavaScript renders nested REST and Cloud errors without collapsing them to Array.' );
toolbox_assert( false !== strpos( $admin_js, 'can_retry_after_revision' ) && false !== strpos( $admin_js, 'core_evidence' ), 'Operator feedback renderer shows retry state and Core evidence.' );
toolbox_assert( false !== strpos( $admin_js, 'Revise fields' ) && false !== strpos( $admin_js, 'Next steps' ), 'Operator feedback renderer shows revision fields and next steps.' );
toolbox_assert( false === strpos( $admin_js, 'result.textContent = JSON.stringify(value, null, 2)' ), 'Tool results do not default to raw JSON in the main result surface.' );
toolbox_assert( false !== strpos( $admin_js, 'initContextDrafts' ) && false !== strpos( $admin_js, 'applyContextDraft' ), 'Admin JavaScript can prefill editable content context drafts.' );
toolbox_assert( false !== strpos( $admin_js, 'clearContextForm' ), 'Admin JavaScript can clear the content context form before a new draft.' );
toolbox_assert( false !== strpos( $admin_js, 'initSiteKnowledge' ) && false !== strpos( $admin_js, 'site-knowledge/sync' ) && false !== strpos( $admin_js, 'site-knowledge/status' ), 'Admin JavaScript runs Site Knowledge status and sync actions.' );
toolbox_assert( false !== strpos( $admin_js, 'setSiteKnowledgeSyncBusy' ) && false !== strpos( $admin_js, 'pollSiteKnowledgeStatus' ) && false !== strpos( $admin_js, 'Sync queued...' ), 'Admin JavaScript disables duplicate Site Knowledge sync submissions and polls status.' );
toolbox_assert( false !== strpos( $admin_js, 'updateSiteKnowledgeActionState' ) && false !== strpos( $admin_js, 'indexState' ), 'Admin JavaScript updates the Site Knowledge indexing action from start to refresh after coverage exists.' );
toolbox_assert( false !== strpos( $admin_js, "modeInput.value = hasIndex ? 'rebuild' : 'refresh'" ), 'Admin JavaScript maps the simple Refresh index action to a Cloud rebuild when an index already exists.' );
toolbox_assert( false !== strpos( $admin_js, 'progress.message' ) && false !== strpos( $admin_js, 'Active run' ) && false !== strpos( $admin_js, 'Indexing...' ), 'Admin JavaScript renders Site Knowledge progress and disables indexing while Cloud is active.' );
toolbox_assert( false !== strpos( $admin_js, 'payload.evidence_gate' ) && false !== strpos( $admin_js, 'payload.message' ), 'Admin JavaScript renders Site Knowledge evidence state and active-run guidance.' );

$editor_support = file_get_contents( $root . '/includes/Editor_Content_Support.php' );
toolbox_assert( false !== strpos( $editor_support, 'assets/editor-content-support.js' ) && false !== strpos( $editor_support, 'assets/editor-content-support.css' ), 'Post editor content support enqueues its editor assets.' );
toolbox_assert( false !== strpos( $editor_support, 'MagickAIToolboxEditorSupport' ) && false !== strpos( $editor_support, "wp_create_nonce( 'wp_rest' )" ), 'Post editor content support localizes REST configuration and nonce.' );

$editor_js = file_get_contents( $root . '/assets/editor-content-support.js' );
toolbox_assert( false !== strpos( $editor_js, 'PluginDocumentSettingPanel' ) && false !== strpos( $editor_js, 'Magick AI Content Support' ), 'Editor JavaScript registers a Magick AI Content Support document panel.' );
foreach ( array( 'publish_preflight', 'taxonomy_tags', 'internal_links', 'image_candidates' ) as $editor_intent ) {
	toolbox_assert( false !== strpos( $editor_js, $editor_intent ), "Editor Content Support exposes fixed flow intent {$editor_intent}." );
}
toolbox_assert( false !== strpos( $editor_js, 'editor/content-support' ) && false !== strpos( $editor_js, 'getEditedPostAttribute' ), 'Editor Content Support posts current draft context to the fixed flow route.' );
toolbox_assert( false !== strpos( $editor_js, 'Suggestions only. Final writes require Core approval.' ), 'Editor Content Support preserves suggestion-only Core-governed copy.' );

$editor_css = file_get_contents( $root . '/assets/editor-content-support.css' );
toolbox_assert( false !== strpos( $editor_css, 'magick-ai-toolbox-editor-support__flow' ) && false !== strpos( $editor_css, 'magick-ai-toolbox-editor-support__result' ), 'Editor Content Support CSS styles fixed flow rows and result summaries.' );

$admin_css = file_get_contents( $root . '/assets/admin.css' );
toolbox_assert( false !== strpos( $admin_css, 'magick-ai-toolbox__result-summary' ), 'Admin CSS styles summary-first result panels.' );
toolbox_assert( false !== strpos( $admin_css, 'magick-ai-toolbox__result-details' ), 'Admin CSS styles collapsed result detail disclosures.' );
toolbox_assert( false !== strpos( $admin_css, 'magick-ai-toolbox__image-preview' ), 'Admin CSS styles adoption result image previews.' );
toolbox_assert( false !== strpos( $admin_css, 'magick-ai-toolbox__image-thumb' ), 'Admin CSS supports browser image-source previews.' );
toolbox_assert( false !== strpos( $admin_css, 'magick-ai-toolbox__connector-tabs' ) && false !== strpos( $admin_css, 'magick-ai-toolbox__connector-tab.is-active' ), 'Admin CSS styles connector sub tabs.' );
toolbox_assert( false !== strpos( $admin_css, 'magick-ai-toolbox__connector-provider-workspace' ) && false !== strpos( $admin_css, 'magick-ai-toolbox__connector-provider-button.is-active' ), 'Admin CSS styles connector provider rail and selected vendor state.' );
toolbox_assert( false !== strpos( $admin_css, 'magick-ai-toolbox__context-tabs' ) && false !== strpos( $admin_css, 'magick-ai-toolbox__context-group-workspace' ), 'Admin CSS styles content context tabs and field rail.' );
toolbox_assert( false !== strpos( $admin_css, 'magick-ai-toolbox__media-picker' ) && false !== strpos( $admin_css, 'magick-ai-toolbox__inline-actions' ) && false !== strpos( $admin_css, 'magick-ai-toolbox__derivative-preview' ), 'Admin CSS supports media derivative picker, action controls, and derivative preview image.' );
toolbox_assert( false !== strpos( $admin_css, 'magick-ai-toolbox__url-resolution' ), 'Admin CSS supports media URL resolution evidence.' );
toolbox_assert( false !== strpos( $admin_css, 'magick-ai-toolbox__batch-panel' ) && false !== strpos( $admin_css, 'magick-ai-toolbox__batch-row' ), 'Admin CSS supports media derivative batch planning rows.' );

$plugin = file_get_contents( $root . '/includes/Plugin.php' );
toolbox_assert( false !== strpos( $plugin, 'register_with_magick_ai_abilities' ) && false !== strpos( $plugin, "'wp_abilities_api_categories_init'" ) && false !== strpos( $plugin, ', 1 );' ), 'Helper ability registration is deferred to the Abilities API category hook.' );
toolbox_assert( false === strpos( $plugin, '$this->abilities->register_with_magick_ai_abilities();' ), 'Helper ability registration is not executed during plugin hook setup.' );
toolbox_assert( false !== strpos( $plugin, 'Editor_Content_Support' ) && false !== strpos( $plugin, 'enqueue_block_editor_assets' ), 'Plugin registers the post editor content support surface.' );

$rest = file_get_contents( $root . '/includes/Rest_Controller.php' );
$allowed_rest_routes = array(
	'/status',
	'/image-candidates',
	'/vector-search',
	'/knowledge-search',
	'/web-search/test',
	'/web-search/diagnostics',
	'/site-knowledge/search',
	'/site-knowledge/sync',
	'/site-knowledge/status',
	'/flows/article-brief',
	'/flows/article-assistant',
	'/flows/article-plan',
	'/flows/image-candidate-adoption-plan',
	'/flows/media-brief',
	'/editor/content-support',
	'/media-derivative-handoff',
);
preg_match_all( "/\\\$this->post\\(\\s*'([^']+)'/", $rest, $post_route_matches );
preg_match_all( "/register_rest_route\\(\\s*Plugin::REST_NAMESPACE\\s*,\\s*'([^']+)'/s", $rest, $direct_route_matches );
$registered_rest_routes = array_values(
	array_unique(
		array_merge(
			$post_route_matches[1] ?? array(),
			$direct_route_matches[1] ?? array()
		)
	)
);
sort( $allowed_rest_routes );
sort( $registered_rest_routes );
toolbox_assert( $allowed_rest_routes === $registered_rest_routes, 'REST route matrix exactly matches the first-version allowed routes.' );
toolbox_assert( false !== strpos( $rest, "'methods'             => 'GET'" ) && false !== strpos( $rest, "private function post( string \$route, string \$method ): void" ) && false !== strpos( $rest, "'methods'             => 'POST'" ), 'REST route matrix keeps status as GET and tool actions as POST.' );
toolbox_assert( false !== strpos( $rest, 'editor_content_support' ) && false !== strpos( $rest, "'artifact_type'          => 'editor_content_support_flow'" ) && false !== strpos( $rest, 'editor_support_section' ), 'REST controller exposes a safe suggestion-only editor content support flow.' );
foreach ( array( 'taxonomy_tags', 'internal_links', 'image_candidates', 'publish_preflight' ) as $editor_rest_intent ) {
	toolbox_assert( false !== strpos( $rest, "'{$editor_rest_intent}'" ), "REST editor content support accepts fixed intent {$editor_rest_intent}." );
}
foreach ( array( 'publish', 'delivery', 'workflow-run', 'workflow_run', 'queue', 'scheduler', 'approval', 'approve', 'confirm', 'write', 'featured-image', 'media-upload', 'media-import', 'seo' ) as $forbidden_fragment ) {
	$has_forbidden_route = false;
	foreach ( $registered_rest_routes as $route ) {
		$has_forbidden_route = $has_forbidden_route || str_contains( $route, $forbidden_fragment );
	}
	toolbox_assert( ! $has_forbidden_route, "REST route matrix excludes forbidden route fragment {$forbidden_fragment}." );
}

$abilities = file_get_contents( $root . '/includes/Abilities.php' );
foreach ( array( 'magick-ai-toolbox/search-image-source', 'magick-ai-toolbox/vector-search', 'magick-ai-toolbox/search-site-knowledge', 'magick-ai-toolbox/get-site-knowledge-status', 'magick-ai-toolbox/request-site-knowledge-sync', 'magick-ai-toolbox/build-article-brief', 'magick-ai-toolbox/build-article-assistant', 'magick-ai-toolbox/build-article-write-plan', 'magick-ai-toolbox/build-image-candidate-adoption-plan', 'magick-ai-toolbox/build-media-brief', 'magick-ai-toolbox/build-media-derivative-handoff', 'magick-ai-toolbox/get-content-discoverability-context', 'magick-ai-toolbox/validate-content-discoverability-context', 'magick-ai-toolbox/build-content-discoverability-brief', 'magick-ai-toolbox/build-ai-article-writing-pack' ) as $ability_id ) {
	toolbox_assert( false !== strpos( $abilities, $ability_id ), "Ability {$ability_id} is registered." );
}
toolbox_assert( false === strpos( $abilities, 'magick-ai-toolbox/web-research' ), 'Toolbox no longer registers a local web-research ability.' );

$client = file_get_contents( $root . '/includes/Provider_Client.php' );
toolbox_assert( false === strpos( $client, 'https://api.tavily.com/search' ) && false === strpos( $client, 'search_bocha_web' ) && false === strpos( $client, 'enhance_results_with_jina_reader' ), 'Provider client no longer calls local web search providers.' );
toolbox_assert( false !== strpos( $client, 'test_cloud_web_search' ) && false !== strpos( $client, "'ability_name'        => 'magick-ai-cloud/web-search'" ), 'Provider client can test Cloud-managed web search through the Cloud runtime seam.' );
toolbox_assert( false !== strpos( $client, 'normalize_cloud_web_search_response' ) && false !== strpos( $client, "'web_search_results'" ), 'Provider client normalizes Cloud web search test output for operator review.' );
toolbox_assert( false !== strpos( $client, 'diagnose_automatic_web_search' ) && false !== strpos( $client, "'web_search_diagnostics'" ), 'Provider client can diagnose whether Toolbox workflows attach Cloud web search evidence.' );
toolbox_assert( false !== strpos( $client, 'cloud_web_search_for_content' ) && false !== strpos( $client, "'external_research'      => \$external_research" ), 'Article and discoverability flows can attach Cloud web search evidence without local provider keys.' );
toolbox_assert( false !== strpos( $client, 'execute_image_source_cloud_request' ) && false !== strpos( $client, 'magick_ai_toolbox_image_source_cloud_request' ), 'Image candidates use a Cloud-managed image-source runtime seam.' );
toolbox_assert( false === strpos( $client, 'https://api.unsplash.com/search/photos' ) && false === strpos( $client, 'https://pixabay.com/api/' ) && false === strpos( $client, 'https://api.pexels.com/v1/search' ), 'Image candidates do not directly call public image provider APIs locally.' );
toolbox_assert( false !== strpos( $client, 'magick_ai_toolbox_ai_image_generation_request' ) && false !== strpos( $client, "'ai_generated'" ), 'Image candidates support an explicit AI-generated candidate runtime seam.' );
toolbox_assert( false !== strpos( $client, "'source_type'                   => 'ai_generated'" ) && false !== strpos( $client, "'requires_human_license_review' => true" ), 'AI-generated image candidates preserve source type and license review status.' );
toolbox_assert( false !== strpos( $client, 'normalize_image_candidate_contract' ) && false !== strpos( $client, "'candidate_contract_version' => 'image_candidate.v1'" ), 'Image candidates normalize to image_candidate.v1.' );
toolbox_assert( false !== strpos( $client, "'source_type']                   = \$source_type" ) && false !== strpos( $client, "'download_url']" ) && false !== strpos( $client, "'thumbnail_url']" ) && false !== strpos( $client, "'provider_origin']" ), 'Image candidate v1 output includes source type, download URL, thumbnail URL, and provider origin.' );
toolbox_assert( false !== strpos( $client, "'suggested_filename']" ) && false !== strpos( $client, "'filename_basis']" ) && false !== strpos( $client, "'final_sanitize_unique_required' => true" ), 'Image candidate v1 output carries a bounded filename suggestion for WordPress-side finalization.' );
toolbox_assert( false !== strpos( $client, 'build_image_candidate_adoption_plan' ) && false !== strpos( $client, "'artifact_type'               => 'image_candidate_adoption_plan'" ), 'Provider client can build image candidate adoption plans.' );
toolbox_assert( false !== strpos( $client, "'target_ability_id' => 'magick-ai/upload-media-from-url'" ) && false !== strpos( $client, "'target_ability_id' => 'magick-ai/set-post-featured-image'" ), 'Image candidate adoption plan routes media import and featured image writes through Core-governed abilities.' );
toolbox_assert( false !== strpos( $client, "'proposed_filename'           => \$file_name" ) && false !== strpos( $client, "'filename_policy'             => \$filename_policy" ), 'Image candidate adoption plan previews the proposed filename without becoming the write owner.' );
toolbox_assert( false !== strpos( $client, 'sanitize_provider_error_data' ) && false !== strpos( $client, "'provider_status' => \$status" ), 'Image-source provider failures preserve safe diagnostic status data.' );
toolbox_assert( false === strpos( $client, '/points/query' ), 'Vector search no longer calls Qdrant query points locally.' );
toolbox_assert( false === strpos( $client, '/embeddings' ), 'Vector search no longer calls embedding endpoints locally.' );
toolbox_assert( false === strpos( $client, 'create_siliconflow_embedding' ) && false === strpos( $client, 'create_jina_embedding' ), 'Provider client no longer contains local embedding provider implementations.' );
toolbox_assert( false !== strpos( $client, 'Low-level vector provider configuration has moved to Magick AI Cloud' ), 'Vector search returns a Cloud-managed compatibility pointer.' );
toolbox_assert( false !== strpos( $client, "'target_ability_id' => 'magick-ai-toolbox/search-site-knowledge'" ), 'Vector compatibility output points callers to Cloud-managed Site Knowledge.' );
toolbox_assert( false !== strpos( $client, 'provider_origin' ) && false !== strpos( $client, "'cloud_runtime'              => 'magick_ai_cloud_addon'" ), 'Cloud image-source responses preserve candidate provenance.' );
toolbox_assert( false !== strpos( $client, "'provider_mode'" ) && false !== strpos( $client, "'active_sources'" ), 'Image-source output records provider mode and active sources.' );
toolbox_assert( false !== strpos( $client, 'with_optional_raw' ), 'Provider raw responses are optional.' );
toolbox_assert( false !== strpos( $client, 'with_output_contract' ), 'Provider-backed outputs use a shared AI composition contract.' );
toolbox_assert( false !== strpos( $client, "'artifact_type'          => \$artifact_type" ), 'Shared output contract includes artifact type.' );
toolbox_assert( false !== strpos( $client, "'composition_role'       => \$composition_role" ), 'Shared output contract includes composition role.' );
toolbox_assert( false !== strpos( $client, "'direct_wordpress_write' => false" ), 'Shared output contract forbids direct WordPress writes.' );
toolbox_assert( false !== strpos( $client, 'cloud_web_search_notice' ) && false !== strpos( $client, "'provider_mode'  => 'cloud_managed'" ), 'Article helpers record Cloud-managed web search status without local provider execution.' );
toolbox_assert( false !== strpos( $client, "'image_source_candidates'" ), 'Image source output is classified as image-source candidates.' );
toolbox_assert( false !== strpos( $client, "'site_knowledge_context'" ), 'Vector compatibility output is classified as site knowledge context.' );
toolbox_assert( false !== strpos( $client, 'search_site_knowledge' ) && false !== strpos( $client, "'site_knowledge_search.v1'" ), 'Provider client exposes Cloud-managed site knowledge search.' );
toolbox_assert( false !== strpos( $client, "'faq_candidates'" ) && false !== strpos( $client, "'content_gap_analysis'" ) && false !== strpos( $client, "'duplicate_check'" ), 'Provider client allows high-value site knowledge search intents.' );
toolbox_assert( false !== strpos( $client, 'get_site_knowledge_status' ) && false !== strpos( $client, "'site_knowledge_status.v1'" ), 'Provider client exposes Cloud-managed site knowledge status.' );
toolbox_assert( false !== strpos( $client, 'request_site_knowledge_sync' ) && false !== strpos( $client, "'site_knowledge_sync.v1'" ), 'Provider client exposes Cloud-managed site knowledge sync requests.' );
toolbox_assert( false !== strpos( $client, 'magick_ai_toolbox_site_knowledge_cloud_request' ) && false !== strpos( $client, 'magick_ai_cloud_addon_runtime_client' ), 'Site knowledge execution uses a host filter or Cloud Addon runtime seam.' );
toolbox_assert( false !== strpos( $client, "'ability_name'        => \$ability_name" ) && false !== strpos( $client, "'execution_pattern'   => \$execution_pattern" ), 'Site knowledge runtime payload preserves ability name and execution pattern.' );
toolbox_assert( false !== strpos( $client, 'collect_site_knowledge_documents' ) && false !== strpos( $client, "'post_status'    => 'publish'" ) && false !== strpos( $client, "'post_type'      => array( 'post', 'page' )" ), 'Site knowledge sync uses bounded public WordPress post and page manifests.' );
toolbox_assert( false !== strpos( $client, 'collect_site_knowledge_comments' ) && false !== strpos( $client, "'status'   => 'approve'" ) && false !== strpos( $client, "'type'     => 'comment'" ) && false !== strpos( $client, "'comment_status'  => 'approve'" ), 'Site knowledge sync includes bounded approved comment manifests.' );
toolbox_assert( false !== strpos( $client, 'extract_cloud_runtime_result' ) && false !== strpos( $client, "'result_json'" ), 'Provider client unwraps nested Cloud runtime result payloads.' );
toolbox_assert( false !== strpos( $client, 'is_cloud_concurrency_error' ) && false !== strpos( $client, 'site_knowledge_active_run_response' ), 'Provider client turns active Cloud run concurrency into Site Knowledge status guidance.' );
toolbox_assert( false !== strpos( $client, 'filter_current_public_site_knowledge_results' ) && false !== strpos( $client, "'publish' === get_post_status" ), 'Provider client filters stale Site Knowledge search results against current public WordPress status.' );
toolbox_assert( false !== strpos( $client, "'progress'" ) && false !== strpos( $client, "'active_run'" ), 'Provider client preserves Cloud Site Knowledge progress and active run status.' );
toolbox_assert( false === strpos( $client, 'provider_body' ), 'Provider error responses do not expose raw provider bodies.' );
toolbox_assert( false !== strpos( $client, "'write_posture' => 'suggestion_only'" ), 'Article brief handoff stays suggestion-only.' );
toolbox_assert( false !== strpos( $client, 'Create WordPress draft or media proposals through Abilities/Core.' ), 'Article brief handoff points write-like actions to Abilities/Core.' );
toolbox_assert( false !== strpos( $client, 'build_article_assistant' ), 'Provider client can build local article assistant workbench artifacts.' );
toolbox_assert( false !== strpos( $client, "'artifact_type'          => 'article_assistant_workbench'" ), 'Article Assistant declares the workbench artifact type.' );
toolbox_assert( false !== strpos( $client, "'workflow_runtime'       => false" ) && false !== strpos( $client, "'batch_execution'        => false" ), 'Article Assistant explicitly avoids workflow runtime and batch execution ownership.' );
toolbox_assert( false !== strpos( $client, "'assistant_ability_id'   => 'magick-ai-toolbox/build-article-assistant'" ), 'Article Assistant handoff carries its ability id.' );
toolbox_assert( false !== strpos( $client, 'build_article_write_plan' ), 'Provider client can build Core-ready article write plans.' );
toolbox_assert( false !== strpos( $client, "'artifact_type'          => 'article_write_plan'" ), 'Article write plan declares the Core contract artifact type.' );
toolbox_assert( false !== strpos( $client, "'composition_role'       => 'core_article_write_plan'" ), 'Article write plan declares its composition role.' );
toolbox_assert( false !== strpos( $client, "'source_recipe_id'       => 'article_draft_v1'" ) && false !== strpos( $client, "'source_recipe_ref'      => 'workflow/wordpress_article_draft'" ), 'Article write plan is explicitly tied to the local article_draft_v1 Ability recipe.' );
toolbox_assert( false !== strpos( $client, "'target_ability_id' => 'magick-ai/create-draft'" ), 'Article write plan targets the governed create-draft ability.' );
toolbox_assert( false !== strpos( $client, "'recipe_step'       => 'host_governed_create_draft'" ), 'Article write plan marks create-draft as the host-governed recipe step.' );
toolbox_assert( false !== strpos( $client, "'status'  => 'draft'" ), 'Article write plan is draft-only.' );
toolbox_assert( false !== strpos( $client, "'recipe_id'              => 'article_draft_v1'" ), 'Article write plan handoff carries the local recipe id.' );
toolbox_assert( false !== strpos( $client, "'core_route'             => '/wp-json/magick-ai-core/v1/proposals/from-plan'" ), 'Article write plan points to Core plan intake.' );
toolbox_assert( false !== strpos( $client, 'build_article_batch_write_plan' ), 'Provider client can build Core-ready article batch write plans.' );
toolbox_assert( false !== strpos( $client, "'artifact_type'             => 'article_batch_write_plan'" ), 'Article batch write plan declares the Core contract artifact type.' );
toolbox_assert( false !== strpos( $client, "'composition_role'          => 'core_article_batch_write_plan'" ), 'Article batch write plan declares its composition role.' );
toolbox_assert( false !== strpos( $client, "'proposal_mode'             => 'batch'" ) && false !== strpos( $client, "'batch_approval'            => true" ), 'Article batch write plan uses one batch approval.' );
toolbox_assert( false !== strpos( $client, "'articles'                  => \$article_artifacts" ) && false !== strpos( $client, "'article_draft_candidate' =>" ), 'Article batch write plan includes reviewed article artifacts.' );
toolbox_assert( false !== strpos( $client, "'publish_allowed'           => false" ) && false !== strpos( $client, "'partial_success'           => false" ), 'Article batch write plan is draft-only and fail-closed.' );
toolbox_assert( false !== strpos( $client, 'build_article_media_batch_write_plan' ), 'Provider client can build Core-ready article plus media batch write plans.' );
toolbox_assert( false !== strpos( $client, "'artifact_type'             => 'article_media_batch_write_plan'" ), 'Article plus media batch write plan declares the Core contract artifact type.' );
toolbox_assert( false !== strpos( $client, "'composition_role'          => 'core_article_media_batch_write_plan'" ), 'Article plus media batch write plan declares its composition role.' );
toolbox_assert( false !== strpos( $client, "'target_ability_id' => 'magick-ai/upload-media-from-url'" ) && false !== strpos( $client, "'target_ability_id' => 'magick-ai/set-post-featured-image'" ), 'Article plus media batch write plan routes media upload and featured image writes through Core-governed abilities.' );
toolbox_assert( false !== strpos( $client, "'file_name'         => \$file_name" ), 'Article plus media batch write plan preserves approved media file names.' );
toolbox_assert( false !== strpos( $client, "'attach_to_post_id' => '\$outputs.' . \$create_id . '.post_id'" ) && false !== strpos( $client, "'attachment_id'  => '\$outputs.' . \$upload_id . '.attachment_id'" ), 'Article plus media batch write plan uses output references for dependent media writes.' );
toolbox_assert( false !== strpos( $client, 'build_image_candidate_adoption_plan' ), 'Provider client can build image candidate adoption plans.' );
toolbox_assert( false !== strpos( $client, "\$input['download_url']" ) && false !== strpos( $client, 'A selected image URL or image_candidate object is required' ), 'Image candidate adoption plan accepts simplified selected image URL input.' );
toolbox_assert( false !== strpos( $client, 'build_media_derivative_handoff' ), 'Provider client can build media derivative handoffs.' );
toolbox_assert( false !== strpos( $client, "'artifact_type'          => 'media_derivative_handoff'" ), 'Media derivative handoff declares its artifact type.' );
toolbox_assert( false !== strpos( $client, "'composition_role'       => 'media_derivative_operator_handoff'" ), 'Media derivative handoff declares its composition role.' );
toolbox_assert( false !== strpos( $client, "'ability_id'             => 'magick-ai/build-media-derivative-cloud-request'" ), 'Media derivative handoff points to the local Abilities request builder.' );
toolbox_assert( false !== strpos( $client, 'magick_ai_core_build_media_derivative_ability_input' ), 'Media derivative handoff reads Core media policy ability input when available.' );
toolbox_assert( false !== strpos( $client, 'fallback_media_derivative_policy' ), 'Media derivative handoff has a fallback when Core is unavailable.' );
toolbox_assert( false !== strpos( $client, 'media_derivative_watermark_overrides' ) && false !== strpos( $client, "'watermark_enabled' => false" ) && false !== strpos( $client, "'scale_percent' =>" ), 'Media derivative handoff supports explicit one-run watermark overrides while leaving Core as policy owner.' );
toolbox_assert( false !== strpos( $client, 'build_content_discoverability_brief' ), 'Provider client can build content discoverability briefs.' );
toolbox_assert( false !== strpos( $client, "'artifact_type'          => 'content_discoverability_brief'" ), 'Content discoverability brief declares its artifact type.' );
toolbox_assert( false !== strpos( $client, "'composition_role'       => 'seo_aeo_geo_brief'" ), 'Content discoverability brief declares its composition role.' );
toolbox_assert( false !== strpos( $client, "'primary_contract'       => true" ), 'Content discoverability brief is the primary SEO/AEO/GEO contract.' );
toolbox_assert( false !== strpos( $client, "'final_write_path'       => 'core_proposal_required'" ), 'Content discoverability brief points final writes to Core proposals.' );
toolbox_assert( false !== strpos( $client, "'seo'                    =>" ) && false !== strpos( $client, "'aeo'                    =>" ) && false !== strpos( $client, "'geo'                    =>" ) && false !== strpos( $client, 'content_discoverability_field_group' ), 'Content discoverability brief exposes SEO/AEO/GEO section blocks.' );
toolbox_assert( false !== strpos( $client, "'exceptions'             =>" ) && false !== strpos( $client, "'special_cases'          =>" ), 'Content discoverability brief exposes exception and special-case rules.' );
toolbox_assert( false !== strpos( $client, 'proposal_template' ) && false !== strpos( $client, 'candidate_suggestions' ), 'Content discoverability brief returns proposal templates and conservative candidates.' );
toolbox_assert( false !== strpos( $client, "'brief_ability_id'       => 'magick-ai-toolbox/build-content-discoverability-brief'" ), 'Content discoverability brief returns ability handoff metadata.' );
toolbox_assert( false !== strpos( $client, 'build_ai_article_writing_pack' ), 'Provider client can build AI article writing packs.' );
toolbox_assert( false !== strpos( $client, "'artifact_type'          => 'ai_article_writing_pack'" ), 'AI article writing pack declares its artifact type.' );
toolbox_assert( false !== strpos( $client, "'composition_role'       => 'ai_article_writing_pack'" ), 'AI article writing pack declares its composition role.' );
toolbox_assert( false !== strpos( $client, "'primary_contract'       => false" ) && false !== strpos( $client, "'contract_role'          => 'openclaw_natural_language_fallback'" ), 'AI article writing pack is marked as a fallback contract.' );
toolbox_assert( false !== strpos( $client, 'article_prompt_pack' ) && false !== strpos( $client, 'suggested_article_structure' ), 'AI article writing pack returns prompt guidance and structure.' );
toolbox_assert( false !== strpos( $client, "'pack_ability_id'       => 'magick-ai-toolbox/build-ai-article-writing-pack'" ), 'AI article writing pack returns ability handoff metadata.' );

$settings = file_get_contents( $root . '/includes/Settings.php' );
toolbox_assert( false === strpos( $settings, 'BAAI/bge-m3' ), 'Local Settings no longer store a default embedding model.' );
toolbox_assert( false === strpos( $settings, 'jina-embeddings-v3' ), 'Local Settings no longer store a Jina embedding model.' );
toolbox_assert( false === strpos( $settings, "'embedding_dimensions'  => 1024" ), 'Local Settings no longer store embedding dimensions.' );
toolbox_assert( false === strpos( $settings, 'SILICONFLOW_API_KEY' ), 'SiliconFlow key cannot be provided by local Toolbox settings.' );
toolbox_assert( false === strpos( $settings, 'JINA_API_KEY' ), 'Jina key cannot be provided by local Toolbox settings.' );
toolbox_assert( false === strpos( $settings, 'QDRANT_API_KEY' ) && false === strpos( $settings, 'qdrant_api_key' ), 'Qdrant key cannot be provided by local Toolbox settings.' );
toolbox_assert( false === strpos( $settings, 'BOCHA_API_KEY' ) && false === strpos( $settings, 'TAVILY_API_KEY' ), 'Search provider keys cannot be provided locally.' );
toolbox_assert( false === strpos( $settings, 'configured_search_providers' ), 'Settings no longer enumerate local search providers.' );
toolbox_assert( false === strpos( $settings, 'PIXABAY_API_KEY' ) && false === strpos( $settings, 'PEXELS_API_KEY' ) && false === strpos( $settings, 'UNSPLASH_ACCESS_KEY' ), 'Public image-source keys are not accepted from local environment settings.' );
toolbox_assert( false !== strpos( $settings, 'configured_image_source_providers' ) && false !== strpos( $settings, 'cloud_image_sources' ), 'Settings expose Cloud-managed image-source availability.' );
toolbox_assert( false !== strpos( $settings, 'content_context_defaults' ), 'Content context has separate defaults.' );
toolbox_assert( false !== strpos( $settings, 'get_content_context_for_ability' ), 'Content context can be exported for Abilities callers.' );
toolbox_assert( false !== strpos( $settings, 'validate_content_context_for_ability' ), 'Content context can be validated for AI callers.' );
toolbox_assert( false !== strpos( $settings, "'composition_role'                => 'site_context'" ), 'Content context export declares the site-context composition role.' );
toolbox_assert( false !== strpos( $settings, "'exceptions'                      => array(" ) && false !== strpos( $settings, "'human_confirmation_required'" ), 'Content context exports exception and special-case rules.' );
toolbox_assert( false !== strpos( $settings, "'composition_role'       => 'context_preflight'" ), 'Content context validation declares the preflight composition role.' );
toolbox_assert( false !== strpos( $settings, 'missing_required' ) && false !== strpos( $settings, 'missing_recommended' ), 'Content context validation reports missing required and recommended fields.' );
toolbox_assert( false !== strpos( $settings, "'write_posture'                   => 'suggestion_only'" ), 'Content context is suggestion-only.' );
toolbox_assert( false !== strpos( $settings, "'final_write_path'                => 'core_proposal_required'" ), 'Content context points writes to Core proposals.' );
toolbox_assert( false !== strpos( $settings, "'direct_wordpress_write'          => false" ), 'Content context forbids direct WordPress writes.' );

toolbox_assert( false === strpos( $rest, 'siliconflow_configured' ), 'Status no longer reports SiliconFlow configuration.' );
toolbox_assert( false === strpos( $rest, 'jina_configured' ), 'Status no longer reports Jina configuration.' );
toolbox_assert( false !== strpos( $rest, "'web_search_owner'         => 'cloud_runtime'" ) && false === strpos( $rest, 'bocha_configured' ) && false === strpos( $rest, 'jina_reader_enabled' ), 'Status reports Cloud ownership for web search and omits local search provider state.' );
toolbox_assert( false !== strpos( $rest, 'image_source_providers' ) && false !== strpos( $rest, 'cloud_image_sources_configured' ), 'Status reports Cloud-managed image-source availability.' );
toolbox_assert( false !== strpos( $rest, 'include_ai_generated' ) && false !== strpos( $rest, 'generated_image_url' ), 'Image candidate REST route accepts explicit AI-generated candidate inputs.' );
toolbox_assert( false === strpos( $rest, 'embedding_dimensions' ), 'Status no longer reports embedding dimensions.' );
toolbox_assert( false !== strpos( $rest, "'vector_provider'          => 'cloud_site_knowledge'" ) && false !== strpos( $rest, "'vector_owner'             => 'cloud_runtime'" ), 'Status reports Cloud ownership for vector infrastructure.' );
toolbox_assert( false !== strpos( $rest, 'query or vector field' ), 'Vector REST route accepts query or vector input.' );
toolbox_assert( false !== strpos( $rest, 'site_knowledge_sync' ) && false !== strpos( $rest, 'site_knowledge_status' ) && false !== strpos( $rest, 'site_knowledge_search' ), 'REST routes expose Cloud-managed site knowledge operations.' );
toolbox_assert( false === strpos( $rest, 'enhance_with_reader' ) && false === strpos( $rest, 'web_research' ) && false === strpos( $rest, 'jina_reader' ), 'REST exposes Cloud web search testing without local web research or reader enhancement inputs.' );
toolbox_assert( false !== strpos( $rest, "'provider'    => sanitize_key" ), 'Image candidate REST route accepts provider selection.' );
toolbox_assert( false !== strpos( $rest, 'magick_ai_toolbox_rest_permission' ), 'REST permission can be mediated by a host scope filter.' );

toolbox_assert( false === strpos( $abilities, 'cap.toolbox.search' ), 'Removed local web ability no longer exposes a Toolbox search scope.' );
toolbox_assert( false !== strpos( $abilities, 'cap.toolbox.vector_search' ), 'Vector ability exposes a Toolbox vector scope.' );
toolbox_assert( false !== strpos( $abilities, 'cap.toolbox.knowledge.search' ) && false !== strpos( $abilities, 'cap.toolbox.knowledge.read' ) && false !== strpos( $abilities, 'cap.toolbox.knowledge.sync' ), 'Site knowledge abilities expose stable knowledge scopes.' );
toolbox_assert( false !== strpos( $abilities, 'cap.toolbox.workflow_suggest' ), 'Workflow abilities expose the stable Toolbox workflow scope.' );
toolbox_assert( false !== strpos( $abilities, 'cap.toolbox.context.read' ), 'Content context ability exposes a read scope.' );
toolbox_assert( false !== strpos( $abilities, 'public_context' ), 'Content context ability declares public context classification.' );
toolbox_assert( false !== strpos( $abilities, 'planning_artifact' ), 'Article write plan ability declares planning artifact classification.' );
toolbox_assert( false === strpos( $abilities, "'composition_role' => 'research_evidence'" ), 'Removed local web ability no longer declares a research composition role.' );
toolbox_assert( false !== strpos( $abilities, "'composition_role' => 'image_source_candidates'" ), 'Image-source ability declares its content composition role.' );
toolbox_assert( false !== strpos( $abilities, 'include_ai_generated' ) && false !== strpos( $abilities, 'generated_image_url' ), 'Image-source ability accepts explicit AI-generated candidate inputs.' );
toolbox_assert( false !== strpos( $abilities, "'composition_role'    => 'core_image_candidate_adoption_plan'" ) && false !== strpos( $abilities, "'candidate_contract'  => 'image_candidate.v1'" ), 'Image candidate adoption ability declares its Core handoff role and candidate contract.' );
toolbox_assert( false !== strpos( $abilities, "'knowledge_layer' => 'cloud_managed_site_knowledge'" ), 'Vector compatibility ability declares Cloud-managed site knowledge ownership.' );
toolbox_assert( false !== strpos( $abilities, "'composition_role'    => 'site_knowledge_context'" ), 'Site knowledge search ability declares its composition role.' );
toolbox_assert( false !== strpos( $abilities, "'composition_role'    => 'site_knowledge_status'" ), 'Site knowledge status ability declares its composition role.' );
toolbox_assert( false !== strpos( $abilities, "'composition_role'    => 'site_knowledge_sync_request'" ), 'Site knowledge sync ability declares its composition role.' );
toolbox_assert( false !== strpos( $abilities, "'composition_role'    => 'core_article_write_plan'" ), 'Article write plan ability declares its content composition role.' );
toolbox_assert( false !== strpos( $abilities, "'composition_role'    => 'article_assistant_workbench'" ), 'Article Assistant ability declares its content composition role.' );
toolbox_assert( false !== strpos( $abilities, "'local_recipe_id'     => 'article_draft_v1'" ) && false !== strpos( $abilities, "'ability_recipe_ref'  => 'workflow/wordpress_article_draft'" ), 'Article write plan ability declares the local Ability recipe reference.' );
toolbox_assert( false !== strpos( $abilities, "'composition_role'    => 'media_derivative_operator_handoff'" ), 'Media derivative handoff ability declares its content composition role.' );
toolbox_assert( false !== strpos( $abilities, "'composition_role'    => 'site_context'" ), 'Content context ability declares its content composition role.' );
toolbox_assert( false !== strpos( $abilities, "'composition_role'    => 'context_preflight'" ), 'Context validation ability declares its content composition role.' );
toolbox_assert( false !== strpos( $abilities, "'composition_role'    => 'seo_aeo_geo_brief'" ), 'Content discoverability brief ability declares its content composition role.' );
toolbox_assert( false !== strpos( $abilities, "'composition_role'    => 'ai_article_writing_pack'" ), 'AI article writing pack ability declares its content composition role.' );
toolbox_assert( false !== strpos( $abilities, 'core_proposal_handoff' ), 'Article write plan ability declares Core proposal handoff posture.' );
toolbox_assert( false !== strpos( $abilities, 'validate_content_discoverability_context' ), 'Content context validation ability has an execution callback.' );
toolbox_assert( false !== strpos( $abilities, 'build_content_discoverability_brief' ), 'Content discoverability brief ability has an execution callback.' );
toolbox_assert( false !== strpos( $abilities, 'build_ai_article_writing_pack' ), 'AI article writing pack ability has an execution callback.' );
toolbox_assert( false !== strpos( $abilities, 'build_article_batch_write_plan' ) && false !== strpos( $abilities, 'magick-ai-toolbox/build-article-batch-write-plan' ), 'Article batch write plan ability has an execution callback.' );
toolbox_assert( false !== strpos( $abilities, 'build_article_media_batch_write_plan' ) && false !== strpos( $abilities, 'magick-ai-toolbox/build-article-media-batch-write-plan' ), 'Article plus media batch write plan ability has an execution callback.' );
toolbox_assert( false !== strpos( $abilities, 'search_site_knowledge' ) && false !== strpos( $abilities, 'get_site_knowledge_status' ) && false !== strpos( $abilities, 'request_site_knowledge_sync' ), 'Site knowledge abilities have execution callbacks.' );
toolbox_assert( false !== strpos( $abilities, "'provider_execution'       => 'server_side_toolbox'" ), 'Provider-backed abilities declare server-side execution.' );
toolbox_assert( false !== strpos( $abilities, "'provider_secret_exposure' => 'none'" ), 'Abilities declare that provider secrets are not exposed.' );
toolbox_assert( false !== strpos( $abilities, "'final_write_path'         => 'core_proposal_required'" ), 'Abilities point write-like outcomes to Core proposals.' );
toolbox_assert( false !== strpos( $abilities, "'direct_wordpress_write'   => false" ), 'Abilities declare direct WordPress writes disabled.' );
toolbox_assert( false !== strpos( $abilities, 'get_content_discoverability_context' ), 'Content context ability has an execution callback.' );
toolbox_assert( false !== strpos( $abilities, "array( 'query' )" ), 'Vector ability accepts query input for AI callers.' );
toolbox_assert( false !== strpos( $abilities, 'magick_ai_toolbox_ability_permission' ), 'Ability permission can be mediated by a host scope filter.' );
toolbox_assert( false !== strpos( $abilities, '$this->registered_with_helpers || ! function_exists( \'wp_register_ability_category\' )' ), 'Native category registration skips when helper registration already succeeded.' );
toolbox_assert( false !== strpos( $abilities, 'wp_has_ability_category' ), 'Native category registration checks for an existing WordPress ability category.' );

$readme = file_get_contents( $root . '/README.md' );
toolbox_assert( false !== strpos( $readme, 'Cloud-managed image-source candidates' ), 'README documents Cloud-managed image-source providers.' );
toolbox_assert( false !== strpos( $readme, 'Cloud-managed web search' ) && false === strpos( $readme, 'magick-ai-toolbox/web-research' ), 'README documents Cloud-managed web search without local web-research ability.' );
toolbox_assert( false !== strpos( $readme, 'Cloud-managed site knowledge' ) && false !== strpos( $readme, 'magick-ai-toolbox/search-site-knowledge' ), 'README documents Cloud-managed site knowledge abilities.' );
toolbox_assert( false === strpos( $readme, 'Pinecone and Weaviate' ), 'README does not advertise reserved local vector providers.' );
toolbox_assert( false !== strpos( $readme, 'AI Content Composition Abilities' ), 'README links the AI content composition abilities contract.' );
toolbox_assert( false !== strpos( $readme, 'Connector Ability Exposure' ), 'README links the connector ability exposure contract.' );
toolbox_assert( false !== strpos( $readme, 'Content Discoverability Context' ), 'README links the content context contract.' );
toolbox_assert( false !== strpos( $readme, 'OpenClaw Content Discoverability Handoff' ), 'README links the OpenClaw content discoverability handoff.' );
toolbox_assert( false !== strpos( $readme, 'Content Assistant Surface Lessons' ), 'README links the Content Assistant surface lessons contract.' );
toolbox_assert( false !== strpos( $readme, 'Article Assistant Workbench' ), 'README links the Article Assistant workbench contract.' );

$boundary_doc = file_get_contents( $root . '/docs/boundary.md' );
toolbox_assert( false !== $boundary_doc && false !== strpos( $boundary_doc, 'REST Route Boundary' ) && false !== strpos( $boundary_doc, 'Connector Status Catalog' ), 'Boundary documentation records route and connector status catalog limits.' );
toolbox_assert( false !== strpos( $boundary_doc, 'publishing' ) && false !== strpos( $boundary_doc, 'content indexing' ), 'Boundary documentation blocks write/runtime/indexing routes.' );
toolbox_assert( false !== strpos( $boundary_doc, 'AI Tool Composition Boundary' ) && false !== strpos( $boundary_doc, 'local vector context for style' ), 'Boundary documentation records AI tool composition limits.' );
toolbox_assert( false !== strpos( $boundary_doc, 'not only' ) && false !== strpos( $boundary_doc, 'article drafting' ), 'Boundary documentation keeps web research general-purpose.' );
toolbox_assert( false !== strpos( $boundary_doc, 'Cloud-managed site knowledge may run through the Cloud Addon runtime seam' ) && false !== strpos( $boundary_doc, 'second ability registry' ), 'Boundary documentation keeps Cloud site knowledge as runtime/detail, not a second control plane.' );

$architecture_doc = file_get_contents( $root . '/docs/architecture.md' );
toolbox_assert( false !== $architecture_doc && false !== strpos( $architecture_doc, 'static matrix in' ) && false !== strpos( $architecture_doc, 'Future connector owner' ), 'Architecture documentation records the route matrix and connector owner split.' );
toolbox_assert( false !== strpos( $architecture_doc, 'Reviewed Draft Handoff' ) && false !== strpos( $architecture_doc, 'submit the plan to Core' ) && false !== strpos( $architecture_doc, 'approve execution' ), 'Architecture documentation records the reviewed draft handoff UI boundary.' );
toolbox_assert( false !== strpos( $architecture_doc, 'artifact_type' ) && false !== strpos( $architecture_doc, 'composition_role' ), 'Architecture documentation records the compact provider payload contract.' );
toolbox_assert( false !== strpos( $architecture_doc, 'Cloud-managed web search' ) && false === strpos( $architecture_doc, '`magick-ai-toolbox/web-research`' ), 'Architecture documentation records Cloud ownership for web search.' );
toolbox_assert( false === strpos( $architecture_doc, '| Bocha | External web search | `/web-research` |' ) && false === strpos( $architecture_doc, '| Jina Reader | Search result URL extraction | `/web-research` enhancement only |' ), 'Architecture documentation no longer records local Bocha or Jina Reader search roles.' );
toolbox_assert( false !== strpos( $architecture_doc, '`search-site-knowledge` is the high-level ability' ) && false !== strpos( $architecture_doc, 'magick_ai_toolbox_site_knowledge_runtime_payload' ), 'Architecture documentation records the Cloud site knowledge ability seam.' );

$first_version_doc = file_get_contents( $root . '/docs/first-version-reference.md' );
toolbox_assert( false !== $first_version_doc && false !== strpos( $first_version_doc, 'REST Route Matrix' ) && false !== strpos( $first_version_doc, 'Connector settings now include a compact status catalog' ), 'First-version reference captures route matrix and connector status catalog guidance.' );
toolbox_assert( false !== strpos( $first_version_doc, 'canonical composition sequence' ) && false !== strpos( $first_version_doc, 'The sequence is a recommendation for composing tool inputs' ) && false !== strpos( $first_version_doc, 'runtime contract' ), 'First-version reference captures the AI content composition sequence.' );

$content_composition_doc = file_get_contents( $root . '/docs/ai-content-composition-abilities.md' );
toolbox_assert( false !== $content_composition_doc && false !== strpos( $content_composition_doc, 'Content Support First' ), 'AI content composition documentation records the content-support-first sequence.' );
toolbox_assert( false !== strpos( $content_composition_doc, 'General Tool Usage' ) && false !== strpos( $content_composition_doc, 'article drafting is only one consumer' ), 'AI content composition documentation keeps provider abilities general-purpose.' );
toolbox_assert( false !== strpos( $content_composition_doc, 'magick-ai-toolbox/vector-search' ) && false !== strpos( $content_composition_doc, 'Cloud-managed site knowledge compatibility pointer' ), 'AI content composition documentation maps vector search to a Cloud-managed compatibility pointer.' );
toolbox_assert( false !== strpos( $content_composition_doc, 'magick-ai-toolbox/search-site-knowledge' ) && false !== strpos( $content_composition_doc, 'related content' ), 'AI content composition documentation maps site knowledge to general search and recommendations.' );
toolbox_assert( false !== strpos( $content_composition_doc, 'magick-ai-toolbox/search-image-source' ) && false !== strpos( $content_composition_doc, 'download_location' ), 'AI content composition documentation maps image source search to attribution-preserving image candidates.' );
toolbox_assert( false !== strpos( $content_composition_doc, 'source_type=ai_generated' ) && false !== strpos( $content_composition_doc, 'magick_ai_toolbox_ai_image_generation_request' ), 'AI content composition documentation separates generated-image candidates from public source search.' );
toolbox_assert( false !== strpos( $content_composition_doc, 'Cloud-managed web search' ) && false === strpos( $content_composition_doc, 'magick-ai-toolbox/web-research' ), 'AI content composition documentation maps web research to Cloud.' );
toolbox_assert( false === strpos( $content_composition_doc, 'Tavily, Bocha, and Jina Reader output' ), 'AI content composition documentation no longer presents local search provider output.' );
toolbox_assert( false !== strpos( $content_composition_doc, 'content indexing' ), 'AI content composition documentation blocks indexing ownership.' );
toolbox_assert( false !== strpos( $content_composition_doc, 'Final WordPress writes still require Core proposal approval' ), 'AI content composition documentation preserves Core write governance.' );

$connector_exposure_doc = file_get_contents( $root . '/docs/connector-ability-exposure.md' );
toolbox_assert( false !== $connector_exposure_doc && false !== strpos( $connector_exposure_doc, 'provider_secret_exposure: none' ), 'Connector exposure documentation records secret non-exposure.' );
toolbox_assert( false !== strpos( $connector_exposure_doc, 'server_side_toolbox' ), 'Connector exposure documentation records server-side provider execution.' );
toolbox_assert( false !== strpos( $connector_exposure_doc, 'composition_role:' ), 'Connector exposure documentation records machine-readable composition role metadata.' );
toolbox_assert( false !== strpos( $connector_exposure_doc, 'Cloud-managed web search' ) && false === strpos( $connector_exposure_doc, 'single `web-research` ability' ), 'Connector exposure documentation keeps web search Cloud-owned.' );
toolbox_assert( false === strpos( $connector_exposure_doc, 'Tavily and Bocha' ) && false === strpos( $connector_exposure_doc, 'bounded post-search enhancement' ), 'Connector exposure documentation removes local Bocha and Jina Reader boundaries.' );
toolbox_assert( false !== strpos( $connector_exposure_doc, '`search-site-knowledge` for semantic site search' ) && false !== strpos( $connector_exposure_doc, 'cap.toolbox.knowledge.search' ), 'Connector exposure documentation records site knowledge abilities and scopes.' );
toolbox_assert( false !== strpos( $connector_exposure_doc, 'Do not add `confirm_token`, `write_confirmed`' ), 'Connector exposure documentation blocks direct write confirmation contracts.' );
toolbox_assert( false !== strpos( $connector_exposure_doc, 'magick-ai-toolbox/build-content-discoverability-brief' ), 'Connector exposure documentation lists the content discoverability brief ability.' );

$content_context_doc = file_get_contents( $root . '/docs/content-discoverability-context.md' );
toolbox_assert( false !== $content_context_doc && false !== strpos( $content_context_doc, 'magick-ai-toolbox/get-content-discoverability-context' ), 'Content context documentation records the ability id.' );
toolbox_assert( false !== strpos( $content_context_doc, 'magick-ai-toolbox/validate-content-discoverability-context' ), 'Content context documentation records the validation ability id.' );
toolbox_assert( false !== strpos( $content_context_doc, 'magick-ai-toolbox/build-content-discoverability-brief' ), 'Content context documentation records the brief ability id.' );
toolbox_assert( false !== strpos( $content_context_doc, 'magick-ai-toolbox/build-ai-article-writing-pack' ), 'Content context documentation records the article writing pack ability id.' );
toolbox_assert( false !== strpos( $content_context_doc, 'primary lightweight SEO/AEO/GEO contract' ) && false !== strpos( $content_context_doc, 'special_cases' ), 'Content context documentation records the lightweight contract and special cases.' );
toolbox_assert( false !== strpos( $content_context_doc, 'does not call a model and does not write WordPress data' ), 'Content context documentation keeps brief generation bounded.' );
toolbox_assert( false !== strpos( $content_context_doc, 'wp eval-file tests/smoke-content-discoverability.php' ), 'Content context documentation records the local readiness smoke command.' );
toolbox_assert( false !== strpos( $content_context_doc, 'Missing `wp_*` Agent Gateway exposure is a host-side admission task' ), 'Content context documentation keeps Agent Gateway admission outside Toolbox.' );
toolbox_assert( false !== strpos( $content_context_doc, 'Do not add an update-context ability' ), 'Content context documentation blocks third-party updates in the first version.' );

$content_context_smoke = file_get_contents( $root . '/tests/smoke-content-discoverability.php' );
toolbox_assert( false !== $content_context_smoke && false !== strpos( $content_context_smoke, 'magick_ai_abilities_get_registered' ), 'Content context smoke checks the Magick AI Abilities registry.' );
toolbox_assert( false !== strpos( $content_context_smoke, 'magick_ai_open_platform_ability_catalog' ) && false !== strpos( $content_context_smoke, 'magick_ai_open_platform_get_ability_catalog' ), 'Content context smoke checks Magick catalog projection.' );
toolbox_assert( false !== strpos( $content_context_smoke, 'magick_ai_open_platform_get_projection_matrix' ) && false !== strpos( $content_context_smoke, 'Core-side allowed_channels/tool-name admission is required' ), 'Content context smoke reports Agent Gateway admission status without owning it.' );
toolbox_assert( false !== strpos( $content_context_smoke, "direct_wordpress_write'] ?? true" ) && false !== strpos( $content_context_smoke, "'suggestion_only'" ), 'Content context smoke verifies suggestion-only no-write outputs.' );
toolbox_assert( false !== strpos( $content_context_smoke, 'build-ai-article-writing-pack' ) && false !== strpos( $content_context_smoke, 'ai_article_writing_pack' ), 'Content context smoke verifies the AI article writing pack.' );
toolbox_assert( false === strpos( $content_context_smoke, 'update_post_meta' ) && false === strpos( $content_context_smoke, 'wp_update_post' ), 'Content context smoke does not write WordPress content.' );

$openclaw_handoff_doc = file_get_contents( $root . '/docs/openclaw-content-discoverability-handoff.md' );
toolbox_assert( false !== $openclaw_handoff_doc && false !== strpos( $openclaw_handoff_doc, 'OpenClaw Content Discoverability Handoff' ), 'OpenClaw handoff documentation exists.' );
toolbox_assert( false !== strpos( $openclaw_handoff_doc, 'magick-ai-toolbox/validate-content-discoverability-context' ) && false !== strpos( $openclaw_handoff_doc, 'magick-ai-toolbox/build-content-discoverability-brief' ), 'OpenClaw handoff documentation records the required ability sequence.' );
toolbox_assert( false !== strpos( $openclaw_handoff_doc, 'GET /content-discoverability-brief?post_id=POST_ID' ), 'OpenClaw handoff documentation records Adapter shortcut usage.' );
toolbox_assert( false !== strpos( $openclaw_handoff_doc, 'Do not write WordPress data' ) && false !== strpos( $openclaw_handoff_doc, 'Final writes must go through Core proposal' ), 'OpenClaw handoff documentation preserves no-write guidance.' );

$content_assistant_surface_doc = file_get_contents( $root . '/docs/content-assistant-surface-lessons.md' );
toolbox_assert( false !== $content_assistant_surface_doc && false !== strpos( $content_assistant_surface_doc, 'summary -> detail' ), 'Content Assistant surface lessons document records summary-first display discipline.' );
toolbox_assert( false !== strpos( $content_assistant_surface_doc, 'Do Not Absorb' ) && false !== strpos( $content_assistant_surface_doc, 'preview -> confirm apply' ), 'Content Assistant surface lessons document blocks write-flow absorption.' );
toolbox_assert( false !== strpos( $content_assistant_surface_doc, 'Toolbox surfaces. Core governs. WordPress writes through abilities.' ), 'Content Assistant surface lessons document records the Toolbox-specific boundary phrase.' );

toolbox_assert( false === strpos( $client, 'write_confirmed' ), 'Legacy write_confirmed contract is absent.' );
toolbox_assert( false === strpos( $client, 'confirm_token' ), 'Legacy confirm_token contract is absent.' );

$uninstall = file_get_contents( $root . '/uninstall.php' );
toolbox_assert( false !== strpos( $uninstall, 'magick_ai_toolbox_content_context' ), 'Uninstall removes content context option.' );

echo "Static contract checks passed.\n";
