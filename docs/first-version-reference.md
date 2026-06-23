# First Version Reference

Status: active handoff note for future AI sessions.

This file summarizes the first working shape of `npcink-toolbox` after the
provider, Abilities API, settings, lifecycle, and local smoke-test work.

## Product Boundary

Npcink Toolbox is an operator-facing AI tool plugin. It returns suggestions,
source candidates, image candidates, vector matches, and planning artifacts.

Toolbox must not:

- commit WordPress writes;
- import media or set featured images directly;
- own Core approval, audit, or proposal records;
- own workflow runtime, queue, scheduler, MCP, Agent Gateway, or OpenClaw state;
- own OpenClaw, Agent Gateway, Open API, or MCP projection truth;
- own WordPress content indexing, re-indexing, stale-index detection, or vector
  collection lifecycle;
- leak provider keys into logs, REST responses, proposals, docs, prompts, or
  handoff text.

Write-like outcomes must be handed to WordPress abilities and Npcink Governance Core
proposal approval.

## Providers

Current runtime providers:

| Capability | Provider | Runtime status |
| --- | --- | --- |
| Cloud-managed web search | Npcink Cloud | General external source candidates. Cloud owns provider configuration and routing. |
| Image source candidates | Unsplash | Active provider; preserve attribution and `download_location`. |
| Image source candidates | Pixabay | Active provider when configured; preserve attribution and source URL. |
| Image source candidates | Pexels | Active provider when configured; preserve attribution and source URL. |
| AI-generated image candidates | Caller URL or host filter | Explicit `ai_generated` mode; preserve prompt/model evidence and human license review status. |
| Site knowledge vector infrastructure | Npcink Cloud | Cloud-managed embedding, vector storage, indexing, rerank, status, and search. |

## Cloud-Managed Vector

Toolbox no longer configures vector providers locally. The legacy
`vector-search` route and ability return a Cloud-managed site knowledge
compatibility pointer. New callers should use `search-site-knowledge`,
`get-site-knowledge-status`, and `request-site-knowledge-sync`.

Toolbox must not store vector provider keys, embedding models, dimensions,
provider endpoints, collection names, rerank settings, or vector lifecycle
controls.

## Settings And Secrets

The settings page supports stored options plus env/constant fallback.

Provider connector settings are stored in:

```text
npcink_toolbox_settings
```

Secrets:

- `TAVILY_API_KEY` / `NPCINK_TOOLBOX_TAVILY_API_KEY`
- `BOCHA_API_KEY` / `NPCINK_TOOLBOX_BOCHA_API_KEY`

Provider raw payloads are excluded by default. Enable
`include_raw_responses` only for debugging.

The first version is single-site global configuration. Do not add multisite or
per-user isolation without a new decision.

Cloud Checks keep the stable `cloud-checks` deep-link id while opening
directly into verification tools for Cloud-managed search, image-source
candidates, preview-only media derivatives, Site Knowledge search, and Nightly
Inspection runtime detail. The tabs may show compact readiness labels, but each
panel should prioritize the Toolbox ability reachability check or recovery task
instead of repeating provider ownership detail.
Search checks use Cloud auto execution only; provider
selection, Jina Reader toggles, routing diagnostics, Cloud API key verification,
entitlement, billing, quota, and request logs belong in Cloud Addon or Cloud
service-plane surfaces. Image derivative checks may generate short-lived Cloud
preview artifacts only, with one-run text or image/logo watermark overrides
that stay compatible with OpenClaw media derivative handoffs; Core proposal,
batch proposal, and URL repair handoffs stay in Workflows. Marketplace,
provider routing, vector provider settings, and vector lifecycle controls do
not belong in Toolbox.

The admin page should default to Start: readiness, site-level and media next
actions, and one folded advanced directory. It should not render a single-post
article-support work block. Article-specific jobs use the editor Content
Support sidebar:
publish preflight, summary suggestions, category suggestions, tag suggestions,
internal links, and image candidates. Related existing-post review is folded
into publish preflight duplicate-risk checks and internal-link candidates, so
`writing_support` remains route-compatible but is not a default editor button.
The Full-site Insights tab is the site-level analysis surface. It builds a
manual local `site_ops_insight_pack.v1` from bounded public content,
approved-comment signal counts, media metadata, taxonomy summaries, Site
Context readiness, and Cloud availability, then presents the current run as
coverage metrics, lightweight charts, deterministic local summary, content,
media, comments, structure, findings, Cloud analysis, and advanced data views.
It does not call Cloud, persist run state, schedule jobs, create Core proposals,
or write WordPress data. It may also render a copyable
`site_ops_cloud_analysis_request.v1`; generating that request still does not
send it or create local runtime state. When Cloud is ready, an administrator
can explicitly run Cloud analysis and review the suggestion-only
`site_ops_cloud_analysis_result.v1` without Toolbox creating a local queue,
local run table, Core proposal, or WordPress write.
The admin Workflows tab defaults to Media, with Optimize Existing Image as the
first visible tool, and keeps Site Helpers as a secondary low-frequency group.
Governed Handoffs and Fallback Bundles sit under the folded advanced/fallback
area so the combined Article Planning Bundle remains a backup package, not the
primary workflow. The lower-level `taxonomy_tags` intent remains available to
the route but is not a separate default button in the editor UI.

## Content Discoverability Context

The admin page also includes an operator-filled Site Context form for SEO,
AEO, and GEO guidance. It is stored separately from connector settings:

```text
npcink_toolbox_content_context
```

This option may contain:

- site positioning;
- target audience;
- brand voice;
- primary, long-tail, and entity keywords;
- allowed claims and forbidden claims;
- SEO, AEO, and GEO rules;
- toggles for FAQ, AEO summary, GEO summary, and structured data suggestions;
- fields that third-party AI may suggest in proposal-ready outputs.

It must not contain provider keys, private credentials, billing details, quotas,
request logs, or final write authorization.

The Abilities payload is read-only and fixed to:

```text
write_posture: suggestion_only
final_write_path: core_proposal_required
direct_wordpress_write: false
```

## Abilities API

Toolbox ability ids stay under `npcink-toolbox/*`:

- `npcink-toolbox/search-image-source`
- `npcink-toolbox/vector-search`
- `npcink-toolbox/search-site-knowledge`
- `npcink-toolbox/get-site-knowledge-status`
- `npcink-toolbox/request-site-knowledge-sync`
- `npcink-toolbox/build-article-brief`
- `npcink-toolbox/build-article-write-plan`
- `npcink-abilities-toolkit/build-image-candidate-adoption-plan`
- `npcink-toolbox/build-site-knowledge-review-plan`
- `npcink-toolbox/build-media-brief`
- `npcink-toolbox/get-content-discoverability-context`
- `npcink-toolbox/validate-content-discoverability-context`
- `npcink-toolbox/build-content-discoverability-brief`

General-purpose provider abilities:

- Cloud-managed web search is the external source-candidate capability for any
  workflow that needs web evidence, comparison material, Chinese source lookup,
  public references, support context, source coverage, or article preparation.
  Npcink Cloud owns provider configuration and execution; Toolbox does not
  register a local search ability.
- `npcink-toolbox/search-image-source` is the image-candidate ability for
  any workflow that needs sourced images. It returns `image_candidate.v1`
  candidates for stock, AI-generated, owned, or external image sources.
- `npcink-toolbox/generate-image` is the reviewed-prompt AI image candidate
  ability exposed when Cloud image-source results include an
  `ai_generation_handoff`. It calls hosted image generation through Cloud
  Addon, returns candidates only, and does not import media or write
  WordPress.
- `npcink-abilities-toolkit/build-image-candidate-adoption-plan` turns one reviewed
  `image_candidate.v1` into a Core-ready `image_candidate_adoption_plan` for
  media upload, metadata, and optional featured-image proposal intake.
- `npcink-toolbox/search-site-knowledge` is the Cloud-managed site knowledge
  ability for semantic site search, related content, writing context, internal
  links, refresh suggestions, or image context. When Cloud returns
  `agent_handoff`, Toolbox displays it as a local Core proposal candidate only;
  the operator may prepare a local candidate packet for review or submit a
  blocked Core review proposal through `site_knowledge_review_plan`, but
  Toolbox does not approve, pass preflight, or execute the proposal.
- `npcink-toolbox/build-site-knowledge-review-plan` turns a Cloud Site
  Knowledge `proposal_input` into a Core-only `site_knowledge_review_plan`.
  It preserves evidence refs, targets only a non-ready
  `npcink-abilities-toolkit/create-draft` review action, requires human
  `title` and `content` input, and does not generate or write WordPress
  content.
- `npcink-toolbox/vector-search` is a Cloud-managed site knowledge
  compatibility pointer for older clients.

Site knowledge status and sync:

- `npcink-toolbox/get-site-knowledge-status` reads Cloud-managed coverage
  and freshness state.
- `npcink-toolbox/request-site-knowledge-sync` requests bounded Cloud sync
  or rebuild work from public WordPress content. It does not write WordPress
  content and does not create a local indexing queue.

Site Knowledge Agent handoff acceptance:

- Cloud may return evidence-backed `agent_handoff` and `proposal_input`.
- Toolbox may render `Governed handoff`, `Agent proposal input`, and a local
  `site_knowledge_core_proposal_candidate` packet for operator review.
- The local candidate packet must keep `core_submission=not_submitted`,
  `direct_wordpress_write=false`, and `final_writes=core_proposal_required`.
- Toolbox may submit the reviewed handoff only as
  `npcink-toolbox/build-site-knowledge-review-plan` with
  `artifact_type=site_knowledge_review_plan`.
- The Core proposal created from that plan must remain blocked/not ready until a
  human supplies draft `title` and `content`; Toolbox must not approve it, run
  preflight, or execute WordPress writes.
- Core remains the only owner of proposal approval, commit preflight, audit, and
  final WordPress writes.

Post editor content support:

- `POST /wp-json/npcink-toolbox/v1/editor/content-support` runs one bounded
  fixed flow from the current draft context.
- Supported intents are `writing_support`, `publish_preflight`,
  `summary_suggestions`, `category_suggestions`, `tag_suggestions`,
  `summary_terms_optimization`, `taxonomy_tags`, `internal_links`,
  `image_candidates`, selection-only paragraph checks via `polish_notes`, and
  `discoverability`.
- The editor UI shows primary buttons for `publish_preflight`,
  `summary_suggestions`, `category_suggestions`, `tag_suggestions`,
  `internal_links`, and `image_candidates`; `writing_support`,
  `summary_terms_optimization`, and `taxonomy_tags` remain supported route
  intents but are not separate default buttons.
- Returned artifacts are `editor_content_support_flow` suggestions. They do not
  assign terms, insert links, import media, publish content, or write SEO fields.
- The selected-block toolbar may trigger `polish_notes` for the current
  selection or paragraph only. It returns clarity, fact-gap, tone, and editing
  direction notes; it does not return replacement copy or update the block.
- `internal_links` returns `internal_link_candidates.v1`: related internal
  targets, suggested anchor text, placement hints, and Site Knowledge evidence
  for manual editor review only.
- `publish_preflight` returns `pre_publish_review.v1` as the unified readiness
  panel, plus duplicate-risk evidence and a `seo_meta_handoff_preview.v1`
  single-post Core proposal payload when a title and description candidate are
  available. The editor can submit it as one pending Core review proposal, but
  Toolbox does not approve, execute, or write SEO fields itself.
- The split metadata intents return the same
  `article_discoverability_optimization.v1` section shape through faster
  draft/taxonomy paths. The full `summary_terms_optimization` intent still
  returns hosted AI summary candidates, existing category/tag candidates,
  related Site Knowledge, web search evidence from the discoverability brief,
  ranking and dedupe guidance, review metrics, input scope, proposed new-term
  review notes, preview-only Core handoff guidance, a `content_metadata_delta`
  P0 artifact, and review notes. Existing WordPress terms are preferred;
  proposed new tags remain Core policy-gated strong-review vocabulary-gap
  candidates only. Related Site Knowledge terms
  from current local WordPress posts can boost existing category/tag candidates
  as ranking evidence only; they do not create taxonomy terms, assign terms,
  write excerpts, persist feedback, or own index lifecycle state. The delta artifact records an
  issue record, diagnosis, excerpt/category/tag delta, authorization
  classification, outcome checks, and learning candidates without persisting
  learning or audit truth. Its handoff packet labels proposal-ready actions for
  Generate and apply summary, Recommend and apply tags, Recommend categories,
  and Create new tags and assign; Core policy owns any auto-approval decision.
  It does not update excerpts, assign terms, create terms, mutate SEO fields,
  index content, own taxonomy governance, store acceptance/audit truth, or own a
  RAG lifecycle.

For content-support AI callers, the canonical composition sequence is:

1. `npcink-toolbox/build-content-discoverability-brief`
2. `npcink-toolbox/search-site-knowledge`
3. `npcink-toolbox/search-image-source`
4. `npcink-abilities-toolkit/build-image-candidate-adoption-plan` after operator
   review
5. `npcink-toolbox/build-media-brief`
6. `npcink-toolbox/build-ai-article-writing-pack` only as a broad
   writing-support fallback
7. `npcink-toolbox/build-article-write-plan` only after a reviewed human
   draft exists

The sequence is a recommendation for composing tool inputs, not a workflow
runtime contract. Toolbox does not schedule, retry, index, import media, publish
posts, write article bodies, or mutate SEO fields.

Stable first-version scopes:

- `cap.toolbox.image_source`
- `cap.toolbox.vector_search`
- `cap.toolbox.knowledge.search`
- `cap.toolbox.knowledge.read`
- `cap.toolbox.knowledge.sync`
- `cap.toolbox.workflow_suggest`
- `cap.toolbox.context.read`

Content context consumers should call
`npcink-toolbox/validate-content-discoverability-context` before using the
context for third-party AI workflows. For one post or topic, call
`npcink-toolbox/build-content-discoverability-brief` to get the
suggestion-only SEO/AEO/GEO instruction pack, proposal template, conservative
candidate values, and Core handoff reminders.

Do not rename these scopes unless Npcink Governance Core explicitly changes the app-key
scope contract.

## Ability Registration Lifecycle

Do not call `register_with_npcink_abilities_toolkit()` synchronously during plugin
hook setup. That triggers translation too early on modern WordPress.

Current lifecycle:

- helper registration is deferred to `wp_abilities_api_categories_init` with
  priority `1`;
- native category registration skips if helper registration already succeeded;
- native category registration also checks `wp_has_ability_category()` before
  registering `npcink-toolbox`;
- native ability registration skips when helper registration already succeeded.

This prevents early textdomain notices and duplicate Toolbox category notices.

## Admin Surface

Preferred menu:

- `Npcink -> Toolbox`
- `admin.php?page=npcink-toolbox`

When no shared Npcink parent menu exists:

- `Tools -> Npcink Toolbox`
- `tools.php?page=npcink-toolbox`

Submenu position is `45`, after Abilities and before Cloud Addon.

Toolbox may reuse Content Assistant's product-surface discipline, but only as a
UI and contract pattern. The default result surface should show summary,
candidates, governed handoff, and then collapsed details. Do not import Content
Assistant article/comment/media lanes, local write flows, or runtime ownership
into Toolbox.

## Local Smoke Environment

Verified local site path:

```bash
/Users/muze/Local Sites/npcink/app/public
```

Verified plugin symlink:

```bash
/Users/muze/Local Sites/npcink/app/public/wp-content/plugins/npcink-toolbox -> /Users/muze/gitee/npcink-toolbox
```

Global WP-CLI is installed on this workstation and should be preferred for
WordPress plugin development, smoke tests, Plugin Check, activation, and status
checks. Confirm it before use:

```bash
command -v wp
wp --info
```

The verified current global binary is:

```bash
/opt/homebrew/bin/wp
```

Always set `WP_PATH` or pass `--path`; do not assume the current repository is
the WordPress root. For the local Toolbox site:

```bash
WP_PATH="/Users/muze/Local Sites/npcink/app/public"
```

Do not write local admin passwords into repository files.

Useful smoke commands:

```bash
wp --path="$WP_PATH" plugin activate npcink-toolbox

wp --path="$WP_PATH" plugin status npcink-toolbox

wp --path="$WP_PATH" eval 'wp_set_current_user( 1 ); do_action( "rest_api_init" ); $request = new WP_REST_Request( "GET", "/npcink-toolbox/v1/status" ); $response = rest_do_request( $request ); echo "status=" . $response->get_status() . "\n";'
```

If a Local.app site has `DB_HOST=localhost` and WP-CLI cannot connect to the
database, find the active Local MySQL socket and inject it through
`WP_CLI_MYSQL_SOCKET`:

```bash
find "$HOME/Library/Application Support/Local/run" -path '*/mysql/mysqld.sock' -print

WP_CLI_MYSQL_SOCKET="/path/to/Local/run/site-id/mysql/mysqld.sock"
php -d mysqli.default_socket="$WP_CLI_MYSQL_SOCKET" \
    -d pdo_mysql.default_socket="$WP_CLI_MYSQL_SOCKET" \
    "$(command -v wp)" --path="$WP_PATH" plugin status npcink-toolbox
```

If the global `wp` becomes unavailable, the fallback is a temporary WP-CLI phar
plus Local PHP and the active Local MySQL socket:

```bash
WP_CLI=/tmp/wp-cli.phar
WP_CLI_PHP="/Users/muze/Library/Application Support/Local/lightning-services/php-8.0.30+0/bin/darwin-arm64/bin/php"
WP_CLI_MYSQL_SOCKET="/Users/muze/Library/Application Support/Local/run/NPb24Zg9g/mysql/mysqld.sock"
WP_PATH="/Users/muze/Local Sites/npcink/app/public"
```

Adapter and Abilities smoke commands can use the same variables:

```bash
cd /Users/muze/gitee/npcink-abilities-toolkit
WP_CLI=/tmp/wp-cli.phar WP_CLI_PHP="$WP_CLI_PHP" WP_CLI_ERROR_REPORTING=8191 WP_CLI_MYSQL_SOCKET="$WP_CLI_MYSQL_SOCKET" WP_PATH="$WP_PATH" composer smoke:wp

cd /Users/muze/gitee/npcink-openclaw-adapter
WP_CLI=/tmp/wp-cli.phar WP_CLI_PHP="$WP_CLI_PHP" WP_CLI_ERROR_REPORTING=8191 WP_CLI_MYSQL_SOCKET="$WP_CLI_MYSQL_SOCKET" WP_PATH="$WP_PATH" composer smoke:wp
```

Do not manually re-fire `wp_abilities_api_categories_init` and
`wp_abilities_api_init` after WordPress has already loaded all active plugins;
that can produce duplicate notices from other active plugins unrelated to
Toolbox.

## REST Route Matrix

The first-version route matrix is exact:

- `GET /status`
- `POST /image-candidates`
- `POST /vector-search`
- `POST /knowledge-search`
- `POST /web-search/test`
- `POST /web-search/diagnostics`
- `POST /site-knowledge/search`
- `POST /site-knowledge/sync`
- `GET /site-knowledge/status`
- `POST /agent-feedback`
- `POST /agent-feedback/summary`
- `POST /ai/content-support`
- `POST /ai/site-helpers`
- `POST /ai/image-generation`
- `POST /flows/article-brief`
- `POST /flows/article-assistant`
- `POST /flows/article-plan`
- `POST /flows/image-candidate-adoption-plan`
- `POST /local-admin-consent/featured-image`
- `POST /flows/site-knowledge-review-plan`
- `POST /flows/nightly-inspection-review-plan`
- `POST /flows/content-metadata-apply-plan`
- `POST /flows/media-brief`
- `POST /editor/content-support`
- `POST /media-derivative-handoff`
- `GET /nightly-inspection/cloud-runtime-entitlement`
- `POST /nightly-inspection/cloud-batch`
- `GET /nightly-inspection/cloud-batch/recent`
- `GET /nightly-inspection/cloud-batch/{run_id}`
- `GET|POST /nightly-inspection/cloud-batch/{run_id}/result`
- `POST /nightly-inspection/cloud-batch/{run_id}/retry`

Do not add routes for publish, delivery, workflow-run consoles, queues,
schedulers, approval stores, write confirmation, featured-image mutation, media
upload/import, SEO mutation, indexing, or re-indexing without a new boundary
decision.

Nightly Inspection recent and retry routes are bounded Cloud detail bridges.
They do not create a server-side Toolbox run history, local queue, retry
processor, Core proposal, or WordPress write.

## Verification Gates

Default Toolbox gates:

```bash
composer test:all
composer validate --no-check-publish
git diff --check
```

`composer.json` intentionally omits a Composer `version` field. The plugin
version belongs in the plugin header and `readme.txt`.
