# Magick AI Toolbox Boundary

Magick AI Toolbox owns product-facing tools and fixed-flow buttons.

Owned here:

- external research actions;
- image-source candidate actions;
- vector search actions;
- query embedding actions for vector search;
- fixed-flow buttons that return planning artifacts;
- non-secret content discoverability context for SEO, AEO, and GEO suggestion
  workflows;
- operator-facing admin UI for the toolbox.

Not owned here:

- Core governance truth;
- final WordPress write approval;
- reusable first-party WordPress ability definitions already owned by
  `magick-ai-abilities`;
- workflow runtime, queues, or MCP control-plane state;
- long-term provider billing, quota, and request log ownership.
- content indexing jobs, re-indexing, and vector collection lifecycle in the
  current stage.
- final SEO meta, slug, excerpt, FAQ, schema, media, or post writes without
  Core proposal approval.
- OpenClaw, Agent Gateway, Open API, or MCP projection truth.

First-version write posture:

1. Run research, image-source, or vector-search actions.
2. Return suggestions and handoff notes.
3. Expose operator-filled content context as read-only Abilities guidance.
4. Expose provider-backed actions through server-side Toolbox abilities without
   exposing provider keys to AI callers.
5. Use WordPress abilities and Core proposals for final WordPress writes.

## REST Route Boundary

The first-version REST surface is an allowlist, not an open namespace. Allowed
routes are limited to status, bounded provider-backed tool actions, and fixed
planning flows:

- `/status`
- `/web-research`
- `/image-candidates`
- `/vector-search`
- `/knowledge-search`
- `/flows/article-brief`
- `/flows/article-plan`
- `/flows/media-brief`

Do not add Toolbox REST routes for publishing, delivery, workflow runs, queues,
schedulers, approvals, write confirmation, featured image setting, media
upload/import, SEO mutation, content indexing, or re-indexing without a new
boundary decision. Write-like outcomes must be prepared as suggestions or Core
proposal handoffs, not executed by Toolbox.

`/flows/article-plan` prepares a Core-ready `article_write_plan` for
`magick-ai-toolbox/build-article-write-plan`. It is a planning artifact route,
not a WordPress write route and not a Core proposal execution route.

## Content Context Boundary

Toolbox may store the non-secret `magick_ai_toolbox_content_context` option and
expose it through `magick-ai-toolbox/get-content-discoverability-context`.

The context can include site positioning, audience, brand voice, keywords,
allowed claims, forbidden claims, SEO rules, AEO rules, GEO rules, and fields
that third-party AI may suggest in a proposal-ready payload.

The context must not include provider keys, private credentials, request logs,
billing details, quotas, or final write authorization. Third-party AI callers
may consume it as suggestion-only guidance; they must not mutate it or use it
as permission to bypass Core governance.

## Connector Boundaries

### Tavily

Tavily owns external web search results and source snippets.

Toolbox may store a Tavily API key and submit bounded search requests. Toolbox
must not treat Tavily results as verified truth; results are source candidates
for operator review.

### Unsplash

Unsplash owns photo search and photo download tracking.

Toolbox may search and display image candidates. Toolbox must preserve
photographer attribution, Unsplash source metadata, and `download_location` for
future import flows. Toolbox must not describe this as image generation.

### Qdrant

Qdrant owns vector collection query storage.

Toolbox may query a configured collection with a text query, supplied vector
JSON payload, or full Qdrant query object.

### SiliconFlow And Jina

SiliconFlow and Jina own text-to-vector embedding generation for the first
Toolbox vector-search path.

Toolbox may send a bounded query string to the configured embedding provider
and use the returned embedding only to query the configured vector database.
Toolbox does not own WordPress indexing, re-index jobs, stale index detection,
or vector collection lifecycle management.

Jina Reader and Jina Reranker are reserved for future workflow-level source
extraction and candidate reranking. They are not part of the first runtime
surface.

## Connector Status Catalog

The local connector settings page may show a compact read-only status catalog
for current and reserved providers. Its job is to identify:

- whether the current MVP connector is configured;
- whether the owner is `Local MVP config` or a `Future connector owner`;
- whether a provider is active, missing local setup, or reserved only;
- what boundary applies to the provider output.

The catalog must not become provider billing, quota, key-rotation, request-log,
or marketplace ownership. Those are future connector-owner concerns, not the
Toolbox MVP settings surface.
