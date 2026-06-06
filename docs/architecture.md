# Architecture

Status: MVP architecture.

## Components

| Component | Responsibility |
| --- | --- |
| `npcink-toolbox.php` | Plugin header and bootstrap. |
| `Plugin` | Shared service construction and hook registration. |
| `Settings` | Option defaults, sanitization, non-search connector secret lookup, and content context export. |
| `Provider_Client` | Cloud image-source runtime calls, explicit AI-generated image candidate normalization, Cloud-managed site knowledge calls, Cloud-managed web search status, and fixed-flow planning actions. |
| `Rest_Controller` | Admin-facing REST routes for tool execution. |
| `Admin_Page` | WordPress admin tool surface, connector settings form, content context form, and Npcink submenu fallback. |
| `Editor_Content_Support` | Post editor document panel entrypoint for fixed content-support flows. |
| `Abilities` | WordPress Abilities API exposure for Toolbox actions. |
| `assets/admin.js` | Vanilla JS for fixed tool form submission and summary-first result rendering. |
| `assets/admin.css` | Admin layout, summary/detail result panels, and tool result styling. |
| `assets/editor-content-support.js` | Block editor sidebar panel for publish preflight, taxonomy/tag, internal-link, and image-candidate support flows. |
| `assets/editor-content-support.css` | Compact editor-side layout for the content-support panel. |

## Current Data Storage

The MVP stores two WordPress options:

- `npcink_toolbox_settings`
- `npcink_toolbox_content_context`

The settings option may contain feature flags and non-vector connector keys when
the operator chooses not to use environment variables. It must not contain web
search provider keys, vector provider keys, embedding models, dimensions,
endpoints, or collection names.

The content context option stores non-secret SEO, AEO, and GEO guidance for
third-party AI callers. It must not contain provider keys, private credentials,
request logs, quotas, billing details, or write authorization.

No custom database tables are used in the first version.

## Provider Path

Current MVP provider flow:

1. Admin user submits a tool form or REST request.
2. `Rest_Controller` checks `manage_options`.
3. `Provider_Client` calls Cloud image-source runtime, a host AI image
   generation seam when explicitly requested, or Cloud-managed Site Knowledge.
   Cloud-managed web search is executed by Npcink Cloud rather than a local
   Toolbox provider route.
4. Toolbox returns `image_candidate.v1` image-source or generated-image
   candidates, Cloud site-knowledge context, Cloud-managed web search status,
   or planning output. Raw provider payloads are included only when the debug
   setting is enabled.
5. Any WordPress write remains a separate Abilities/Core handoff.

The current provider client is deliberately small. Future durable connector
ownership may move to connector plugins if quotas, billing, logs, multi-provider
routing, or key rotation become product requirements.

Current connector routes:

| Connector | API role | Current Toolbox action |
| --- | --- | --- |
| Cloud-managed web search | External web search | Npcink Cloud runtime, no local Toolbox route |
| Unsplash | Image-source candidates | `/image-candidates` |
| Pixabay | Image-source candidates | `/image-candidates` |
| Pexels | Image-source candidates | `/image-candidates` |
| Host AI image generation seam | AI-generated image candidates | `/image-candidates` with `provider=ai_generated` |
| Cloud Site Knowledge | Semantic site context | `/site-knowledge/*` and vector compatibility pointer |

The legacy `/vector-search` route remains only as a compatibility pointer. It
does not query a local vector database or call local embedding providers.
Vector provider details live in Npcink Cloud.

Reserved provider slots:

| Capability | Current provider | Reserved future providers |
| --- | --- | --- |
| External search | Npcink Cloud | Additional search providers are Cloud-owned by later contract. |
| Image source | Unsplash, Pixabay, Pexels | Additional image-source providers by later contract. |
| AI-generated image candidates | Caller-supplied generated URL or host filter | Durable AI image connector by later contract. |
| Site knowledge vector infrastructure | Npcink Cloud | Cloud operator console by later contract. |

## Abilities Path

Toolbox exposes its actions through the WordPress Abilities API when available.
Abilities are server-side Toolbox tool wrappers: AI callers provide task input,
Toolbox uses local configuration or Cloud runtime ownership to execute the
provider call, and the caller receives a normalized suggestion payload instead
of provider secrets. For AI composition, callers should treat provider-backed
abilities as reusable tool inputs. Cloud-managed web search is owned by Magick
AI Cloud, `search-image-source` is the general image-candidate ability, and
`search-site-knowledge` is the general Cloud-managed semantic site-context
ability. `vector-search` remains a compatibility pointer only. Article
briefs, article writing packs, and article write plans are only one workflow
family built from those lower-level tools.

If `npcink-abilities-toolkit` is active, Toolbox uses its public helper functions.
Otherwise, Toolbox falls back to native WordPress Abilities API registration.

Current ability ids:

- `npcink-toolbox/search-image-source`
- `npcink-toolbox/vector-search`
- `npcink-toolbox/search-site-knowledge`
- `npcink-toolbox/get-site-knowledge-status`
- `npcink-toolbox/request-site-knowledge-sync`
- `npcink-toolbox/build-article-brief`
- `npcink-toolbox/build-article-write-plan`
- `npcink-toolbox/build-article-batch-write-plan`
- `npcink-toolbox/build-article-media-batch-write-plan`
- `npcink-toolbox/build-image-candidate-adoption-plan`
- `npcink-toolbox/build-media-brief`
- `npcink-toolbox/get-content-discoverability-context`
- `npcink-toolbox/validate-content-discoverability-context`
- `npcink-toolbox/build-content-discoverability-brief`

These are read/suggestion tools. They must not imply final WordPress write
approval, media import approval, or indexing lifecycle ownership.
`npcink-toolbox/build-article-write-plan` assembles a Core-ready
`article_write_plan` for a reviewed draft and leaves proposal creation,
approval, preflight, audit, and final execution outside Toolbox.
`npcink-toolbox/build-article-media-batch-write-plan` assembles a
Core-ready `article_media_batch_write_plan` from reviewed drafts and reviewed
image-source candidates. It does not upload media, set featured images, approve
proposals, or execute writes.
`npcink-toolbox/build-image-candidate-adoption-plan` assembles a Core-ready
`image_candidate_adoption_plan` from one reviewed `image_candidate.v1`. It does
not import media, update metadata, set featured images, approve proposals, or
execute writes.
`npcink-toolbox/build-content-discoverability-brief` assembles a
suggestion-only SEO, AEO, and GEO instruction pack and proposal template for a
post or topic. It does not mutate SEO meta, slugs, excerpts, schema, or post
content.

Ability ids remain under `npcink-toolbox/*` to keep them distinct from Core
governance abilities and first-party reusable WordPress abilities. Ability
metadata declares Toolbox scopes:

- `cap.toolbox.image_source`
- `cap.toolbox.vector_search`
- `cap.toolbox.knowledge.search`
- `cap.toolbox.knowledge.read`
- `cap.toolbox.knowledge.sync`
- `cap.toolbox.workflow_suggest`
- `cap.toolbox.context.read`

Ability metadata also declares that provider execution is server-side, provider
secret exposure is `none`, write posture is `suggestion_only`, final writes use
Core proposals, and direct WordPress writes are disabled.
Provider-backed ability payloads keep the runtime contract smaller:
`artifact_type`, `composition_role`, `write_posture`, and
`direct_wordpress_write`.

Cloud-managed web search and Cloud-managed site knowledge abilities use the
Cloud Addon runtime seam, not local connector credentials.
`search-site-knowledge` is the high-level ability
for semantic site search, related content, writing context, internal-link
candidates, refresh suggestions, image-context lookup, FAQ candidates, content
gap analysis, and publish preflight duplicate checks. `vector-search` returns a
Cloud-managed compatibility pointer and should not be used for new low-level
vector integrations.

The host can intercept site knowledge execution with
`npcink_toolbox_site_knowledge_cloud_request` or adjust the runtime payload
with `npcink_toolbox_site_knowledge_runtime_payload`. Without a host or
Cloud Addon runtime client, these abilities fail closed. Toolbox does not store
Cloud credentials and does not own the Cloud index lifecycle.

`request-site-knowledge-sync` collects only public WordPress manifests before
calling Cloud: published posts and pages, plus bounded approved comments for
the selected public entries. The comment manifest carries public text and
source identifiers only. WordPress still owns moderation, edits, deletion, and
final write decisions.

The first admin REST surface remains `manage_options` gated by default.
External AI access should be mediated by Core/app-key scope checks in the host
that consumes these ability definitions. The host can use
`npcink_toolbox_rest_permission` and
`npcink_toolbox_ability_permission` as first-version integration hooks.

## REST Surface

Current routes require `manage_options`:

- `GET /wp-json/npcink-toolbox/v1/status`
- `POST /wp-json/npcink-toolbox/v1/image-candidates`
- `POST /wp-json/npcink-toolbox/v1/vector-search`
- `POST /wp-json/npcink-toolbox/v1/knowledge-search`
- `POST /wp-json/npcink-toolbox/v1/free-gpt55/content-support`
- `POST /wp-json/npcink-toolbox/v1/flows/article-brief`
- `POST /wp-json/npcink-toolbox/v1/flows/article-plan`
- `POST /wp-json/npcink-toolbox/v1/flows/image-candidate-adoption-plan`
- `POST /wp-json/npcink-toolbox/v1/flows/media-brief`
- `POST /wp-json/npcink-toolbox/v1/editor/content-support`

`/status` reports Cloud-backed surfaces as registered capabilities plus current
availability. `web_search_registered`, `vector_search_registered`,
`cloud_runtime.available`, and `free_gpt55.available` distinguish an installed
Toolbox UI contract from a connected Cloud Addon or host runtime that can
actually execute the request.

`/knowledge-search` remains as a compatibility alias for the first local MVP.
New clients should use `/vector-search`.

The route surface is intentionally controlled by a static matrix in
`tests/run.php`. The matrix must stay exact: adding a route requires updating
the allowlist and boundary docs in the same change. The first version must not
register routes whose purpose is publish, delivery, workflow-run display,
queue/scheduler ownership, approval, write confirmation, featured-image
mutation, media upload/import, SEO mutation, indexing, or re-indexing.

`/editor/content-support` is the post-editor entrypoint for fixed, bounded
support flows. It accepts current draft context plus one intent:
`publish_preflight`, `taxonomy_tags`, `internal_links`, `image_candidates`, or
`discoverability`. It returns an `editor_content_support_flow` artifact with
suggestion-only sections and no direct WordPress write posture.

## Admin Surface

When another Npcink plugin has registered the shared `npcink` parent menu,
Toolbox appears as:

- `Npcink -> Toolbox`
- `admin.php?page=npcink-toolbox`

The submenu position is `45`, intentionally after `npcink-abilities-toolkit` (`40`)
and before Cloud Addon (`50`).

When no Npcink parent menu exists, Toolbox falls back to:

- `Tools -> Npcink Toolbox`
- `tools.php?page=npcink-toolbox`

Tool result panels follow a summary-first display contract adapted from
Content Assistant product-surface discipline:

1. show the operator summary first;
2. show source, image, vector, or planning candidates next;
3. show governed handoff guidance before any write-like next step;
4. keep provider raw responses and complete payloads inside collapsed result
   disclosures.

The **Reviewed Draft Handoff** fallback tool has a dedicated panel for reviewed
draft title, draft body, SEO hints, and risk level. Its result renderer shows
`article_write_plan` workflow artifacts, the risk report, the final
`npcink-abilities-toolkit/create-draft` action, and the Core from-plan handoff route. It does
not submit the plan to Core or approve execution.

The admin **Content Support** tab groups fixed buttons by operator job. The
default **Everyday Support** group uses the same fixed
`/editor/content-support` intents as the post editor panel: discoverability,
publish preflight, taxonomy/tag candidates, internal-link candidates, and image
candidates. Media work and governed handoffs are separate groups. The combined
`Article Planning Bundle` is kept as a fallback bundle, not the default support
flow.

Toolbox also renders additive `operator_feedback` payloads from governed
handoff failures, including reasons, revision fields, next steps, retry state,
and Core evidence. This is display-only feedback for the operator; Core remains
the approval and preflight truth, and Adapter remains the OpenClaw execution
channel.

This is a display contract only. It does not add Content Assistant article,
comment, media, preview, confirm, or apply responsibilities to Toolbox.

## Editor Surface

Toolbox registers a **Npcink Content Support** document panel in the block
editor for users who can run the existing Toolbox REST tools. The panel is a
high-frequency entrypoint for the same fixed workflows that the admin surface
owns:

- publish/readiness preflight;
- taxonomy/tag candidates from existing WordPress terms;
- internal-link candidates through Cloud-managed Site Knowledge;
- image-source candidates through the configured Cloud image-source runtime.

The editor panel reads the current draft title, excerpt, content, terms, status,
and featured image id. It never mutates the draft, assigns terms, inserts links,
imports media, publishes content, or writes SEO fields. Write-like follow-up
must still go through Core proposals and reusable WordPress abilities.

Cloud Checks use compact tabs for Cloud-managed search, image-source,
preview-only media derivative, and Site Knowledge checks. Each panel opens
directly into the relevant Toolbox ability reachability check instead of
repeating provider ownership detail. Search checks use Cloud auto execution
only; provider selection, Jina Reader toggles, routing diagnostics, Cloud API
key verification, entitlement, quota, billing, and request logs belong in Cloud
Addon or Cloud service-plane surfaces. Image derivative checks may generate
short-lived Cloud previews only, including one-run text or image/logo watermark
overrides that match the Adapter/OpenClaw media derivative request shape; Core
proposal submission, batch proposal submission, and URL repair handoffs stay in
Content Support. The surface is not a billing, quota, request-log, marketplace,
provider-routing, key-rotation, vector-provider, or vector-lifecycle surface.

## Dependency Direction

Allowed:

- Toolbox may consume provider APIs.
- Toolbox may register WordPress abilities.
- Toolbox may submit future write handoffs to Core through public REST.
- Toolbox may consume `npcink-abilities-toolkit` public helper functions.
- Toolbox may expose non-secret content context as read-only Abilities guidance.

Disallowed:

- Toolbox requiring Core internals.
- Toolbox requiring Abilities internals.
- Toolbox writing directly to Core tables.
- Toolbox owning final write approval or audit truth.
- Core depending on Toolbox.
- Toolbox owning OpenClaw, Agent Gateway, Open API, or MCP projection truth.
- Toolbox claiming image-source search as AI image generation.
- Toolbox treating vector search as complete RAG/indexing ownership.
- Toolbox importing image candidates into the media library or setting featured
  images without a Core proposal.
