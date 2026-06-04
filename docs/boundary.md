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
5. Let external AI workflows compose context, search, image-source, and vector
   abilities as inputs, not as write authority.
6. Use WordPress abilities and Core proposals for final WordPress writes.

## AI Tool Composition Boundary

Toolbox may expose tool abilities needed by external AI workflows. Article
writing is one consumer, but the same abilities should also serve research,
comparison, support, media planning, page layout, source coverage, and other
bounded suggestion workflows.

- site content context;
- context validation;
- external research evidence for any workflow that needs source candidates;
- Cloud-managed site knowledge for semantic search, related content, writing
  context, internal-link candidates, refresh suggestions, or image context;
- local vector context for style, related articles, internal links, or image
  recommendation context;
- image-source candidates;
- suggestion-only SEO/AEO/GEO briefs;
- reviewed article write plans for Core handoff.

Toolbox must not own the drafting model, workflow runtime, content indexing,
media import, featured-image setting, SEO mutation, publishing, approval, or
audit trail. The final output of a composition run is a draft candidate,
research evidence pack, image recommendation, discoverability suggestion,
support/reference pack, comparison notes, or Core-ready plan.

Cloud-managed site knowledge may run through the Cloud Addon runtime seam or a
host-provided site knowledge filter. Toolbox remains the local Ability
registration and contract surface. Cloud may manage embeddings, vector storage,
indexing, reranking, and status detail, but it must not become a second
WordPress write owner, second ability registry, or local control plane.

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
- `/media-derivative-handoff`

Do not add Toolbox REST routes for publishing, delivery, workflow runs, queues,
schedulers, approvals, write confirmation, featured image setting, media
upload/import, SEO mutation, content indexing, or re-indexing without a new
boundary decision. Write-like outcomes must be prepared as suggestions or Core
proposal handoffs, not executed by Toolbox.

`/flows/article-plan` prepares a Core-ready `article_write_plan` for
`magick-ai-toolbox/build-article-write-plan`. It is a planning artifact route,
not a WordPress write route and not a Core proposal execution route.

`/media-derivative-handoff` prepares one-run ability input for
`magick-ai/build-media-derivative-cloud-request` from Core media policy defaults
and operator overrides. It is a planning artifact route. The admin media
derivative preview surface may call Adapter's bounded media-derivative recipe
to create one short-lived Cloud artifact and may submit a Core replacement
proposal containing artifact evidence. It may render the same-origin signed Adapter
preview proxy for operator review, but that URL is not a public Cloud URL or a
WordPress media write. Toolbox must not store site media policy truth, own
Cloud credentials, create an artifact registry, approve proposals, execute proposals, replace
attachment files, or update attachment metadata.

The same admin surface may call
`magick-ai/build-media-derivative-batch-plan` through Adapter
`run-read-ability` for bounded bulk requests such as date-range format
conversion. The batch surface may show candidates, skipped reasons, selected
per-attachment previews, and selected Core proposal submissions. It must still
use the per-attachment Adapter media derivative recipe for Cloud artifacts and
must not create a Toolbox-side media registry, approval queue, scheduler, or
write executor.

After a local media replacement has been approved and executed, the admin
surface may ask Adapter to run `magick-ai/build-media-reference-repair-plan`
and submit non-empty exact-match `patch-post-content` actions to Core
`/proposals/from-plan`. Toolbox must not search-replace post content directly,
rewrite sized variants automatically, or treat the repair plan as write truth.

For plugin/theme settings that contain hard-coded media URLs, the admin surface
may ask Adapter to run `magick-ai/build-media-settings-reference-repair-plan`
with local exclusion filters such as blocked formats and minimum dimensions,
then submit non-empty exact-match `patch-setting-value` actions to Core
`/proposals/from-plan`. Toolbox must not update options, theme mods, serialized
settings, or excluded small/logo/icon media directly.

## Content Context Boundary

Toolbox may store the non-secret `magick_ai_toolbox_content_context` option and
expose it through `magick-ai-toolbox/get-content-discoverability-context`.

The context can include site positioning, audience, brand voice, keywords,
allowed claims, forbidden claims, exception/special-case rules, SEO rules, AEO
rules, GEO rules, and fields that third-party AI may suggest in a
proposal-ready payload.

The context must not include provider keys, private credentials, request logs,
billing details, quotas, or final write authorization. Third-party AI callers
may consume it as suggestion-only guidance; they must not mutate it or use it
as permission to bypass Core governance.

## Connector Boundaries

### Tavily And Bocha

Tavily and Bocha own external web search results and source snippets.

Toolbox may store Tavily and Bocha API keys and submit bounded search requests.
Toolbox must not treat search results as verified truth; results are source
candidates for operator review. `magick-ai-toolbox/web-research` is a
general-purpose external source ability for any AI workflow that needs web
evidence, not only article drafting. Bocha is a synchronous search provider,
not a crawler or workflow runtime.

### Jina Reader

Jina Reader owns URL-to-clean-text extraction.

Toolbox may use Jina Reader to enhance a small number of selected search result
URLs after Tavily or Bocha has returned source candidates. Jina Reader must not
be represented as the default search provider, a crawler, a bulk extraction
job, a citation verifier, or a write authority.

### Image Source Providers

Unsplash, Pixabay, and Pexels own public photo/source search.

Toolbox may search and display image candidates from configured image-source
providers. Toolbox must preserve photographer attribution and source URLs.
Unsplash responses must also preserve `download_location` for future import
flows. Toolbox must not describe this as image generation, import media, set
featured images, or turn image-source search into a provider routing control
plane.

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

Jina Reranker is reserved for future workflow-level candidate reranking. It is
not part of the first runtime surface.

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
