# Npcink Toolbox

Npcink Toolbox is an operator-facing WordPress plugin for Cloud-managed web
search, Cloud-managed image-source candidates, Cloud-managed site knowledge,
and fixed-flow AI actions.

It is intentionally separate from:

- `npcink-governance-core`, which owns governance, proposal records, approval, and audit;
- `npcink-abilities-toolkit`, which owns reusable WordPress Abilities API contracts;
- provider connector plugins, which can later own richer key management,
  provider selection, quota, and request logs.

## First Version

The first version provides:

- a Npcink admin page at **Npcink -> Toolbox** when a Npcink host menu
  exists, with a **Tools -> Npcink Toolbox** fallback for standalone installs;
- a **Free GPT-5.5 Draft Support** entry group in Content Support for
  lightweight title/summary, outline, and polish suggestions rather than
  one-click long-form article generation;
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

- `GET /wp-json/npcink-toolbox/v1/status`
- `POST /wp-json/npcink-toolbox/v1/image-candidates`
- `POST /wp-json/npcink-toolbox/v1/vector-search`
- `POST /wp-json/npcink-toolbox/v1/knowledge-search`
- `POST /wp-json/npcink-toolbox/v1/flows/article-brief`
- `POST /wp-json/npcink-toolbox/v1/flows/article-assistant`
- `POST /wp-json/npcink-toolbox/v1/flows/article-plan`
- `POST /wp-json/npcink-toolbox/v1/flows/media-brief`
- `POST /wp-json/npcink-toolbox/v1/editor/content-support`
- `POST /wp-json/npcink-toolbox/v1/media-derivative-handoff`

The status route distinguishes registered Toolbox surfaces from currently
available Cloud execution. Cloud-backed actions report `registered`,
`cloud_required`, `available`, and `unavailable_reason` fields so standalone
Toolbox installs do not imply that Cloud-managed search, image-source, Site
Knowledge, or hosted GPT actions can run without a connected Cloud Addon or
host-provided runtime filter.

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

- `npcink-toolbox/search-image-source`
- `npcink-toolbox/vector-search`
- `npcink-toolbox/search-site-knowledge`
- `npcink-toolbox/get-site-knowledge-status`
- `npcink-toolbox/request-site-knowledge-sync`
- `npcink-toolbox/build-article-brief`
- `npcink-toolbox/build-article-assistant`
- `npcink-toolbox/build-article-write-plan`
- `npcink-toolbox/build-article-batch-write-plan`
- `npcink-toolbox/build-article-media-batch-write-plan`
- `npcink-toolbox/build-media-brief`
- `npcink-toolbox/get-content-discoverability-context`
- `npcink-toolbox/validate-content-discoverability-context`
- `npcink-toolbox/build-content-discoverability-brief`
- `npcink-toolbox/build-ai-article-writing-pack`

When `npcink-abilities-toolkit` is active, Toolbox uses its public registration
helpers so the tools can be discovered by existing Npcink consumers.
Toolbox ability ids stay under `npcink-toolbox/*` so they do not collide with
Core governance abilities or first-party WordPress abilities.

Ability metadata includes Toolbox scopes such as `cap.toolbox.image_source`,
`cap.toolbox.vector_search`, and `cap.toolbox.workflow_suggest`. Content context uses
`cap.toolbox.context.read`. The first admin REST surface remains
`manage_options` gated; external AI/app-key authorization should be enforced by
Core or the host that consumes the ability scope metadata. First-version host
integration hooks are `npcink_toolbox_rest_permission` and
`npcink_toolbox_ability_permission`.

## Content Discoverability Context

The admin page includes a Content Context form for operator-maintained SEO, AEO,
and GEO guidance: site positioning, target audience, brand voice, keywords,
allowed and forbidden claims, exception rules, SEO/AEO/GEO rules, and proposal
fields AI may suggest. It is stored in `npcink_toolbox_content_context`,
separate from connector settings that may contain provider keys.

The context is exposed only as read-only, suggestion-only guidance through
`npcink-toolbox/get-content-discoverability-context`. Third-party AI callers
may also call `npcink-toolbox/validate-content-discoverability-context` to
check filling quality and `npcink-toolbox/build-content-discoverability-brief`
to get the primary lightweight content-support contract: SEO/AEO/GEO guidance,
taxonomy/tag candidates, internal-link hints, proposal fields, and conservative
candidates from supplied post or topic input. Final WordPress writes still
require Core proposal approval.

For natural-language article requests from OpenClaw or another external AI,
`npcink-toolbox/build-ai-article-writing-pack` composes the context,
validation result, discoverability brief, writing instructions, and guardrails
into one suggestion-only pack. It is a convenience fallback for broad prompts,
not the default SEO/AEO/GEO, taxonomy, link, image, or publish-readiness
surface.

The Article Assistant flow and `npcink-toolbox/build-article-assistant`
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

The article plan flow and `npcink-toolbox/build-article-write-plan` ability
assemble a Core-ready `article_write_plan` for a reviewed draft. They do not
call Core, approve proposals, publish content, or write WordPress data.
The admin **Content Support** surface includes a **Reviewed Draft Handoff**
fallback panel that renders the plan artifacts, risk report, final
`npcink-abilities-toolkit/create-draft` action, and Core handoff route for operator review.

The post editor also exposes **Npcink Content Support** as a plugin sidebar
opened from the editor top toolbar. Its buttons run fixed flows for publish
preflight, taxonomy/tag candidates, internal-link candidates, and image-source
candidates from the current draft context. The panel returns suggestions only;
it does not insert links, assign terms, import media, publish content, or write
SEO fields.
The image-source button opens a Cloud image recommendation modal: it
automatically searches from the current draft context and also lets the editor
enter a manual query. Returned images remain `image_candidate.v1` suggestions
with provider, attribution, source, license-review, and Unsplash download
tracking metadata preserved; media import and featured-image changes still
flow through a governed adoption plan. The editor modal lets the operator
select one candidate and adopt it as the featured image in one visible action.
Toolbox builds the adoption plan with proposed media title, alt text,
description, attribution, filename, and featured-image step, submits it through
Adapter's plan-to-proposal bridge, then calls Adapter's unified
`approve-and-execute` action for the created Core proposal. Adapter calls Core
approval and preflight, then executes the allowlisted WordPress ability writes
when policy permits. Toolbox does not import media, mutate SEO/meta fields,
approve, execute, or set the featured image directly.

The admin **Content Support** tab mirrors that fixed-flow posture. Its default
Free GPT-5.5 Draft Support group runs lightweight title/summary, outline, and
polish helpers through the Cloud hosted runtime profile `text.free-gpt55` when
that route is available. These helpers are deliberately scoped to local draft
support and must not be presented as one-click article generation. Each hosted
result carries a small quality contract with an expected output shape, review
checklist, and reject-if rules so operators can discard unsupported or
full-article output quickly.
Everyday Support remains available for the same bounded jobs:
discoverability brief, publish preflight, taxonomy/tag candidates,
internal-link candidates, or image candidates. Media work, governed handoffs,
and the combined Article Planning Bundle are visually separate groups; the
bundle is a fallback package, not the primary support workflow.

The media derivative preview flow reads Core media optimization defaults when
available, accepts one-run operator overrides, and lets an operator select one
image attachment from the media library. Operators can keep the Core default
watermark, disable it for the run, use a text watermark, or use the configured
Core image/logo watermark source with one-run placement settings. Text
watermark overrides pass text, font, color, background, margin, position, and
opacity directly to the same Cloud request shape used by OpenClaw handoffs. If
an operator starts from a hard-coded
local uploads URL, the same surface can call the local read-only
`npcink-abilities-toolkit/resolve-media-attachment-by-url` ability through Adapter
`run-read-ability`, show bounded match evidence, and fill the attachment ID for
the same preview/proposal flow. The admin action surface dispatches the
bounded Adapter media-derivative recipe, polls the short-lived Cloud artifact
result, renders the same-origin signed Adapter preview proxy when available,
and can submit a Core replacement proposal with the artifact evidence. The same
surface can build a bounded batch conversion plan, show candidates and skipped
reasons, generate selected previews, and submit selected Core replacement
proposals; it still processes each candidate through the governed per-attachment
Adapter recipe. The admin batch surface is intentionally a fixed operator flow:
operators choose a media range and processing goal first, while date, exclusion,
and dimension filters remain in an advanced disclosure for exceptions. Toolbox
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
URL, or a host may handle `npcink_toolbox_ai_image_generation_request` and
return generated-image candidates. Toolbox still does not own model routing,
provider billing, media import, or final WordPress writes.

Every returned image candidate is normalized to `image_candidate.v1` while
preserving legacy URL fields for existing callers. The normalized fields include
`source_type`, `provider`, `provider_origin`, `download_url`,
`thumbnail_url`, `prompt`, `model`, `license_review_status`, attribution,
provenance, and warnings. Stock providers return `source_type=stock`;
generated candidates return `source_type=ai_generated`.

After an operator reviews one candidate, `npcink-toolbox/build-image-candidate-adoption-plan`
or `POST /wp-json/npcink-toolbox/v1/flows/image-candidate-adoption-plan`
can build an `image_candidate_adoption_plan`. That plan targets only
`npcink-abilities-toolkit/upload-media-from-url`, `npcink-abilities-toolkit/update-media-details`, and
optional `npcink-abilities-toolkit/set-post-featured-image` through Core proposal intake. It
does not import media, update metadata, set a featured image, or write
WordPress directly. The editor one-click adoption flow uses Adapter
`/proposals/from-plan` followed by `/proposals/{proposal_id}/approve-and-execute`
so the visible action can complete only through Core approval, preflight, audit,
and allowlisted Abilities execution.

Cloud-managed web search is provided by Npcink Cloud. Toolbox no longer
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
Npcink Cloud through the Cloud Addon runtime seam or the
`npcink_toolbox_site_knowledge_cloud_request` host filter; Toolbox does not
store Cloud credentials, own vector collection lifecycle, or write WordPress
content.

Sync requests send bounded public WordPress manifests: published posts and
pages, plus recent approved comments attached to those indexed public entries.
Comment payloads include only public comment text and source IDs needed for
Cloud indexing; moderation, edits, deletion, and final writes remain local
WordPress responsibilities.

When an allow-listed public post type is published, updated while published,
leaves public status, is trashed, or is permanently deleted, Toolbox queues a
bounded Cloud Site Knowledge refresh for that object. The default allow-list is
`post` and `page`; hosts may extend it with
`npcink_toolbox_site_knowledge_post_types` for public content types such as
docs, products, or FAQs. Attachments are excluded from this text indexing path
by default. When a comment is approved, edited while approved, trashed, deleted,
or moved out of approved status, Toolbox queues a refresh for the parent
allow-listed post/page so Cloud can add or drop comment chunks. A low-frequency
daily reconciliation queues the latest public allow-listed entries as a
missed-event safety net. These jobs only call the existing Cloud sync contract;
they do not store provider credentials, run embeddings locally, or write
WordPress content. The Site Knowledge status panel also shows the local
auto-sync queue, next WP-Cron runs, and a server cron command suggestion for
low-traffic production sites.

The admin **Site Knowledge** tab lets operators start or refresh the
Cloud-managed index and inspect coverage without configuring vector provider
keys in Toolbox. Cloud owns embedding, vector storage, and detailed run health;
Toolbox only starts sync from local public content and displays returned status.
The **Cloud Checks -> Site Knowledge** panel is a read-only verification surface
for the same Cloud-managed site knowledge status and search check; it does not
expose provider keys, embedding settings, collection names, or vector database
configuration.
The **Cloud Checks -> Search** panel uses Cloud auto execution for a bounded
Toolbox reachability check; provider selection, Jina Reader toggles, routing
diagnostics, entitlement, quota, billing, and request logs belong in Cloud
Addon or Cloud service-plane surfaces.
The **Cloud Checks -> Image** panel checks Cloud image-source candidates and can
generate a short-lived derivative preview for one existing media-library image,
including text or image/logo watermark overrides for that run. Core proposal
submission, batch proposal submission, and URL repair handoffs remain in
**Content Support -> Optimize Existing Image**.

Provider responses return normalized fields by default. Set **Include provider
raw responses** to include raw provider payloads for debugging.

## Development

```bash
composer test:all
```

The current gate runs PHP syntax linting and static contract checks.
