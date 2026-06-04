# Magick AI Toolbox

Magick AI Toolbox is an operator-facing WordPress plugin for Tavily and Bocha
research, Jina Reader result enhancement, Unsplash, Pixabay, and Pexels
image-source candidates, SiliconFlow or Jina query embeddings, Qdrant vector
search, and fixed-flow AI actions.

It is intentionally separate from:

- `magick-ai-core`, which owns governance, proposal records, approval, and audit;
- `magick-ai-abilities`, which owns reusable WordPress Abilities API contracts;
- provider connector plugins, which can later own richer key management,
  provider selection, quota, and request logs.

## First Version

The first version provides:

- a Magick AI admin page at **Magick AI -> Toolbox** when a Magick AI host menu
  exists, with a **Tools -> Magick AI Toolbox** fallback for standalone installs;
- Tavily, Bocha, Jina Reader, Unsplash, Pixabay, Pexels, SiliconFlow, Jina, and
  Qdrant connector settings with environment-variable support;
- an operator-filled content discoverability context for SEO, AEO, and GEO
  guidance that can be exposed to third-party AI callers;
- REST endpoints for web research, image-source candidates, vector search,
  article briefs, media briefs, and media derivative handoffs;
- WordPress Abilities API registrations for the same tool actions;
- static tests and PHP syntax linting.

## Boundary

Toolbox returns suggestions and planning artifacts. It does not directly update
posts, upload media, publish content, or bypass governance. WordPress writes
should continue through WordPress abilities and Core proposal approval.

Project goals, ownership, and future-session instructions are documented in:

- [Product Positioning](docs/product-positioning.md)
- [Boundary](docs/boundary.md)
- [Architecture](docs/architecture.md)
- [Roadmap](docs/roadmap.md)
- [AI Content Composition Abilities](docs/ai-content-composition-abilities.md)
- [Connector Ability Exposure](docs/connector-ability-exposure.md)
- [Content Discoverability Context](docs/content-discoverability-context.md)
- [OpenClaw Content Discoverability Handoff](docs/openclaw-content-discoverability-handoff.md)
- [Content Assistant Surface Lessons](docs/content-assistant-surface-lessons.md)
- [Article Assistant Workbench](docs/article-assistant-workbench.md)
- [Development Workflow](docs/development-workflow.md)
- [ADR-001: Build Toolbox As A Product Surface](docs/decisions/ADR-001-toolbox-as-product-surface.md)
- [ADR-002: Expose Content Context Through Abilities](docs/decisions/ADR-002-content-context-via-abilities.md)

## REST Routes

All routes require a logged-in user with `manage_options`.

- `GET /wp-json/magick-ai-toolbox/v1/status`
- `POST /wp-json/magick-ai-toolbox/v1/web-research`
- `POST /wp-json/magick-ai-toolbox/v1/image-candidates`
- `POST /wp-json/magick-ai-toolbox/v1/vector-search`
- `POST /wp-json/magick-ai-toolbox/v1/knowledge-search`
- `POST /wp-json/magick-ai-toolbox/v1/flows/article-brief`
- `POST /wp-json/magick-ai-toolbox/v1/flows/article-assistant`
- `POST /wp-json/magick-ai-toolbox/v1/flows/article-plan`
- `POST /wp-json/magick-ai-toolbox/v1/flows/media-brief`
- `POST /wp-json/magick-ai-toolbox/v1/media-derivative-handoff`

Toolbox admin result panels can render governed `operator_feedback` payloads
from Adapter/Core handoff failures. The feedback is for operator revision only;
Toolbox may submit a media derivative Core replacement proposal from the Adapter
recipe, but it does not approve proposals, execute proposals, or perform
WordPress writes.

## Abilities

Toolbox abilities are server-side tool wrappers. External AI callers provide
task input and receive normalized suggestion payloads; they do not receive
provider API keys or direct provider credentials.

General AI composition guidance is kept in
[AI Content Composition Abilities](docs/ai-content-composition-abilities.md).

When the WordPress Abilities API is available, Toolbox registers:

- `magick-ai-toolbox/web-research`
- `magick-ai-toolbox/search-image-source`
- `magick-ai-toolbox/vector-search`
- `magick-ai-toolbox/search-site-knowledge`
- `magick-ai-toolbox/get-site-knowledge-status`
- `magick-ai-toolbox/request-site-knowledge-sync`
- `magick-ai-toolbox/build-article-brief`
- `magick-ai-toolbox/build-article-assistant`
- `magick-ai-toolbox/build-article-write-plan`
- `magick-ai-toolbox/build-media-brief`
- `magick-ai-toolbox/get-content-discoverability-context`
- `magick-ai-toolbox/validate-content-discoverability-context`
- `magick-ai-toolbox/build-content-discoverability-brief`
- `magick-ai-toolbox/build-ai-article-writing-pack`

When `magick-ai-abilities` is active, Toolbox uses its public registration
helpers so the tools can be discovered by existing Magick AI consumers.
Toolbox ability ids stay under `magick-ai-toolbox/*` so they do not collide with
Core governance abilities or first-party WordPress abilities.

Ability metadata includes Toolbox scopes such as `cap.toolbox.search`,
`cap.toolbox.image_source`, `cap.toolbox.vector_search`, and
`cap.toolbox.workflow_suggest`. Content context uses
`cap.toolbox.context.read`. The first admin REST surface remains
`manage_options` gated; external AI/app-key authorization should be enforced by
Core or the host that consumes the ability scope metadata. First-version host
integration hooks are `magick_ai_toolbox_rest_permission` and
`magick_ai_toolbox_ability_permission`.

## Content Discoverability Context

The admin page includes a Content Context form for operator-maintained SEO, AEO,
and GEO guidance: site positioning, target audience, brand voice, keywords,
allowed and forbidden claims, exception rules, SEO/AEO/GEO rules, and proposal
fields AI may suggest. It is stored in `magick_ai_toolbox_content_context`,
separate from connector settings that may contain provider keys.

The context is exposed only as read-only, suggestion-only guidance through
`magick-ai-toolbox/get-content-discoverability-context`. Third-party AI callers
may also call `magick-ai-toolbox/validate-content-discoverability-context` to
check filling quality and `magick-ai-toolbox/build-content-discoverability-brief`
to get the primary lightweight SEO/AEO/GEO contract: per-section rules,
exception/special-case rules, proposal template, and conservative candidates
from supplied post or topic input. Final WordPress writes still require Core
proposal approval.

For natural-language article requests from OpenClaw or another external AI,
`magick-ai-toolbox/build-ai-article-writing-pack` composes the context,
validation result, discoverability brief, writing instructions, and guardrails
into one suggestion-only pack. It is a convenience fallback for broad prompts,
not the primary SEO/AEO/GEO context contract.

The Article Assistant flow and `magick-ai-toolbox/build-article-assistant`
ability compose one local `article_draft_v1` workbench artifact from topic,
evidence candidates, image-source candidates, site context, operator notes, and
an optional reviewed draft. It does not run a cloud writer or workflow runtime.
Only when the operator supplies a reviewed draft and risk checks pass does the
artifact include an `article_write_plan` for Core proposal intake.
This is a local Article Assistant Workbench, not an article generator or Cloud
writing feature. Keep it to one article, reviewed artifacts, and one optional
Core-ready draft proposal.

The article plan flow and `magick-ai-toolbox/build-article-write-plan` ability
assemble a Core-ready `article_write_plan` for a reviewed draft. They do not
call Core, approve proposals, publish content, or write WordPress data.
The admin **Try Tools** surface includes an **Article Write Plan** panel that
renders the plan artifacts, risk report, final `magick-ai/create-draft` action,
and Core handoff route for operator review.

The media derivative preview flow reads Core media optimization defaults when
available, accepts one-run operator overrides, and lets an operator select one
image attachment from the media library. The admin action surface dispatches the
bounded Adapter media-derivative recipe, polls the short-lived Cloud artifact
result, renders the same-origin signed Adapter preview proxy when available,
and can submit a Core replacement proposal with the artifact evidence. The same
surface can build a bounded batch conversion plan, show candidates and skipped
reasons, generate selected previews, and submit selected Core replacement
proposals; it still processes each candidate through the governed per-attachment
Adapter recipe. Toolbox
can also build a local media reference repair plan for exact hard-coded URL
matches and submit that plan to Core from-plan intake as `patch-post-content`
actions. For theme/plugin settings that store hard-coded media URLs, Toolbox can
build a filtered settings reference repair plan with excluded formats and minimum
image dimensions, then submit exact `patch-setting-value` actions to Core.
Toolbox does not store the site media policy, own Cloud credentials, create an
artifact registry, approve proposals, execute proposals, replace files, write
attachment metadata, patch post content, or update options/theme mods directly.

## Connector Configuration

The plugin reads connector keys in this order:

1. provider-specific PHP constant;
2. provider-specific environment variable;
3. stored WordPress option from the Toolbox settings page.

Supported environment variables:

- `TAVILY_API_KEY`
- `BOCHA_API_KEY`
- `UNSPLASH_ACCESS_KEY`
- `PIXABAY_API_KEY`
- `PEXELS_API_KEY`
- `QDRANT_API_KEY`
- `SILICONFLOW_API_KEY`
- `JINA_API_KEY`

Supported PHP constants:

- `MAGICK_AI_TOOLBOX_TAVILY_API_KEY`
- `MAGICK_AI_TOOLBOX_BOCHA_API_KEY`
- `MAGICK_AI_TOOLBOX_UNSPLASH_ACCESS_KEY`
- `MAGICK_AI_TOOLBOX_PIXABAY_API_KEY`
- `MAGICK_AI_TOOLBOX_PEXELS_API_KEY`
- `MAGICK_AI_TOOLBOX_QDRANT_API_KEY`
- `MAGICK_AI_TOOLBOX_SILICONFLOW_API_KEY`
- `MAGICK_AI_TOOLBOX_JINA_API_KEY`

Image-source search supports `auto`, `unsplash`, `pixabay`, and `pexels`
provider modes. `auto` uses configured image-source provider keys on the
server and returns one normalized `image_source_candidates` payload for any AI
caller that needs images, whether the use case is article drafting, media
planning, layout suggestions, reference selection, or another image-dependent
workflow. This is still source search, not image generation or media import.

Web research is also a general-purpose ability. `magick-ai-toolbox/web-research`
supports `tavily`, `bocha`, and `auto` provider modes and returns normalized
external source candidates for any AI workflow that needs fresh web evidence,
comparison material, Chinese source lookup, source coverage, product research,
support context, or article preparation. Optional Jina Reader enhancement reads
selected result URLs into cleaner text snippets for evidence packs. It does not
verify truth, write WordPress content, or expose provider keys.

The current vector MVP accepts a natural-language `query` and uses the
configured embedding provider to create an embedding before querying Qdrant.
SiliconFlow remains the default provider with `BAAI/bge-m3`. Jina AI is an
optional embedding provider with `jina-embeddings-v3`. It also accepts a
supplied vector JSON payload or full Qdrant query object for clients that
already own embedding.

Cloud-managed site knowledge is the preferred high-level ability surface for
semantic site search, related content, writing context, internal-link
candidates, refresh suggestions, image-context lookup, FAQ candidates, content
gap analysis, and publish preflight duplicate checks. Toolbox registers
`search-site-knowledge`, `get-site-knowledge-status`, and
`request-site-knowledge-sync` as WordPress Abilities. These abilities call
Magick AI Cloud through the Cloud Addon runtime seam or the
`magick_ai_toolbox_site_knowledge_cloud_request` host filter; Toolbox does not
store Cloud credentials, own Qdrant collection lifecycle, or write WordPress
content.

Sync requests send bounded public WordPress manifests: published posts and
pages, plus recent approved comments attached to those indexed public entries.
Comment payloads include only public comment text and source IDs needed for
Cloud indexing; moderation, edits, deletion, and final writes remain local
WordPress responsibilities.

The admin **Site Knowledge** tab lets operators refresh, rebuild, or delete the
Cloud-managed index and inspect coverage without configuring vector provider
keys in Toolbox. Cloud owns embedding, vector storage, and detailed run health;
Toolbox only starts sync from local public content and displays returned status.

The default embedding dimension is `1024`, matching `BAAI/bge-m3` guidance.
Create the Qdrant collection with vector size `1024` and `Cosine` distance for
the default configuration. If the embedding provider returns a vector whose
length does not match the configured dimension, Toolbox returns an explicit
dimension mismatch error before querying Qdrant.

Provider responses return normalized fields by default. Set **Include provider
raw responses** to include raw provider payloads for debugging.

Reserved future provider slots:

- vector database: Pinecone and Weaviate;
- workflow-level enhancement: Jina Reranker for candidate reranking.

## Development

```bash
composer test:all
```

The current gate runs PHP syntax linting and static contract checks.
