# Architecture

Status: MVP architecture.

## Components

| Component | Responsibility |
| --- | --- |
| `magick-ai-toolbox.php` | Plugin header and bootstrap. |
| `Plugin` | Shared service construction and hook registration. |
| `Settings` | Option defaults, sanitization, connector secret lookup, and content context export. |
| `Provider_Client` | Minimal Tavily, Unsplash, SiliconFlow, Jina, and Qdrant HTTP calls for MVP tool actions. |
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
3. `Provider_Client` calls Tavily, Unsplash, SiliconFlow, Jina, or Qdrant.
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
| Unsplash | Image-source candidates | `/image-candidates` |
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
Jina Reader and Jina Reranker remain reserved workflow-level enhancements and
are not part of the first runtime path.

Reserved provider slots:

| Capability | Current provider | Reserved future providers |
| --- | --- | --- |
| External search | Tavily | Additional search providers by later contract. |
| Image source | Unsplash | Pixabay, Pexels. |
| Query embedding | SiliconFlow | Jina AI. |
| Vector database | Qdrant | Pinecone, Weaviate. |

## Abilities Path

Toolbox exposes its actions through the WordPress Abilities API when available.
Abilities are server-side Toolbox tool wrappers: AI callers provide task input,
Toolbox uses local connector configuration to execute the provider call, and the
caller receives a normalized suggestion payload instead of provider secrets.

If `magick-ai-abilities` is active, Toolbox uses its public helper functions.
Otherwise, Toolbox falls back to native WordPress Abilities API registration.

Current ability ids:

- `magick-ai-toolbox/web-research`
- `magick-ai-toolbox/search-image-source`
- `magick-ai-toolbox/vector-search`
- `magick-ai-toolbox/build-article-brief`
- `magick-ai-toolbox/build-media-brief`
- `magick-ai-toolbox/get-content-discoverability-context`

These are read/suggestion tools. They must not imply final WordPress write
approval, media import approval, or indexing lifecycle ownership.

Ability ids remain under `magick-ai-toolbox/*` to keep them distinct from Core
governance abilities and first-party reusable WordPress abilities. Ability
metadata declares Toolbox scopes:

- `cap.toolbox.search`
- `cap.toolbox.image_source`
- `cap.toolbox.vector_search`
- `cap.toolbox.workflow_suggest`
- `cap.toolbox.context.read`

Ability metadata also declares that provider execution is server-side, provider
secret exposure is `none`, write posture is `suggestion_only`, final writes use
Core proposals, and direct WordPress writes are disabled.

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
- Toolbox claiming Unsplash image search as AI image generation.
- Toolbox treating vector search as complete RAG/indexing ownership.
- Toolbox importing image candidates into the media library or setting featured
  images without a Core proposal.
