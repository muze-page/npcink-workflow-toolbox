# Npcink Toolbox Boundary

Npcink Toolbox owns product-facing tools and fixed-flow buttons.

Owned here:

- Cloud-managed web search status and handoff guidance;
- image-source candidate actions;
- vector search actions;
- Cloud-managed site knowledge actions for vector search;
- fixed-flow buttons that return planning artifacts;
- non-secret content discoverability context for SEO, AEO, and GEO suggestion
  workflows;
- operator-facing admin UI for the toolbox.

Not owned here:

- Core governance truth;
- final WordPress write approval;
- reusable first-party WordPress ability definitions already owned by
  `npcink-abilities-toolkit`;
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
5. Let external AI workflows compose context, Cloud-managed web search,
   image-source, and vector abilities as inputs, not as write authority.
6. Use WordPress abilities and Core proposals for final WordPress writes.

## OpenClaw Button Surface Boundary

Toolbox may turn repeatable OpenClaw flows into WordPress admin buttons. This is
a UX projection of the same local ability and Core proposal contracts, not a
second recipe owner.

The safe pattern is:

```text
OpenClaw natural-language request
or Toolbox fixed button
-> Adapter/Core capability discovery
-> Toolbox or Abilities suggestion/read ability
-> reviewed plan or candidate artifact
-> Core proposal
-> approval and preflight
-> WordPress ability write
```

Toolbox buttons must reuse the same ability ids, artifact contracts, and Core
handoff routes that OpenClaw recipes use. They may collect operator inputs,
display candidates, build preview artifacts, and submit reviewed proposals, but
they must not own OpenClaw projection truth, approval truth, prompt/model
routing truth, media registry truth, or final WordPress write execution.

## AI Tool Composition Boundary

Toolbox may expose tool abilities needed by external AI workflows. Article
writing is one consumer, but the same abilities should also serve research,
comparison, support, media planning, page layout, source coverage, and other
bounded suggestion workflows.

- site content context;
- context validation;
- Cloud-managed web search evidence for any workflow that needs source
  candidates;
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
- `/image-candidates`
- `/vector-search`
- `/knowledge-search`
- `/ai/content-support`
- `/ai/site-helpers`
- `/ai/image-generation`
- `/flows/article-brief`
- `/flows/article-assistant`
- `/flows/article-plan`
- `/flows/image-candidate-adoption-plan`
- `/flows/site-knowledge-review-plan`
- `/flows/media-brief`
- `/editor/content-support`
- `/media-derivative-handoff`
- `/media-derivative-handoff`

Do not add Toolbox REST routes for publishing, delivery, workflow runs, queues,
schedulers, approvals, write confirmation, featured image setting, media
upload/import, SEO mutation, content indexing, or re-indexing without a new
boundary decision. Write-like outcomes must be prepared as suggestions or Core
proposal handoffs, not executed by Toolbox.

`/flows/article-plan` prepares a Core-ready `article_write_plan` for
`npcink-toolbox/build-article-write-plan`. It is a planning artifact route,
not a WordPress write route and not a Core proposal execution route.

`/flows/site-knowledge-review-plan` prepares a Core-ready but blocked
`site_knowledge_review_plan` from a Cloud Site Knowledge agent handoff. It may
preserve evidence refs and describe one non-ready draft-review action for Core
from-plan intake, but it must not generate article content, approve proposals,
pass preflight, or execute WordPress writes. The resulting Core proposal still
requires human `title` and `content` input before any later approval path can be
considered.

`/ai/content-support` sends one bounded suggestion request to the Cloud hosted
AI runtime. It returns review-only content-support suggestions and must not
create proposals, approve proposals, publish content, or write WordPress data.
Its default user-facing intents are title/summary suggestions, compact outline
support, short-draft polish, and summary/category/tag optimization. They must
stay lightweight and must not be presented as one-click long-form article
generation. Default draft-support results must include a small quality
contract: expected output shape, operator review checklist, and reject-if rules
for full-article output, unsupported claims, or write-like actions. Summary
and terms optimization may suggest excerpts, categories, and tags, but it must
not update excerpts, assign terms, mutate SEO fields, own taxonomy governance,
own content indexing, store acceptance/audit truth, or be treated as full RAG.
Its precision helpers may expose ranking signals, dedupe guidance, matched
tokens, input scope, proposed new-term review notes, preview-only Core handoff
packets, and suggested review metrics, but those remain operator-review aids.
Proposed new terms are vocabulary-gap candidates only; Toolbox must not create
terms or assign them to posts.
Site-level and media-helper AI routes must be added as separate narrow
surfaces; they must not be hidden compatibility modes inside the draft-support
route.

`/ai/site-helpers` sends one bounded site-helper request to the Cloud hosted AI
runtime. Its first intents are `media_alt_suggestions` and
`content_snapshot_suggestions`. Toolbox may sample recent image attachment
metadata or a small public content snapshot, but Cloud owns the AI output and
the result is suggestion-only. This route must not claim full-site crawling,
site-health scoring, analytics/indexing coverage, image-pixel inspection,
media-library batch updates, local queues, proposal creation, approval, or
WordPress writes.

`/ai/image-generation` sends one reviewed-prompt image generation request
through Cloud Addon runtime and returns candidate-only `image_candidate.v1`
evidence. It must not import media, set featured images, own prompt/model
routing, store provider credentials, approve proposals, or write WordPress
data.

`/flows/image-candidate-adoption-plan` prepares a Core-ready
`image_candidate_adoption_plan` from one reviewed `image_candidate.v1`. It may
describe media upload, metadata, and optional featured-image write actions for
Core proposal intake, but it must not import media, update attachment metadata,
set featured images, approve proposals, or execute writes.
Editor-side adoption may submit that plan through Adapter `/proposals/from-plan`
and then call Adapter `/proposals/{proposal_id}/approve-and-execute` for the
created Core proposal. Adapter must remain only the unified user-action proxy:
Core stays the approval, preflight, proposal, and audit owner, and Abilities
stay the final WordPress write executor. Toolbox must treat any automatic
completion as an Adapter/Core/Abilities result, not as a Toolbox-owned direct
write.

`/flows/site-knowledge-review-plan` prepares a blocked Core review handoff from
Cloud Site Knowledge agent evidence. It may preserve evidence refs, blocked
outputs, and human-required title/content fields for Core review, but it must
not approve, preflight, execute, schedule, queue, or directly write WordPress
content.

`media_optimization_v1` is the fixed governed name for the existing
**Optimize Existing Image** surface. It may guide one operator intent through
media selection, Toolbox policy defaults, Adapter/Cloud derivative preview,
reviewed metadata, and one Core media optimization proposal. It must not add a
generic workflow runner, persistent run table, Toolbox media registry,
automatic approval, retry worker, queue, scheduler, or direct media write.

`/media-derivative-handoff` prepares one-run ability input for
`npcink-abilities-toolkit/build-media-derivative-cloud-request` from Toolbox media policy defaults
and operator overrides. Watermark overrides must distinguish text and
image/logo modes: text watermarks pass text/font/color/background/margin fields
without requiring a logo artifact, while image/logo watermarks use the Toolbox
configured logo source or another reviewed image source before Cloud dispatch.
It is a planning artifact route. The admin media
derivative preview surface may call Adapter's bounded media-derivative recipe
to create one short-lived Cloud artifact and, for the single-image optimize
flow, may submit the returned Adapter `from_plan_request` so Core creates one
batch proposal containing reviewed metadata and derivative adoption actions. It
may render the same-origin signed Adapter preview proxy for operator review, but
that URL is not a public Cloud URL or a WordPress media write. Toolbox must not
store site media policy truth, own Cloud credentials, create an artifact
registry, approve proposals, execute proposals, replace attachment files, or
update attachment metadata.

The same admin surface may call
`npcink-abilities-toolkit/build-media-derivative-batch-plan` through Adapter
`run-read-ability` for bounded bulk requests such as date-range format
conversion. The batch surface may show candidates, skipped reasons, selected
per-attachment previews, and selected Core proposal submissions. It must still
use the per-attachment Adapter media derivative recipe for Cloud artifacts and
must not create a Toolbox-side media registry, approval queue, scheduler, or
write executor.

After a local media replacement has been approved and executed, the admin
surface may ask Adapter to run `npcink-abilities-toolkit/build-media-reference-repair-plan`
and submit non-empty exact-match `patch-post-content` actions to Core
`/proposals/from-plan`. Toolbox must not search-replace post content directly,
rewrite sized variants automatically, or treat the repair plan as write truth.

For plugin/theme settings that contain hard-coded media URLs, the admin surface
may ask Adapter to run `npcink-abilities-toolkit/build-media-settings-reference-repair-plan`
with local exclusion filters such as blocked formats and minimum dimensions,
then submit non-empty exact-match `patch-setting-value` actions to Core
`/proposals/from-plan`. Toolbox must not update options, theme mods, serialized
settings, or excluded small/logo/icon media directly.

## Content Context Boundary

Toolbox may store the non-secret `npcink_toolbox_content_context` option and
expose it through `npcink-toolbox/get-content-discoverability-context`.

The context can include site positioning, audience, brand voice, keywords,
allowed claims, forbidden claims, exception/special-case rules, SEO rules, AEO
rules, GEO rules, and fields that third-party AI may suggest in a
proposal-ready payload.

The context must not include provider keys, private credentials, request logs,
billing details, quotas, or final write authorization. Third-party AI callers
may consume it as suggestion-only guidance; they must not mutate it or use it
as permission to bypass Core governance.

## Connector Boundaries

### Cloud-Managed Web Search

Npcink Cloud owns external web search provider configuration, execution, and
provider routing. Toolbox must not store local web search provider keys,
register a local web search REST route, or expose a local web search ability.
Toolbox must not treat search results as verified truth; Cloud search results
are source candidates for operator review. Cloud-managed web search is a
general-purpose evidence input, not only an article drafting helper.

### Image Source Providers

Unsplash, Pixabay, and Pexels own public photo/source search.

Toolbox may search and display image candidates from configured image-source
providers. Toolbox must preserve photographer attribution and source URLs.
Unsplash responses must also preserve `download_location` for future import
flows. Toolbox must not describe this as image generation, import media, set
featured images, or turn image-source search into a provider routing control
plane.

AI-generated images are a separate explicit candidate mode, not a relabeling of
Unsplash, Pixabay, or Pexels. Toolbox may normalize a caller-supplied generated
image URL, call a host-provided
`npcink_toolbox_ai_image_generation_request` runtime seam, or dispatch a
reviewed `ai_generation_handoff` through the Cloud Addon runtime client to
return suggestion-only candidates with `source_type=ai_generated`, prompt/model
evidence, and human license review status. Toolbox must not own AI image model
routing, prompt management, provider credentials, billing, media import,
featured-image setting, or approval truth.

### Cloud Site Knowledge

Npcink Cloud owns vector storage, embedding provider configuration,
embedding dimensions, indexing, rerank, quotas, and detailed run health.

Toolbox may collect bounded public WordPress manifests, request Cloud sync,
show Cloud-returned status, and search Cloud-managed site knowledge. Toolbox
must not store vector provider keys, provider endpoints, collection names,
embedding model settings, or vector database lifecycle controls.

## Cloud Checks Surface

The Toolbox **Cloud Checks** page shows compact tabs for Cloud-managed checks.
Each tab should open directly into the useful verification tool instead of
repeating ownership prose or provider catalogs.

The verification surface may identify whether a Cloud-backed action is
reachable from Toolbox, but it should keep provider ownership detail in Cloud
or in documentation.

When Cloud Addon transport is missing or unverified, Cloud Checks should show a
blocked state and disable Cloud-only submits instead of waiting for a failed
runtime request. Content Context and governed handoff planning may remain
available because they are local suggestion/planning surfaces.

Cloud web search checks must use the Cloud-managed auto route. Toolbox must not
expose provider selection, Jina Reader toggles, provider routing diagnostics,
quota, billing, request logs, entitlement, or key verification controls here.
Cloud Addon owns the WordPress-side connection and authorization check; Cloud
service-plane surfaces own provider/runtime diagnostics.

Cloud Checks may include a preview-only media derivative check under Image. It
may select a local attachment, resolve a local uploads URL, apply one-run
format/size/quality overrides, and show the short-lived Cloud preview artifact.
It must not submit Core proposals, run batch proposal submission, repair URLs,
replace media files, update attachment metadata, or treat preview artifacts as
WordPress media writes. Those handoff actions stay in Content Support and Core
governance.

The connector surface must not become provider billing, quota, key-rotation,
request-log, marketplace, provider-routing, vector-provider, or vector
lifecycle ownership. Those are Cloud or future connector-owner concerns, not
the Toolbox MVP settings surface.
