# Magick AI Toolbox

Magick AI Toolbox is an operator-facing WordPress plugin for Cloud-managed web
search, Cloud-managed image-source candidates, Cloud-managed site knowledge,
and fixed-flow AI actions.

It is intentionally separate from:

- `magick-ai-core`, which owns governance, proposal records, approval, and audit;
- `magick-ai-abilities`, which owns reusable WordPress Abilities API contracts;
- provider connector plugins, which can later own richer key management,
  provider selection, quota, and request logs.

## First Version

The first version provides:

- a Magick AI admin page at **Magick AI -> Toolbox** when a Magick AI host menu
  exists, with a **Tools -> Magick AI Toolbox** fallback for standalone installs;
- Cloud-managed web search status, plus read-only Cloud-managed image-source
  and vector availability;
- an operator-filled content discoverability context for SEO, AEO, and GEO
  guidance that can be exposed to third-party AI callers;
- REST endpoints for image-source candidates, site knowledge/search, content
  discoverability, article-support fallback flows, media briefs, and media
  derivative handoffs;
- WordPress Abilities API registrations for the same tool actions;
- static tests and PHP syntax linting.

## Boundary

Toolbox returns suggestions and planning artifacts. It does not directly update
posts, upload media, publish content, or bypass governance. WordPress writes
should continue through WordPress abilities and Core proposal approval.

The default product posture is content support outside the article body:
taxonomy/tag candidates, internal-link candidates, image candidates,
SEO/AEO/GEO suggestions, media metadata plans, and publish-readiness checks.
Human editors own the article text. Article Assistant exists only as a fallback
workbench for reviewed local draft artifacts.

Project goals, ownership, and future-session instructions are documented in:

- [Product Positioning](docs/product-positioning.md)
- [Boundary](docs/boundary.md)
- [Architecture](docs/architecture.md)
- [Roadmap](docs/roadmap.md)
- [AI Content Composition Abilities](docs/ai-content-composition-abilities.md)
- [Connector Ability Exposure](docs/connector-ability-exposure.md)
- [Content Discoverability Context](docs/content-discoverability-context.md)
- [OpenClaw Content Discoverability Handoff](docs/openclaw-content-discoverability-handoff.md)
- [OpenClaw SEO/GEO/AEO Acceptance Summary](docs/openclaw-seo-geo-aeo-acceptance-summary.md)
- [Content Assistant Surface Lessons](docs/content-assistant-surface-lessons.md)
- [Article Assistant Workbench](docs/article-assistant-workbench.md)
- [Development Workflow](docs/development-workflow.md)
- [ADR-001: Build Toolbox As A Product Surface](docs/decisions/ADR-001-toolbox-as-product-surface.md)
- [ADR-002: Expose Content Context Through Abilities](docs/decisions/ADR-002-content-context-via-abilities.md)

## REST Routes

All routes require a logged-in user with `manage_options`.

- `GET /wp-json/magick-ai-toolbox/v1/status`
- `POST /wp-json/magick-ai-toolbox/v1/image-candidates`
- `POST /wp-json/magick-ai-toolbox/v1/vector-search`
- `POST /wp-json/magick-ai-toolbox/v1/knowledge-search`
- `POST /wp-json/magick-ai-toolbox/v1/flows/article-brief`
- `POST /wp-json/magick-ai-toolbox/v1/flows/article-assistant`
- `POST /wp-json/magick-ai-toolbox/v1/flows/article-plan`
- `POST /wp-json/magick-ai-toolbox/v1/flows/media-brief`
- `POST /wp-json/magick-ai-toolbox/v1/editor/content-support`
- `POST /wp-json/magick-ai-toolbox/v1/media-derivative-handoff`

Toolbox admin result panels can render governed `operator_feedback` payloads
from Adapter/Core handoff failures. The feedback is for operator revision only;
Toolbox may submit one Core media optimization proposal from the Adapter media
derivative recipe after reviewed metadata and derivative artifact evidence are
present, but it does not approve proposals, execute proposals, or perform
WordPress writes.

## Abilities

Toolbox abilities are server-side tool wrappers. External AI callers provide
task input and receive normalized suggestion payloads; they do not receive
provider API keys or direct provider credentials.

General AI composition guidance is kept in
[AI Content Composition Abilities](docs/ai-content-composition-abilities.md).

When the WordPress Abilities API is available, Toolbox registers:

- `magick-ai-toolbox/search-image-source`
- `magick-ai-toolbox/vector-search`
- `magick-ai-toolbox/search-site-knowledge`
- `magick-ai-toolbox/get-site-knowledge-status`
- `magick-ai-toolbox/request-site-knowledge-sync`
- `magick-ai-toolbox/build-article-brief`
- `magick-ai-toolbox/build-article-assistant`
- `magick-ai-toolbox/build-article-write-plan`
- `magick-ai-toolbox/build-article-batch-write-plan`
- `magick-ai-toolbox/build-article-media-batch-write-plan`
- `magick-ai-toolbox/build-media-brief`
- `magick-ai-toolbox/get-content-discoverability-context`
- `magick-ai-toolbox/validate-content-discoverability-context`
- `magick-ai-toolbox/build-content-discoverability-brief`
- `magick-ai-toolbox/build-ai-article-writing-pack`

When `magick-ai-abilities` is active, Toolbox uses its public registration
helpers so the tools can be discovered by existing Magick AI consumers.
Toolbox ability ids stay under `magick-ai-toolbox/*` so they do not collide with
Core governance abilities or first-party WordPress abilities.

Ability metadata includes Toolbox scopes such as `cap.toolbox.image_source`,
`cap.toolbox.vector_search`, and `cap.toolbox.workflow_suggest`. Content context uses
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
to get the primary lightweight content-support contract: SEO/AEO/GEO guidance,
taxonomy/tag candidates, internal-link hints, proposal fields, and conservative
candidates from supplied post or topic input. Final WordPress writes still
require Core proposal approval.

For natural-language article requests from OpenClaw or another external AI,
`magick-ai-toolbox/build-ai-article-writing-pack` composes the context,
validation result, discoverability brief, writing instructions, and guardrails
into one suggestion-only pack. It is a convenience fallback for broad prompts,
not the default SEO/AEO/GEO, taxonomy, link, image, or publish-readiness
surface.

The Article Assistant flow and `magick-ai-toolbox/build-article-assistant`
ability compose one local `article_draft_v1` workbench artifact from topic,
evidence candidates, image-source candidates, site context, operator notes, and
an optional reviewed draft. It does not run a cloud writer or workflow runtime.
Only when the operator supplies a reviewed draft and risk checks pass does the
artifact include an `article_write_plan` for Core proposal intake.
This is a local Article Assistant Workbench, not an article generator or Cloud
writing feature. Keep it to one article, reviewed artifacts, and one optional
Core-ready draft proposal.

Toolbox fixed buttons are the operator-click surface for repeatable OpenClaw
flows. They should reuse the same ability ids, plan artifact shapes, Adapter
recipe guidance, and Core proposal handoff as OpenClaw natural-language flows.
Toolbox must not turn those buttons into a separate approval store, media
registry, workflow runtime, prompt/model control plane, or WordPress write
executor.

The article plan flow and `magick-ai-toolbox/build-article-write-plan` ability
assemble a Core-ready `article_write_plan` for a reviewed draft. They do not
call Core, approve proposals, publish content, or write WordPress data.
The admin **Content Support** surface includes a **Reviewed Draft Handoff**
fallback panel that renders the plan artifacts, risk report, final
`magick-ai/create-draft` action, and Core handoff route for operator review.

The post editor also exposes a **Magick AI Content Support** document panel for
the same productized support posture. Its buttons run fixed flows for publish
preflight, taxonomy/tag candidates, internal-link candidates, and image-source
candidates from the current draft context. The panel returns suggestions only;
it does not insert links, assign terms, import media, publish content, or write
SEO fields.

The media derivative preview flow reads Core media optimization defaults when
available, accepts one-run operator overrides, and lets an operator select one
image attachment from the media library. If an operator starts from a hard-coded
local uploads URL, the same surface can call the local read-only
`magick-ai/resolve-media-attachment-by-url` ability through Adapter
`run-read-ability`, show bounded match evidence, and fill the attachment ID for
the same preview/proposal flow. The admin action surface dispatches the
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

Toolbox no longer stores provider keys for web search, public image-source
providers, or vector infrastructure. Configure Cloud connectivity in the Cloud
Addon and configure provider keys, routing, quotas, and health in the Cloud
operator surface.

Image-source search supports `auto`, `cloud`, and provider hints such as
`unsplash`, `pixabay`, or `pexels`, but the public provider keys and provider
selection live in Cloud. Toolbox sends one Cloud runtime request and returns a
normalized `image_source_candidates` payload for any AI caller that needs
images, whether the use case is article drafting, media planning, layout
suggestions, reference selection, or another image-dependent workflow.
`ai_generated` remains explicit: callers may provide a reviewed generated image
URL, or a host may handle `magick_ai_toolbox_ai_image_generation_request` and
return generated-image candidates. Toolbox still does not own model routing,
provider billing, media import, or final WordPress writes.

Every returned image candidate is normalized to `image_candidate.v1` while
preserving legacy URL fields for existing callers. The normalized fields include
`source_type`, `provider`, `provider_origin`, `download_url`,
`thumbnail_url`, `prompt`, `model`, `license_review_status`, attribution,
provenance, and warnings. Stock providers return `source_type=stock`;
generated candidates return `source_type=ai_generated`.

After an operator reviews one candidate, `magick-ai-toolbox/build-image-candidate-adoption-plan`
or `POST /wp-json/magick-ai-toolbox/v1/flows/image-candidate-adoption-plan`
can build an `image_candidate_adoption_plan`. That plan targets only
`magick-ai/upload-media-from-url`, `magick-ai/update-media-details`, and
optional `magick-ai/set-post-featured-image` through Core proposal intake. It
does not import media, update metadata, set a featured image, or write
WordPress directly.

Cloud-managed web search is provided by Magick AI Cloud. Toolbox no longer
stores local web search provider keys, registers a local web search ability,
or exposes a local web search REST route. AI workflows that need current
external evidence, comparison material, Chinese source lookup, source coverage,
product research, support context, or article preparation should call the Cloud
runtime and preserve returned source URLs in their evidence packs. Toolbox does
not verify truth, write WordPress content, or expose provider keys.

The legacy `vector-search` route and ability are compatibility pointers only.
Toolbox no longer stores vector provider keys, embedding models, dimensions,
provider endpoints, collection names, or local vector database settings. New
callers should use Cloud-managed Site Knowledge for semantic site context.

Cloud-managed site knowledge is the preferred high-level ability surface for
semantic site search, related content, writing context, internal-link
candidates, refresh suggestions, image-context lookup, FAQ candidates, content
gap analysis, and publish preflight duplicate checks. Toolbox registers
`search-site-knowledge`, `get-site-knowledge-status`, and
`request-site-knowledge-sync` as WordPress Abilities. These abilities call
Magick AI Cloud through the Cloud Addon runtime seam or the
`magick_ai_toolbox_site_knowledge_cloud_request` host filter; Toolbox does not
store Cloud credentials, own vector collection lifecycle, or write WordPress
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

Provider responses return normalized fields by default. Set **Include provider
raw responses** to include raw provider payloads for debugging.

## Development

```bash
composer test:all
```

The current gate runs PHP syntax linting and static contract checks.
