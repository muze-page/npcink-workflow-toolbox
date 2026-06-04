# First Version Reference

Status: active handoff note for future AI sessions.

This file summarizes the first working shape of `magick-ai-toolbox` after the
provider, Abilities API, settings, lifecycle, and local smoke-test work.

## Product Boundary

Magick AI Toolbox is an operator-facing AI tool plugin. It returns suggestions,
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

Write-like outcomes must be handed to WordPress abilities and Magick AI Core
proposal approval.

## Providers

Current runtime providers:

| Capability | Provider | Runtime status |
| --- | --- | --- |
| Cloud-managed web search | Magick AI Cloud | General external source candidates. Cloud owns provider configuration and routing. |
| Image source candidates | Unsplash | Active provider; preserve attribution and `download_location`. |
| Image source candidates | Pixabay | Active provider when configured; preserve attribution and source URL. |
| Image source candidates | Pexels | Active provider when configured; preserve attribution and source URL. |
| AI-generated image candidates | Caller URL or host filter | Explicit `ai_generated` mode; preserve prompt/model evidence and human license review status. |
| Site knowledge vector infrastructure | Magick AI Cloud | Cloud-managed embedding, vector storage, indexing, rerank, status, and search. |

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
magick_ai_toolbox_settings
```

Secrets:

- `TAVILY_API_KEY` / `MAGICK_AI_TOOLBOX_TAVILY_API_KEY`
- `BOCHA_API_KEY` / `MAGICK_AI_TOOLBOX_BOCHA_API_KEY`

Provider raw payloads are excluded by default. Enable
`include_raw_responses` only for debugging.

The first version is single-site global configuration. Do not add multisite or
per-user isolation without a new decision.

Connector settings now include a compact status catalog. It may show Cloud
entry points and missing Cloud connection state, but it must stay a read-only
orientation surface. Billing, quota, key rotation, request-log, marketplace,
provider routing, vector provider settings, and vector lifecycle controls do
not belong in Toolbox.

## Content Discoverability Context

The admin page also includes an operator-filled Content Context form for SEO,
AEO, and GEO guidance. It is stored separately from connector settings:

```text
magick_ai_toolbox_content_context
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

Toolbox ability ids stay under `magick-ai-toolbox/*`:

- `magick-ai-toolbox/search-image-source`
- `magick-ai-toolbox/vector-search`
- `magick-ai-toolbox/search-site-knowledge`
- `magick-ai-toolbox/get-site-knowledge-status`
- `magick-ai-toolbox/request-site-knowledge-sync`
- `magick-ai-toolbox/build-article-brief`
- `magick-ai-toolbox/build-article-write-plan`
- `magick-ai-toolbox/build-image-candidate-adoption-plan`
- `magick-ai-toolbox/build-media-brief`
- `magick-ai-toolbox/get-content-discoverability-context`
- `magick-ai-toolbox/validate-content-discoverability-context`
- `magick-ai-toolbox/build-content-discoverability-brief`

General-purpose provider abilities:

- Cloud-managed web search is the external source-candidate capability for any
  workflow that needs web evidence, comparison material, Chinese source lookup,
  public references, support context, source coverage, or article preparation.
  Magick AI Cloud owns provider configuration and execution; Toolbox does not
  register a local search ability.
- `magick-ai-toolbox/search-image-source` is the image-candidate ability for
  any workflow that needs sourced images. It returns `image_candidate.v1`
  candidates for stock, AI-generated, owned, or external image sources.
- `magick-ai-toolbox/build-image-candidate-adoption-plan` turns one reviewed
  `image_candidate.v1` into a Core-ready `image_candidate_adoption_plan` for
  media upload, metadata, and optional featured-image proposal intake.
- `magick-ai-toolbox/search-site-knowledge` is the Cloud-managed site knowledge
  ability for semantic site search, related content, writing context, internal
  links, refresh suggestions, or image context.
- `magick-ai-toolbox/vector-search` is a Cloud-managed site knowledge
  compatibility pointer for older clients.

Site knowledge status and sync:

- `magick-ai-toolbox/get-site-knowledge-status` reads Cloud-managed coverage
  and freshness state.
- `magick-ai-toolbox/request-site-knowledge-sync` requests bounded Cloud sync
  or rebuild work from public WordPress content. It does not write WordPress
  content and does not create a local indexing queue.

For article-writing AI callers, the canonical composition sequence is:

1. `magick-ai-toolbox/get-content-discoverability-context`
2. `magick-ai-toolbox/validate-content-discoverability-context`
3. Cloud-managed web search
4. `magick-ai-toolbox/vector-search`
5. `magick-ai-toolbox/search-image-source`
6. `magick-ai-toolbox/build-content-discoverability-brief`
7. `magick-ai-toolbox/build-article-brief`
8. `magick-ai-toolbox/build-article-write-plan`

The sequence is a recommendation for composing tool inputs, not a workflow
runtime contract. Toolbox does not schedule, retry, index, import media, publish
posts, or mutate SEO fields.

Stable first-version scopes:

- `cap.toolbox.image_source`
- `cap.toolbox.vector_search`
- `cap.toolbox.knowledge.search`
- `cap.toolbox.knowledge.read`
- `cap.toolbox.knowledge.sync`
- `cap.toolbox.workflow_suggest`
- `cap.toolbox.context.read`

Content context consumers should call
`magick-ai-toolbox/validate-content-discoverability-context` before using the
context for third-party AI workflows. For one post or topic, call
`magick-ai-toolbox/build-content-discoverability-brief` to get the
suggestion-only SEO/AEO/GEO instruction pack, proposal template, conservative
candidate values, and Core handoff reminders.

Do not rename these scopes unless Magick AI Core explicitly changes the app-key
scope contract.

## Ability Registration Lifecycle

Do not call `register_with_magick_ai_abilities()` synchronously during plugin
hook setup. That triggers translation too early on modern WordPress.

Current lifecycle:

- helper registration is deferred to `wp_abilities_api_categories_init` with
  priority `1`;
- native category registration skips if helper registration already succeeded;
- native category registration also checks `wp_has_ability_category()` before
  registering `magick-ai-toolbox`;
- native ability registration skips when helper registration already succeeded.

This prevents early textdomain notices and duplicate Toolbox category notices.

## Admin Surface

Preferred menu:

- `Magick AI -> Toolbox`
- `admin.php?page=magick-ai-toolbox`

When no shared Magick AI parent menu exists:

- `Tools -> Magick AI Toolbox`
- `tools.php?page=magick-ai-toolbox`

Submenu position is `45`, after Abilities and before Cloud Addon.

Toolbox may reuse Content Assistant's product-surface discipline, but only as a
UI and contract pattern. The default result surface should show summary,
candidates, governed handoff, and then collapsed details. Do not import Content
Assistant article/comment/media lanes, local write flows, or runtime ownership
into Toolbox.

## Local Smoke Environment

Verified local site path:

```bash
/Users/muze/Local Sites/magick-ai/app/public
```

Verified plugin symlink:

```bash
/Users/muze/Local Sites/magick-ai/app/public/wp-content/plugins/magick-ai-toolbox -> /Users/muze/gitee/magick-ai-toolbox
```

Global `wp` may not be installed. The verified fallback is a temporary WP-CLI
phar plus Local PHP and the active Local MySQL socket:

```bash
WP_CLI=/tmp/wp-cli.phar
WP_CLI_PHP="/Users/muze/Library/Application Support/Local/lightning-services/php-8.0.30+0/bin/darwin-arm64/bin/php"
WP_CLI_MYSQL_SOCKET="/Users/muze/Library/Application Support/Local/run/NPb24Zg9g/mysql/mysqld.sock"
WP_PATH="/Users/muze/Local Sites/magick-ai/app/public"
```

Do not write local admin passwords into repository files.

Useful smoke commands:

```bash
"$WP_CLI_PHP" -d mysqli.default_socket="$WP_CLI_MYSQL_SOCKET" -d pdo_mysql.default_socket="$WP_CLI_MYSQL_SOCKET" "$WP_CLI" --path="$WP_PATH" plugin activate magick-ai-toolbox

"$WP_CLI_PHP" -d mysqli.default_socket="$WP_CLI_MYSQL_SOCKET" -d pdo_mysql.default_socket="$WP_CLI_MYSQL_SOCKET" "$WP_CLI" --path="$WP_PATH" plugin status magick-ai-toolbox

"$WP_CLI_PHP" -d mysqli.default_socket="$WP_CLI_MYSQL_SOCKET" -d pdo_mysql.default_socket="$WP_CLI_MYSQL_SOCKET" "$WP_CLI" --path="$WP_PATH" eval 'wp_set_current_user( 1 ); do_action( "rest_api_init" ); $request = new WP_REST_Request( "GET", "/magick-ai-toolbox/v1/status" ); $response = rest_do_request( $request ); echo "status=" . $response->get_status() . "\n";'
```

Adapter and Abilities smoke commands can use the same variables:

```bash
cd /Users/muze/gitee/magick-ai-abilities
WP_CLI=/tmp/wp-cli.phar WP_CLI_PHP="$WP_CLI_PHP" WP_CLI_ERROR_REPORTING=8191 WP_CLI_MYSQL_SOCKET="$WP_CLI_MYSQL_SOCKET" WP_PATH="$WP_PATH" composer smoke:wp

cd /Users/muze/gitee/magick-ai-adapter
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
- `POST /flows/article-brief`
- `POST /flows/article-plan`
- `POST /flows/image-candidate-adoption-plan`
- `POST /flows/media-brief`

Do not add routes for publish, delivery, workflow-run consoles, queues,
schedulers, approval stores, write confirmation, featured-image mutation, media
upload/import, SEO mutation, indexing, or re-indexing without a new boundary
decision.

## Verification Gates

Default Toolbox gates:

```bash
composer test:all
composer validate --no-check-publish
git diff --check
```

`composer.json` intentionally omits a Composer `version` field. The plugin
version belongs in the plugin header and `readme.txt`.
