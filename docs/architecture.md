# Architecture

Status: MVP architecture.

## Components

| Component | Responsibility |
| --- | --- |
| `magick-ai-toolbox.php` | Plugin header and bootstrap. |
| `Plugin` | Shared service construction and hook registration. |
| `Settings` | Option defaults, sanitization, connector secret lookup, and content context export. |
| `Provider_Client` | Minimal Tavily, Bocha, Jina Reader, Unsplash, Pixabay, Pexels, SiliconFlow, Jina, and Qdrant HTTP calls for MVP tool actions. |
| `Rest_Controller` | Admin-facing REST routes for tool execution. |
| `Admin_Page` | WordPress admin tool surface, connector settings form, content context form, and Magick AI submenu fallback. |
| `Abilities` | WordPress Abilities API exposure for Toolbox actions. |
| `assets/admin.js` | Vanilla JS for fixed tool form submission and summary-first result rendering. |
| `assets/admin.css` | Admin layout, summary/detail result panels, and tool result styling. |

## Current Data Storage

The MVP stores two WordPress options:

- `magick_ai_toolbox_settings`
- `magick_ai_toolbox_content_context`

The settings option may contain connector endpoints, feature flags, collection
names, and connector keys when the operator chooses not to use environment
variables.

The content context option stores non-secret SEO, AEO, and GEO guidance for
third-party AI callers. It must not contain provider keys, private credentials,
request logs, quotas, billing details, or write authorization.

No custom database tables are used in the first version.

## Provider Path

Current MVP provider flow:

1. Admin user submits a tool form or REST request.
2. `Rest_Controller` checks `manage_options`.
3. `Provider_Client` calls Tavily, Bocha, optional Jina Reader, Unsplash,
   Pixabay, Pexels, SiliconFlow, Jina, or Qdrant.
4. Toolbox returns normalized source results, image-source candidates, vector
   matches, or planning output. Raw provider payloads are included only when
   the debug setting is enabled.
5. Any WordPress write remains a separate Abilities/Core handoff.

The current provider client is deliberately small. Future durable connector
ownership may move to connector plugins if quotas, billing, logs, multi-provider
routing, or key rotation become product requirements.

Current connector routes:

| Connector | API role | Current Toolbox action |
| --- | --- | --- |
| Tavily | External web search | `/web-research` |
| Bocha | External web search | `/web-research` |
| Jina Reader | Search result URL extraction | `/web-research` enhancement only |
| Unsplash | Image-source candidates | `/image-candidates` |
| Pixabay | Image-source candidates | `/image-candidates` |
| Pexels | Image-source candidates | `/image-candidates` |
| SiliconFlow | Default query text embedding | `/vector-search` when input is text |
| Jina AI | Optional query text embedding | `/vector-search` when input is text and Jina is selected |
| Qdrant | Vector collection query | `/vector-search` |

The Qdrant action accepts a natural-language `query`, a vector JSON payload, or
a full Qdrant query object. Natural-language input is embedded through the
configured embedding provider before Qdrant is queried.

Default vector contract:

- default embedding provider: SiliconFlow;
- default model: `BAAI/bge-m3`;
- default dimensions: `1024`;
- recommended Qdrant distance: `Cosine`.

Jina AI is available as an optional embedding provider for the first version.
Jina Reader is available as a bounded post-search enhancement for selected
result URLs. Jina Reranker remains a reserved workflow-level enhancement and is
not part of the first runtime path.

Reserved provider slots:

| Capability | Current provider | Reserved future providers |
| --- | --- | --- |
| External search | Tavily, Bocha | Additional search providers by later contract. |
| Search result extraction | Jina Reader | Additional extraction providers by later contract. |
| Image source | Unsplash, Pixabay, Pexels | Additional image-source providers by later contract. |
| Query embedding | SiliconFlow | Jina AI. |
| Vector database | Qdrant | Pinecone, Weaviate. |

## Abilities Path

Toolbox exposes its actions through the WordPress Abilities API when available.
Abilities are server-side Toolbox tool wrappers: AI callers provide task input,
Toolbox uses local connector configuration to execute the provider call, and the
caller receives a normalized suggestion payload instead of provider secrets.
For AI composition, callers should treat provider-backed abilities as reusable
tool inputs. `web-research` is the general external source-candidate ability,
`search-image-source` is the general image-candidate ability, and
`vector-search` is the general configured vector-query ability. Article
briefs, article writing packs, and article write plans are only one workflow
family built from those lower-level tools.

If `magick-ai-abilities` is active, Toolbox uses its public helper functions.
Otherwise, Toolbox falls back to native WordPress Abilities API registration.

Current ability ids:

- `magick-ai-toolbox/web-research`
- `magick-ai-toolbox/search-image-source`
- `magick-ai-toolbox/vector-search`
- `magick-ai-toolbox/search-site-knowledge`
- `magick-ai-toolbox/get-site-knowledge-status`
- `magick-ai-toolbox/request-site-knowledge-sync`
- `magick-ai-toolbox/build-article-brief`
- `magick-ai-toolbox/build-article-write-plan`
- `magick-ai-toolbox/build-media-brief`
- `magick-ai-toolbox/get-content-discoverability-context`
- `magick-ai-toolbox/validate-content-discoverability-context`
- `magick-ai-toolbox/build-content-discoverability-brief`

These are read/suggestion tools. They must not imply final WordPress write
approval, media import approval, or indexing lifecycle ownership.
`magick-ai-toolbox/build-article-write-plan` assembles a Core-ready
`article_write_plan` for a reviewed draft and leaves proposal creation,
approval, preflight, audit, and final execution outside Toolbox.
`magick-ai-toolbox/build-content-discoverability-brief` assembles a
suggestion-only SEO, AEO, and GEO instruction pack and proposal template for a
post or topic. It does not mutate SEO meta, slugs, excerpts, schema, or post
content.

Ability ids remain under `magick-ai-toolbox/*` to keep them distinct from Core
governance abilities and first-party reusable WordPress abilities. Ability
metadata declares Toolbox scopes:

- `cap.toolbox.search`
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

Cloud-managed site knowledge abilities use the Cloud Addon runtime seam, not
local connector credentials. `search-site-knowledge` is the high-level ability
for semantic site search, related content, writing context, internal-link
candidates, refresh suggestions, image-context lookup, FAQ candidates, content
gap analysis, and publish preflight duplicate checks. `vector-search` remains
the low-level Qdrant query ability for clients that explicitly need a configured
vector query.

The host can intercept site knowledge execution with
`magick_ai_toolbox_site_knowledge_cloud_request` or adjust the runtime payload
with `magick_ai_toolbox_site_knowledge_runtime_payload`. Without a host or
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
`magick_ai_toolbox_rest_permission` and
`magick_ai_toolbox_ability_permission` as first-version integration hooks.

## REST Surface

Current routes require `manage_options`:

- `GET /wp-json/magick-ai-toolbox/v1/status`
- `POST /wp-json/magick-ai-toolbox/v1/web-research`
- `POST /wp-json/magick-ai-toolbox/v1/image-candidates`
- `POST /wp-json/magick-ai-toolbox/v1/vector-search`
- `POST /wp-json/magick-ai-toolbox/v1/knowledge-search`
- `POST /wp-json/magick-ai-toolbox/v1/flows/article-brief`
- `POST /wp-json/magick-ai-toolbox/v1/flows/article-plan`
- `POST /wp-json/magick-ai-toolbox/v1/flows/media-brief`

`/knowledge-search` remains as a compatibility alias for the first local MVP.
New clients should use `/vector-search`.

The route surface is intentionally controlled by a static matrix in
`tests/run.php`. The matrix must stay exact: adding a route requires updating
the allowlist and boundary docs in the same change. The first version must not
register routes whose purpose is publish, delivery, workflow-run display,
queue/scheduler ownership, approval, write confirmation, featured-image
mutation, media upload/import, SEO mutation, indexing, or re-indexing.

## Admin Surface

When another Magick AI plugin has registered the shared `magick-ai` parent menu,
Toolbox appears as:

- `Magick AI -> Toolbox`
- `admin.php?page=magick-ai-toolbox`

The submenu position is `45`, intentionally after `magick-ai-abilities` (`40`)
and before Cloud Addon (`50`).

When no Magick AI parent menu exists, Toolbox falls back to:

- `Tools -> Magick AI Toolbox`
- `tools.php?page=magick-ai-toolbox`

Tool result panels follow a summary-first display contract adapted from
Content Assistant product-surface discipline:

1. show the operator summary first;
2. show source, image, vector, or planning candidates next;
3. show governed handoff guidance before any write-like next step;
4. keep provider raw responses and complete payloads inside collapsed result
   disclosures.

The **Article Write Plan** tool has a dedicated panel for reviewed draft title,
draft body, SEO hints, and risk level. Its result renderer shows
`article_write_plan` workflow artifacts, the risk report, the final
`magick-ai/create-draft` action, and the Core from-plan handoff route. It does
not submit the plan to Core or approve execution.

Toolbox also renders additive `operator_feedback` payloads from governed
handoff failures, including reasons, revision fields, next steps, retry state,
and Core evidence. This is display-only feedback for the operator; Core remains
the approval and preflight truth, and Adapter remains the OpenClaw execution
channel.

This is a display contract only. It does not add Content Assistant article,
comment, media, preview, confirm, or apply responsibilities to Toolbox.

Connector settings use a compact status catalog before editable fields. The
catalog separates `Local MVP config` providers from `Future connector owner`
slots, keeps reserved providers visible as planning context, and repeats the
provider boundary where needed. It is not a billing, quota, request-log,
marketplace, or key-rotation surface.

## Dependency Direction

Allowed:

- Toolbox may consume provider APIs.
- Toolbox may register WordPress abilities.
- Toolbox may submit future write handoffs to Core through public REST.
- Toolbox may consume `magick-ai-abilities` public helper functions.
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
