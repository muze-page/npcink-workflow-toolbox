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

$article_assistant_doc = file_get_contents( $root . '/docs/article-assistant-workbench.md' );
foreach ( array( 'Surface Budget', 'Article Assistant Workbench', 'one article per run', 'Do not present it as an', 'article generator, autonomous writer', 'no Cloud article generation' ) as $required_article_assistant_doc ) {
	toolbox_assert( false !== strpos( $article_assistant_doc, $required_article_assistant_doc ), 'Article Assistant workbench doc preserves surface budget: ' . $required_article_assistant_doc );
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
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-context-sections' ) && false !== strpos( $admin_page, 'data-toolbox-context-panel' ), 'Content context uses a focused tabbed workspace.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-context-target="brief" aria-selected="true"' ) && false !== strpos( $admin_page, 'data-toolbox-context-target="boundaries"' ), 'Content context defaults to Brief and keeps Boundaries as a focused section.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-context-groups' ) && false !== strpos( $admin_page, 'data-toolbox-context-group-target="brief-profile"' ) && false !== strpos( $admin_page, 'data-toolbox-context-group-target="boundaries-preview"' ), 'Content context sections use a left field list and right detail panel.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-tab-target="tools" aria-selected="false"' ) && false !== strpos( $admin_page, 'Try Tools' ), 'Tool execution is a secondary Try Tools tab.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-tab-panel="connectors"' ), 'Connector settings are moved out of the default tools view.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-connectors' ) && false !== strpos( $admin_page, 'data-toolbox-connector-panel' ), 'Connector settings use a single active connector workspace.' );
toolbox_assert( false !== strpos( $admin_page, 'magick-ai-toolbox__connector-tabs' ) && false !== strpos( $admin_page, 'magick-ai-toolbox__connector-tab' ), 'Connector groups use horizontal sub tabs near the connector heading.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-connector-providers' ) && false !== strpos( $admin_page, 'data-toolbox-connector-provider-target' ) && false !== strpos( $admin_page, 'data-toolbox-connector-provider-panel' ), 'Connector groups show a left provider list and right provider detail panel.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-connector-target="search" aria-selected="true"' ), 'Search is the default connector section.' );
toolbox_assert( strpos( $admin_page, 'data-toolbox-connector-target="search"' ) < strpos( $admin_page, 'data-toolbox-connector-target="image"' ) && strpos( $admin_page, 'data-toolbox-connector-target="image"' ) < strpos( $admin_page, 'data-toolbox-connector-target="vector"' ), 'Connector sections are ordered Search, Image, Vector.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-connector-provider-target="image-unsplash"' ) && false !== strpos( $admin_page, 'data-toolbox-connector-provider-target="image-pixabay"' ) && false !== strpos( $admin_page, 'data-toolbox-connector-provider-target="vector-qdrant"' ), 'Provider lists expose per-category vendor choices.' );
toolbox_assert( false !== strpos( $admin_page, 'magick-ai-toolbox__connector-status' ) && false !== strpos( $admin_page, 'Connector status catalog' ), 'Connector panels expose a read-only status catalog.' );
toolbox_assert( false !== strpos( $admin_page, 'Local MVP config' ) && false !== strpos( $admin_page, 'Future connector owner' ), 'Connector status catalog separates local MVP config from future connector ownership.' );
toolbox_assert( false !== strpos( $admin_page, 'Pixabay' ) && false !== strpos( $admin_page, 'Pexels' ) && false !== strpos( $admin_page, 'Pinecone' ) && false !== strpos( $admin_page, 'Weaviate' ), 'Provider lists expose active image providers and reserved vector slots.' );
toolbox_assert( false !== strpos( $admin_page, 'Bocha' ) && false !== strpos( $admin_page, 'Jina Reader' ) && false !== strpos( $admin_page, 'search-bocha' ) && false !== strpos( $admin_page, 'search-jina-reader' ), 'Search provider list exposes Bocha and Jina Reader enhancement.' );
toolbox_assert( false !== strpos( $admin_page, 'pixabay_api_key' ) && false !== strpos( $admin_page, 'pexels_api_key' ), 'Pixabay and Pexels image-source keys are configurable.' );
toolbox_assert( false !== strpos( $admin_page, 'bocha_api_key' ) && false !== strpos( $admin_page, 'enable_jina_reader' ), 'Bocha and Jina Reader search settings are configurable.' );
toolbox_assert( false !== strpos( $admin_page, 'https://www.tavily.com/' ) && false !== strpos( $admin_page, 'https://unsplash.com/developers' ) && false !== strpos( $admin_page, 'https://qdrant.tech/' ), 'Connector provider rows expose vendor addresses.' );
toolbox_assert( false !== strpos( $admin_page, 'External web research API' ) && false !== strpos( $admin_page, 'Photo search API' ) && false !== strpos( $admin_page, 'Vector database used here only' ), 'Connector provider rows expose short vendor descriptions.' );
toolbox_assert( false !== strpos( $admin_page, 'external source candidates that any AI workflow can use' ), 'Web Research tool copy keeps search general-purpose.' );
toolbox_assert( false !== strpos( $admin_page, 'does not index WordPress content' ) && false !== strpos( $admin_page, 'This is not AI image generation or media import' ), 'Connector catalog copy preserves image-source and vector-indexing boundaries.' );
toolbox_assert( false !== strpos( $admin_page, 'Jina test setup' ) && false !== strpos( $admin_page, 'jina-embeddings-v3' ), 'Vector connector includes Jina AI test setup guidance.' );
toolbox_assert( false !== strpos( $admin_page, 'Advanced / Debug' ) && false !== strpos( $admin_page, 'Clear stored Jina AI key' ), 'Connector key clearing and debug toggles are moved to an advanced area.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-tools' ) && false !== strpos( $admin_page, 'data-toolbox-tool-panel' ), 'Tool actions use a single active tool workspace instead of a card matrix.' );
toolbox_assert( false !== strpos( $admin_page, 'Article Assistant' ) && false !== strpos( $admin_page, 'render_article_assistant_tool' ), 'Tool actions include a dedicated Article Assistant workbench panel.' );
toolbox_assert( false !== strpos( $admin_page, 'reviewed_draft_markdown' ) && false !== strpos( $admin_page, 'Build assistant artifact' ), 'Article Assistant panel collects optional reviewed draft input for Core-ready handoff.' );
toolbox_assert( false !== strpos( $admin_page, 'Article Write Plan' ) && false !== strpos( $admin_page, 'render_article_plan_tool' ), 'Tool actions include a dedicated Article Write Plan panel.' );
toolbox_assert( false !== strpos( $admin_page, 'content_markdown' ) && false !== strpos( $admin_page, 'Final execution remains magick-ai/create-draft after Core approval.' ), 'Article Write Plan panel collects reviewed draft content and preserves Core handoff copy.' );
toolbox_assert( false !== strpos( $admin_page, 'Media Derivative Preview' ) && false !== strpos( $admin_page, 'render_media_derivative_tool' ), 'Tool actions include a dedicated Media Derivative Preview panel.' );
toolbox_assert( false !== strpos( $admin_page, 'Core defaults' ) && false !== strpos( $admin_page, 'magick_ai_core_get_media_derivative_settings' ), 'Media Derivative Handoff reads Core media policy defaults when available.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-select-media' ) && false !== strpos( $admin_page, 'Generate preview' ) && false !== strpos( $admin_page, 'Submit replacement proposal' ) && false !== strpos( $admin_page, 'data-toolbox-submit-reference-repair' ) && false !== strpos( $admin_page, 'data-toolbox-submit-settings-repair' ), 'Media Derivative Preview supports media selection, Cloud preview generation, Core replacement proposal submission, post reference repair, and settings reference repair submission.' );
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
toolbox_assert( false !== strpos( $admin_js, 'createRawDetails' ) && false !== strpos( $admin_js, 'Complete payload' ), 'Complete payload output is moved behind a result disclosure.' );
toolbox_assert( false !== strpos( $admin_js, 'Provider raw response' ), 'Provider raw responses are rendered only as disclosure details.' );
toolbox_assert( false !== strpos( $admin_js, 'Download tracking' ) && false !== strpos( $admin_js, 'Attribution metadata' ), 'Image candidate rendering preserves Unsplash attribution and download tracking metadata.' );
toolbox_assert( false !== strpos( $admin_js, 'Governed handoff' ) && false !== strpos( $admin_js, 'Core proposal required' ), 'Workflow result rendering keeps governed handoff guidance visible.' );
toolbox_assert( false !== strpos( $admin_js, 'renderArticlePlan' ) && false !== strpos( $admin_js, "payload.artifact_type === 'article_write_plan'" ), 'Admin JavaScript renders article write plans through a dedicated view.' );
toolbox_assert( false !== strpos( $admin_js, 'renderArticleAssistant' ) && false !== strpos( $admin_js, "payload.artifact_type === 'article_assistant_workbench'" ), 'Admin JavaScript renders article assistant workbench artifacts through a dedicated view.' );
toolbox_assert( false !== strpos( $admin_js, 'Write plan' ) && false !== strpos( $admin_js, 'Local workbench' ), 'Article Assistant renderer shows local workbench and write-plan readiness.' );
toolbox_assert( false !== strpos( $admin_js, 'Goal brief' ) && false !== strpos( $admin_js, 'Risk report' ) && false !== strpos( $admin_js, 'Final ability' ), 'Article write plan renderer shows artifacts, risk, and final ability summary.' );
toolbox_assert( false !== strpos( $admin_js, 'renderMediaDerivativeHandoff' ) && false !== strpos( $admin_js, "payload.artifact_type === 'media_derivative_handoff'" ), 'Admin JavaScript renders media derivative handoffs through a dedicated view.' );
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
toolbox_assert( false !== strpos( $admin_js, 'can_retry_after_revision' ) && false !== strpos( $admin_js, 'core_evidence' ), 'Operator feedback renderer shows retry state and Core evidence.' );
toolbox_assert( false !== strpos( $admin_js, 'Revise fields' ) && false !== strpos( $admin_js, 'Next steps' ), 'Operator feedback renderer shows revision fields and next steps.' );
toolbox_assert( false === strpos( $admin_js, 'result.textContent = JSON.stringify(value, null, 2)' ), 'Tool results do not default to raw JSON in the main result surface.' );
toolbox_assert( false !== strpos( $admin_js, 'initContextDrafts' ) && false !== strpos( $admin_js, 'applyContextDraft' ), 'Admin JavaScript can prefill editable content context drafts.' );
toolbox_assert( false !== strpos( $admin_js, 'clearContextForm' ), 'Admin JavaScript can clear the content context form before a new draft.' );
toolbox_assert( false !== strpos( $admin_js, 'initSiteKnowledge' ) && false !== strpos( $admin_js, 'site-knowledge/sync' ) && false !== strpos( $admin_js, 'site-knowledge/status' ), 'Admin JavaScript runs Site Knowledge status and sync actions.' );

$admin_css = file_get_contents( $root . '/assets/admin.css' );
toolbox_assert( false !== strpos( $admin_css, 'magick-ai-toolbox__result-summary' ), 'Admin CSS styles summary-first result panels.' );
toolbox_assert( false !== strpos( $admin_css, 'magick-ai-toolbox__result-details' ), 'Admin CSS styles collapsed result detail disclosures.' );
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

$rest = file_get_contents( $root . '/includes/Rest_Controller.php' );
$allowed_rest_routes = array(
	'/status',
	'/web-research',
	'/image-candidates',
	'/vector-search',
	'/knowledge-search',
	'/site-knowledge/search',
	'/site-knowledge/sync',
	'/site-knowledge/status',
	'/flows/article-brief',
	'/flows/article-assistant',
	'/flows/article-plan',
	'/flows/media-brief',
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
foreach ( array( 'publish', 'delivery', 'workflow-run', 'workflow_run', 'queue', 'scheduler', 'approval', 'approve', 'confirm', 'write', 'featured-image', 'media-upload', 'media-import', 'seo' ) as $forbidden_fragment ) {
	$has_forbidden_route = false;
	foreach ( $registered_rest_routes as $route ) {
		$has_forbidden_route = $has_forbidden_route || str_contains( $route, $forbidden_fragment );
	}
	toolbox_assert( ! $has_forbidden_route, "REST route matrix excludes forbidden route fragment {$forbidden_fragment}." );
}

$abilities = file_get_contents( $root . '/includes/Abilities.php' );
foreach ( array( 'magick-ai-toolbox/web-research', 'magick-ai-toolbox/search-image-source', 'magick-ai-toolbox/vector-search', 'magick-ai-toolbox/search-site-knowledge', 'magick-ai-toolbox/get-site-knowledge-status', 'magick-ai-toolbox/request-site-knowledge-sync', 'magick-ai-toolbox/build-article-brief', 'magick-ai-toolbox/build-article-assistant', 'magick-ai-toolbox/build-article-write-plan', 'magick-ai-toolbox/build-media-brief', 'magick-ai-toolbox/build-media-derivative-handoff', 'magick-ai-toolbox/get-content-discoverability-context', 'magick-ai-toolbox/validate-content-discoverability-context', 'magick-ai-toolbox/build-content-discoverability-brief', 'magick-ai-toolbox/build-ai-article-writing-pack' ) as $ability_id ) {
	toolbox_assert( false !== strpos( $abilities, $ability_id ), "Ability {$ability_id} is registered." );
}

$client = file_get_contents( $root . '/includes/Provider_Client.php' );
toolbox_assert( false !== strpos( $client, 'https://api.tavily.com/search' ), 'Web research uses Tavily search.' );
toolbox_assert( false !== strpos( $client, '/web-search' ) && false !== strpos( $client, 'search_bocha_web' ), 'Web research uses Bocha web search.' );
toolbox_assert( false !== strpos( $client, 'enhance_results_with_jina_reader' ) && false !== strpos( $client, 'jina_reader_base_url' ), 'Web research can enhance selected results through Jina Reader.' );
toolbox_assert( false !== strpos( $client, 'https://api.unsplash.com/search/photos' ), 'Image candidates use Unsplash photo search.' );
toolbox_assert( false !== strpos( $client, 'https://pixabay.com/api/' ), 'Image candidates use Pixabay photo search.' );
toolbox_assert( false !== strpos( $client, 'https://api.pexels.com/v1/search' ), 'Image candidates use Pexels photo search.' );
toolbox_assert( false !== strpos( $client, '/points/query' ), 'Vector search uses Qdrant query points.' );
toolbox_assert( false !== strpos( $client, '/embeddings' ), 'Text vector search uses the configured embedding endpoint.' );
toolbox_assert( false !== strpos( $client, 'siliconflow' ), 'Embedding provider is normalized as SiliconFlow.' );
toolbox_assert( false !== strpos( $client, 'jina' ), 'Jina AI is available as an optional embedding provider.' );
toolbox_assert( false !== strpos( $client, 'embedding_dimension_mismatch' ), 'Vector search guards against embedding dimension mismatch.' );
toolbox_assert( false !== strpos( $client, 'download_location' ), 'Unsplash responses preserve download tracking location.' );
toolbox_assert( false !== strpos( $client, "'provider_mode'" ) && false !== strpos( $client, "'active_sources'" ), 'Image-source output records provider mode and active sources.' );
toolbox_assert( false !== strpos( $client, 'with_optional_raw' ), 'Provider raw responses are optional.' );
toolbox_assert( false !== strpos( $client, 'with_output_contract' ), 'Provider-backed outputs use a shared AI composition contract.' );
toolbox_assert( false !== strpos( $client, "'artifact_type'          => \$artifact_type" ), 'Shared output contract includes artifact type.' );
toolbox_assert( false !== strpos( $client, "'composition_role'       => \$composition_role" ), 'Shared output contract includes composition role.' );
toolbox_assert( false !== strpos( $client, "'direct_wordpress_write' => false" ), 'Shared output contract forbids direct WordPress writes.' );
toolbox_assert( false !== strpos( $client, "'research_evidence'" ), 'Web research output is classified as research evidence.' );
toolbox_assert( false !== strpos( $client, "'provider_mode'" ) && false !== strpos( $client, "'reader_enhancement'" ), 'Web research output records provider mode and reader enhancement status.' );
toolbox_assert( false !== strpos( $client, "'image_source_candidates'" ), 'Image source output is classified as image-source candidates.' );
toolbox_assert( false !== strpos( $client, "'local_style_context'" ), 'Vector output is classified as local style context.' );
toolbox_assert( false !== strpos( $client, 'search_site_knowledge' ) && false !== strpos( $client, "'site_knowledge_search.v1'" ), 'Provider client exposes Cloud-managed site knowledge search.' );
toolbox_assert( false !== strpos( $client, "'faq_candidates'" ) && false !== strpos( $client, "'content_gap_analysis'" ) && false !== strpos( $client, "'duplicate_check'" ), 'Provider client allows high-value site knowledge search intents.' );
toolbox_assert( false !== strpos( $client, 'get_site_knowledge_status' ) && false !== strpos( $client, "'site_knowledge_status.v1'" ), 'Provider client exposes Cloud-managed site knowledge status.' );
toolbox_assert( false !== strpos( $client, 'request_site_knowledge_sync' ) && false !== strpos( $client, "'site_knowledge_sync.v1'" ), 'Provider client exposes Cloud-managed site knowledge sync requests.' );
toolbox_assert( false !== strpos( $client, 'magick_ai_toolbox_site_knowledge_cloud_request' ) && false !== strpos( $client, 'magick_ai_cloud_addon_runtime_client' ), 'Site knowledge execution uses a host filter or Cloud Addon runtime seam.' );
toolbox_assert( false !== strpos( $client, "'ability_name'        => \$ability_name" ) && false !== strpos( $client, "'execution_pattern'   => \$execution_pattern" ), 'Site knowledge runtime payload preserves ability name and execution pattern.' );
toolbox_assert( false !== strpos( $client, 'collect_site_knowledge_documents' ) && false !== strpos( $client, "'post_status'    => 'publish'" ) && false !== strpos( $client, "'post_type'      => array( 'post', 'page' )" ), 'Site knowledge sync uses bounded public WordPress post and page manifests.' );
toolbox_assert( false !== strpos( $client, 'collect_site_knowledge_comments' ) && false !== strpos( $client, "'status'   => 'approve'" ) && false !== strpos( $client, "'type'     => 'comment'" ) && false !== strpos( $client, "'comment_status'  => 'approve'" ), 'Site knowledge sync includes bounded approved comment manifests.' );
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
toolbox_assert( false !== strpos( $client, "'attach_to_post_id' => '\$outputs.' . \$create_id . '.post_id'" ) && false !== strpos( $client, "'attachment_id'  => '\$outputs.' . \$upload_id . '.attachment_id'" ), 'Article plus media batch write plan uses output references for dependent media writes.' );
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
toolbox_assert( false !== strpos( $settings, 'BAAI/bge-m3' ), 'SiliconFlow default embedding model is configured.' );
toolbox_assert( false !== strpos( $settings, 'jina-embeddings-v3' ), 'Jina default embedding model is configured.' );
toolbox_assert( false !== strpos( $settings, "'embedding_dimensions'  => 1024" ), 'Default embedding dimensions match BAAI/bge-m3 and Qdrant guidance.' );
toolbox_assert( false !== strpos( $settings, 'SILICONFLOW_API_KEY' ), 'SiliconFlow key can be provided by environment.' );
toolbox_assert( false !== strpos( $settings, 'JINA_API_KEY' ), 'Jina key can be provided by environment.' );
toolbox_assert( false !== strpos( $settings, 'BOCHA_API_KEY' ), 'Bocha key can be provided by environment.' );
toolbox_assert( false !== strpos( $settings, 'configured_search_providers' ), 'Settings can enumerate configured search providers.' );
toolbox_assert( false !== strpos( $settings, 'PIXABAY_API_KEY' ), 'Pixabay key can be provided by environment.' );
toolbox_assert( false !== strpos( $settings, 'PEXELS_API_KEY' ), 'Pexels key can be provided by environment.' );
toolbox_assert( false !== strpos( $settings, 'configured_image_source_providers' ), 'Settings can enumerate configured image-source providers.' );
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

toolbox_assert( false !== strpos( $rest, 'siliconflow_configured' ), 'Status reports SiliconFlow configuration.' );
toolbox_assert( false !== strpos( $rest, 'jina_configured' ), 'Status reports Jina configuration.' );
toolbox_assert( false !== strpos( $rest, 'search_providers' ) && false !== strpos( $rest, 'bocha_configured' ) && false !== strpos( $rest, 'jina_reader_enabled' ), 'Status reports configured search providers and reader enhancement.' );
toolbox_assert( false !== strpos( $rest, 'image_source_providers' ) && false !== strpos( $rest, 'pixabay_configured' ) && false !== strpos( $rest, 'pexels_configured' ), 'Status reports configured image-source providers.' );
toolbox_assert( false !== strpos( $rest, 'embedding_dimensions' ), 'Status reports embedding dimensions.' );
toolbox_assert( false !== strpos( $rest, 'query or vector field' ), 'Vector REST route accepts query or vector input.' );
toolbox_assert( false !== strpos( $rest, 'site_knowledge_sync' ) && false !== strpos( $rest, 'site_knowledge_status' ) && false !== strpos( $rest, 'site_knowledge_search' ), 'REST routes expose Cloud-managed site knowledge operations.' );
toolbox_assert( false !== strpos( $rest, 'enhance_with_reader' ), 'Web research REST route accepts Jina Reader enhancement.' );
toolbox_assert( false !== strpos( $rest, "'provider'    => sanitize_key" ), 'Image candidate REST route accepts provider selection.' );
toolbox_assert( false !== strpos( $rest, 'magick_ai_toolbox_rest_permission' ), 'REST permission can be mediated by a host scope filter.' );

toolbox_assert( false !== strpos( $abilities, 'cap.toolbox.search' ), 'Web ability exposes the stable Toolbox search scope.' );
toolbox_assert( false !== strpos( $abilities, 'cap.toolbox.vector_search' ), 'Vector ability exposes a Toolbox vector scope.' );
toolbox_assert( false !== strpos( $abilities, 'cap.toolbox.knowledge.search' ) && false !== strpos( $abilities, 'cap.toolbox.knowledge.read' ) && false !== strpos( $abilities, 'cap.toolbox.knowledge.sync' ), 'Site knowledge abilities expose stable knowledge scopes.' );
toolbox_assert( false !== strpos( $abilities, 'cap.toolbox.workflow_suggest' ), 'Workflow abilities expose the stable Toolbox workflow scope.' );
toolbox_assert( false !== strpos( $abilities, 'cap.toolbox.context.read' ), 'Content context ability exposes a read scope.' );
toolbox_assert( false !== strpos( $abilities, 'public_context' ), 'Content context ability declares public context classification.' );
toolbox_assert( false !== strpos( $abilities, 'planning_artifact' ), 'Article write plan ability declares planning artifact classification.' );
toolbox_assert( false !== strpos( $abilities, "'composition_role' => 'research_evidence'" ), 'Web research ability declares its content composition role.' );
toolbox_assert( false !== strpos( $abilities, "'composition_role' => 'image_source_candidates'" ), 'Image-source ability declares its content composition role.' );
toolbox_assert( false !== strpos( $abilities, "'composition_role' => 'local_style_context'" ), 'Vector ability declares its content composition role.' );
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
toolbox_assert( false !== strpos( $readme, 'Unsplash, Pixabay, and Pexels' ), 'README documents active image-source providers.' );
toolbox_assert( false !== strpos( $readme, 'Tavily and Bocha' ) && false !== strpos( $readme, 'Jina Reader result enhancement' ), 'README documents active search providers and Reader enhancement.' );
toolbox_assert( false !== strpos( $readme, 'Cloud-managed site knowledge' ) && false !== strpos( $readme, 'magick-ai-toolbox/search-site-knowledge' ), 'README documents Cloud-managed site knowledge abilities.' );
toolbox_assert( false !== strpos( $readme, 'Pinecone and Weaviate' ), 'Pinecone and Weaviate remain documentation-only reserved vector providers.' );
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
toolbox_assert( false !== strpos( $architecture_doc, 'Article Write Plan' ) && false !== strpos( $architecture_doc, 'submit the plan to Core' ) && false !== strpos( $architecture_doc, 'approve execution' ), 'Architecture documentation records the article plan UI boundary.' );
toolbox_assert( false !== strpos( $architecture_doc, 'artifact_type' ) && false !== strpos( $architecture_doc, 'composition_role' ), 'Architecture documentation records the compact provider payload contract.' );
toolbox_assert( false !== strpos( $architecture_doc, '`web-research` is the general external source-candidate ability' ), 'Architecture documentation records web research as a reusable tool input.' );
toolbox_assert( false !== strpos( $architecture_doc, '| Bocha | External web search | `/web-research` |' ) && false !== strpos( $architecture_doc, '| Jina Reader | Search result URL extraction | `/web-research` enhancement only |' ), 'Architecture documentation records Bocha and Jina Reader search roles.' );
toolbox_assert( false !== strpos( $architecture_doc, '`search-site-knowledge` is the high-level ability' ) && false !== strpos( $architecture_doc, 'magick_ai_toolbox_site_knowledge_runtime_payload' ), 'Architecture documentation records the Cloud site knowledge ability seam.' );

$first_version_doc = file_get_contents( $root . '/docs/first-version-reference.md' );
toolbox_assert( false !== $first_version_doc && false !== strpos( $first_version_doc, 'REST Route Matrix' ) && false !== strpos( $first_version_doc, 'Connector settings now include a compact status catalog' ), 'First-version reference captures route matrix and connector status catalog guidance.' );
toolbox_assert( false !== strpos( $first_version_doc, 'canonical composition sequence' ) && false !== strpos( $first_version_doc, 'The sequence is a recommendation for composing tool inputs' ) && false !== strpos( $first_version_doc, 'runtime contract' ), 'First-version reference captures the AI content composition sequence.' );

$content_composition_doc = file_get_contents( $root . '/docs/ai-content-composition-abilities.md' );
toolbox_assert( false !== $content_composition_doc && false !== strpos( $content_composition_doc, 'Article Call Sequence' ), 'AI content composition documentation records the article call sequence.' );
toolbox_assert( false !== strpos( $content_composition_doc, 'General Tool Usage' ) && false !== strpos( $content_composition_doc, 'article drafting is only one consumer' ), 'AI content composition documentation keeps provider abilities general-purpose.' );
toolbox_assert( false !== strpos( $content_composition_doc, 'magick-ai-toolbox/vector-search' ) && false !== strpos( $content_composition_doc, 'local_style_context' ), 'AI content composition documentation maps vector search to local style context.' );
toolbox_assert( false !== strpos( $content_composition_doc, 'magick-ai-toolbox/search-site-knowledge' ) && false !== strpos( $content_composition_doc, 'related content' ), 'AI content composition documentation maps site knowledge to general search and recommendations.' );
toolbox_assert( false !== strpos( $content_composition_doc, 'magick-ai-toolbox/search-image-source' ) && false !== strpos( $content_composition_doc, 'download_location' ), 'AI content composition documentation maps image source search to attribution-preserving image candidates.' );
toolbox_assert( false !== strpos( $content_composition_doc, 'magick-ai-toolbox/web-research' ) && false !== strpos( $content_composition_doc, 'support answers' ), 'AI content composition documentation maps web research to general external source candidates.' );
toolbox_assert( false !== strpos( $content_composition_doc, 'Tavily, Bocha, and Jina Reader output' ), 'AI content composition documentation preserves search and reader evidence boundaries.' );
toolbox_assert( false !== strpos( $content_composition_doc, 'content indexing' ), 'AI content composition documentation blocks indexing ownership.' );
toolbox_assert( false !== strpos( $content_composition_doc, 'Final WordPress writes still require Core proposal approval' ), 'AI content composition documentation preserves Core write governance.' );

$connector_exposure_doc = file_get_contents( $root . '/docs/connector-ability-exposure.md' );
toolbox_assert( false !== $connector_exposure_doc && false !== strpos( $connector_exposure_doc, 'provider_secret_exposure: none' ), 'Connector exposure documentation records secret non-exposure.' );
toolbox_assert( false !== strpos( $connector_exposure_doc, 'server_side_toolbox' ), 'Connector exposure documentation records server-side provider execution.' );
toolbox_assert( false !== strpos( $connector_exposure_doc, 'composition_role:' ), 'Connector exposure documentation records machine-readable composition role metadata.' );
toolbox_assert( false !== strpos( $connector_exposure_doc, 'single `web-research` ability' ) && false !== strpos( $connector_exposure_doc, 'not only article drafting' ), 'Connector exposure documentation keeps web research general-purpose.' );
toolbox_assert( false !== strpos( $connector_exposure_doc, 'Tavily and Bocha' ) && false !== strpos( $connector_exposure_doc, 'bounded post-search enhancement' ), 'Connector exposure documentation records Bocha and Jina Reader boundaries.' );
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
