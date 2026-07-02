# Npcink Toolbox Boundary

Npcink Toolbox owns product-facing tools and fixed-flow buttons.

Owned here:

- Cloud-managed web search status and handoff guidance;
- image-source candidate actions;
- vector search actions;
- Cloud-managed site knowledge actions for vector search;
- fixed-flow buttons that return planning artifacts;
- local operations insight previews that turn bounded public content,
  approved-comment signals, media metadata, taxonomy summaries, and context
  readiness into `suggestion_only` findings;
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
7. Render article audio on the public post only after approved audio has been
   adopted into local WordPress metadata; the playback surface is read-only and
   does not call Cloud, create proposals, import media, or write post meta.

Hard blocks:

- Toolbox must never implement `confirm_token`, `write_confirmed`, hidden
  write confirmation, or a local approval-state store.
- Toolbox must not add direct publish, media import, media metadata mutation,
  SEO mutation, post-content mutation, or featured-image writes outside the
  single Local Admin Consent featured-image exception recorded in the Boundary
  Exceptions Registry and ADR-003.
- Toolbox must not create a second ability registry, workflow registry,
  approval store, queue, scheduler truth, run-recovery workspace, indexing
  lifecycle owner, or provider-secret store.

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

For batch operations, OpenClaw/Adapter must prove the governed execution
contract before Toolbox productizes it. The required proof includes bounded
scope, selected actions, execution profile allowlist evidence, Core approval,
commit preflight, per-action status/result payloads, retry guidance, and final
Abilities callbacks. Toolbox may then render that accepted path as a fixed
best-practice button. It must not implement a separate batch writer, queue, or
direct attachment replacement path.

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
Automatic public content-change delivery belongs to Cloud Addon when its Site
Knowledge bridge is installed and verified. Toolbox must not keep a legacy
standalone fallback queue; it may only show bridge health, clear retired local
auto-sync state, and keep explicit manual refresh/search surfaces. Toolbox
must reject local Site Knowledge `rebuild` and `delete` sync modes; those
operations belong in Cloud Site Knowledge.

## REST Route Boundary

The first-version REST surface is an allowlist, not an open namespace. Allowed
routes are limited to status, bounded provider-backed tool actions, and fixed
planning flows:

- `/status`
- `/image-candidates`
- `/vector-search`
- `/knowledge-search`
- `/web-search/test`
- `/web-search/diagnostics`
- `/site-knowledge/status`
- `/site-knowledge/search`
- `/site-knowledge/sync`
- `/agent-feedback`
- `/agent-feedback/summary`
- `/ai/content-support`
- `/ai/site-helpers`
- `/ai/image-generation`
- `/flows/article-brief`
- `/flows/article-assistant`
- `/flows/article-plan`
- `/flows/image-candidate-adoption-plan`
- `/flows/article-audio-adoption-plan`
- `/local-admin-consent/featured-image`
- `/flows/site-knowledge-review-plan`
- `/flows/nightly-inspection-review-plan`
- `/flows/content-metadata-apply-plan`
- `/flows/media-alt-caption-review-plan`
- `/flows/media-brief`
- `/editor/content-support`
- `/media-derivative-handoff`
- `/nightly-inspection/cloud-runtime-entitlement`
- `/nightly-inspection/cloud-batch`
- `/nightly-inspection/cloud-batch/recent`
- `/nightly-inspection/cloud-batch/{run_id}`
- `/nightly-inspection/cloud-batch/{run_id}/result`
- `/nightly-inspection/cloud-batch/{run_id}/retry`

Do not add Toolbox REST routes for publishing, delivery, workflow runs, queues,
schedulers, approvals, write confirmation, featured image setting, media
upload/import, SEO mutation, content indexing, or re-indexing without a new
boundary decision. Write-like outcomes must be prepared as suggestions or Core
proposal handoffs, not executed by Toolbox.

Nightly Inspection Cloud runtime routes are compatibility bridges for existing
callers only. Runtime entitlement, quota, batch limit, retention, recent run,
status, result, and retry detail belong in Cloud Addon Runtime Runs, not in a
Toolbox recovery workspace. `/cloud-batch/recent` may read Cloud-owned run cards
for legacy callers, and `/cloud-batch/{run_id}/retry` may ask Cloud to queue a
retry with a fresh idempotency key and a new bounded local snapshot. Neither
route stores a server-side Toolbox run history, claims retry ownership, creates
Core proposals, or writes WordPress data.

`local_admin_consent` is now implemented only for one narrow proof:
`/local-admin-consent/featured-image` may set one existing WordPress image
attachment as the current post's featured image after a present administrator
clicks a visible selection. The route must classify the operation as
`local_admin_consent`, record Core-owned audit before and after the write, and
roll back if completion audit fails. It must not import media, update media
metadata, create a proposal, approve a proposal, execute an ability, or process
multiple posts or multiple images. All other accepted write-like changes keep
using Core proposal handoff or Adapter/Core/Abilities user actions until a
separate boundary decision defines the specific local write and audit contract.

`/flows/article-plan` prepares a Core-ready `article_write_plan` for
`npcink-toolbox/build-article-write-plan`. It is a planning artifact route,
not a WordPress write route and not a Core proposal execution route.

`/flows/article-audio-adoption-plan` prepares a Core-governed
`article_audio_adoption_plan.v1` from one reviewed audio candidate. It may
describe playback metadata projection, evidence refs, and the target Toolkit
audio adoption ability, including whether the adopted audio should be imported
into the local WordPress media library. Toolbox itself must not import media,
update post meta, create or approve a Core proposal, execute a write ability, or
patch post content.
It may include lightweight source-content freshness evidence such as content
hash, word count, and generation timestamp so Core/Abilities can later decide
whether the adopted audio is current, lightly drifted, review-recommended, or
stale. Toolbox must not use that evidence to auto-regenerate audio, run
background refresh jobs, or patch audio segments.

The high-risk contrast for Local Admin Consent is the article/media batch
handoff. `npcink-toolbox/build-article-media-batch-write-plan` may group
reviewed draft creation, media upload, media metadata, and featured-image
actions into one Core `plan_to_proposal_batch`, but it must not use
`/local-admin-consent/featured-image`, record `local_admin_consent.*` audit
events, create posts, import media, or set featured images during proposal
intake.

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
Its default user-facing intents are local article checkup, title/summary
suggestions, compact outline support, selection-only paragraph review, and
summary/category/tag review support. Article checkup is a full-draft diagnostic
surface for sentence density, fact-gap, tone, structure, semantic consistency,
and format issues; it must point to review locations and editing direction
without rewriting, inserting, or replacing body text. They must stay lightweight
and must not be presented as one-click long-form article generation. Default
draft-support results must include a small quality
contract: expected output shape, operator review checklist, and reject-if rules
for full-article output, unsupported claims, or write-like actions. Summary
and terms optimization may suggest excerpts, categories, and tags, but it must
not update excerpts, assign terms, mutate SEO fields, own taxonomy governance,
own content indexing, store acceptance/audit truth, or be treated as full RAG.
Its precision helpers may expose ranking signals, dedupe guidance, matched
tokens, input scope, proposed new-term review notes, preview-only Core handoff
packets, a `content_metadata_delta` issue/diagnosis/delta artifact, and
suggested review metrics, but those remain operator-review aids. Related Site
Knowledge results may contribute existing category/tag evidence from their
current local WordPress posts to improve candidate ranking, but that evidence is
ranking-only context: Toolbox must not create terms, assign terms, persist
feedback, update the index, or treat related-content terms as automatic truth.
`content_metadata_delta` is the P0 feedback-loop contract for one current post:
it may preserve observed signals, context refs, existing-term recommendations,
authorization classification, outcome checks, and future learning candidates,
but it must not persist a learning store, write audit truth, update excerpts,
assign terms, or create terms.
The Core handoff packet may label proposal-ready actions for Generate and apply
summary, Recommend and apply tags, and Recommend categories. Auto-approval
eligibility belongs to Core policy; Toolbox must not auto-approve, create
terms, assign terms, or update excerpts itself. Proposed new terms are deferred
to a later taxonomy governance workflow; they are not current editor shortcut
candidates or apply-plan inputs. The only apply-oriented local Toolbox surface is
`/flows/content-metadata-apply-plan`; its Core handoff ability id is
`npcink-abilities-toolkit/build-content-metadata-apply-plan`. The route still
returns read-only planning output: it packages reviewed excerpt, existing
category, and existing tag choices into dry-run Core handoff actions and rejects
missing term creation by keeping `create_missing=false`.
Existing category/tag candidate ranking is sourced from
`npcink-abilities-toolkit/suggest-post-taxonomy-terms`; Toolbox only supplies
editor context, related-term evidence, and the review UI.
Image ALT/caption review follows the same planning boundary. Current-article
image text checks belong in the editor sidebar; backend Image Handling may build
a small selected media-library review set and pass accepted items to
`/flows/media-alt-caption-review-plan`. That plan points at
`npcink-abilities-toolkit/update-media-details` for later Core review, but
Toolbox does not create the proposal, approve it, execute it, or write media
metadata.
Internal-link support returns `internal_link_candidates.v1` with reviewable
targets, anchor suggestions, and placement hints from
`npcink-abilities-toolkit/resolve-internal-link-targets`. Toolbox supplies
editor context and optional Cloud Site Knowledge related-content evidence, but
it does not own candidate assembly, link insertion, post-content patching, or a
link graph control plane. The editor sidebar may offer explicit copy-link and
open-target actions only; the human editor still owns where any reviewed link is
placed in the draft.
Publish preflight may aggregate summary, taxonomy, image, internal-link,
duplicate-risk, and SEO readiness into `pre_publish_review.v1`, but that
artifact remains advisory. SEO metadata support is limited to a single current
post `seo_meta_handoff_preview.v1` proposal payload that the editor may submit
through Adapter. For this single current-post SEO title/description action, the
editor may ask Adapter `/proposals/{proposal_id}/approve-and-execute` to finish
the same reviewed user action when host policy allows. Adapter/Core remain the
approval, preflight, proposal, and audit owner, and Abilities remain the final
WordPress write executor. If policy blocks execution, the proposal stays in
Core review. Toolbox must not batch SEO changes, mutate SEO plugin fields
directly, or write schema/GEO metadata directly.
The standalone discoverability button may render post-publish optimization
tasks and expose the same SEO Adapter/Core apply action. Excerpt and slug
suggestions may update the current editor draft only after an explicit operator
click; slug application must show a permalink-risk confirmation, with stronger
warning for already published posts. FAQ, answer-summary, GEO-summary, and
schema suggestions remain review notes or copyable candidates unless a
separate governed write path exists.
A future direct apply path for one current post's excerpt plus existing
category/tag ids must not be treated as Local Admin Consent expansion. It
would first require a `strong_local_confirmation` UX and audit contract with
exact final metadata values, old/new evidence, actor/source evidence,
confirmation text, recovery evidence, and fail-closed audit behavior. Until
that contract exists, accepted metadata choices stay on the Core proposal path.
Site-level and media-helper AI routes must be added as separate narrow
surfaces; they must not be hidden compatibility modes inside the draft-support
route.
Comment reply suggestions in the editor are a review-only projection over
`npcink-abilities-toolkit/build-comment-mention-reply-suggest`; Toolbox supplies
the current article context and selected or operator-supplied comment text, but
does not publish replies, approve comments, mutate comment status, or own
comment workflow governance.

## Site Check Boundary

The **Site Check** panel is the operator-facing decision router for the fixed
Toolbox button surface. It builds one administrator-requested local
`site_ops_insight_pack.v1` preview. The underlying contract keeps the Site Ops
name for compatibility, but the product surface is a site-check action, not a
general analytics workspace: it may read bounded public posts/pages, approved
comment signal counts, media metadata, category/tag summaries, Site Context
readiness, and Cloud availability. It must not return comment author emails, IP
addresses, user agents, or full comment text.

The output is a read-only site-check report: a current-run ranked review list,
treatment paths, evidence summaries, impact, recommended actions, safe
first-action links, blocked items, and handoff candidates that route the
operator to manual handling, existing fixed workflows, or optional Cloud
detail. Coverage metrics, lightweight charts, deterministic local summary,
content findings, media findings, comment findings, and structure findings are
supporting detail, not a separate analytics product. It is not a site automation runner. The local preview does not call
Cloud, schedule jobs, persist run state, create Core proposals, submit Adapter
actions, mutate comments, update media, write SEO fields, create taxonomy
terms, or publish content. First-action links may open existing WordPress
admin objects or detail panels only; they must not execute changes. Explicit Cloud detail may add semantic,
cross-run, trend, AI-summary, and external-data detail, but Cloud remains
runtime detail and must not become a second WordPress write owner, approval
store, or control plane.

Toolbox may prepare a copyable `site_ops_cloud_analysis_request.v1` from the
local pack. When Cloud is ready and an administrator explicitly clicks **Use
Cloud detail**, Toolbox may send that request through the Cloud runtime seam.
It may include aggregate post/page, media, taxonomy, and approved-comment
signal counts plus local finding summaries. It must not include full comment
text, comment author email, comment IP address, user agent, provider secrets,
private content, request logs, queue instructions, local scheduler
instructions, or WordPress write actions. The expected Cloud result is
`site_ops_cloud_analysis_result.v1`, with `write_posture=suggestion_only` and
`core_proposal_created=false`; Toolbox must not create local queues, local run
tables, retries, scheduler truth, Core proposals, or WordPress writes.

`/ai/site-helpers` sends one bounded site-helper request to the Cloud hosted AI
runtime. Its first intents are `media_alt_suggestions` and
`content_snapshot_suggestions`. The editor uses current-article media metadata
for single-post image text review; the backend uses only an explicit small
media-library sample for batch review-set selection. Cloud owns the AI output
and the result is suggestion-only. This route must not claim full-site
crawling, site-health scoring, analytics/indexing coverage, image-pixel
inspection, media-library batch updates, local queues, proposal creation,
approval, or WordPress writes.
The backend admin surface exposes selected media ALT/caption review sets. The
`content_snapshot_suggestions` intent remains route-only/internal for bounded
public samples, while operator-facing site opportunity review belongs in
Site Check. Those samples may support reviewable update, linking,
expansion, or image suggestions only; they are not full-site audits, content
generators, local crawlers, or write plans.
Current article media ALT support belongs in the editor sidebar; backend batch
media ALT support must stay on the explicit selected review-set surface and
must not become a whole-library update path.
When the editor sidebar needs image ALT support, it may pass only the current
article's featured image and image-block metadata through `/editor/content-support`;
that narrow snapshot must not become a media-library scan, batch update path,
or direct media metadata write.

	`/ai/image-generation` is a legacy-compatible route name for one
	reviewed-prompt hosted image candidate request through Cloud Addon runtime. It
	returns candidate-only `image_candidate.v1` evidence and must not import
	media, set featured images, own prompt/model routing, store provider
	credentials, approve proposals, or write WordPress data.

`/flows/image-candidate-adoption-plan` prepares a Core-ready
`image_candidate_adoption_plan` from one reviewed `image_candidate.v1`. It may
describe media upload, metadata, and optional featured-image write actions for
Core proposal intake, but it must not import media, update attachment metadata,
set featured images, approve proposals, or execute writes. This route remains
available for editor-side image adoption and machine clients; it is not a
standalone Toolbox admin workbench, and old admin
`tool=image-candidate-adoption` links should be treated as deprecated.
Editor image candidate review may delegate already retrieved candidates to
`npcink-abilities-toolkit/build-image-candidate-review-artifact` for a shared
`image_candidate_review.v1` artifact and recommendation projection. Toolbox
still owns the image-source UX and Cloud/provider request; Toolkit does not
search providers, generate images, download files, import media, or write
WordPress state.
When a selected candidate is already an existing WordPress image attachment,
`/local-admin-consent/featured-image` may set it as the current post's featured
image without creating a Core proposal. This is not media adoption: it is a
single-object local write with Core audit. External URLs, generated image URLs,
media import, metadata updates, and combined import-plus-featured-image actions
must keep using `/flows/image-candidate-adoption-plan` and the
Adapter/Core/Abilities path.
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

`media_optimization_v1` is the fixed governed name for the image optimization
workflow. Single-image ALT and optimization actions start from the media-library
attachment details panel or image row actions, then pass that attachment into
the selected-image review workbench. Deprecated `tool=optimize` and legacy
`toolbox_tool=media-derivative` requests fall back to the batch optimization
workbench instead of rendering a standalone one-image picker. Batch
optimization starts from selected media-library attachments or the
`tab=image&tool=batch-optimize` workbench. These surfaces may guide operator
intent through media selection, Toolbox policy defaults, Adapter/Cloud
derivative preview, reviewed metadata, selected Core proposal submission, and
explicit Adapter/Core execution. They must not add a generic workflow runner,
persistent run table, Toolbox media registry, automatic approval, retry worker,
queue, scheduler, or direct media write.

`/media-derivative-handoff` prepares one-run ability input for
`npcink-abilities-toolkit/build-media-derivative-cloud-request` from Toolbox media policy defaults
and operator overrides. Crop overrides are bounded to common aspect-ratio
requests and remain preview/proposal input only. Watermark overrides must distinguish text and
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

The dedicated batch admin surface may call
`npcink-abilities-toolkit/build-media-derivative-batch-plan` through Adapter
`run-read-ability` for selected attachment IDs or bounded bulk requests such as
date-range format conversion. The batch surface may show candidates, skipped
reasons, selected per-attachment previews, selected Core proposal submissions,
and a policy-gated Adapter/Core execution request for selected proposals. It
must still use the per-attachment Adapter media derivative recipe for Cloud
artifacts and must not create a Toolbox-side media registry, approval queue,
scheduler, or write executor.
When selected batch items are submitted and executed, it must be the
Adapter/Core/Abilities approved execution path: Core approval and preflight
first, Adapter allowlisted execution second, Abilities media replacement
callback last. If Core policy blocks automatic execution, the proposal remains
pending for Core review. Toolbox may show Adapter's per-action results and
rollback evidence, but it must not update attachment files or URL references
directly.

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
plane. In the post editor, the image-candidate modal may expose a secondary
	saved-post media brief action as image planning context for source search,
	hosted image candidate requests, and media SEO review. That action remains
	suggestion-only and must not become a separate write surface, media import
	path, or featured-image setter.

Host-generated images are a separate explicit candidate mode, not a relabeling
of Unsplash, Pixabay, or Pexels. Toolbox may normalize a caller-supplied
generated image URL, call a host-provided
`npcink_toolbox_ai_image_generation_request` runtime seam, or dispatch a
reviewed `ai_generation_handoff` through the Cloud Addon runtime client to
return suggestion-only candidates with `source_type=ai_generated`, prompt/model
evidence, and human license review status. The compatibility route and ability
names may still contain "image-generation"; Toolbox must not own AI image model
routing, prompt management, provider credentials, billing, media import,
featured-image setting, or approval truth.

### Cloud Site Knowledge

Npcink Cloud owns vector storage, embedding provider configuration,
embedding dimensions, indexing, rerank, quotas, and detailed run health.

Toolbox may collect bounded public WordPress manifests, request Cloud sync,
show Cloud-returned status, and search Cloud-managed site knowledge. Toolbox
must not store vector provider keys, provider endpoints, collection names,
embedding model settings, or vector database lifecycle controls.

## Cloud Diagnostics Ownership

Toolbox does not own a Cloud Checks or Troubleshooting Checks product surface.
Cloud connection checks, hosted runtime health, provider/search/image-source
diagnostics, entitlement, quota, request logs, key verification, and service
monitoring belong in `npcink-cloud-addon` or Cloud service-plane surfaces.

Toolbox may show Cloud availability as read-only readiness state inside a
specific product workflow and may disable Cloud-only submits when the Cloud
Addon transport is unavailable. It must not render a local Cloud diagnostics
console, provider catalog, support-check workspace, or compatibility copy of
Cloud Addon Monitoring.

Toolbox product surfaces stay task-owned:

- **Content Library Usage** owns read-only Site Knowledge status/result
  consumption and review handoff context. Cloud Addon owns connector state,
  refresh, indexing, and delivery detail.
- **Site Check** owns the everyday site-maintenance entry: manual read-only site
  checks, explicit Cloud detail requests, and the operator-facing path to
  low-frequency scheduled review.
- **Scheduled Review** owns the Nightly/Morning Brief implementation details:
  scheduled-review preview and optional local fallback settings. Cloud Addon
  owns Nightly Inspection run status, result reads, and recovery.
- **Image Handling** owns selected-media review and governed handoff flows.

Cloud web search, image-source, and site-knowledge runtime routes may remain as
bounded call sites for those product workflows, but their standalone diagnostic
UI belongs outside Toolbox. Standalone diagnostics do not live in Toolbox.

## Scheduled Review Surface

The **Scheduled Review** surface is a low-frequency sub tab inside Site Check,
beside the **Current Check** manual report. The former Advanced and Morning
Brief entries may remain only as compatibility routes into Site Check; they
must not render separate directories that list Site Check detail and Scheduled
Review preview as parallel choices. Scheduled Review owns the
Nightly/Morning Brief preview entry and optional local fallback preview
settings. Cloud run status, result reads, recent runs, and retry requests
belong in the Cloud Addon Runtime Runs tab.
Scheduled Review must not live inside Cloud Checks and must not be presented as
an ordinary connection diagnostic or as a second site-check product.

The default Scheduled Review view should show one primary action: preview the
scheduled review. Site Check remains the ordinary manual site-check report and
the primary operator-facing site-maintenance entry.
Local fallback settings should stay folded behind an advanced disclosure inside
Toolbox because they control only the WordPress-side WP-Cron dry-run fallback.
They should not migrate to Cloud Addon, where operators manage Cloud runtime
state, retention, and recovery instead.
Toolbox may retain compatibility routes for existing Nightly Inspection Cloud
callers, but the visible recovery workspace should link operators to Cloud
Addon. Runtime entitlement, quota, batch limit, retention, recent/status/result,
and retry detail must stay in Cloud Addon Runtime Runs. Cloud remains
runtime/detail owner and Toolbox must not become a local billing ledger,
entitlement engine, retry queue, scheduler truth, local run history, Core
proposal creator, or WordPress write owner.

The connector surface must not become provider billing, quota, key-rotation,
request-log, marketplace, provider-routing, vector-provider, or vector
lifecycle ownership. Those are Cloud or future connector-owner concerns, not
the Toolbox MVP settings surface.
