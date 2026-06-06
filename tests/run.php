<?php
/**
 * Static contract checks for the first Toolbox release.
 *
 * @package Npcink_Toolbox
 */

$root = dirname( __DIR__ );

function toolbox_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}

	echo "PASS: {$message}\n";
}

$main = file_get_contents( $root . '/npcink-toolbox.php' );
toolbox_assert( false !== $main && str_contains( $main, 'Plugin Name: Npcink Toolbox' ), 'Plugin header is present.' );
toolbox_assert( false !== strpos( $main, 'Text Domain: npcink-toolbox' ) && false !== strpos( $main, 'Domain Path: /languages' ), 'Plugin header declares the Toolbox text domain and languages path.' );
toolbox_assert( false !== strpos( $main, 'includes/Editor_Content_Support.php' ), 'Plugin bootstrap loads the post editor content support entrypoint.' );
toolbox_assert( false !== strpos( $main, 'includes/Site_Knowledge_Auto_Sync.php' ), 'Plugin bootstrap loads the Site Knowledge auto-sync bridge.' );
toolbox_assert( false !== strpos( $main, 'register_deactivation_hook' ) && false !== strpos( $main, 'Site_Knowledge_Auto_Sync::class' ), 'Plugin deactivation clears Site Knowledge auto-sync cron hooks.' );

$plugin = file_get_contents( $root . '/includes/Plugin.php' );
toolbox_assert( false !== $plugin && false !== strpos( $plugin, 'load_plugin_textdomain' ) && false !== strpos( $plugin, "dirname( plugin_basename( NPCINK_TOOLBOX_FILE ) ) . '/languages'" ), 'Plugin loads translations from the bundled languages directory.' );

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
foreach ( array( 'media_optimization_v1', 'fixed governed Optimize Existing Image', 'duplicate runner', 'workflow runtime, persistent run store, media registry' ) as $required_media_positioning_doc ) {
	toolbox_assert( false !== strpos( $positioning_doc, $required_media_positioning_doc ), 'Product positioning keeps media optimization as a fixed governed workflow: ' . $required_media_positioning_doc );
}

$boundary_doc = file_get_contents( $root . '/docs/boundary.md' );
foreach ( array( 'OpenClaw Button Surface Boundary', 'UX projection of the same local ability and Core proposal contracts', 'OpenClaw natural-language request', 'Toolbox fixed button', 'reviewed plan or candidate artifact', 'must not own OpenClaw projection truth', 'approval truth, prompt/model', 'media registry truth, or final WordPress write execution' ) as $required_boundary_doc ) {
	toolbox_assert( false !== strpos( $boundary_doc, $required_boundary_doc ), 'Boundary doc preserves Toolbox/OpenClaw split: ' . $required_boundary_doc );
}
foreach ( array( 'media_optimization_v1', 'Optimize Existing Image', 'one Core media optimization proposal', 'generic workflow runner, persistent run table', 'queue, scheduler, or direct media write' ) as $required_media_boundary_doc ) {
	toolbox_assert( false !== strpos( $boundary_doc, $required_media_boundary_doc ), 'Boundary doc keeps media optimization fixed and governed: ' . $required_media_boundary_doc );
}

$media_optimization_doc = file_get_contents( $root . '/docs/media-optimization-v1.md' );
foreach ( array( 'media_optimization_v1', 'fixed, governed Toolbox workflow', 'not a new workflow runtime', 'Select media', 'Generate Cloud preview', 'Review media metadata', 'Submit optimization review', 'persistent Toolbox run table', 'one Core proposal', 'Expansion Rule' ) as $required_media_optimization_doc ) {
	toolbox_assert( false !== strpos( $media_optimization_doc, $required_media_optimization_doc ), 'Media Optimization V1 doc preserves the fixed workflow contract: ' . $required_media_optimization_doc );
}

$composition_doc = file_get_contents( $root . '/docs/ai-content-composition-abilities.md' );
foreach ( array( 'Fixed Button Mapping', 'OpenClaw natural-language recipes and Toolbox fixed buttons should compose the', 'same ability contracts', 'Article/media batch fallback', 'Adopt New Image', 'Optimize Existing Image', 'One Core media optimization proposal', 'separate workflow runtime, direct write path, or approval store' ) as $required_composition_doc ) {
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
foreach ( array( 'Media Optimization V1', 'media_optimization_v1', 'fixed governed workflow', 'not a new workflow runtime or persistent run', 'Optimize Existing Image' ) as $required_media_readme_text ) {
	toolbox_assert( false !== strpos( $readme, $required_media_readme_text ), 'README documents media optimization as an existing fixed governed workflow: ' . $required_media_readme_text );
}

$architecture_doc = file_get_contents( $root . '/docs/architecture.md' );
foreach ( array( 'media_optimization_v1', 'existing **Optimize Existing Image** surface', 'does not introduce a Toolbox custom table', '/workflow-runs route', 'artifact registry, or direct media writer' ) as $required_media_architecture_doc ) {
	toolbox_assert( false !== strpos( $architecture_doc, $required_media_architecture_doc ), 'Architecture doc keeps media optimization out of runtime storage: ' . $required_media_architecture_doc );
}

$admin_page = file_get_contents( $root . '/includes/Admin_Page.php' );
toolbox_assert( false !== strpos( $admin_page, "private const PARENT_MENU_SLUG = 'npcink-ai';" ), 'Admin page targets the shared Npcink AI parent menu.' );
toolbox_assert( false !== strpos( $admin_page, "private const MENU_SLUG        = 'npcink-toolbox';" ), 'Admin page uses stable Toolbox menu slug.' );
toolbox_assert( false !== strpos( $admin_page, "wp_set_script_translations(\n\t\t\t'npcink-toolbox-admin'" ) && false !== strpos( $admin_page, "NPCINK_TOOLBOX_DIR . 'languages'" ), 'Admin page registers the Toolbox script translation path.' );
toolbox_assert( false !== strpos( $admin_page, 'add_submenu_page' ) && false !== strpos( $admin_page, '45' ), 'Admin page registers after Abilities and before Cloud Addon.' );
toolbox_assert( false !== strpos( $admin_page, 'add_management_page' ), 'Admin page keeps a Tools fallback for standalone installs.' );
toolbox_assert( false === strpos( $admin_page, 'npcink-toolbox__status-strip' ), 'Admin page omits the stale local status strip now owned by Cloud and focused panels.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-tabs' ), 'Admin page separates context, site knowledge, tools, and Cloud checks into top-level tabs.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-tab-target="context" aria-selected="true"' ), 'Content Context is the default admin tab.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-tab-target="site-knowledge"' ) && false !== strpos( $admin_page, 'data-toolbox-site-knowledge' ), 'Admin page exposes a Site Knowledge operation panel.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-site-knowledge-sync-submit' ) && false !== strpos( $admin_page, 'Start indexing' ) && false !== strpos( $admin_page, 'Refresh index' ), 'Site Knowledge exposes one simple indexing action that changes from start to refresh.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-site-knowledge-status' ) && false === strpos( $admin_page, 'data-toolbox-site-knowledge-action-status' ), 'Site Knowledge keeps status refresh in the status section only.' );
toolbox_assert( false === strpos( $admin_page, '<select name="sync_mode"' ) && false === strpos( $admin_page, 'name="post_ids"' ) && false === strpos( $admin_page, 'Delete index' ), 'Site Knowledge hides rebuild/delete/post ID controls from the default user indexing action.' );
toolbox_assert( false !== strpos( $admin_page, 'Used by Content Support' ) && false !== strpos( $admin_page, 'Internal Link Candidates' ) && false !== strpos( $admin_page, 'Publish Preflight' ) && false !== strpos( $admin_page, 'Article Planning Bundle and OpenClaw' ), 'Site Knowledge explains concrete downstream usage without exposing vector configuration.' );
toolbox_assert( false !== strpos( $admin_page, 'Open Content Support' ) && false !== strpos( $admin_page, 'toolbox_tool=internal-links' ), 'Site Knowledge provides a direct entry to the primary Content Support usage path.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-context-sections' ) && false !== strpos( $admin_page, 'data-toolbox-context-panel' ), 'Content context uses a focused tabbed workspace.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-context-target="brief" aria-selected="true"' ) && false !== strpos( $admin_page, 'data-toolbox-context-target="boundaries"' ), 'Content context defaults to Brief and keeps Boundaries as a focused section.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-context-groups' ) && false !== strpos( $admin_page, 'data-toolbox-context-group-target="brief-profile"' ) && false !== strpos( $admin_page, 'data-toolbox-context-group-target="boundaries-preview"' ), 'Content context sections use a left field list and right detail panel.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-tab-target="tools" aria-selected="false"' ) && false !== strpos( $admin_page, 'Content Support' ), 'Tool execution is a secondary Content Support tab.' );
toolbox_assert( false !== strpos( $admin_page, 'AI Draft Support' ) && false !== strpos( $admin_page, 'ai-title-summary' ) && false !== strpos( $admin_page, 'ai-outline' ) && false !== strpos( $admin_page, 'ai-polish' ), 'Tool actions expose the hosted AI draft-support entry group.' );
toolbox_assert( false !== strpos( $admin_page, "'intent'      => 'title_summary'" ) && false !== strpos( $admin_page, "'intent'      => 'article_outline'" ) && false !== strpos( $admin_page, "'intent'      => 'polish_notes'" ), 'AI draft-support tools map to title, outline, and polish intents.' );
toolbox_assert( false === strpos( $admin_page, "'intent'      => 'site_checkup'" ) && false === strpos( $admin_page, "'intent'      => 'media_alt'" ) && false === strpos( $admin_page, "'intent'      => 'smart_recommendations'" ), 'Default AI draft-support tools do not expose site-level snapshot intents.' );
toolbox_assert( false !== strpos( $admin_page, 'Hosted AI route' ) && false !== strpos( $admin_page, 'reviewable suggestion, not a finished article' ) && false !== strpos( $admin_page, "'powered_by'  => 'hosted_ai'" ), 'AI tools remain suggestion-only hosted-runtime entries.' );
toolbox_assert( false !== strpos( $admin_page, "'endpoint'    => 'ai/content-support'" ), 'AI tools call the dedicated hosted runtime route.' );
toolbox_assert( false !== strpos( $admin_page, 'AI Site Helpers' ) && false !== strpos( $admin_page, 'ai-media-alt-suggestions' ) && false !== strpos( $admin_page, 'ai-content-snapshot-suggestions' ), 'Tool actions expose lightweight AI site-helper entries.' );
toolbox_assert( false !== strpos( $admin_page, "'endpoint'    => 'ai/site-helpers'" ) && false !== strpos( $admin_page, "'intent'      => 'media_alt_suggestions'" ) && false !== strpos( $admin_page, "'intent'      => 'content_snapshot_suggestions'" ), 'AI site helpers call a separate narrow route with site-helper intents.' );
toolbox_assert( false !== strpos( $admin_page, 'render_hosted_ai_site_helper_tool' ) && false !== strpos( $admin_page, 'Optional focus' ) && false !== strpos( $admin_page, 'sample to Cloud' ), 'AI site-helper form stays lightweight and does not reuse draft-body fields.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-tab-target="cloud-checks" aria-selected="false"' ) && false !== strpos( $admin_page, 'Cloud Checks' ), 'Cloud checks use their own top-level tab id and label.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-tab-panel="cloud-checks"' ), 'Cloud checks are moved out of the default tools view.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-cloud-checks' ) && false !== strpos( $admin_page, 'data-toolbox-cloud-check-panel' ), 'Cloud checks use a single active verification workspace.' );
toolbox_assert( false !== strpos( $admin_page, 'npcink-toolbox__cloud-check-tabs' ) && false !== strpos( $admin_page, 'npcink-toolbox__cloud-check-tab' ), 'Cloud check groups use horizontal sub tabs near the check heading.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-cloud-check-groups' ) && false !== strpos( $admin_page, 'data-toolbox-cloud-check-group-target="search-test"' ) && false !== strpos( $admin_page, 'data-toolbox-cloud-check-group-target="site-knowledge-search"' ), 'Cloud checks mirror the Context left-list and right-detail layout.' );
toolbox_assert( false === strpos( $admin_page, 'data-toolbox-connector-providers' ) && false === strpos( $admin_page, 'data-toolbox-connector-provider-target' ) && false === strpos( $admin_page, 'data-toolbox-connector-provider-panel' ), 'Cloud checks no longer expose provider rail configuration locally.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-cloud-check-target="search" aria-selected="true"' ), 'Search is the default Cloud check section for Cloud web search testing.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-endpoint="web-search/test"' ) && false !== strpos( $admin_page, 'Run search test' ), 'Cloud checks expose a Cloud-managed Web Search test action.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-endpoint="web-search/diagnostics"' ) && false !== strpos( $admin_page, 'Workflow diagnostic' ), 'Cloud checks expose a Cloud web search workflow diagnostic action.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-cloud-check-target="image" aria-selected="false"' ), 'Image check stays available after the Cloud Search test section.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-cloud-check-target="site-knowledge" aria-selected="false"' ) && false !== strpos( $admin_page, 'data-toolbox-cloud-check-panel="site-knowledge"' ), 'Cloud checks expose a Cloud-managed Site Knowledge verification section.' );
toolbox_assert( false !== strpos( $admin_page, 'Search check' ) && false !== strpos( $admin_page, 'Run check' ) && false !== strpos( $admin_page, 'data-toolbox-site-knowledge-search' ), 'Site Knowledge check exposes a read-only Cloud site knowledge search check.' );
toolbox_assert( false !== strpos( $admin_page, 'Manage index' ) && false !== strpos( $admin_page, 'toolbox_tab=site-knowledge' ), 'Site Knowledge check links indexing operations back to Site Knowledge.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-cloud-check-panel="image"' ) && false !== strpos( $admin_page, 'Image source smoke test' ) && false !== strpos( $admin_page, 'Test Cloud image source' ) && false !== strpos( $admin_page, 'Candidate count' ) && false !== strpos( $admin_page, '<option value="auto">' ), 'Image connector exposes a Cloud auto provider smoke-test flow.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-cloud-check-group-target="image-derivative-preview"' ) && false !== strpos( $admin_page, 'data-toolbox-media-derivative-preview-only' ) && false !== strpos( $admin_page, 'Open Optimize Existing Image' ), 'Cloud Checks Image splits preview-only media derivative checks from Core handoff.' );
toolbox_assert( false === strpos( $admin_page, '<option value="tavily">' ) && false === strpos( $admin_page, '<option value="bocha">' ) && false === strpos( $admin_page, '<option value="apify">' ) && false === strpos( $admin_page, 'Enhance returned pages with Jina Reader' ), 'Cloud Search checks use Cloud auto execution without provider or Jina controls.' );
toolbox_assert( false === strpos( $admin_page, 'npcink-toolbox__connector-status' ) && false === strpos( $admin_page, 'Connector status catalog' ), 'Connector panels go directly from tabs into verification tools.' );
toolbox_assert( false === strpos( $admin_page, 'Local MVP config' ) && false === strpos( $admin_page, 'Future connector owner' ), 'Connector panels no longer present local or reserved vector provider ownership.' );
toolbox_assert( false === strpos( $admin_page, 'Pinecone' ) && false === strpos( $admin_page, 'Weaviate' ), 'Provider lists do not expose reserved vector slots locally.' );
toolbox_assert( false === strpos( $admin_page, 'search-bocha' ) && false === strpos( $admin_page, 'search-jina-reader' ) && false === strpos( $admin_page, 'tavily_api_key' ), 'Search provider keys and panels are removed from the connector UI.' );
toolbox_assert( false === strpos( $admin_page, 'unsplash_access_key' ) && false === strpos( $admin_page, 'pixabay_api_key' ) && false === strpos( $admin_page, 'pexels_api_key' ), 'Public image-source provider keys are not configurable in local Toolbox.' );
toolbox_assert( false === strpos( $admin_page, 'bocha_api_key' ) && false === strpos( $admin_page, 'enable_jina_reader' ), 'Bocha and Jina Reader search settings are not configurable locally.' );
toolbox_assert( false === strpos( $admin_page, 'admin.php?page=npcink-cloud-addon' ) && false === strpos( $admin_page, 'https://qdrant.tech/' ), 'Connector verification panels omit Cloud provider catalog links and local vector vendor links.' );
toolbox_assert( false !== strpos( $admin_page, 'Search' ) && false !== strpos( $admin_page, 'Cloud managed' ), 'Cloud check tabs keep Cloud-managed readiness labels.' );
toolbox_assert( false === strpos( $admin_page, 'Cloud owns the vector database' ) && false !== strpos( $admin_page, 'Image source smoke test' ), 'Connector verification copy preserves image-source testing without vector detail.' );
toolbox_assert( false === strpos( $admin_page, 'Jina test setup' ) && false === strpos( $admin_page, 'jina-embeddings-v3' ), 'Vector connector does not include local embedding setup guidance.' );
toolbox_assert( false === strpos( $admin_page, 'Advanced / Debug' ) && false === strpos( $admin_page, 'Image source search' ) && false === strpos( $admin_page, 'Include provider raw responses' ), 'Cloud checks keep verification focused and hide unrelated debug toggles.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-tools' ) && false !== strpos( $admin_page, 'data-toolbox-tool-panel' ), 'Tool actions use a single active tool workspace instead of a card matrix.' );
toolbox_assert( false !== strpos( $admin_page, 'Everyday Support' ) && false !== strpos( $admin_page, 'Fallback Bundles' ) && false !== strpos( $admin_page, 'Governed Handoffs' ) && false !== strpos( $admin_page, 'Media' ), 'Tool actions visually separate fixed support flows, fallback bundles, governed handoffs, and media work.' );
toolbox_assert( false !== strpos( $admin_page, 'Discoverability Brief' ) && false !== strpos( $admin_page, 'Publish Preflight' ) && false !== strpos( $admin_page, 'Summary and Terms Optimization' ) && false !== strpos( $admin_page, 'Taxonomy/Tag Candidates' ) && false !== strpos( $admin_page, 'Internal Link Candidates' ) && false !== strpos( $admin_page, 'Image Candidates' ), 'Tool actions expose fixed Content Support flows as first-class buttons.' );
$handoff_group_pos = strpos( $admin_page, "'group'       => __( 'Governed Handoffs'" );
$adopt_image_pos   = strpos( $admin_page, "'id'          => 'image-candidate-adoption'" );
$media_group_pos   = strpos( $admin_page, "'group'       => __( 'Media'" );
toolbox_assert( false !== $handoff_group_pos && false !== $adopt_image_pos && false !== $media_group_pos && $handoff_group_pos < $adopt_image_pos && $adopt_image_pos < $media_group_pos, 'Governed handoff tools stay grouped before media tools.' );
toolbox_assert( false !== strpos( $admin_page, 'render_content_support_flow_tool' ) && false !== strpos( $admin_page, 'editor/content-support' ) && false !== strpos( $admin_page, 'content_support_flow' ), 'Admin Content Support fixed buttons reuse the governed editor content-support route.' );
toolbox_assert( false !== strpos( $admin_page, 'Fixed support flow' ) && false !== strpos( $admin_page, 'It does not write posts, assign terms, insert links, import media, or publish.' ), 'Admin Content Support fixed flow forms preserve suggestion-only no-write copy.' );
toolbox_assert( false !== strpos( $admin_page, "'id'          => 'discoverability-brief'" ) && false !== strpos( $admin_page, "'id'          => 'article-brief'" ) && false !== strpos( $admin_page, 'Article Planning Bundle' ) && false !== strpos( $admin_page, "'endpoint'    => 'flows/article-brief'" ), 'Article brief URL remains available as a fallback Article Planning Bundle after fixed flows.' );
toolbox_assert( false === strpos( $admin_page, 'Content Support Brief' ), 'Combined article brief is no longer presented as the default Content Support brief.' );
toolbox_assert( false !== strpos( $admin_page, 'Article Assistant Fallback' ) && false !== strpos( $admin_page, 'render_article_assistant_tool' ), 'Tool actions include a dedicated Article Assistant fallback workbench panel.' );
toolbox_assert( false !== strpos( $admin_page, 'reviewed_draft_markdown' ) && false !== strpos( $admin_page, 'Build assistant artifact' ), 'Article Assistant panel collects optional reviewed draft input for Core-ready handoff.' );
toolbox_assert( false !== strpos( $admin_page, 'Reviewed Draft Handoff' ) && false !== strpos( $admin_page, 'render_article_plan_tool' ), 'Tool actions include a dedicated Reviewed Draft Handoff fallback panel.' );
toolbox_assert( false !== strpos( $admin_page, 'content_markdown' ) && false !== strpos( $admin_page, 'Final execution remains npcink-abilities-toolkit/create-draft after Core approval.' ), 'Article Write Plan panel collects reviewed draft content and preserves Core handoff copy.' );
toolbox_assert( false !== strpos( $admin_page, 'Adopt New Image' ) && false !== strpos( $admin_page, 'render_image_candidate_adoption_tool' ), 'Tool actions include an Adopt New Image panel.' );
toolbox_assert( false !== strpos( $admin_page, 'Selected image URL' ) && false !== strpos( $admin_page, 'Source type' ) && false !== strpos( $admin_page, 'Advanced candidate details' ) && false !== strpos( $admin_page, 'Build import proposal plan' ), 'Adopt New Image panel hides image_candidate internals behind a simpler button flow.' );
toolbox_assert( false !== strpos( $admin_page, 'Candidate JSON' ) && false !== strpos( $admin_page, 'Toolbox does not import media directly' ), 'Adopt New Image panel keeps advanced candidate JSON optional and preserves no-write copy.' );
toolbox_assert( false === strpos( $admin_page, "'custom'       => 'image_source_candidates'" ) && false !== strpos( $admin_page, 'render_image_source_candidates_smoke_form' ), 'Image source smoke test is owned by the Image connector instead of the Content Support tool list.' );
toolbox_assert( false !== strpos( $admin_page, 'Optimize Existing Image' ) && false !== strpos( $admin_page, 'render_media_derivative_tool' ), 'Tool actions include a dedicated Optimize Existing Image panel.' );
toolbox_assert( false !== strpos( $admin_page, 'Core defaults' ) && false !== strpos( $admin_page, 'magick_ai_core_get_media_derivative_settings' ), 'Media Derivative Handoff reads Core media policy defaults when available.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-select-media' ) && false !== strpos( $admin_page, 'Generate preview' ) && false !== strpos( $admin_page, 'Submit optimization review' ) && false !== strpos( $admin_page, 'Repair and handoff actions' ) && false !== strpos( $admin_page, 'data-toolbox-submit-reference-repair' ) && false !== strpos( $admin_page, 'data-toolbox-submit-settings-repair' ), 'Optimize Existing Image supports media selection, Cloud preview generation, Core optimization review, and advanced repair actions.' );
toolbox_assert( false !== strpos( $admin_page, 'Reviewed media details' ) && false !== strpos( $admin_page, 'name="media_alt"' ) && false !== strpos( $admin_page, 'name="media_source_type"' ), 'Optimize Existing Image collects reviewed media metadata for one Core optimization proposal.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-media-url' ) && false !== strpos( $admin_page, 'data-toolbox-resolve-media-url' ) && false !== strpos( $admin_page, 'data-toolbox-media-url-resolution' ), 'Media Derivative Preview supports resolving a local uploads URL to an attachment before preview generation.' );
toolbox_assert( false !== strpos( $admin_page, 'Batch conversion plan' ) && false !== strpos( $admin_page, 'data-toolbox-build-media-batch-plan' ) && false !== strpos( $admin_page, 'data-toolbox-run-media-batch-previews' ) && false !== strpos( $admin_page, 'data-toolbox-submit-media-batch-proposals' ), 'Media Derivative Preview supports bounded batch planning, selected previews, and selected proposal submission.' );
toolbox_assert( false !== strpos( $admin_page, 'Fixed batch flow' ) && false !== strpos( $admin_page, 'name="batch_scope_preset"' ) && false !== strpos( $admin_page, 'name="batch_recipe"' ) && false !== strpos( $admin_page, 'Advanced filters' ), 'Media Derivative Preview presents batch work as a fixed operator flow with advanced filters folded away.' );
toolbox_assert( false !== strpos( $admin_page, 'settings_excluded_formats' ) && false !== strpos( $admin_page, 'settings_min_dimensions' ), 'Media Derivative Preview exposes bounded settings reference repair exclusions.' );
toolbox_assert( false !== strpos( $admin_page, 'Watermark override' ) && false !== strpos( $admin_page, 'name="watermark_mode"' ) && false !== strpos( $admin_page, 'Text watermark' ) && false !== strpos( $admin_page, 'Image/logo watermark' ), 'Media Derivative Preview exposes explicit text and image one-run watermark modes without owning stored policy.' );
toolbox_assert( false !== strpos( $admin_page, 'name="watermark_text"' ) && false !== strpos( $admin_page, 'name="watermark_font_size"' ) && false !== strpos( $admin_page, 'name="watermark_color"' ) && false !== strpos( $admin_page, 'name="watermark_background"' ), 'Media Derivative Preview exposes text watermark fields for OpenClaw-equivalent Cloud request payloads.' );
toolbox_assert( false !== strpos( $admin_page, 'Core remains the policy owner and final WordPress write owner' ), 'Media Derivative Preview copy keeps Core as policy and final write owner.' );
toolbox_assert( false !== strpos( $admin_page, 'data-toolbox-context-group-target="boundaries-preview"' ) && false !== strpos( $admin_page, 'data-toolbox-context-group-panel="boundaries-preview" hidden' ) && false !== strpos( $admin_page, 'Ability preview' ), 'Lower-frequency details stay out of the default working view.' );
toolbox_assert( false !== strpos( $admin_page, '<div class="npcink-toolbox__result is-empty"' ), 'Tool result panels use structured result containers instead of raw preformatted output.' );
toolbox_assert( false !== strpos( $admin_page, 'contextDrafts' ) && false !== strpos( $admin_page, 'get_ai_blog_context_template' ), 'Content context exposes an editable AI technology blog draft template.' );
toolbox_assert( false !== strpos( $admin_page, 'get_site_content_context_suggestion' ) && false !== strpos( $admin_page, 'get_posts(' ) && false !== strpos( $admin_page, 'get_terms(' ), 'Content context can draft suggestions from current public site content signals.' );
toolbox_assert( false !== strpos( $admin_page, "data-toolbox-context-draft=\"aiBlog\"" ) && false !== strpos( $admin_page, "data-toolbox-context-draft=\"site\"" ), 'Content context includes template and current-site draft buttons.' );
toolbox_assert( false !== strpos( $admin_page, 'SEO fields AI may suggest' ) && false !== strpos( $admin_page, 'AEO fields AI may suggest' ) && false !== strpos( $admin_page, 'GEO fields AI may suggest' ), 'Content context groups proposal fields by SEO, AEO, and GEO.' );
toolbox_assert( false !== strpos( $admin_page, 'Drafts are editable suggestions and do not change posts, media, SEO meta, or provider settings.' ), 'Content context draft copy preserves suggestion-only boundaries.' );
toolbox_assert( false !== strpos( $admin_page, 'JSON_UNESCAPED_UNICODE' ), 'Content context ability preview keeps non-Latin text readable.' );
toolbox_assert( false !== strpos( $admin_page, 'wp_enqueue_media' ) && false !== strpos( $admin_page, "'adapterRestUrl'" ) && false !== strpos( $admin_page, "rest_url( 'npcink-openclaw-adapter/v1' )" ), 'Media Derivative Preview loads the WordPress media picker and Adapter REST URL.' );
toolbox_assert( false !== strpos( $admin_page, "'dateTime'      => \$this->datetime_display_config()" ), 'Admin page localizes WordPress datetime display config for dynamic results.' );
toolbox_assert( false !== strpos( $admin_page, 'datetime_display_config' ) && false !== strpos( $admin_page, "'format'        => 'Y-m-d H:i:s'" ) && false !== strpos( $admin_page, 'wp_timezone_string' ), 'Admin page exposes the WordPress site timezone and standard display format.' );

$admin_js = file_get_contents( $root . '/assets/admin.js' );
toolbox_assert( false !== strpos( $admin_js, 'const __ = typeof i18n.__' ) && false !== strpos( $admin_js, "return __(String(text), 'npcink-toolbox')" ), 'Admin JavaScript routes runtime UI copy through the Toolbox script text domain.' );
toolbox_assert( false !== strpos( $admin_js, 'node.textContent = t(text)' ) && false !== strpos( $admin_js, 't(\'Revise fields: \')' ), 'Admin JavaScript translates result renderer text and concatenated operator feedback prefixes.' );
toolbox_assert( false !== strpos( $admin_js, 'initTopTabs' ) && false !== strpos( $admin_js, 'initToolSwitcher' ), 'Admin JavaScript initializes section tabs and tool switching.' );
toolbox_assert( false !== strpos( $admin_js, 'function formatDateTime' ) && false !== strpos( $admin_js, 'window.NpcinkToolbox.dateTime' ) && false !== strpos( $admin_js, "return parts.year + '-' + parts.month + '-' + parts.day + ' ' + hour" ), 'Admin JavaScript formats visible timestamps through the localized WordPress timezone.' );
toolbox_assert( false !== strpos( $admin_js, "appendMeta(meta, 'Last sync', formatDateTime(coverage.last_sync_at))" ), 'Site Knowledge last sync display uses WordPress datetime formatting.' );
toolbox_assert( false !== strpos( $admin_js, "appendMeta(meta, 'Expires', formatDateTime(derivative.expires_at))" ) && false !== strpos( $admin_js, "appendMeta(itemMeta, 'Expires', formatDateTime(derivative.expires_at))" ), 'Media derivative expiry display uses WordPress datetime formatting.' );
toolbox_assert( false !== strpos( $admin_js, 'initCloudCheckSwitcher' ) && false !== strpos( $admin_js, 'data-toolbox-cloud-check-target' ), 'Admin JavaScript initializes Cloud check section switching.' );
toolbox_assert( false !== strpos( $admin_js, 'initCloudCheckGroupSwitcher' ) && false !== strpos( $admin_js, 'data-toolbox-cloud-check-group-target' ), 'Admin JavaScript initializes Cloud check left-list detail switching.' );
toolbox_assert( false === strpos( $admin_js, 'initConnectorProviderSwitcher' ) && false === strpos( $admin_js, 'data-toolbox-connector-provider-target' ), 'Admin JavaScript no longer initializes removed connector provider switching.' );
toolbox_assert( false !== strpos( $admin_js, 'initContextSectionSwitcher' ) && false !== strpos( $admin_js, 'initContextGroupSwitcher' ), 'Admin JavaScript initializes content context section and field switching.' );
toolbox_assert( false !== strpos( $admin_js, "container.matches('[data-toolbox-tabs]')" ) && false !== strpos( $admin_js, 'const panelRoot' ), 'Nested switchers keep panel activation scoped to their own workspace.' );
toolbox_assert( false !== strpos( $admin_js, 'initUrlState' ) && false !== strpos( $admin_js, 'history.replaceState' ), 'Admin JavaScript restores tab state from the URL and updates URLs without reloading.' );
toolbox_assert( false !== strpos( $admin_js, "toolbox_tab: 'cloud-checks'" ) && false !== strpos( $admin_js, 'toolbox_tool' ) && false !== strpos( $admin_js, 'toolbox_cloud_check' ) && false !== strpos( $admin_js, 'toolbox_cloud_check_group' ) && false === strpos( $admin_js, 'toolbox_connector' ), 'Admin tab URL state uses Cloud Checks query parameters.' );
toolbox_assert( false !== strpos( $admin_js, "result.hidden = false" ), 'Tool results stay hidden until a tool returns output.' );
toolbox_assert( false !== strpos( $admin_js, 'renderStructuredResult' ) && false !== strpos( $admin_js, 'renderShell' ), 'Admin JavaScript renders tool results through a summary-first structured renderer.' );
toolbox_assert( false !== strpos( $admin_js, 'renderWebSearchResults' ) && false !== strpos( $admin_js, "payload.artifact_type === 'web_search_results'" ), 'Admin JavaScript renders Cloud web search test results through a dedicated view.' );
toolbox_assert( false !== strpos( $admin_js, "appendMeta(meta, 'Provider calls', payload.provider_call_count)" ) && false === strpos( $admin_js, 'payload.usage_summary.reader_status' ) && false === strpos( $admin_js, 'Reader enhancement' ), 'Admin JavaScript surfaces Cloud web search reachability metadata without reader controls.' );
toolbox_assert( false !== strpos( $admin_js, 'renderWebSearchDiagnostics' ) && false !== strpos( $admin_js, "payload.artifact_type === 'web_search_diagnostics'" ), 'Admin JavaScript renders Cloud web search workflow diagnostics through a dedicated view.' );
toolbox_assert( false !== strpos( $admin_js, "appendMeta(meta, 'Error code', payload.error_code" ) && false !== strpos( $admin_js, 'payload.usage_summary.evidence_status' ), 'Admin JavaScript surfaces workflow search diagnostic errors and evidence status.' );
toolbox_assert( false !== strpos( $admin_js, 'renderEditorContentSupport' ) && false !== strpos( $admin_js, "payload.artifact_type === 'editor_content_support_flow'" ), 'Admin JavaScript renders fixed Content Support flow artifacts as structured suggestions.' );
toolbox_assert( false !== strpos( $admin_js, 'renderSummaryTermsOptimization' ) && false !== strpos( $admin_js, 'Summary and terms optimization' ) && false !== strpos( $admin_js, 'Related Site Knowledge' ), 'Admin JavaScript renders summary/category/tag optimization as structured suggestions.' );
toolbox_assert( false !== strpos( $admin_js, 'renderHostedAiContentSupport' ) && false !== strpos( $admin_js, "payload.artifact_type === 'hosted_ai_content_support'" ) && false !== strpos( $admin_js, 'text.ai' ), 'Admin JavaScript renders hosted AI runtime suggestions.' );
toolbox_assert( false !== strpos( $admin_js, 'Title and summary suggestions' ) && false !== strpos( $admin_js, 'Outline suggestions' ) && false !== strpos( $admin_js, 'Polish suggestions' ), 'Admin JavaScript labels the hosted AI draft-support result modes.' );
toolbox_assert( false !== strpos( $admin_js, 'renderHostedAiSiteHelper' ) && false !== strpos( $admin_js, "payload.artifact_type === 'hosted_ai_site_helper'" ), 'Admin JavaScript renders AI site-helper suggestions through a dedicated view.' );
toolbox_assert( false !== strpos( $admin_js, 'Media ALT suggestions' ) && false !== strpos( $admin_js, 'Content snapshot suggestions' ) && false !== strpos( $admin_js, 'No media or WordPress content was changed' ), 'Admin JavaScript labels AI site-helper modes and keeps write posture visible.' );
toolbox_assert( false !== strpos( $admin_js, 'renderHostedAiQualityGuardrails' ) && false !== strpos( $admin_js, 'Review checklist' ) && false !== strpos( $admin_js, 'Expected output shape' ), 'Admin JavaScript renders AI quality guardrails without adding a heavier workflow.' );
toolbox_assert( false !== strpos( $admin_js, 'toolboxAdminUrl' ) && false !== strpos( $admin_js, 'Open Cloud Search test' ) && false !== strpos( $admin_js, 'Live Cloud web search verification belongs in Cloud Checks' ), 'Article planning bundle points live Cloud search verification to Cloud Checks instead of rendering it as bundle content.' );
toolbox_assert( false !== strpos( $admin_js, 'createRawDetails' ) && false !== strpos( $admin_js, 'Complete payload' ), 'Complete payload output is moved behind a result disclosure.' );
toolbox_assert( false !== strpos( $admin_js, 'Provider raw response' ), 'Provider raw responses are rendered only as disclosure details.' );
toolbox_assert( false !== strpos( $admin_js, 'Download tracking' ) && false !== strpos( $admin_js, 'Attribution metadata' ), 'Image candidate rendering preserves Unsplash attribution and download tracking metadata.' );
toolbox_assert( false !== strpos( $admin_js, 'Governed handoff' ) && false !== strpos( $admin_js, 'Core proposal required' ), 'Workflow result rendering keeps governed handoff guidance visible.' );
toolbox_assert( false !== strpos( $admin_js, 'renderArticlePlan' ) && false !== strpos( $admin_js, "payload.artifact_type === 'article_write_plan'" ), 'Admin JavaScript renders article write plans through a dedicated view.' );
toolbox_assert( false !== strpos( $admin_js, 'renderArticleAssistant' ) && false !== strpos( $admin_js, "payload.artifact_type === 'article_assistant_workbench'" ), 'Admin JavaScript renders article assistant workbench artifacts through a dedicated view.' );
toolbox_assert( false !== strpos( $admin_js, 'Write plan' ) && false !== strpos( $admin_js, 'Local workbench' ), 'Article Assistant renderer shows local workbench and write-plan readiness.' );
toolbox_assert( false !== strpos( $admin_js, 'Goal brief' ) && false !== strpos( $admin_js, 'Risk report' ) && false !== strpos( $admin_js, 'Final ability' ), 'Article write plan renderer shows artifacts, risk, and final ability summary.' );
toolbox_assert( false !== strpos( $admin_js, 'renderMediaDerivativeHandoff' ) && false !== strpos( $admin_js, "payload.artifact_type === 'media_derivative_handoff'" ), 'Admin JavaScript renders media derivative handoffs through a dedicated view.' );
toolbox_assert( false !== strpos( $admin_js, 'mediaDetailsInput' ) && false !== strpos( $admin_js, 'media_details_input' ) && false !== strpos( $admin_js, "'proposals/from-plan'" ), 'Admin JavaScript submits single-image optimization through Adapter from-plan with reviewed metadata.' );
toolbox_assert( false !== strpos( $admin_js, 'Optimization plan is ready for one Core proposal approval' ) && false !== strpos( $admin_js, 'Do not split this optimization into two proposals' ), 'Admin JavaScript surfaces media optimization readiness and version guard copy.' );
toolbox_assert( false !== strpos( $admin_js, 'renderImageCandidateAdoptionPlan' ) && false !== strpos( $admin_js, "payload.artifact_type === 'image_candidate_adoption_plan'" ), 'Admin JavaScript renders image candidate adoption plans through a dedicated view.' );
toolbox_assert( false !== strpos( $admin_js, 'Image import proposal plan' ) && false !== strpos( $admin_js, 'License or source review is required before approval.' ), 'Image candidate adoption renderer keeps next steps and review status visible.' );
toolbox_assert( false !== strpos( $admin_js, 'renderImageSourceCandidates' ) && false !== strpos( $admin_js, "payload.artifact_type === 'image_source_candidates'" ) && false !== strpos( $admin_js, 'Resolved provider' ) && false !== strpos( $admin_js, 'Auto strategy' ), 'Admin JavaScript renders Cloud image-source candidates with auto provider evidence.' );
toolbox_assert( false !== strpos( $admin_js, 'Suggested filename' ) && false !== strpos( $admin_js, 'Cloud returned image candidates only' ), 'Image-source renderer shows filename evidence and no-write guidance.' );
toolbox_assert( false !== strpos( $admin_js, 'appendAiImageGenerationHandoff' ) && false !== strpos( $admin_js, 'Reviewed prompt' ) && false !== strpos( $admin_js, "postJson(config.restUrl, 'ai/image-generation'" ), 'Admin JavaScript renders reviewed-prompt AI image generation from image-source handoff.' );
toolbox_assert( false !== strpos( $admin_js, 'Generated images are candidates only' ) && false !== strpos( $admin_js, 'Use Adopt New Image and Core review' ), 'Admin JavaScript keeps AI-generated images candidate-only before local adoption.' );
toolbox_assert( false !== strpos( $admin_js, 'One-run planning artifact' ) && false !== strpos( $admin_js, 'Core policy' ), 'Media derivative renderer keeps one-run Core policy handoff visible.' );
toolbox_assert( false !== strpos( $admin_js, 'initMediaDerivativeControls' ) && false !== strpos( $admin_js, 'runMediaDerivative' ) && false !== strpos( $admin_js, 'submitMediaDerivativeProposal' ) && false !== strpos( $admin_js, 'submitMediaReferenceRepairProposal' ) && false !== strpos( $admin_js, 'submitMediaSettingsReferenceRepairProposal' ), 'Admin JavaScript runs the media derivative preview, replacement proposal, post reference repair, and settings reference repair proposal flows through Adapter routes.' );
toolbox_assert( false !== strpos( $admin_js, 'data-toolbox-media-derivative-preview-only' ) && false !== strpos( $admin_js, 'createMediaDerivativePreview(input, mediaDetails, previewOnly)' ) && false !== strpos( $admin_js, 'This check does not submit a Core proposal or write media.' ), 'Admin JavaScript keeps Cloud Check media derivative previews proposal-free.' );
toolbox_assert( false !== strpos( $admin_js, 'media-derivative-runs' ) && false !== strpos( $admin_js, 'media-derivative-proposal-payload' ) && false !== strpos( $admin_js, "ability_id: 'npcink-abilities-toolkit/adopt-cloud-media-derivative'" ), 'Media derivative preview uses Adapter recipe routes and submits a governed local replacement proposal.' );
toolbox_assert( false !== strpos( $admin_js, 'resolveMediaAttachmentUrl' ) && false !== strpos( $admin_js, "ability_id: 'npcink-abilities-toolkit/resolve-media-attachment-by-url'" ) && false !== strpos( $admin_js, 'data-toolbox-use-media-resolution-candidate' ), 'Media derivative URL resolution calls the local read-only resolver ability and lets the operator choose a candidate.' );
toolbox_assert( false !== strpos( $admin_js, 'buildMediaDerivativeBatchPlan' ) && false !== strpos( $admin_js, "ability_id: 'npcink-abilities-toolkit/build-media-derivative-batch-plan'" ) && false !== strpos( $admin_js, 'runMediaDerivativeBatchPreviews' ) && false !== strpos( $admin_js, 'submitMediaDerivativeBatchProposals' ), 'Media derivative batch flow builds a read-only plan, previews selected candidates, and submits selected Core proposals.' );
toolbox_assert( false !== strpos( $admin_js, 'resolveMediaBatchScopePreset' ) && false !== strpos( $admin_js, 'resolveMediaBatchRecipeDefaults' ) && false !== strpos( $admin_js, 'current_month' ) && false !== strpos( $admin_js, 'smart_optimize' ), 'Media derivative batch flow derives Adapter inputs from fixed scope and processing-goal presets.' );
toolbox_assert( false !== strpos( $admin_js, 'syncMediaBatchFixedFlow' ) && false !== strpos( $admin_js, "recipeField.value === 'resize_only'" ) && false !== strpos( $admin_js, "scopeField.value === 'custom'" ), 'Media derivative batch flow keeps visible controls aligned with fixed presets.' );
toolbox_assert( false !== strpos( $admin_js, 'mediaDerivativeWatermarkInput' ) && false !== strpos( $admin_js, 'watermark_enabled: false' ) && false !== strpos( $admin_js, 'Object.assign({}, candidate.cloud_request_input || {}, watermarkInput)' ), 'Media derivative preview and batch flows preserve Core default watermarks and support explicit one-run watermark overrides.' );
toolbox_assert( false !== strpos( $admin_js, "type: 'text'" ) && false !== strpos( $admin_js, 'watermark_text' ) && false !== strpos( $admin_js, 'font_size' ) && false !== strpos( $admin_js, 'Text "' ) && false !== strpos( $admin_js, 'Image logo' ), 'Media derivative JavaScript builds and labels both text and image watermark payloads.' );
toolbox_assert( false !== strpos( $admin_js, 'cloud_media_derivative_watermark_source_missing' ) && false !== strpos( $admin_js, 'Switch this run to Text watermark' ), 'Media derivative errors give actionable watermark mode guidance.' );
toolbox_assert( false !== strpos( $admin_js, "ability_id: 'npcink-abilities-toolkit/build-media-reference-repair-plan'" ) && false !== strpos( $admin_js, "'proposals/from-plan'" ) && false !== strpos( $admin_js, 'patch-post-content actions' ), 'Media reference repair flow builds a read-only plan and submits it to Core from-plan intake.' );
toolbox_assert( false !== strpos( $admin_js, "ability_id: 'npcink-abilities-toolkit/build-media-settings-reference-repair-plan'" ) && false !== strpos( $admin_js, 'patch-setting-value actions' ) && false !== strpos( $admin_js, 'excluded_formats' ), 'Media settings reference repair flow builds a filtered read-only plan and submits it to Core from-plan intake.' );
toolbox_assert( false !== strpos( $admin_js, 'derivative_artifact' ) && false !== strpos( $admin_js, 'npcink-cloud-backup' ), 'Media derivative replacement proposal carries artifact evidence and backup intent.' );
toolbox_assert( false !== strpos( $admin_js, 'withRestNonce' ) && false !== strpos( $admin_js, 'derivative.preview_url' ) && false !== strpos( $admin_js, 'Same-origin signed preview proxy' ), 'Media derivative preview renders the local signed Adapter proxy URL with a REST nonce.' );
toolbox_assert( false !== strpos( $admin_js, 'renderOperatorFeedback' ) && false !== strpos( $admin_js, 'operator_feedback' ), 'Admin JavaScript renders operator feedback from governed handoffs.' );
toolbox_assert( false !== strpos( $admin_js, 'formatErrorMessage' ) && false !== strpos( $admin_js, 'renderErrorResult' ) && false !== strpos( $admin_js, 'Error payload' ), 'Admin JavaScript renders nested REST and Cloud errors without collapsing them to Array.' );
toolbox_assert( false !== strpos( $admin_js, 'can_retry_after_revision' ) && false !== strpos( $admin_js, 'core_evidence' ), 'Operator feedback renderer shows retry state and Core evidence.' );
toolbox_assert( false !== strpos( $admin_js, 'Revise fields' ) && false !== strpos( $admin_js, 'Next steps' ), 'Operator feedback renderer shows revision fields and next steps.' );
toolbox_assert( false === strpos( $admin_js, 'result.textContent = JSON.stringify(value, null, 2)' ), 'Tool results do not default to raw JSON in the main result surface.' );
toolbox_assert( false !== strpos( $admin_js, 'initContextDrafts' ) && false !== strpos( $admin_js, 'applyContextDraft' ), 'Admin JavaScript can prefill editable content context drafts.' );
toolbox_assert( false !== strpos( $admin_js, 'clearContextForm' ), 'Admin JavaScript can clear the content context form before a new draft.' );
toolbox_assert( false !== strpos( $admin_js, 'initSiteKnowledge' ) && false !== strpos( $admin_js, 'site-knowledge/sync' ) && false !== strpos( $admin_js, 'site-knowledge/status' ), 'Admin JavaScript runs Site Knowledge status and sync actions.' );
toolbox_assert( false !== strpos( $admin_js, 'Truncated documents' ) && false !== strpos( $admin_js, 'coverage.truncated_documents' ) && false !== strpos( $admin_js, 'sync.truncated_documents' ), 'Admin JavaScript surfaces Cloud Site Knowledge truncation status.' );
toolbox_assert( false !== strpos( $admin_js, 'appendHighlightedText' ) && false !== strpos( $admin_js, 'item.match_context' ) && false !== strpos( $admin_js, "'Exact hits'" ) && false !== strpos( $admin_js, "el('mark'" ), 'Admin JavaScript highlights exact Site Knowledge query matches and labels semantic matches.' );
toolbox_assert( false !== strpos( $admin_js, 'exactResults.length ? exactResults : results' ) && false !== strpos( $admin_js, 'semantic-only result' ), 'Admin JavaScript hides semantic-only Site Knowledge rows from the main exact-match result list.' );
toolbox_assert( false !== strpos( $admin_js, 'payload.rerank' ) && false !== strpos( $admin_js, "'Rerank provider'" ) && false !== strpos( $admin_js, "'Rerank model'" ) && false !== strpos( $admin_js, 'Cloud rerank failed; vector order was used as the fallback.' ), 'Admin JavaScript surfaces Cloud Site Knowledge rerank status without local provider configuration.' );
toolbox_assert( false !== strpos( $admin_js, 'setSiteKnowledgeSyncBusy' ) && false !== strpos( $admin_js, 'pollSiteKnowledgeStatus' ) && false !== strpos( $admin_js, 'Sync queued...' ), 'Admin JavaScript disables duplicate Site Knowledge sync submissions and polls status.' );
toolbox_assert( false !== strpos( $admin_js, 'updateSiteKnowledgeActionState' ) && false !== strpos( $admin_js, 'indexState' ), 'Admin JavaScript updates the Site Knowledge indexing action from start to refresh after coverage exists.' );
toolbox_assert( false !== strpos( $admin_js, "modeInput.value = hasIndex ? 'rebuild' : 'refresh'" ), 'Admin JavaScript maps the simple Refresh index action to a Cloud rebuild when an index already exists.' );
toolbox_assert( false !== strpos( $admin_js, 'progress.message' ) && false !== strpos( $admin_js, 'Active run' ) && false !== strpos( $admin_js, 'Indexing...' ), 'Admin JavaScript renders Site Knowledge progress and disables indexing while Cloud is active.' );
toolbox_assert( false !== strpos( $admin_js, 'payload.evidence_gate' ) && false !== strpos( $admin_js, 'payload.message' ), 'Admin JavaScript renders Site Knowledge evidence state and active-run guidance.' );
toolbox_assert( false !== strpos( $admin_js, 'payload.handoff || payload.agent_handoff' ) && false !== strpos( $admin_js, 'Agent proposal input' ) && false !== strpos( $admin_js, 'Proposal candidate only' ), 'Admin JavaScript renders Cloud Site Knowledge agent handoff as a local Core proposal candidate.' );
toolbox_assert( false !== strpos( $admin_js, 'Prepare local proposal candidate' ) && false !== strpos( $admin_js, 'site_knowledge_core_proposal_candidate' ) && false !== strpos( $admin_js, "core_submission: 'not_submitted'" ), 'Admin JavaScript prepares a local Site Knowledge proposal candidate packet without Core submission.' );
toolbox_assert( false !== strpos( $admin_js, 'Submit Core review proposal' ) && false !== strpos( $admin_js, "'flows/site-knowledge-review-plan'" ) && false !== strpos( $admin_js, "'npcink-toolbox/build-site-knowledge-review-plan'" ) && false !== strpos( $admin_js, "'proposals/from-plan'" ), 'Admin JavaScript submits Site Knowledge review plans through Adapter/Core from-plan intake.' );
toolbox_assert( false !== strpos( $admin_js, 'renderSiteKnowledgeAutoSync' ) && false !== strpos( $admin_js, 'Server cron suggestion' ) && false !== strpos( $admin_js, 'WP-Cron disabled' ), 'Admin JavaScript renders Site Knowledge auto-sync queue health and cron guidance.' );

$development_workflow = file_get_contents( $root . '/docs/development-workflow.md' );
toolbox_assert( false !== strpos( $development_workflow, 'WordPress site timezone' ) && false !== strpos( $development_workflow, 'Y-m-d H:i:s' ) && false !== strpos( $development_workflow, 'Keep machine timestamps unchanged' ), 'Development workflow documents the wp-admin time display standard.' );

$editor_support = file_get_contents( $root . '/includes/Editor_Content_Support.php' );
toolbox_assert( false !== strpos( $editor_support, 'assets/editor-content-support.js' ) && false !== strpos( $editor_support, 'assets/editor-content-support.css' ), 'Post editor content support enqueues its editor assets.' );
toolbox_assert( false !== strpos( $editor_support, "'wp-block-editor'" ), 'Post editor content support declares the block editor dependency for selected-block toolbar controls.' );
toolbox_assert( false !== strpos( $editor_support, "'wp-hooks'" ), 'Post editor content support declares the hooks dependency for BlockEdit toolbar registration.' );
toolbox_assert( false !== strpos( $editor_support, 'asset_version' ) && false !== strpos( $editor_support, 'filemtime' ), 'Post editor content support cache-busts editor assets during active development.' );
toolbox_assert( false !== strpos( $editor_support, 'NpcinkToolboxEditorSupport' ) && false !== strpos( $editor_support, "wp_create_nonce( 'wp_rest' )" ) && false !== strpos( $editor_support, "'coreRestUrl'" ), 'Post editor content support localizes REST configuration, Core route hints, and nonce.' );
toolbox_assert( false !== strpos( $editor_support, "wp_set_script_translations(\n\t\t\t'npcink-toolbox-editor-content-support'" ) && false !== strpos( $editor_support, "NPCINK_TOOLBOX_DIR . 'languages'" ), 'Post editor content support registers the Toolbox script translation path.' );

$zh_cn_po = file_get_contents( $root . '/languages/npcink-toolbox-zh_CN.po' );
toolbox_assert( false !== $zh_cn_po && false !== strpos( $zh_cn_po, 'Language: zh_CN' ) && false !== strpos( $zh_cn_po, 'msgstr "Npcink 工具箱"' ), 'Bundled zh_CN translation file includes the Chinese display name.' );
toolbox_assert( file_exists( $root . '/languages/npcink-toolbox-zh_CN.mo' ), 'Bundled zh_CN machine object file is present.' );
$editor_support_json = file_get_contents( $root . '/languages/npcink-toolbox-zh_CN-npcink-toolbox-editor-content-support.json' );
toolbox_assert( false !== $editor_support_json && null !== json_decode( $editor_support_json, true ) && false !== strpos( $editor_support_json, '"Publish preflight": ["发布预检"]' ) && false !== strpos( $editor_support_json, '"Writing preparation": ["写作准备"]' ), 'Bundled zh_CN editor script translation JSON is valid and covers fixed-flow labels.' );
$admin_json = file_get_contents( $root . '/languages/npcink-toolbox-zh_CN-npcink-toolbox-admin.json' );
toolbox_assert( false !== $admin_json && null !== json_decode( $admin_json, true ) && false !== strpos( $admin_json, '"Revise fields: ": ["需修订字段："]' ), 'Bundled zh_CN admin script translation JSON is valid and covers operator feedback copy.' );
toolbox_assert( false !== strpos( $admin_json, '"Cloud returned image candidates only. Media import still requires an Adopt New Image plan and Core approval."' ) && false !== strpos( $admin_json, '"Submitting Core proposal "' ), 'Bundled zh_CN admin script translation JSON covers runtime result and Core handoff progress copy.' );

$auto_sync = file_get_contents( $root . '/includes/Site_Knowledge_Auto_Sync.php' );
toolbox_assert( false !== strpos( $auto_sync, 'transition_post_status' ) && false !== strpos( $auto_sync, "add_action( 'save_post'" ), 'Site Knowledge auto-sync watches allow-listed public content publish and update events.' );
toolbox_assert( false !== strpos( $auto_sync, 'trashed_post' ) && false !== strpos( $auto_sync, 'before_delete_post' ), 'Site Knowledge auto-sync watches public post/page removal events.' );
toolbox_assert( false !== strpos( $auto_sync, 'transition_comment_status' ) && false !== strpos( $auto_sync, 'comment_post' ) && false !== strpos( $auto_sync, 'edit_comment' ), 'Site Knowledge auto-sync watches approved comment publish and edit events.' );
toolbox_assert( false !== strpos( $auto_sync, 'trashed_comment' ) && false !== strpos( $auto_sync, 'deleted_comment' ), 'Site Knowledge auto-sync watches approved comment removal events.' );
toolbox_assert( false !== strpos( $auto_sync, 'request_site_knowledge_sync' ) && false !== strpos( $auto_sync, "'sync_mode' => 'refresh'" ) && false !== strpos( $auto_sync, "'post_ids'  =>" ) && false !== strpos( $auto_sync, 'array_slice( $post_ids' ), 'Site Knowledge auto-sync uses Cloud refresh for affected post IDs.' );
toolbox_assert( false !== strpos( $auto_sync, 'wp_schedule_single_event' ) && false !== strpos( $auto_sync, 'npcink_toolbox_site_knowledge_auto_sync_queue' ), 'Site Knowledge auto-sync queues debounced background work instead of blocking content actions.' );
toolbox_assert( false !== strpos( $auto_sync, 'wp_schedule_event' ) && false !== strpos( $auto_sync, "'daily'" ) && false !== strpos( $auto_sync, 'queue_recent_public_content' ), 'Site Knowledge auto-sync runs a low-frequency reconciliation safety net.' );
toolbox_assert( false !== strpos( $auto_sync, 'MAX_RETRY_ATTEMPTS' ) && false !== strpos( $auto_sync, 'retry_or_drop_queue' ), 'Site Knowledge auto-sync limits background retries when Cloud is unavailable.' );
toolbox_assert( false !== strpos( $auto_sync, 'DEFAULT_POST_TYPES' ) && false !== strpos( $auto_sync, 'npcink_toolbox_site_knowledge_post_types' ) && false !== strpos( $auto_sync, "'attachment' !== \$post_type" ), 'Site Knowledge auto-sync uses an explicit allow-list and excludes attachments by default.' );
toolbox_assert( false !== strpos( $auto_sync, 'health_snapshot' ) && false !== strpos( $auto_sync, 'DISABLE_WP_CRON' ) && false !== strpos( $auto_sync, 'cron_command' ), 'Site Knowledge auto-sync exposes local queue health and server cron guidance.' );

$editor_js = file_get_contents( $root . '/assets/editor-content-support.js' );
toolbox_assert( false !== strpos( $editor_js, 'PluginSidebar' ) && false !== strpos( $editor_js, 'npcink-content-support-sidebar' ), 'Editor JavaScript registers a Npcink Content Support plugin sidebar.' );
toolbox_assert( false === strpos( $editor_js, 'PluginDocumentSettingPanel' ) && false === strpos( $editor_js, 'openGeneralSidebar' ), 'Editor JavaScript does not add a duplicate document settings shortcut.' );
foreach ( array( 'writing_support', 'publish_preflight', 'summary_terms_optimization', 'taxonomy_tags', 'internal_links', 'image_candidates' ) as $editor_intent ) {
	toolbox_assert( false !== strpos( $editor_js, $editor_intent ), "Editor Content Support exposes fixed flow intent {$editor_intent}." );
}
toolbox_assert( false !== strpos( $editor_js, 'editor/content-support' ) && false !== strpos( $editor_js, 'getEditedPostAttribute' ), 'Editor Content Support posts current draft context to the fixed flow route.' );
toolbox_assert( false !== strpos( $editor_js, 'Suggestions only. Final writes require Core approval.' ), 'Editor Content Support preserves suggestion-only Core-governed copy.' );
toolbox_assert( false !== strpos( $editor_js, 'extractWritingSupportItems' ) && false !== strpos( $editor_js, 'No writing preparation evidence returned.' ), 'Editor Content Support renders writing preparation evidence as a compact suggestion list.' );
toolbox_assert( false !== strpos( $editor_js, 'renderSummaryOptimization' ) && false !== strpos( $editor_js, 'Category candidates' ) && false !== strpos( $editor_js, 'Tag candidates' ), 'Editor Content Support renders summary/category/tag optimization as grouped suggestion sections.' );
toolbox_assert( false !== strpos( $editor_js, 'Image source suggestions' ) && false !== strpos( $editor_js, 'openImageRecommendations' ), 'Editor Content Support opens image candidates in a source suggestion modal.' );
toolbox_assert( false !== strpos( $editor_js, "postJson('image-candidates'" ) && false !== strpos( $editor_js, 'Search image sources' ), 'Editor image recommendation modal supports manual cloud image-source search.' );
toolbox_assert( false !== strpos( $editor_js, 'renderImageCandidateCards' ) && false !== strpos( $editor_js, 'download_location' ), 'Editor image recommendation modal renders attribution-aware image candidate cards.' );
toolbox_assert( false !== strpos( $editor_js, 'npcink-toolbox-editor-support__image-workspace' ) && false !== strpos( $editor_js, 'npcink-toolbox-editor-support__image-inspector' ), 'Editor image recommendation modal uses a left candidate workspace and right selected-image inspector.' );
toolbox_assert( false !== strpos( $editor_js, 'selectedImageSeo' ) && false !== strpos( $editor_js, 'updateSelectedImageSeo' ) && false !== strpos( $editor_js, 'TextareaControl' ), 'Editor image inspector lets operators edit media SEO fields before adoption.' );
toolbox_assert( false !== strpos( $editor_js, "postJson('flows/image-candidate-adoption-plan'" ) && false !== strpos( $editor_js, 'Adopt as featured image' ), 'Editor image recommendation modal presents selected image adoption as one user action.' );
toolbox_assert( false !== strpos( $editor_js, 'Import media only' ) && false !== strpos( $editor_js, 'set_featured_image: Boolean(setFeaturedImage)' ), 'Editor image inspector supports importing media without setting it as the featured image.' );
toolbox_assert( false !== strpos( $editor_js, 'postAdapterAdoption' ) && false !== strpos( $editor_js, 'proposals/from-plan' ) && false !== strpos( $editor_js, 'approve-and-execute' ), 'Editor image adoption uses Adapter plan intake and unified approve-and-execute.' );
toolbox_assert( false === strpos( $editor_js, 'auto_approve_if_allowed' ) && false === strpos( $editor_js, 'execute_if_allowed' ), 'Editor image adoption does not send unsupported auto-execution flags to Core from-plan.' );
toolbox_assert( false !== strpos( $editor_js, 'Media SEO' ) && false !== strpos( $editor_js, 'buildImageSeoFields' ) && false !== strpos( $editor_js, 'set_featured_image: Boolean(setFeaturedImage)' ), 'Editor image adoption plan edits media SEO fields before Core handoff.' );
toolbox_assert( false !== strpos( $editor_js, 'syncFeaturedMediaFromCore' ) && false !== strpos( $editor_js, 'featured_media' ), 'Editor image adoption can sync the featured media field after Core execution returns an attachment id.' );
toolbox_assert( false !== strpos( $editor_js, 'renderImageDiagnostics' ) && false !== strpos( $editor_js, 'Cloud note' ) && false !== strpos( $editor_js, 'Received' ), 'Editor image recommendation modal surfaces Cloud image-source diagnostics for empty results.' );
toolbox_assert( false !== strpos( $editor_js, 'Cloud image search is unavailable.' ) && false !== strpos( $editor_js, 'Connect or verify Npcink Cloud Addon' ), 'Editor image recommendation modal distinguishes Cloud configuration errors from empty searches.' );
toolbox_assert( false !== strpos( $editor_js, 'formatImageErrorMessage' ) && false !== strpos( $editor_js, 'image-source.managed' ), 'Editor image recommendation modal turns Cloud image-source routing errors into actionable operator copy.' );
toolbox_assert( false !== strpos( $editor_js, 'hasDraftImageContext' ) && false !== strpos( $editor_js, 'manual search query' ), 'Editor image recommendation modal avoids draft-based Cloud calls for empty posts.' );
toolbox_assert( false !== strpos( $editor_js, 'selectedBlockText' ) && false !== strpos( $editor_js, 'browserSelectedText' ) && false !== strpos( $editor_js, 'Selected paragraph context' ), 'Editor image recommendation modal can use the selected paragraph as image recommendation context.' );
toolbox_assert( false !== strpos( $editor_js, 'BlockControls' ) && false !== strpos( $editor_js, 'ToolbarButton' ) && false !== strpos( $editor_js, 'dispatchParagraphImageRequest' ), 'Editor image recommendations expose a selected-block toolbar shortcut for paragraph images.' );
toolbox_assert( false !== strpos( $editor_js, "hooks.addFilter('editor.BlockEdit'" ) && false !== strpos( $editor_js, 'PARAGRAPH_IMAGE_EVENT' ), 'Editor paragraph image toolbar button is registered through the selected block edit context.' );
toolbox_assert( false !== strpos( $editor_js, "group: 'inline'" ) && false !== strpos( $editor_js, 'dashicons-format-image' ) && false !== strpos( $editor_js, 'showTooltip' ), 'Editor paragraph image toolbar button uses a visible image icon in the inline toolbar.' );
toolbox_assert( false !== strpos( $editor_js, 'imagePickerPresets' ) && false !== strpos( $editor_js, "adoptionMode: 'media_import'" ) && false !== strpos( $editor_js, "adoptionMode: 'select_only'" ), 'Editor image picker uses reusable mode presets for featured, paragraph, inline, and setting images.' );
toolbox_assert( false !== strpos( $editor_js, 'autoSearch: false' ) && false !== strpos( $editor_js, 'activePicker.autoSearch' ) && false !== strpos( $editor_js, 'setImageQuery(activePicker.initialQuery' ), 'Editor setting-image picker defaults to manual search while reusable callers can supply an initial query.' );
toolbox_assert( false !== strpos( $editor_js, 'NpcinkToolboxImageSourcePicker' ) && false !== strpos( $editor_js, 'IMAGE_SOURCE_PICKER_EVENT' ) && false !== strpos( $editor_js, 'IMAGE_SOURCE_PICKER_SELECTED_EVENT' ), 'Editor image picker exposes an event-based reuse contract for future image fields.' );
toolbox_assert( false !== strpos( $editor_js, 'dispatchSelectedImageToCaller' ) && false !== strpos( $editor_js, 'Toolbox does not write settings directly' ), 'Editor setting-image mode returns the selected image to callers without direct settings writes.' );
toolbox_assert( false !== strpos( $editor_js, 'buildImageVisualContext' ) && false !== strpos( $editor_js, 'visual_context: buildImageVisualContext' ) && false !== strpos( $editor_js, 'avoid_brand_logos: true' ), 'Editor image requests send bounded visual context for Cloud AI optimization.' );
toolbox_assert( false !== strpos( $editor_js, 'renderImageVisualBrief' ) && false !== strpos( $editor_js, 'Cloud visual brief' ) && false !== strpos( $editor_js, 'alternate_queries' ), 'Editor image modal renders Cloud visual brief summaries without raw payload clutter.' );
toolbox_assert( false !== strpos( $editor_js, 'seo_suggestions' ) && false !== strpos( $editor_js, 'match_reason' ) && false !== strpos( $editor_js, 'visual_keywords' ), 'Editor image modal consumes Cloud match reasons, media SEO suggestions, and visual keywords.' );
toolbox_assert( false !== strpos( $editor_js, 'IMAGE_RESULT_CACHE_TTL' ) && false !== strpos( $editor_js, 'IMAGE_RESULT_CACHE_MAX_ENTRIES' ) && false !== strpos( $editor_js, 'readCachedImageResult' ) && false !== strpos( $editor_js, 'writeCachedImageResult' ), 'Editor image picker caches recent modal results briefly with a bounded entry count to reduce duplicate Cloud calls.' );
toolbox_assert( false !== strpos( $editor_js, 'extractImageSearchSuggestions' ) && false !== strpos( $editor_js, 'fallbackImageSearchSuggestions' ) && false !== strpos( $editor_js, 'Try one of these shorter visual searches' ), 'Editor image picker offers shorter query suggestions for empty image-source results.' );
toolbox_assert( false !== strpos( $editor_js, 'quality_tags' ) && false !== strpos( $editor_js, 'risk_flags' ) && false !== strpos( $editor_js, 'imageCandidateTagValues' ), 'Editor image cards show concise quality and risk signals without expanding provider details by default.' );

$editor_css = file_get_contents( $root . '/assets/editor-content-support.css' );
toolbox_assert( false !== strpos( $editor_css, 'npcink-toolbox-editor-support__flow' ) && false !== strpos( $editor_css, 'npcink-toolbox-editor-support__result' ), 'Editor Content Support CSS styles fixed flow rows and result summaries.' );
toolbox_assert( false !== strpos( $editor_css, 'npcink-toolbox-editor-support__toolbar-icon' ) && false !== strpos( $editor_css, 'npcink-toolbox-editor-support__surface' ), 'Editor Content Support CSS styles the toolbar icon sidebar entry.' );
toolbox_assert( false !== strpos( $editor_css, 'npcink-toolbox-editor-support__block-toolbar-icon' ) && false !== strpos( $editor_css, 'npcink-toolbox-editor-support__block-toolbar-button' ), 'Editor Content Support CSS styles the paragraph image toolbar button.' );
toolbox_assert( false !== strpos( $editor_css, 'npcink-toolbox-editor-support__selected-image' ) && false !== strpos( $editor_css, 'npcink-toolbox-editor-support__image-details' ), 'Editor image recommendation CSS keeps cards compact and exposes selected-image adoption controls.' );
toolbox_assert( false !== strpos( $editor_css, 'npcink-toolbox-editor-support__image-modal' ) && false !== strpos( $editor_css, 'npcink-toolbox-editor-support__image-grid' ) && false !== strpos( $editor_css, 'aspect-ratio: 4 / 3' ), 'Editor Content Support CSS styles cloud image recommendation modal cards.' );
toolbox_assert( false !== strpos( $editor_css, 'npcink-toolbox-editor-support__query-chips button' ), 'Editor image recommendation CSS styles clickable query suggestion chips.' );
toolbox_assert( false !== strpos( $editor_css, 'npcink-toolbox-editor-support__image-workspace' ) && false !== strpos( $editor_css, 'grid-template-columns: minmax(0, 1fr) minmax(300px, 360px)' ), 'Editor image recommendation CSS lays out candidates beside the selected-image inspector.' );
toolbox_assert( false !== strpos( $editor_css, 'npcink-toolbox-editor-support__seo-fields' ) && false !== strpos( $editor_css, 'npcink-toolbox-editor-support__selected-actions' ), 'Editor image recommendation CSS separates editable SEO fields from adoption actions.' );
toolbox_assert( false !== strpos( $editor_css, 'npcink-toolbox-editor-support__selection-context' ), 'Editor image recommendation CSS styles selected paragraph context.' );
toolbox_assert( false !== strpos( $editor_css, 'npcink-toolbox-editor-support__visual-brief' ) && false !== strpos( $editor_css, 'npcink-toolbox-editor-support__query-chips' ), 'Editor image recommendation CSS keeps Cloud visual brief output compact.' );
toolbox_assert( false !== strpos( $editor_css, 'npcink-toolbox-editor-support__match-reason' ) && false !== strpos( $editor_css, '-webkit-line-clamp: 2' ), 'Editor image recommendation CSS clamps Cloud match reasons in image cards.' );

$editor_entrypoint = file_get_contents( $root . '/includes/Editor_Content_Support.php' );
toolbox_assert( false !== strpos( $editor_entrypoint, 'adapterRestUrl' ) && false !== strpos( $editor_entrypoint, 'npcink-openclaw-adapter/v1' ), 'Editor Content Support localizes the Adapter REST base for governed adoption execution.' );
toolbox_assert( false !== strpos( $editor_css, 'npcink-toolbox-editor-support__diagnostics' ), 'Editor Content Support CSS styles Cloud image-source diagnostics.' );

$admin_css = file_get_contents( $root . '/assets/admin.css' );
toolbox_assert( false !== strpos( $admin_css, '.npcink-toolbox__usage-list' ), 'Admin CSS styles Site Knowledge usage scenarios as compact utility rows.' );
toolbox_assert( false !== strpos( $admin_css, '.npcink-toolbox__tool-group-label' ), 'Admin CSS styles grouped Content Support tool labels.' );
toolbox_assert( false !== strpos( $admin_css, 'npcink-toolbox__result-summary' ), 'Admin CSS styles summary-first result panels.' );
toolbox_assert( false !== strpos( $admin_css, 'npcink-toolbox__result-details' ), 'Admin CSS styles collapsed result detail disclosures.' );
toolbox_assert( false !== strpos( $admin_css, 'npcink-toolbox__image-preview' ), 'Admin CSS styles adoption result image previews.' );
toolbox_assert( false !== strpos( $admin_css, 'npcink-toolbox__image-thumb' ), 'Admin CSS supports browser image-source previews.' );
toolbox_assert( false !== strpos( $admin_css, 'npcink-toolbox__cloud-check-tabs' ) && false !== strpos( $admin_css, 'npcink-toolbox__cloud-check-tab.is-active' ), 'Admin CSS styles Cloud Check sub tabs.' );
toolbox_assert( false !== strpos( $admin_css, 'npcink-toolbox__cloud-check-group-workspace' ) && false !== strpos( $admin_css, 'npcink-toolbox__cloud-check-group-button.is-active' ), 'Admin CSS styles Cloud Check detail groups like Context detail groups.' );
toolbox_assert( false === strpos( $admin_css, 'npcink-toolbox__connector-provider-workspace' ) && false === strpos( $admin_css, 'npcink-toolbox__connector-provider-button.is-active' ), 'Admin CSS removes dead connector provider rail styles.' );
toolbox_assert( false !== strpos( $admin_css, 'npcink-toolbox__inline-form > .button' ) && false !== strpos( $admin_css, 'margin-top: 16px' ), 'Admin CSS keeps inline form buttons separated from preceding fields.' );
toolbox_assert( false !== strpos( $admin_css, 'npcink-toolbox__context-tabs' ) && false !== strpos( $admin_css, 'npcink-toolbox__context-group-workspace' ), 'Admin CSS styles content context tabs and field rail.' );
toolbox_assert( false !== strpos( $admin_css, 'npcink-toolbox__media-picker' ) && false !== strpos( $admin_css, 'npcink-toolbox__inline-actions' ) && false !== strpos( $admin_css, 'npcink-toolbox__derivative-preview' ), 'Admin CSS supports media derivative picker, action controls, and derivative preview image.' );
toolbox_assert( false !== strpos( $admin_css, 'npcink-toolbox__url-resolution' ), 'Admin CSS supports media URL resolution evidence.' );
toolbox_assert( false !== strpos( $admin_css, 'npcink-toolbox__batch-panel' ) && false !== strpos( $admin_css, 'npcink-toolbox__batch-row' ), 'Admin CSS supports media derivative batch planning rows.' );

$plugin = file_get_contents( $root . '/includes/Plugin.php' );
toolbox_assert( false !== strpos( $plugin, 'register_with_npcink_abilities_toolkit' ) && false !== strpos( $plugin, "'wp_abilities_api_categories_init'" ) && false !== strpos( $plugin, ', 1 );' ), 'Helper ability registration is deferred to the Abilities API category hook.' );
toolbox_assert( false === strpos( $plugin, '$this->abilities->register_with_npcink_abilities_toolkit();' ), 'Helper ability registration is not executed during plugin hook setup.' );
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
	'/ai/content-support',
	'/ai/site-helpers',
	'/ai/image-generation',
	'/flows/article-brief',
	'/flows/article-assistant',
	'/flows/article-plan',
	'/flows/image-candidate-adoption-plan',
	'/flows/site-knowledge-review-plan',
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
toolbox_assert( false !== strpos( $rest, 'editor_image_support_query' ) && false !== strpos( $rest, 'digital marketing workspace analytics' ), 'REST editor image candidates use a short visual image-source query instead of the full support query.' );
toolbox_assert( false !== strpos( $rest, "'seo'       => 'search engine optimization'" ) && false !== strpos( $rest, "'ai'        => 'artificial intelligence'" ), 'REST editor image query maps abstract SEO/AEO/GEO/AI topics to image-source search terms.' );
toolbox_assert( false !== strpos( $rest, 'selected_text' ) && false !== strpos( $rest, 'selected_block_text' ) && false !== strpos( $rest, '$selection' ), 'REST editor image query prefers selected paragraph text while retaining article context.' );
toolbox_assert( false !== strpos( $rest, 'image_visual_context_from_request' ) && false !== strpos( $rest, 'editor_image_visual_context' ) && false !== strpos( $rest, 'sanitize_image_visual_context' ), 'REST image routes normalize bounded visual context for Cloud image search.' );
toolbox_assert( false !== strpos( $rest, "'image_mode'          => \$mode" ) && false !== strpos( $rest, "'avoid_brand_logos'   => ! empty" ), 'REST visual context preserves image use and editorial-safe constraints.' );
toolbox_assert( false !== strpos( $rest, "'setting_image'" ) && false !== strpos( $rest, "'setting' === \$mode" ), 'REST visual context accepts setting-image usage without adding a write route.' );
foreach ( array( 'writing_support', 'summary_terms_optimization', 'taxonomy_tags', 'internal_links', 'image_candidates', 'publish_preflight' ) as $editor_rest_intent ) {
	toolbox_assert( false !== strpos( $rest, "'{$editor_rest_intent}'" ), "REST editor content support accepts fixed intent {$editor_rest_intent}." );
}
toolbox_assert( false !== strpos( $rest, "'intent'          => 'writing_support_plan'" ) && false !== strpos( $rest, "\$result['sections']['writing_support']" ), 'REST editor writing support calls Cloud Site Knowledge writing support plan without adding a route.' );
toolbox_assert( false !== strpos( $rest, "'article_discoverability_optimization.v1'" ) && false !== strpos( $rest, "'summary_taxonomy_tag_candidates'" ) && false !== strpos( $rest, "'intent'  => 'summary_terms_optimization'" ), 'REST editor summary/terms optimization returns a suggestion-only metadata artifact.' );
foreach ( array( 'publish', 'delivery', 'workflow-run', 'workflow_run', 'queue', 'scheduler', 'approval', 'approve', 'confirm', 'write', 'featured-image', 'media-upload', 'media-import', 'seo' ) as $forbidden_fragment ) {
	$has_forbidden_route = false;
	foreach ( $registered_rest_routes as $route ) {
		$has_forbidden_route = $has_forbidden_route || str_contains( $route, $forbidden_fragment );
	}
	toolbox_assert( ! $has_forbidden_route, "REST route matrix excludes forbidden route fragment {$forbidden_fragment}." );
}

$abilities = file_get_contents( $root . '/includes/Abilities.php' );
foreach ( array( 'npcink-toolbox/search-image-source', 'npcink-toolbox/vector-search', 'npcink-toolbox/search-site-knowledge', 'npcink-toolbox/get-site-knowledge-status', 'npcink-toolbox/request-site-knowledge-sync', 'npcink-toolbox/build-article-brief', 'npcink-toolbox/build-article-assistant', 'npcink-toolbox/build-article-write-plan', 'npcink-toolbox/build-image-candidate-adoption-plan', 'npcink-toolbox/build-site-knowledge-review-plan', 'npcink-toolbox/build-media-brief', 'npcink-toolbox/build-media-derivative-handoff', 'npcink-toolbox/get-content-discoverability-context', 'npcink-toolbox/validate-content-discoverability-context', 'npcink-toolbox/build-content-discoverability-brief', 'npcink-toolbox/build-ai-article-writing-pack' ) as $ability_id ) {
	toolbox_assert( false !== strpos( $abilities, $ability_id ), "Ability {$ability_id} is registered." );
}
toolbox_assert( false === strpos( $abilities, 'npcink-toolbox/web-research' ), 'Toolbox no longer registers a local web-research ability.' );

$client = file_get_contents( $root . '/includes/Provider_Client.php' );
$legacy_model_name = 'GPT-' . '5.5';
$legacy_model_slug = 'gpt' . '55';
$legacy_route_name = 'free-' . $legacy_model_slug;
$legacy_key_name   = 'free_' . $legacy_model_slug;
toolbox_assert( false === strpos( $admin_page . $admin_js . $rest . $client, $legacy_model_name ) && false === strpos( $admin_page . $admin_js . $rest . $client, $legacy_route_name ) && false === strpos( $admin_page . $admin_js . $rest . $client, $legacy_key_name ), 'Hosted AI surfaces avoid model-specific naming.' );
toolbox_assert( false === strpos( $client, 'https://api.tavily.com/search' ) && false === strpos( $client, 'search_bocha_web' ) && false === strpos( $client, 'enhance_results_with_jina_reader' ), 'Provider client no longer calls local web search providers.' );
toolbox_assert( false !== strpos( $client, 'test_cloud_web_search' ) && false !== strpos( $client, "'ability_name'        => 'npcink-cloud/web-search'" ), 'Provider client can test Cloud-managed web search through the Cloud runtime seam.' );
toolbox_assert( false === strpos( $client, 'enhance_with_reader' ) && false === strpos( $client, "array( 'auto', 'tavily', 'bocha', 'apify' )" ), 'Provider client Cloud search test does not expose Toolbox-side provider routing or Jina Reader toggles.' );
toolbox_assert( false !== strpos( $client, 'normalize_cloud_web_search_response' ) && false !== strpos( $client, "'web_search_results'" ), 'Provider client normalizes Cloud web search test output for operator review.' );
toolbox_assert( false !== strpos( $client, 'diagnose_automatic_web_search' ) && false !== strpos( $client, "'web_search_diagnostics'" ), 'Provider client can diagnose whether Toolbox workflows attach Cloud web search evidence.' );
toolbox_assert( false !== strpos( $client, "'usage_summary'" ) && false !== strpos( $client, "'provider_call_count'" ) && false !== strpos( $client, "'error_code'" ), 'Provider client returns Cloud search usage summary fields for diagnostics.' );
toolbox_assert( false !== strpos( $client, 'cloud_web_search_for_content' ) && false !== strpos( $client, "'external_research'      => \$external_research" ) && false !== strpos( $client, "'cloud_evidence'         => \$cloud_evidence" ), 'Article and discoverability flows can attach Cloud web search evidence without local provider keys.' );
toolbox_assert( false !== strpos( $client, 'cloud_web_search_evidence' ) && false !== strpos( $client, "'web_search' => array(" ) && false !== strpos( $client, 'cloud_managed_toolbox_content_search' ), 'Provider client promotes ready Cloud search results into cloud_evidence.web_search.' );
toolbox_assert( false !== strpos( $client, 'execute_image_source_cloud_request' ) && false !== strpos( $client, 'npcink_toolbox_image_source_cloud_request' ) && false !== strpos( $client, "'ability_name'        => 'magick-ai-toolbox/search-image-source'" ), 'Image candidates use a Cloud-managed image-source runtime seam.' );
toolbox_assert( false !== strpos( $client, "'execution_kind'      => 'image_source'" ) && false !== strpos( $client, "'profile_id'          => 'image-source.managed'" ), 'Image-source Cloud payload declares the managed image-source execution kind and profile.' );
toolbox_assert( false !== strpos( $client, "'execution_pattern'   => 'inline'" ), 'Image-source search uses inline Cloud execution for interactive candidate lookup.' );
toolbox_assert( false !== strpos( $client, "'policy'              => array(\n\t\t\t\t'allow_fallback' => true,\n\t\t\t)" ), 'Image-source runtime payload keeps Cloud policy limited to schema-supported fields.' );
toolbox_assert( false === strpos( $client, 'https://api.unsplash.com/search/photos' ) && false === strpos( $client, 'https://pixabay.com/api/' ) && false === strpos( $client, 'https://api.pexels.com/v1/search' ), 'Image candidates do not directly call public image provider APIs locally.' );
toolbox_assert( false !== strpos( $client, 'npcink_toolbox_ai_image_generation_request' ) && false !== strpos( $client, "'ai_generated'" ), 'Image candidates support an explicit AI-generated candidate runtime seam.' );
toolbox_assert( false !== strpos( $client, "'source_type'                   => 'ai_generated'" ) && false !== strpos( $client, "'requires_human_license_review' => true" ), 'AI-generated image candidates preserve source type and license review status.' );
toolbox_assert( false !== strpos( $client, 'normalize_image_candidate_contract' ) && false !== strpos( $client, "'candidate_contract_version' => 'image_candidate.v1'" ), 'Image candidates normalize to image_candidate.v1.' );
toolbox_assert( false !== strpos( $client, 'normalize_image_source_candidates_response' ) && false !== strpos( $client, '$result = $this->extract_cloud_runtime_result( $response );' ), 'Image candidates unwrap nested Cloud runtime result envelopes.' );
toolbox_assert( false !== strpos( $client, 'image_visual_context_input' ) && false !== strpos( $client, "'contract_version'       => 'image_visual_brief_request.v1'" ), 'Image-source Cloud payload accepts a bounded visual brief request contract.' );
toolbox_assert( false !== strpos( $client, "'cloud_ai_steps'         => array(" ) && false !== strpos( $client, "'site_context_vectors'" ) && false !== strpos( $client, "'candidate_rerank'" ), 'Image-source Cloud payload can request Cloud AI visual brief, site context vectors, and candidate rerank.' );
toolbox_assert( false !== strpos( $client, "'quality_filters'        => array(" ) && false !== strpos( $client, "'dedupe_similar_images'" ) && false !== strpos( $client, "'avoid_visible_watermarks'" ), 'Image-source Cloud payload asks Cloud to dedupe and quality-filter source candidates.' );
toolbox_assert( false !== strpos( $client, "'rights_requirements'    => array(" ) && false !== strpos( $client, "'preserve_download_location'" ) && false !== strpos( $client, "'return_license_review_status'" ), 'Image-source Cloud payload requires source, attribution, and license evidence preservation.' );
toolbox_assert( false !== strpos( $client, "'ui_contract'            => array(" ) && false !== strpos( $client, "'return_empty_query_suggestions'" ), 'Image-source Cloud payload requests UI-ready query suggestions and candidate signals.' );
toolbox_assert( false !== strpos( $client, "'setting_image'" ) && false !== strpos( $client, "'image_use'              => \$mode" ), 'Image-source Cloud payload carries setting-image usage as candidate context only.' );
toolbox_assert( false !== strpos( $client, 'extract_image_source_candidate_items' ) && false !== strpos( $client, "'results', 'items', 'photos'" ), 'Image candidates accept common Cloud/provider result list keys.' );
toolbox_assert( false !== strpos( $client, "\$candidate['urls']['regular']" ) && false !== strpos( $client, "\$candidate['links']['download_location']" ) && false !== strpos( $client, "\$candidate['user']['name']" ), 'Image candidates normalize common Unsplash-style raw candidate fields.' );
toolbox_assert( false !== strpos( $client, "'source_type']                   = \$source_type" ) && false !== strpos( $client, "'download_url']" ) && false !== strpos( $client, "'thumbnail_url']" ) && false !== strpos( $client, "'provider_origin']" ), 'Image candidate v1 output includes source type, download URL, thumbnail URL, and provider origin.' );
toolbox_assert( false !== strpos( $client, "'suggested_filename']" ) && false !== strpos( $client, "'filename_basis']" ) && false !== strpos( $client, "'final_sanitize_unique_required' => true" ), 'Image candidate v1 output carries a bounded filename suggestion for WordPress-side finalization.' );
toolbox_assert( false !== strpos( $client, "'match_reason']" ) && false !== strpos( $client, "'recommended_use']" ) && false !== strpos( $client, "'seo_suggestions']" ), 'Image candidate v1 output carries Cloud match reason, recommended use, and media SEO suggestions.' );
toolbox_assert( false !== strpos( $client, "'quality_tags']" ) && false !== strpos( $client, "'risk_flags']" ) && false !== strpos( $client, "'query_suggestions'" ), 'Image candidate v1 output and response summary carry quality/risk tags and query rewrite suggestions.' );
toolbox_assert( false !== strpos( $client, 'build_image_candidate_adoption_plan' ) && false !== strpos( $client, "'artifact_type'               => 'image_candidate_adoption_plan'" ), 'Provider client can build image candidate adoption plans.' );
toolbox_assert( false !== strpos( $client, "'target_ability_id' => 'npcink-abilities-toolkit/upload-media-from-url'" ) && false !== strpos( $client, "'target_ability_id' => 'npcink-abilities-toolkit/set-post-featured-image'" ), 'Image candidate adoption plan routes media import and featured image writes through Core-governed abilities.' );
toolbox_assert( false !== strpos( $client, "'proposed_filename'           => \$file_name" ) && false !== strpos( $client, "'filename_policy'             => \$filename_policy" ), 'Image candidate adoption plan previews the proposed filename without becoming the write owner.' );
toolbox_assert( false !== strpos( $client, 'sanitize_provider_error_data' ) && false !== strpos( $client, "'provider_status' => \$status" ), 'Image-source provider failures preserve safe diagnostic status data.' );
toolbox_assert( false === strpos( $client, '/points/query' ), 'Vector search no longer calls Qdrant query points locally.' );
toolbox_assert( false === strpos( $client, '/embeddings' ), 'Vector search no longer calls embedding endpoints locally.' );
toolbox_assert( false === strpos( $client, 'create_siliconflow_embedding' ) && false === strpos( $client, 'create_jina_embedding' ), 'Provider client no longer contains local embedding provider implementations.' );
toolbox_assert( false !== strpos( $client, 'Low-level vector provider configuration has moved to Npcink Cloud' ), 'Vector search returns a Cloud-managed compatibility pointer.' );
toolbox_assert( false !== strpos( $client, "'target_ability_id' => 'npcink-toolbox/search-site-knowledge'" ), 'Vector compatibility output points callers to Cloud-managed Site Knowledge.' );
toolbox_assert( false !== strpos( $client, 'provider_origin' ) && false !== strpos( $client, "'cloud_runtime'              => 'magick_ai_cloud_addon'" ), 'Cloud image-source responses preserve candidate provenance.' );
toolbox_assert( false !== strpos( $client, "'provider_mode'" ) && false !== strpos( $client, "'active_sources'" ) && false !== strpos( $client, "'resolved_provider'" ) && false !== strpos( $client, "'auto_strategy'" ), 'Image-source output records provider mode, resolved provider, strategy, and active sources.' );
toolbox_assert( false !== strpos( $client, 'normalize_image_visual_brief' ) && false !== strpos( $client, "'visual_brief'" ) && false !== strpos( $client, "'rerank_status'" ) && false !== strpos( $client, "'site_context_status'" ), 'Image-source output records Cloud visual brief, rerank status, and site-context status.' );
toolbox_assert( false !== strpos( $client, 'with_optional_raw' ), 'Provider raw responses are optional.' );
toolbox_assert( false !== strpos( $client, 'with_output_contract' ), 'Provider-backed outputs use a shared AI composition contract.' );
toolbox_assert( false !== strpos( $client, "'artifact_type'          => \$artifact_type" ), 'Shared output contract includes artifact type.' );
toolbox_assert( false !== strpos( $client, "'composition_role'       => \$composition_role" ), 'Shared output contract includes composition role.' );
toolbox_assert( false !== strpos( $client, "'direct_wordpress_write' => false" ), 'Shared output contract forbids direct WordPress writes.' );
toolbox_assert( false !== strpos( $client, 'cloud_web_search_notice' ) && false !== strpos( $client, "'provider_mode'  => 'cloud_managed'" ), 'Article helpers record Cloud-managed web search status without local provider execution.' );
toolbox_assert( false !== strpos( $client, "'image_source_candidates'" ), 'Image source output is classified as image-source candidates.' );
toolbox_assert( false !== strpos( $client, "'site_knowledge_context'" ), 'Vector compatibility output is classified as site knowledge context.' );
toolbox_assert( false !== strpos( $client, 'search_site_knowledge' ) && false !== strpos( $client, "'site_knowledge_search.v1'" ), 'Provider client exposes Cloud-managed site knowledge search.' );
toolbox_assert( false !== strpos( $client, "'magick-ai-cloud/site-knowledge-search'" ) && false !== strpos( $client, "'magick-ai-cloud/site-knowledge-status'" ) && false !== strpos( $client, "'magick-ai-cloud/site-knowledge-sync'" ), 'Provider client uses current Cloud Site Knowledge ability ids.' );
toolbox_assert( false === strpos( $client, "'npcink-cloud/site-knowledge-search'" ) && false === strpos( $client, "'npcink-cloud/site-knowledge-status'" ) && false === strpos( $client, "'npcink-cloud/site-knowledge-sync'" ), 'Provider client no longer sends legacy Site Knowledge ability ids.' );
toolbox_assert( false !== strpos( $client, "'faq_candidates'" ) && false !== strpos( $client, "'content_gap_analysis'" ) && false !== strpos( $client, "'duplicate_check'" ), 'Provider client allows high-value site knowledge search intents.' );
toolbox_assert( false !== strpos( $client, 'get_site_knowledge_status' ) && false !== strpos( $client, "'site_knowledge_status.v1'" ), 'Provider client exposes Cloud-managed site knowledge status.' );
toolbox_assert( false !== strpos( $client, 'request_site_knowledge_sync' ) && false !== strpos( $client, "'site_knowledge_sync.v1'" ), 'Provider client exposes Cloud-managed site knowledge sync requests.' );
toolbox_assert( false !== strpos( $client, 'npcink_toolbox_site_knowledge_cloud_request' ) && false !== strpos( $client, 'cloud_runtime_client' ) && false !== strpos( $client, 'npcink_cloud_addon_runtime_client' ) && false !== strpos( $client, 'magick_ai_cloud_addon_runtime_client' ), 'Site knowledge execution uses a host filter or Cloud Addon runtime compatibility seam.' );
toolbox_assert( false !== strpos( $client, "'ability_name'        => \$ability_name" ) && false !== strpos( $client, "'execution_pattern'   => \$execution_pattern" ), 'Site knowledge runtime payload preserves ability name and execution pattern.' );
toolbox_assert( false !== strpos( $client, 'collect_site_knowledge_documents' ) && false !== strpos( $client, "'post_status'    => 'publish'" ) && false !== strpos( $client, "'post_type'      => \$this->site_knowledge_post_types()" ), 'Site knowledge sync uses bounded public allow-listed WordPress content manifests.' );
toolbox_assert( false !== strpos( $client, 'npcink_toolbox_site_knowledge_post_types' ) && false !== strpos( $client, "'attachment' !== \$post_type" ), 'Provider client shares the explicit Site Knowledge content-type allow-list.' );
toolbox_assert( false !== strpos( $client, 'SITE_KNOWLEDGE_CONTENT_CHARS = 30000' ) && false !== strpos( $client, 'trim_site_knowledge_content' ) && false === strpos( $client, 'wp_trim_words( $content, 600' ), 'Site knowledge sync sends bounded public content to Cloud instead of a short word excerpt.' );
toolbox_assert( false !== strpos( $client, 'collect_site_knowledge_comments' ) && false !== strpos( $client, "'status'   => 'approve'" ) && false !== strpos( $client, "'type'     => 'comment'" ) && false !== strpos( $client, "'comment_status'  => 'approve'" ), 'Site knowledge sync includes bounded approved comment manifests.' );
toolbox_assert( false !== strpos( $client, 'extract_cloud_runtime_result' ) && false !== strpos( $client, "'result_json'" ), 'Provider client unwraps nested Cloud runtime result payloads.' );
toolbox_assert( false !== strpos( $client, 'is_cloud_concurrency_error' ) && false !== strpos( $client, 'site_knowledge_active_run_response' ), 'Provider client turns active Cloud run concurrency into Site Knowledge status guidance.' );
toolbox_assert( false !== strpos( $client, 'filter_current_public_site_knowledge_results' ) && false !== strpos( $client, "'publish' === get_post_status" ), 'Provider client filters stale Site Knowledge search results against current public WordPress status.' );
toolbox_assert( false !== strpos( $client, "'progress'" ) && false !== strpos( $client, "'active_run'" ), 'Provider client preserves Cloud Site Knowledge progress and active run status.' );
toolbox_assert( false !== strpos( $client, "'agent_handoff'" ) && false !== strpos( $client, 'site_knowledge_handoff_for_display' ) && false !== strpos( $client, "'proposal_input'" ), 'Provider client preserves Cloud Site Knowledge agent handoff as a local proposal candidate only.' );
toolbox_assert( false === strpos( $client, 'provider_body' ), 'Provider error responses do not expose raw provider bodies.' );
toolbox_assert( false !== strpos( $client, "'write_posture' => 'suggestion_only'" ), 'Article brief handoff stays suggestion-only.' );
toolbox_assert( false !== strpos( $client, 'Create WordPress draft or media proposals through Abilities/Core.' ), 'Article brief handoff points write-like actions to Abilities/Core.' );
toolbox_assert( false !== strpos( $client, 'build_article_assistant' ), 'Provider client can build local article assistant workbench artifacts.' );
toolbox_assert( false !== strpos( $client, "'artifact_type'          => 'article_assistant_workbench'" ), 'Article Assistant declares the workbench artifact type.' );
toolbox_assert( false !== strpos( $client, "'workflow_runtime'       => false" ) && false !== strpos( $client, "'batch_execution'        => false" ), 'Article Assistant explicitly avoids workflow runtime and batch execution ownership.' );
toolbox_assert( false !== strpos( $client, "'assistant_ability_id'   => 'npcink-toolbox/build-article-assistant'" ), 'Article Assistant handoff carries its ability id.' );
toolbox_assert( false !== strpos( $client, 'build_article_write_plan' ), 'Provider client can build Core-ready article write plans.' );
toolbox_assert( false !== strpos( $client, "'artifact_type'          => 'article_write_plan'" ), 'Article write plan declares the Core contract artifact type.' );
toolbox_assert( false !== strpos( $client, "'composition_role'       => 'core_article_write_plan'" ), 'Article write plan declares its composition role.' );
toolbox_assert( false !== strpos( $client, "'source_recipe_id'       => 'article_draft_v1'" ) && false !== strpos( $client, "'source_recipe_ref'      => 'workflow/wordpress_article_draft'" ), 'Article write plan is explicitly tied to the local article_draft_v1 Ability recipe.' );
toolbox_assert( false !== strpos( $client, "'target_ability_id' => 'npcink-abilities-toolkit/create-draft'" ), 'Article write plan targets the governed create-draft ability.' );
toolbox_assert( false !== strpos( $client, "'recipe_step'       => 'host_governed_create_draft'" ), 'Article write plan marks create-draft as the host-governed recipe step.' );
toolbox_assert( false !== strpos( $client, "'status'  => 'draft'" ), 'Article write plan is draft-only.' );
toolbox_assert( false !== strpos( $client, "'recipe_id'              => 'article_draft_v1'" ), 'Article write plan handoff carries the local recipe id.' );
toolbox_assert( false !== strpos( $client, "'core_route'             => '/wp-json/npcink-governance-core/v1/proposals/from-plan'" ), 'Article write plan points to Core plan intake.' );
toolbox_assert( false !== strpos( $client, 'build_article_batch_write_plan' ), 'Provider client can build Core-ready article batch write plans.' );
toolbox_assert( false !== strpos( $client, "'artifact_type'             => 'article_batch_write_plan'" ), 'Article batch write plan declares the Core contract artifact type.' );
toolbox_assert( false !== strpos( $client, "'composition_role'          => 'core_article_batch_write_plan'" ), 'Article batch write plan declares its composition role.' );
toolbox_assert( false !== strpos( $client, "'proposal_mode'             => 'batch'" ) && false !== strpos( $client, "'batch_approval'            => true" ), 'Article batch write plan uses one batch approval.' );
toolbox_assert( false !== strpos( $client, "'articles'                  => \$article_artifacts" ) && false !== strpos( $client, "'article_draft_candidate' =>" ), 'Article batch write plan includes reviewed article artifacts.' );
toolbox_assert( false !== strpos( $client, "'publish_allowed'           => false" ) && false !== strpos( $client, "'partial_success'           => false" ), 'Article batch write plan is draft-only and fail-closed.' );
toolbox_assert( false !== strpos( $client, 'build_article_media_batch_write_plan' ), 'Provider client can build Core-ready article plus media batch write plans.' );
toolbox_assert( false !== strpos( $client, "'artifact_type'             => 'article_media_batch_write_plan'" ), 'Article plus media batch write plan declares the Core contract artifact type.' );
toolbox_assert( false !== strpos( $client, "'composition_role'          => 'core_article_media_batch_write_plan'" ), 'Article plus media batch write plan declares its composition role.' );
toolbox_assert( false !== strpos( $client, "'target_ability_id' => 'npcink-abilities-toolkit/upload-media-from-url'" ) && false !== strpos( $client, "'target_ability_id' => 'npcink-abilities-toolkit/set-post-featured-image'" ), 'Article plus media batch write plan routes media upload and featured image writes through Core-governed abilities.' );
toolbox_assert( false !== strpos( $client, "'file_name'         => \$file_name" ), 'Article plus media batch write plan preserves approved media file names.' );
toolbox_assert( false !== strpos( $client, "'attach_to_post_id' => '\$outputs.' . \$create_id . '.post_id'" ) && false !== strpos( $client, "'attachment_id'  => '\$outputs.' . \$upload_id . '.attachment_id'" ), 'Article plus media batch write plan uses output references for dependent media writes.' );
toolbox_assert( false !== strpos( $client, 'build_image_candidate_adoption_plan' ), 'Provider client can build image candidate adoption plans.' );
toolbox_assert( false !== strpos( $client, "\$input['download_url']" ) && false !== strpos( $client, 'A selected image URL or image_candidate object is required' ), 'Image candidate adoption plan accepts simplified selected image URL input.' );
toolbox_assert( false !== strpos( $client, 'build_media_derivative_handoff' ), 'Provider client can build media derivative handoffs.' );
toolbox_assert( false !== strpos( $client, "'artifact_type'          => 'media_derivative_handoff'" ), 'Media derivative handoff declares its artifact type.' );
toolbox_assert( false !== strpos( $client, "'composition_role'       => 'media_derivative_operator_handoff'" ), 'Media derivative handoff declares its composition role.' );
toolbox_assert( false !== strpos( $client, "'ability_id'             => 'npcink-abilities-toolkit/build-media-derivative-cloud-request'" ), 'Media derivative handoff points to the local Abilities request builder.' );
toolbox_assert( false !== strpos( $client, "'optimization_plan_ability_id' => 'npcink-abilities-toolkit/build-media-optimization-plan'" ) && false !== strpos( $client, "'preferred_core_route'   => '/wp-json/npcink-openclaw-adapter/v1/proposals/from-plan'" ), 'Media derivative handoff defaults full optimization to Core from-plan.' );
toolbox_assert( false !== strpos( $client, "'do_not_split_user_intent' => true" ) && false !== strpos( $client, 'instead of splitting the same user intent into two proposals' ), 'Media derivative handoff blocks split proposal fallback for full optimization.' );
toolbox_assert( false !== strpos( $client, 'magick_ai_core_build_media_derivative_ability_input' ), 'Media derivative handoff reads Core media policy ability input when available.' );
toolbox_assert( false !== strpos( $client, 'fallback_media_derivative_policy' ), 'Media derivative handoff has a fallback when Core is unavailable.' );
toolbox_assert( false !== strpos( $client, 'media_derivative_watermark_overrides' ) && false !== strpos( $client, "'watermark_enabled' => false" ) && false !== strpos( $client, "'scale_percent' =>" ), 'Media derivative handoff supports explicit one-run watermark overrides while leaving Core as policy owner.' );
toolbox_assert( false !== strpos( $client, "'type'       => 'text'" ) && false !== strpos( $client, "'font_size'  =>" ) && false !== strpos( $client, 'sanitize_media_derivative_watermark_color' ), 'Media derivative handoff sanitizes text watermark overrides without requiring a logo artifact.' );
toolbox_assert( false !== strpos( $client, 'build_content_discoverability_brief' ), 'Provider client can build content discoverability briefs.' );
toolbox_assert( false !== strpos( $client, 'run_hosted_ai_content_support' ) && false !== strpos( $client, "'profile_id'          => 'text.ai'" ) && false !== strpos( $client, "'ability_name'        => 'npcink-toolbox/ai-content-support'" ), 'Provider client runs hosted AI content support through the dedicated hosted profile.' );
toolbox_assert( false !== strpos( $client, "array( 'title_summary', 'article_outline', 'polish_notes', 'summary_terms_optimization' )" ) && false !== strpos( $client, "'max_tokens'  => 650" ), 'Provider client keeps hosted AI draft-support intents lightweight and model-neutral.' );
toolbox_assert( false !== strpos( $client, "'summary_terms_optimization' => array(" ) && false !== strpos( $client, "'category_candidates'" ) && false !== strpos( $client, "'tag_candidates'" ), 'Provider client gives summary/category/tag optimization a reviewable quality contract.' );
toolbox_assert( false !== strpos( $client, 'hosted_ai_quality_contract' ) && false !== strpos( $client, "'quality_contract'" ) && false !== strpos( $client, "'review_checklist'" ) && false !== strpos( $client, "'reject_if'" ), 'Provider client attaches AI quality guardrails to prompts and normalized outputs.' );
toolbox_assert( false === strpos( $client, "'article_optimization' =>" ) && false === strpos( $client, "'smart_recommendations' =>" ) && false === strpos( $client, "'site_checkup'      =>" ), 'Hosted AI draft support does not keep legacy site, media, or recommendation task prompts.' );
toolbox_assert( false !== strpos( $client, 'hosted_ai_content_support_prompt' ) && false !== strpos( $client, 'direct_wordpress_write' ) && false !== strpos( $client, 'core_proposal_required' ) && false !== strpos( $client, 'Do not generate a full article' ), 'AI prompt preserves suggestion-only Core approval boundaries.' );
toolbox_assert( false !== strpos( $client, 'run_hosted_ai_site_helper' ) && false !== strpos( $client, "'ability_name'        => 'npcink-toolbox/ai-site-helper'" ) && false !== strpos( $client, "'contract_version'    => 'hosted_ai_site_helper.v1'" ), 'Provider client runs AI site helpers through a separate hosted runtime contract.' );
toolbox_assert( false !== strpos( $client, 'run_ai_image_generation' ) && false !== strpos( $client, "'image.grok-imagine-quality'" ) && false !== strpos( $client, "'execution_kind'      => 'image_generation'" ), 'Provider client runs reviewed AI image generation through the hosted image profile.' );
toolbox_assert( false !== strpos( $client, 'prompt_reviewed_by_operator' ) && false !== strpos( $client, 'normalize_ai_image_generation_response' ) && false !== strpos( $client, "'source_type'                   => 'ai_generated'" ), 'Provider client normalizes generated images as candidate-only AI image sources.' );
toolbox_assert( false !== strpos( $client, "array( 'media_alt_suggestions', 'content_snapshot_suggestions' )" ) && false !== strpos( $client, 'collect_hosted_ai_media_alt_snapshot( 10 )' ) && false !== strpos( $client, 'collect_hosted_ai_site_snapshot()' ), 'Provider client keeps AI site-helper sampling bounded and local-light.' );
toolbox_assert( false !== strpos( $client, 'hosted_ai_site_helper_quality_contract' ) && false !== strpos( $client, 'needs_human_visual_check' ) && false !== strpos( $client, 'full site audit' ), 'Provider client attaches site-helper guardrails for media ALT and snapshot suggestions.' );
toolbox_assert( false !== strpos( $client, "'writing_support_plan'" ), 'Provider client permits Cloud Site Knowledge writing support plan intent.' );
toolbox_assert( false !== strpos( $client, "'artifact_type'          => 'content_discoverability_brief'" ), 'Content discoverability brief declares its artifact type.' );
toolbox_assert( false !== strpos( $client, "'composition_role'       => 'seo_aeo_geo_brief'" ), 'Content discoverability brief declares its composition role.' );
toolbox_assert( false !== strpos( $client, "'primary_contract'       => true" ), 'Content discoverability brief is the primary SEO/AEO/GEO contract.' );
toolbox_assert( false !== strpos( $client, "'final_write_path'       => 'core_proposal_required'" ), 'Content discoverability brief points final writes to Core proposals.' );
toolbox_assert( false !== strpos( $client, "'seo'                    =>" ) && false !== strpos( $client, "'aeo'                    =>" ) && false !== strpos( $client, "'geo'                    =>" ) && false !== strpos( $client, 'content_discoverability_field_group' ), 'Content discoverability brief exposes SEO/AEO/GEO section blocks.' );
toolbox_assert( false !== strpos( $client, "'exceptions'             =>" ) && false !== strpos( $client, "'special_cases'          =>" ), 'Content discoverability brief exposes exception and special-case rules.' );
toolbox_assert( false !== strpos( $client, 'proposal_template' ) && false !== strpos( $client, 'candidate_suggestions' ), 'Content discoverability brief returns proposal templates and conservative candidates.' );
toolbox_assert( false !== strpos( $client, "'brief_ability_id'       => 'npcink-toolbox/build-content-discoverability-brief'" ), 'Content discoverability brief returns ability handoff metadata.' );
toolbox_assert( false !== strpos( $client, 'build_ai_article_writing_pack' ), 'Provider client can build AI article writing packs.' );
toolbox_assert( false !== strpos( $client, "'artifact_type'          => 'ai_article_writing_pack'" ), 'AI article writing pack declares its artifact type.' );
toolbox_assert( false !== strpos( $client, "'composition_role'       => 'ai_article_writing_pack'" ), 'AI article writing pack declares its composition role.' );
toolbox_assert( false !== strpos( $client, "'primary_contract'       => false" ) && false !== strpos( $client, "'contract_role'          => 'openclaw_natural_language_fallback'" ), 'AI article writing pack is marked as a fallback contract.' );
toolbox_assert( false !== strpos( $client, 'article_prompt_pack' ) && false !== strpos( $client, 'suggested_article_structure' ), 'AI article writing pack returns prompt guidance and structure.' );
toolbox_assert( false !== strpos( $client, "'pack_ability_id'       => 'npcink-toolbox/build-ai-article-writing-pack'" ), 'AI article writing pack returns ability handoff metadata.' );

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
toolbox_assert( false !== strpos( $rest, "'hosted_ai'" ) && false !== strpos( $rest, "'hosted_profile'          => 'text.ai'" ) && false !== strpos( $rest, "'site_helpers_registered' => true" ) && false !== strpos( $rest, "'posture'                 => 'suggestion_only_core_approval_required'" ) && false === strpos( $rest, "'model_id'       =>" ), 'Status exposes model-neutral hosted AI metadata without adding write authority.' );
toolbox_assert( false !== strpos( $rest, "\$this->post( '/ai/content-support', 'hosted_ai_content_support' )" ) && false !== strpos( $rest, 'run_hosted_ai_content_support' ), 'REST exposes the hosted AI content-support runtime route.' );
toolbox_assert( false !== strpos( $rest, "\$this->post( '/ai/site-helpers', 'hosted_ai_site_helper' )" ) && false !== strpos( $rest, 'run_hosted_ai_site_helper' ), 'REST exposes the dedicated AI site-helper runtime route.' );
toolbox_assert( false !== strpos( $rest, "\$this->post( '/ai/image-generation', 'ai_image_generation' )" ) && false !== strpos( $rest, "'ai_image_generation'" ) && false !== strpos( $rest, "'direct_wordpress_write'  => false" ), 'REST exposes a narrow AI image generation route without WordPress write authority.' );
toolbox_assert( false !== strpos( $rest, 'query or vector field' ), 'Vector REST route accepts query or vector input.' );
toolbox_assert( false !== strpos( $rest, 'site_knowledge_sync' ) && false !== strpos( $rest, 'site_knowledge_status' ) && false !== strpos( $rest, 'site_knowledge_search' ), 'REST routes expose Cloud-managed site knowledge operations.' );
toolbox_assert( false !== strpos( $rest, "Site_Knowledge_Auto_Sync::health_snapshot()" ) && false !== strpos( $rest, "\$status['auto_sync']" ), 'Site Knowledge status REST response includes local auto-sync health.' );
toolbox_assert( false === strpos( $rest, 'enhance_with_reader' ) && false === strpos( $rest, 'web_research' ) && false === strpos( $rest, 'jina_reader' ), 'REST exposes Cloud web search testing without local web research or reader enhancement inputs.' );
toolbox_assert( false !== strpos( $rest, "'provider'    => sanitize_key" ), 'Image candidate REST route accepts provider selection.' );
toolbox_assert( false !== strpos( $rest, 'npcink_toolbox_rest_permission' ), 'REST permission can be mediated by a host scope filter.' );

toolbox_assert( false === strpos( $abilities, 'cap.toolbox.search' ), 'Removed local web ability no longer exposes a Toolbox search scope.' );
toolbox_assert( false !== strpos( $abilities, 'cap.toolbox.vector_search' ), 'Vector ability exposes a Toolbox vector scope.' );
toolbox_assert( false !== strpos( $abilities, "'npcink-toolbox/generate-image'" ) && false !== strpos( $abilities, 'generate_image_candidate' ) && false !== strpos( $abilities, 'candidate_only_core_approval_required' ), 'Abilities expose reviewed AI image candidate generation without write authority.' );
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
toolbox_assert( false !== strpos( $abilities, 'build_article_batch_write_plan' ) && false !== strpos( $abilities, 'npcink-toolbox/build-article-batch-write-plan' ), 'Article batch write plan ability has an execution callback.' );
toolbox_assert( false !== strpos( $abilities, 'build_article_media_batch_write_plan' ) && false !== strpos( $abilities, 'npcink-toolbox/build-article-media-batch-write-plan' ), 'Article plus media batch write plan ability has an execution callback.' );
toolbox_assert( false !== strpos( $abilities, 'search_site_knowledge' ) && false !== strpos( $abilities, 'get_site_knowledge_status' ) && false !== strpos( $abilities, 'request_site_knowledge_sync' ), 'Site knowledge abilities have execution callbacks.' );
toolbox_assert( false !== strpos( $abilities, "'provider_execution'       => 'server_side_toolbox'" ), 'Provider-backed abilities declare server-side execution.' );
toolbox_assert( false !== strpos( $abilities, "'provider_secret_exposure' => 'none'" ), 'Abilities declare that provider secrets are not exposed.' );
toolbox_assert( false !== strpos( $abilities, "'final_write_path'         => 'core_proposal_required'" ), 'Abilities point write-like outcomes to Core proposals.' );
toolbox_assert( false !== strpos( $abilities, "'direct_wordpress_write'   => false" ), 'Abilities declare direct WordPress writes disabled.' );
toolbox_assert( false !== strpos( $abilities, 'get_content_discoverability_context' ), 'Content context ability has an execution callback.' );
toolbox_assert( false !== strpos( $abilities, "array( 'query' )" ), 'Vector ability accepts query input for AI callers.' );
toolbox_assert( false !== strpos( $abilities, 'npcink_toolbox_ability_permission' ), 'Ability permission can be mediated by a host scope filter.' );
toolbox_assert( false !== strpos( $abilities, '$this->registered_with_helpers || ! function_exists( \'wp_register_ability_category\' )' ), 'Native category registration skips when helper registration already succeeded.' );
toolbox_assert( false !== strpos( $abilities, 'wp_has_ability_category' ), 'Native category registration checks for an existing WordPress ability category.' );

$readme = file_get_contents( $root . '/README.md' );
toolbox_assert( false !== strpos( $readme, 'Cloud-managed image-source candidates' ), 'README documents Cloud-managed image-source providers.' );
toolbox_assert( false !== strpos( $readme, 'visual brief' ) && false !== strpos( $readme, 'site context vectors' ) && false !== strpos( $readme, 'media SEO suggestions' ), 'README documents Cloud AI visual optimization for image-source candidates.' );
toolbox_assert( false !== strpos( $readme, 'same image-source picker contract can be reused' ) && false !== strpos( $readme, 'Toolbox does not write setting values directly' ), 'README documents reusable image-source picker selection without settings writes.' );
toolbox_assert( false !== strpos( $readme, 'Image-source picker optimization stays lightweight in Toolbox' ) && false !== strpos( $readme, 'dedupe near-identical candidates' ) && false !== strpos( $readme, 'must not own provider routing' ), 'README documents the lightweight local and richer Cloud image picker optimization boundary.' );
toolbox_assert( false !== strpos( $readme, 'Cloud-managed web search' ) && false === strpos( $readme, 'npcink-toolbox/web-research' ), 'README documents Cloud-managed web search without local web-research ability.' );
toolbox_assert( false !== strpos( $readme, 'Cloud-managed site knowledge' ) && false !== strpos( $readme, 'npcink-toolbox/search-site-knowledge' ), 'README documents Cloud-managed site knowledge abilities.' );
toolbox_assert( false === strpos( $readme, 'Pinecone and Weaviate' ), 'README does not advertise reserved local vector providers.' );
toolbox_assert( false !== strpos( $readme, 'AI Content Composition Abilities' ), 'README links the AI content composition abilities contract.' );
toolbox_assert( false !== strpos( $readme, 'Connector Ability Exposure' ), 'README links the connector ability exposure contract.' );
toolbox_assert( false !== strpos( $readme, 'Content Discoverability Context' ), 'README links the content context contract.' );
toolbox_assert( false !== strpos( $readme, 'OpenClaw Content Discoverability Handoff' ), 'README links the OpenClaw content discoverability handoff.' );
toolbox_assert( false !== strpos( $readme, 'OpenClaw SEO/GEO/AEO Acceptance Summary' ), 'README links the OpenClaw SEO/GEO/AEO acceptance summary.' );
toolbox_assert( false !== strpos( $readme, 'Content Assistant Surface Lessons' ), 'README links the Content Assistant surface lessons contract.' );
toolbox_assert( false !== strpos( $readme, 'Article Assistant Workbench' ), 'README links the Article Assistant workbench contract.' );

$boundary_doc = file_get_contents( $root . '/docs/boundary.md' );
toolbox_assert( false !== $boundary_doc && false !== strpos( $boundary_doc, 'REST Route Boundary' ) && false !== strpos( $boundary_doc, 'Cloud Checks Surface' ), 'Boundary documentation records route and Cloud Checks limits.' );
toolbox_assert( false !== strpos( $boundary_doc, 'publishing' ) && false !== strpos( $boundary_doc, 'content indexing' ), 'Boundary documentation blocks write/runtime/indexing routes.' );
toolbox_assert( false !== strpos( $boundary_doc, 'AI Tool Composition Boundary' ) && false !== strpos( $boundary_doc, 'local vector context for style' ), 'Boundary documentation records AI tool composition limits.' );
toolbox_assert( false !== strpos( $boundary_doc, 'not only' ) && false !== strpos( $boundary_doc, 'article drafting' ), 'Boundary documentation keeps web research general-purpose.' );
toolbox_assert( false !== strpos( $boundary_doc, 'Cloud-managed site knowledge may run through the Cloud Addon runtime seam' ) && false !== strpos( $boundary_doc, 'second ability registry' ), 'Boundary documentation keeps Cloud site knowledge as runtime/detail, not a second control plane.' );

$architecture_doc = file_get_contents( $root . '/docs/architecture.md' );
toolbox_assert( false !== $architecture_doc && false !== strpos( $architecture_doc, 'static matrix in' ) && false !== strpos( $architecture_doc, 'Cloud Checks use compact tabs' ), 'Architecture documentation records the route matrix and Cloud Checks split.' );
toolbox_assert( false !== strpos( $architecture_doc, 'Reviewed Draft Handoff' ) && false !== strpos( $architecture_doc, 'submit the plan to Core' ) && false !== strpos( $architecture_doc, 'approve execution' ), 'Architecture documentation records the reviewed draft handoff UI boundary.' );
toolbox_assert( false !== strpos( $architecture_doc, 'groups fixed buttons by operator job' ) && false !== strpos( $architecture_doc, 'Article Planning Bundle' ) && false !== strpos( $architecture_doc, 'not the default support' ), 'Architecture documentation records grouped fixed Content Support flows and fallback bundle status.' );
toolbox_assert( false !== strpos( $architecture_doc, 'artifact_type' ) && false !== strpos( $architecture_doc, 'composition_role' ), 'Architecture documentation records the compact provider payload contract.' );
toolbox_assert( false !== strpos( $architecture_doc, 'Cloud-managed web search' ) && false === strpos( $architecture_doc, '`npcink-toolbox/web-research`' ), 'Architecture documentation records Cloud ownership for web search.' );
toolbox_assert( false === strpos( $architecture_doc, '| Bocha | External web search | `/web-research` |' ) && false === strpos( $architecture_doc, '| Jina Reader | Search result URL extraction | `/web-research` enhancement only |' ), 'Architecture documentation no longer records local Bocha or Jina Reader search roles.' );
toolbox_assert( false !== strpos( $architecture_doc, '`search-site-knowledge` is the high-level ability' ) && false !== strpos( $architecture_doc, 'npcink_toolbox_site_knowledge_runtime_payload' ), 'Architecture documentation records the Cloud site knowledge ability seam.' );
toolbox_assert( false !== strpos( $architecture_doc, 'Cloud can build a visual brief' ) && false !== strpos( $architecture_doc, 'Cloud-managed site context vectors' ) && false !== strpos( $architecture_doc, 'does not configure or own image providers, vector indexes, or' ), 'Architecture documentation records Cloud AI image rerank without local provider or vector ownership.' );
toolbox_assert( false !== strpos( $architecture_doc, 'reusable image-source picker' ) && false !== strpos( $architecture_doc, 'selection-only mode returns data to the caller' ), 'Architecture documentation records image picker reuse for settings and other fields.' );
toolbox_assert( false !== strpos( $architecture_doc, 'short-lived result caching' ) && false !== strpos( $architecture_doc, 'near-duplicate filtering' ) && false !== strpos( $architecture_doc, 'without turning Toolbox into an image index' ), 'Architecture documentation records the image picker optimization split between local UI and Cloud.' );

$first_version_doc = file_get_contents( $root . '/docs/first-version-reference.md' );
toolbox_assert( false !== $first_version_doc && false !== strpos( $first_version_doc, 'REST Route Matrix' ) && false !== strpos( $first_version_doc, 'Cloud Checks now open directly into verification tools' ), 'First-version reference captures route matrix and Cloud Checks guidance.' );
toolbox_assert( false !== strpos( $first_version_doc, 'canonical composition sequence' ) && false !== strpos( $first_version_doc, 'The sequence is a recommendation for composing tool inputs' ) && false !== strpos( $first_version_doc, 'runtime contract' ), 'First-version reference captures the AI content composition sequence.' );
toolbox_assert( false !== strpos( $first_version_doc, 'fixed, single-job support' ) && false !== strpos( $first_version_doc, 'combined Article Planning Bundle' ) && false !== strpos( $first_version_doc, 'separate groups' ), 'First-version reference captures grouped fixed Content Support buttons before fallback bundles.' );
toolbox_assert( false !== strpos( $first_version_doc, 'Site Knowledge Agent handoff acceptance' ) && false !== strpos( $first_version_doc, 'site_knowledge_core_proposal_candidate' ) && false !== strpos( $first_version_doc, 'core_submission=not_submitted' ) && false !== strpos( $first_version_doc, 'site_knowledge_review_plan' ), 'First-version reference captures Site Knowledge Agent handoff acceptance boundaries.' );

$content_composition_doc = file_get_contents( $root . '/docs/ai-content-composition-abilities.md' );
toolbox_assert( false !== $content_composition_doc && false !== strpos( $content_composition_doc, 'Content Support First' ), 'AI content composition documentation records the content-support-first sequence.' );
toolbox_assert( false !== strpos( $content_composition_doc, 'General Tool Usage' ) && false !== strpos( $content_composition_doc, 'article drafting is only one consumer' ), 'AI content composition documentation keeps provider abilities general-purpose.' );
toolbox_assert( false !== strpos( $content_composition_doc, 'npcink-toolbox/vector-search' ) && false !== strpos( $content_composition_doc, 'Cloud-managed site knowledge compatibility pointer' ), 'AI content composition documentation maps vector search to a Cloud-managed compatibility pointer.' );
toolbox_assert( false !== strpos( $content_composition_doc, 'npcink-toolbox/search-site-knowledge' ) && false !== strpos( $content_composition_doc, 'related content' ), 'AI content composition documentation maps site knowledge to general search and recommendations.' );
toolbox_assert( false !== strpos( $content_composition_doc, 'npcink-toolbox/search-image-source' ) && false !== strpos( $content_composition_doc, 'download_location' ), 'AI content composition documentation maps image source search to attribution-preserving image candidates.' );
toolbox_assert( false !== strpos( $content_composition_doc, 'source_type=ai_generated' ) && false !== strpos( $content_composition_doc, 'npcink_toolbox_ai_image_generation_request' ), 'AI content composition documentation separates generated-image candidates from public source search.' );
toolbox_assert( false !== strpos( $content_composition_doc, 'Cloud-managed web search' ) && false === strpos( $content_composition_doc, 'npcink-toolbox/web-research' ), 'AI content composition documentation maps web research to Cloud.' );
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
toolbox_assert( false !== strpos( $connector_exposure_doc, 'npcink-toolbox/build-content-discoverability-brief' ), 'Connector exposure documentation lists the content discoverability brief ability.' );

$content_context_doc = file_get_contents( $root . '/docs/content-discoverability-context.md' );
toolbox_assert( false !== $content_context_doc && false !== strpos( $content_context_doc, 'npcink-toolbox/get-content-discoverability-context' ), 'Content context documentation records the ability id.' );
toolbox_assert( false !== strpos( $content_context_doc, 'npcink-toolbox/validate-content-discoverability-context' ), 'Content context documentation records the validation ability id.' );
toolbox_assert( false !== strpos( $content_context_doc, 'npcink-toolbox/build-content-discoverability-brief' ), 'Content context documentation records the brief ability id.' );
toolbox_assert( false !== strpos( $content_context_doc, 'npcink-toolbox/build-ai-article-writing-pack' ), 'Content context documentation records the article writing pack ability id.' );
toolbox_assert( false !== strpos( $content_context_doc, 'primary lightweight SEO/AEO/GEO contract' ) && false !== strpos( $content_context_doc, 'special_cases' ), 'Content context documentation records the lightweight contract and special cases.' );
toolbox_assert( false !== strpos( $content_context_doc, 'does not call a model and does not write WordPress data' ), 'Content context documentation keeps brief generation bounded.' );
toolbox_assert( false !== strpos( $content_context_doc, 'wp eval-file tests/smoke-content-discoverability.php' ), 'Content context documentation records the local readiness smoke command.' );
toolbox_assert( false !== strpos( $content_context_doc, 'Missing `wp_*` Agent Gateway exposure is a host-side admission task' ), 'Content context documentation keeps Agent Gateway admission outside Toolbox.' );
toolbox_assert( false !== strpos( $content_context_doc, 'Do not add an update-context ability' ), 'Content context documentation blocks third-party updates in the first version.' );

$content_context_smoke = file_get_contents( $root . '/tests/smoke-content-discoverability.php' );
toolbox_assert( false !== $content_context_smoke && false !== strpos( $content_context_smoke, 'npcink_abilities_toolkit_get_registered' ), 'Content context smoke checks the Npcink Abilities Toolkit registry.' );
toolbox_assert( false !== strpos( $content_context_smoke, 'npcink_ai_open_platform_ability_catalog' ) && false !== strpos( $content_context_smoke, 'npcink_ai_open_platform_get_ability_catalog' ), 'Content context smoke checks Npcink catalog projection.' );
toolbox_assert( false !== strpos( $content_context_smoke, 'npcink_ai_open_platform_get_projection_matrix' ) && false !== strpos( $content_context_smoke, 'Core-side allowed_channels/tool-name admission is required' ), 'Content context smoke reports Agent Gateway admission status without owning it.' );
toolbox_assert( false !== strpos( $content_context_smoke, "direct_wordpress_write'] ?? true" ) && false !== strpos( $content_context_smoke, "'suggestion_only'" ), 'Content context smoke verifies suggestion-only no-write outputs.' );
toolbox_assert( false !== strpos( $content_context_smoke, 'build-ai-article-writing-pack' ) && false !== strpos( $content_context_smoke, 'ai_article_writing_pack' ), 'Content context smoke verifies the AI article writing pack.' );
toolbox_assert( false === strpos( $content_context_smoke, 'update_post_meta' ) && false === strpos( $content_context_smoke, 'wp_update_post' ), 'Content context smoke does not write WordPress content.' );

$openclaw_handoff_doc = file_get_contents( $root . '/docs/openclaw-content-discoverability-handoff.md' );
toolbox_assert( false !== $openclaw_handoff_doc && false !== strpos( $openclaw_handoff_doc, 'OpenClaw Content Discoverability Handoff' ), 'OpenClaw handoff documentation exists.' );
toolbox_assert( false !== strpos( $openclaw_handoff_doc, 'npcink-toolbox/validate-content-discoverability-context' ) && false !== strpos( $openclaw_handoff_doc, 'npcink-toolbox/build-content-discoverability-brief' ), 'OpenClaw handoff documentation records the required ability sequence.' );
toolbox_assert( false !== strpos( $openclaw_handoff_doc, 'GET /content-discoverability-brief?post_id=POST_ID' ), 'OpenClaw handoff documentation records Adapter shortcut usage.' );
toolbox_assert( false !== strpos( $openclaw_handoff_doc, 'Do not write WordPress data' ) && false !== strpos( $openclaw_handoff_doc, 'Final writes must go through Core proposal' ), 'OpenClaw handoff documentation preserves no-write guidance.' );

$openclaw_acceptance_doc = file_get_contents( $root . '/docs/openclaw-seo-geo-aeo-acceptance-summary.md' );
toolbox_assert( false !== $openclaw_acceptance_doc && false !== strpos( $openclaw_acceptance_doc, 'OpenClaw SEO/GEO/AEO Acceptance Summary' ), 'OpenClaw SEO/GEO/AEO acceptance summary exists.' );
foreach (
	array(
		'content-discoverability-brief',
		'article-writing-pack',
		'npcink-toolbox/build-content-discoverability-brief',
		'npcink-toolbox/build-ai-article-writing-pack',
		'write_posture=suggestion_only',
		'direct_wordpress_write=false',
		'final_write_path=core_proposal_required',
		'validation_status=ready',
		'missing_required=0',
		'missing_recommended=0',
		'disallowed_topics',
		'cautious_topics',
		'no_structured_output_topics',
		'human_confirmation_required',
		'OpenClaw is an external natural-language channel',
	) as $required_openclaw_acceptance
) {
	toolbox_assert( false !== strpos( $openclaw_acceptance_doc, $required_openclaw_acceptance ), 'OpenClaw SEO/GEO/AEO acceptance summary preserves: ' . $required_openclaw_acceptance );
}

$content_assistant_surface_doc = file_get_contents( $root . '/docs/content-assistant-surface-lessons.md' );
toolbox_assert( false !== $content_assistant_surface_doc && false !== strpos( $content_assistant_surface_doc, 'summary -> detail' ), 'Content Assistant surface lessons document records summary-first display discipline.' );
toolbox_assert( false !== strpos( $content_assistant_surface_doc, 'Do Not Absorb' ) && false !== strpos( $content_assistant_surface_doc, 'preview -> confirm apply' ), 'Content Assistant surface lessons document blocks write-flow absorption.' );
toolbox_assert( false !== strpos( $content_assistant_surface_doc, 'Toolbox surfaces. Core governs. WordPress writes through abilities.' ), 'Content Assistant surface lessons document records the Toolbox-specific boundary phrase.' );

toolbox_assert( false === strpos( $client, 'write_confirmed' ), 'Legacy write_confirmed contract is absent.' );
toolbox_assert( false === strpos( $client, 'confirm_token' ), 'Legacy confirm_token contract is absent.' );

$uninstall = file_get_contents( $root . '/uninstall.php' );
toolbox_assert( false !== strpos( $uninstall, 'npcink_toolbox_content_context' ), 'Uninstall removes content context option.' );

echo "Static contract checks passed.\n";
