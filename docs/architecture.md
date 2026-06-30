# Architecture

Status: MVP architecture.

## Components

| Component | Responsibility |
| --- | --- |
| `npcink-workflow-toolbox.php` | Plugin header and bootstrap. |
| `Plugin` | Shared service construction and hook registration. |
| `Settings` | Option defaults, sanitization, non-search connector secret lookup, and content context export. |
| `Provider_Client` | Cloud image-source runtime calls, explicit AI-generated image candidate normalization, Cloud-managed site knowledge calls, Cloud-managed web search status, manual Site Check Cloud detail runtime calls, and fixed-flow planning actions. |
| `Rest_Controller` | Admin-facing REST routes for tool execution. |
| `Admin_Page` | WordPress admin tool surface, connector settings form, content context form, and Npcink submenu fallback. |
| `Ability_Surface_Metadata` | Read-only local projection of Toolbox-owned workflow defaults, route-only compatibility entries, runtime ownership, handoff posture, and overlap policy. It is not an ability registry, workflow registry, provider picker, request log, or approval store. |
| `Editor_Content_Support` | Post editor document panel entrypoint for fixed content-support flows. |
| `Article_Audio_Playback` | Frontend single-post playback entry for already adopted article audio metadata. It reads protected post meta or a host-projected approved audio packet and does not generate, adopt, or write audio. |
| `Abilities` | WordPress Abilities API exposure for Toolbox actions. |
| `Site_Ops_Snapshot_Collector` | Bounded read-only collector for Site Check: public posts/pages, approved-comment signals, media metadata, taxonomy summaries, and site info. |
| `Site_Ops_Insight_Builder` | Deterministic `site_ops_insight_pack.v1` builder that ranks local site-check findings for manual handling, existing fixed workflows, or optional Cloud detail without Cloud calls, persistence, proposals, or WordPress writes. |
| `Site_Ops_Cloud_Request_Builder` | Contract-only `site_ops_cloud_analysis_request.v1` builder for Cloud runtime/detail analysis; it does not call Cloud, create local runtime state, schedule work, persist runs, create proposals, or write WordPress data. |
| `Site_Knowledge_Auto_Sync` | Compatibility status projection for the Cloud Addon Site Knowledge change bridge plus retired legacy state cleanup. It does not own public content-change hooks, queues, retries, or refresh hints. |
| `modules/local-automation-runtime/` | Bundled module for the future `npcink-local-automation-runtime` owner; supports Phase 1A Manual Read-Only Preview plus one Phase 2 disabled-by-default Basic WP-Cron dry-run hook for the Local Fallback Preview. |
| `assets/admin.js` | Vanilla JS for fixed tool form submission and summary-first result rendering. |
| `assets/admin.css` | Admin layout, summary/detail result panels, and tool result styling. |
| `assets/editor-content-support.js` | Block editor sidebar panel for article checkup, publish preflight, taxonomy/tag, internal-link, image-candidate, outline, summary support flows, and selected-block paragraph review. |
| `assets/editor-content-support.css` | Compact editor-side layout for the content-support panel. |

Feature ownership and plugin split decisions should follow
[Feature Ownership And Plugin Boundary](feature-ownership-and-plugin-boundary.md).
New AI features should usually become Cloud capabilities, Ability contracts,
Core-governed recipes, and Toolbox surfaces before a new plugin is considered.

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

Article audio playback uses protected post meta as the local adopted playback
projection. Toolbox may prepare a review-only Core handoff plan for adopting a
reviewed audio candidate, but the projection is written only by the governed
Core/Abilities path, not by Toolbox:

- `_npcink_toolbox_article_audio_url`
- `_npcink_toolbox_article_audio_attachment_id`
- `_npcink_toolbox_article_audio_title`
- `_npcink_toolbox_article_audio_kind`
- `_npcink_toolbox_article_audio_duration_seconds`
- `_npcink_toolbox_article_audio_mime_type`
- `_npcink_toolbox_article_audio_source_content_hash`
- `_npcink_toolbox_article_audio_source_word_count`
- `_npcink_toolbox_article_audio_source_generated_at`

These keys are read by the frontend player only. Audio generation, review,
adoption, media import, and final writes remain outside this playback entry and
must use the governed WordPress/Core path.
The source fingerprint keys support a lightweight freshness status:
`current`, `minor_drift`, `review_recommended`, `stale`, or `unknown`. The
status is calculated from the current post content at render time and is shown
only as an editor/admin review hint; it does not trigger automatic
regeneration, local jobs, segment patching, or WordPress writes.

The bundled local automation runtime module does not change this. Phase 1A may
collect bounded read-only preview snapshots when an administrator clicks the
Morning Brief preview entry. This is a Toolbox-hosted operator preview, not a
runtime execution phase. It has no runtime job table, scheduler, worker, lease
store, retry processor, dead-letter processor, persistence path, Cloud call,
Core proposal creation, or WordPress write path.

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
   setting is enabled and `NPCINK_TOOLBOX_DISABLE_RAW_RESPONSES` has not forced
   them off. Debug payloads are bounded, key-redacted, and scanned for common
   token-shaped strings before display.
5. Any WordPress write remains a separate Abilities/Core handoff.

Cloud runtime payloads carry capability-specific timeout metadata, including
short budgets for fast image-source and summary paths. The direct WordPress HTTP
helpers also cap connection time, response size, and total request time so
future connector calls do not silently occupy PHP workers for a long default
timeout.

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
abilities as reusable tool inputs. Cloud-managed web search is owned by Npcink
Cloud, `search-image-source` is the general local image-candidate ability
that wraps the Cloud `npcink-toolbox/search-image-source` runtime ability on the
`image-source.managed` profile, and
`search-site-knowledge` is the general Cloud-managed semantic site-context
ability. The legacy `/vector-search` route remains a REST compatibility pointer
only and is no longer registered as a public Toolbox ability. Article writing
packs and article write plans are only one workflow family built from those
lower-level tools; the old article brief route is compatibility-only.

If `npcink-abilities-toolkit` is active, Toolbox uses its public helper functions.
Otherwise, Toolbox falls back to native WordPress Abilities API registration.

Current ability ids:

- `npcink-toolbox/search-image-source`
- `npcink-toolbox/generate-image`
- `npcink-toolbox/search-site-knowledge`
- `npcink-toolbox/cloud-web-search`
- `npcink-toolbox/get-site-knowledge-status`
- `npcink-toolbox/request-site-knowledge-sync`
- `npcink-toolbox/build-article-write-plan`
- `npcink-toolbox/build-article-batch-write-plan`
- `npcink-toolbox/build-article-media-batch-write-plan`
- `npcink-abilities-toolkit/build-image-candidate-adoption-plan`
- `npcink-abilities-toolkit/build-article-audio-adoption-plan`
- `npcink-toolbox/build-site-knowledge-review-plan`
- `npcink-toolbox/build-nightly-inspection-review-plan`
- `npcink-toolbox/build-media-derivative-handoff`
- `npcink-toolbox/get-content-discoverability-context`
- `npcink-toolbox/validate-content-discoverability-context`
- `npcink-toolbox/build-content-discoverability-brief`
- `npcink-toolbox/build-ai-article-writing-pack`

These are read/suggestion tools. They must not imply final WordPress write
approval, media import approval, or indexing lifecycle ownership. The legacy
`/flows/article-assistant` REST route remains route-only compatibility and is
not registered as a public Toolbox ability.
`npcink-toolbox/build-article-write-plan` assembles a Core-ready
`article_write_plan` for a reviewed draft and leaves proposal creation,
approval, preflight, audit, and final execution outside Toolbox.
`composer smoke:article-core` proves the local route by building the Toolbox
plan through `/wp-json/npcink-toolbox/v1/flows/article-plan`, submitting it to
Core `/wp-json/npcink-governance-core/v1/proposals/from-plan`, and asserting the
result is one pending dry-run `npcink-abilities-toolkit/create-draft` proposal
without creating a WordPress post.
`npcink-toolbox/build-article-media-batch-write-plan` assembles a
Core-ready `article_media_batch_write_plan` from reviewed drafts and reviewed
image-source candidates. It does not upload media, set featured images, approve
proposals, or execute writes. It is the high-risk contrast for Local Admin
Consent: multiple draft, media import, media metadata, and featured-image
actions must become one Core `plan_to_proposal_batch`, not local consent.
`npcink-abilities-toolkit/build-image-candidate-adoption-plan` assembles a Core-ready
`image_candidate_adoption_plan` from one reviewed `image_candidate.v1`. It does
not import media, update metadata, set featured images, approve proposals, or
execute writes. Toolbox keeps this route for the editor image recommendation
sidebar and machine clients; the old standalone admin
`tool=image-candidate-adoption` workbench is deprecated.
`npcink-abilities-toolkit/build-article-audio-adoption-plan` is the Core-ready
planner target for one reviewed narration or audio-summary candidate. Toolbox
may build an `article_audio_adoption_plan.v1` envelope that names the target
write ability, playback metadata projection, source-content fingerprint, and
evidence refs. The plan may request local media-library import, but media
import, playback metadata writes, proposal creation, approval, preflight, audit,
regeneration, and final execution remain outside Toolbox.
`npcink-abilities-toolkit/build-image-candidate-review-artifact` can normalize
already retrieved image candidates into `image_candidate_review.v1` and
`recommendation_candidate.v1` projections for editor or third-party review
surfaces. Toolbox still owns the image-source UX and Cloud/provider request.
For selected candidates that are already WordPress image attachments, the
editor may use `/local-admin-consent/featured-image` to set one attachment as
the current post's featured image. That route is the first Local Admin Consent
proof: it requires a present administrator, exact visible selection, one post,
one existing image attachment, Core audit before and after the write, and
rollback if completion audit fails. It does not import media, update metadata,
create a Core proposal, approve, preflight, or execute abilities.
The editor image-source modal may call this route for the selected candidate
when the selected candidate already has an attachment id, and it returns a
local consent result rather than an Adapter plan. For external URLs, generated
images, media import, media metadata, or combined adoption, the modal shows the
proposed media title, alt text, description, attribution, filename, and
featured-image action preview, then submits the returned plan through Adapter
`/proposals/from-plan`. Adapter calls Core approval and commit preflight before
executing the allowlisted media abilities, so ordinary editor-owned image
adoption can complete without a second manual review step when policy permits.
Core policy remains the proposal, approval, preflight, and audit owner;
Abilities remain the WordPress write executor; Toolbox is still a plan builder
and handoff surface outside the one local-consent featured-image proof.
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
gap analysis, and publish preflight duplicate checks. `/vector-search` remains
a REST compatibility route only and should not be used for new low-level vector
integrations or Ability clients.

The host can intercept site knowledge execution with
`npcink_toolbox_site_knowledge_cloud_request` or adjust the runtime payload
with `npcink_toolbox_site_knowledge_runtime_payload`. Without a host or
Cloud Addon runtime client, these abilities fail closed. Toolbox does not store
Cloud credentials and does not own the Cloud index lifecycle.

`request-site-knowledge-sync` collects only public WordPress manifests before
calling Cloud: published posts and pages, plus bounded approved comments for
the selected public entries. The sync payload is constrained by post count,
content excerpt length, comment count, and an aggregate JSON byte budget before
it leaves WordPress. The comment manifest carries public text and source
identifiers only. WordPress still owns moderation, edits, deletion, and final
write decisions. Automatic public content-change delivery is owned by Cloud
Addon after its bridge is installed and verified. Toolbox clears retired legacy
hooks, skips local fallback queue ownership, and only displays bridge health or
the Cloud Addon install-and-verify requirement.

The first admin REST surface remains `manage_options` gated by default.
External AI access should be mediated by Core/app-key scope checks in the host
that consumes these ability definitions. The host can use
`npcink_toolbox_rest_permission` and
`npcink_toolbox_ability_permission` as first-version integration hooks. Those
filters receive the required route or ability scope, such as
`cap.toolbox.knowledge.search` or `cap.toolbox.workflow_suggest`, so a host can
grant narrower access without treating every Toolbox action as one permission.

## REST Surface

Current routes require `manage_options`:

- `GET /wp-json/npcink-toolbox/v1/status`
- `POST /wp-json/npcink-toolbox/v1/image-candidates`
- `POST /wp-json/npcink-toolbox/v1/vector-search`
- `POST /wp-json/npcink-toolbox/v1/knowledge-search`
- `POST /wp-json/npcink-toolbox/v1/web-search/test`
- `POST /wp-json/npcink-toolbox/v1/web-search/diagnostics`
- `GET /wp-json/npcink-toolbox/v1/site-knowledge/status`
- `POST /wp-json/npcink-toolbox/v1/site-knowledge/search`
- `POST /wp-json/npcink-toolbox/v1/site-knowledge/sync`
- `POST /wp-json/npcink-toolbox/v1/agent-feedback`
- `POST /wp-json/npcink-toolbox/v1/agent-feedback/summary`
- `POST /wp-json/npcink-toolbox/v1/ai/content-support`
- `POST /wp-json/npcink-toolbox/v1/ai/site-helpers`
- `POST /wp-json/npcink-toolbox/v1/ai/image-generation`
- `POST /wp-json/npcink-toolbox/v1/flows/article-brief`
- `POST /wp-json/npcink-toolbox/v1/flows/article-assistant`
- `POST /wp-json/npcink-toolbox/v1/flows/article-plan`
- `POST /wp-json/npcink-toolbox/v1/flows/image-candidate-adoption-plan`
- `POST /wp-json/npcink-toolbox/v1/flows/article-audio-adoption-plan`
- `POST /wp-json/npcink-toolbox/v1/local-admin-consent/featured-image`
- `POST /wp-json/npcink-toolbox/v1/flows/site-knowledge-review-plan`
- `POST /wp-json/npcink-toolbox/v1/flows/nightly-inspection-review-plan`
- `POST /wp-json/npcink-toolbox/v1/flows/content-metadata-apply-plan`
- `POST /wp-json/npcink-toolbox/v1/flows/media-alt-caption-review-plan`
- `POST /wp-json/npcink-toolbox/v1/flows/media-brief`
- `POST /wp-json/npcink-toolbox/v1/editor/content-support`
- `POST /wp-json/npcink-toolbox/v1/media-derivative-handoff`
- `GET /wp-json/npcink-toolbox/v1/nightly-inspection/cloud-runtime-entitlement`
- `POST /wp-json/npcink-toolbox/v1/nightly-inspection/cloud-batch`
- `GET /wp-json/npcink-toolbox/v1/nightly-inspection/cloud-batch/recent`
- `GET /wp-json/npcink-toolbox/v1/nightly-inspection/cloud-batch/{run_id}`
- `GET|POST /wp-json/npcink-toolbox/v1/nightly-inspection/cloud-batch/{run_id}/result`
- `POST /wp-json/npcink-toolbox/v1/nightly-inspection/cloud-batch/{run_id}/retry`

`/status` reports Cloud-backed surfaces as registered capabilities plus current
availability. `web_search_registered`, `vector_search_registered`,
`cloud_runtime.available`, and `hosted_ai.available` distinguish an installed
Toolbox UI contract from a connected Cloud Addon or host runtime that can
actually execute the request.

`/ai/site-helpers` is the hosted AI contract for narrow site-helper suggestions:
bounded content opportunity samples, editor/sidebar current-article image
metadata, or an explicitly selected media review set. The content opportunity
intent remains route-only/internal; operator-facing site opportunity review
belongs in Site Check, not a standalone backend tool. Single-article
media ALT/caption review belongs in the editor sidebar, and batch media review
needs a selected review-set surface. The route returns a
`hosted_ai_site_helper` artifact and must not become a crawler, scoring engine,
batch media updater, proposal creator, or local queue.
When the post editor needs ALT suggestions, `/editor/content-support` passes a
bounded `current_article_media_metadata_only` snapshot of images already used
by the current draft into the same hosted helper runtime; it does not trigger
the site-level media-library sample.

`/ai/image-generation` is a candidate-only image-source extension. It accepts a
reviewed prompt from an image-source handoff, calls Cloud Addon runtime, and
returns `image_candidate.v1` evidence. It must not import media, set featured
images, own model routing, or write WordPress data.

`/nightly-inspection/cloud-runtime-entitlement` reads Cloud
`pro_cloud_runtime` quota detail through the Cloud Addon runtime client. It is
a local display snapshot for Toolbox Pro controls, not billing truth and not a
local entitlement engine. The Cloud Batch routes submit bounded local snapshots,
read Cloud-owned recent run cards, poll Cloud run status, request Cloud-owned
retry for a known terminal run, and read review-only result patches; they do
not create local queues, scheduler truth, Core proposals, approvals, or
WordPress writes.

`/flows/site-knowledge-review-plan` builds a blocked review plan from Cloud Site
Knowledge evidence so an operator can hand it to Core when a specific local
review is warranted. It is not a workflow runtime, queue, approval route,
preflight route, or write executor.

`/knowledge-search` and `/vector-search` remain compatibility aliases for the
first local MVP. New REST clients should use `/site-knowledge/search`, and new
Ability clients should use `npcink-toolbox/search-site-knowledge`.

The route surface is intentionally controlled by a static matrix in
`tests/run.php`. The matrix must stay exact: adding a route requires updating
the allowlist and boundary docs in the same change. The first version must not
register routes whose purpose is publish, delivery, workflow-run display,
queue/scheduler ownership, approval, write confirmation, featured-image
mutation, media upload/import, SEO mutation, indexing, or re-indexing.

`/editor/content-support` is the post-editor entrypoint for fixed, bounded
support flows. It accepts current draft context plus one intent:
`writing_support`, local full-draft diagnostics via `article_checkup`,
`title_suggestions`, `article_outline`, selection-only paragraph review via
`polish_notes`, `publish_preflight`, `discoverability`, `summary_suggestions`,
`category_suggestions`, `tag_suggestions`, `summary_terms_optimization`,
`taxonomy_tags`, `internal_links`, `image_candidates`, or
`image_alt_suggestions`.
The editor UI groups the default buttons around the author workflow. Common
default buttons are now Npcink review and handoff actions: publish preflight,
internal-link candidates, image candidates, and article audio candidates.
Generic AI-plugin-style generation and diagnosis intents such as
`article_checkup`, `title_suggestions`, `summary_suggestions`,
`category_suggestions`, `tag_suggestions`, `article_outline`,
`discoverability`, `image_alt_suggestions`, and `comment_reply_suggestion`
remain supported by compatible route/result-rendering code, but they are not
default visible buttons. Related existing-post review is folded into publish
preflight duplicate-risk checks and internal-link candidates; `writing_support`
also remains a supported route intent for compatibility but is not a default
editor button. Article checkup is a local suggestion-only diagnostic that points
to sentence-density, fact-gap, tone, structure, and format review items without
rewriting or inserting text. Paragraph review lives in the selected-block
toolbar.
The discoverability result may show a current-draft image ALT/caption check and
CTA that reuses the `image_alt_suggestions` intent; generated suggestions merge
back into the discoverability panel while preserving the
`current_article_media_metadata_only` and no-media-write boundary.
The backend Image Handling tab uses the same hosted site-helper intent only for
an explicit small media-library review set. Operators can select returned
items and call `/flows/media-alt-caption-review-plan` to prepare a
`media_alt_caption_core_handoff_plan.v1`. The plan contains ALT-only proposal
payloads that the admin UI may submit through Adapter and then request Core
`approve-and-execute`. Core policy owns auto-approval, execution, and audit;
Toolbox still does not directly write media metadata, and caption edits stay
manual-review work.
The standalone discoverability result is a post-publish optimization task
panel: SEO title, SEO description, slug, and excerpt are shown as actionable
review tasks. SEO title and description use the governed SEO handoff, then ask
Adapter/Core to approve, preflight, and execute the created proposal when host
policy allows; if policy blocks execution, the proposal remains available for
Core review. Excerpt can update the current editor draft after an explicit
operator click. Slug is separated into a permalink-risk action with
confirmation before the editor draft slug is changed; published posts receive
stronger URL/indexing warning copy. AEO/GEO/FAQ/schema ideas remain optional
crawler-facing review notes.
The focused result view accepts a bounded
operator instruction for regeneration; that instruction is treated as tone,
angle, audience, or ranking preference only, not as factual source material or
write authorization. Reviewed title and summary candidates may be applied to
the current editor state by explicit operator click, then the normal WordPress
draft save persists the change. Taxonomy, SEO, media, and new-term outcomes stay
on Core-governed handoff paths. `summary_terms_optimization` and
`taxonomy_tags` remain lower-level/full support intents, not separate default
buttons. The image candidate modal also exposes the saved-post
`/flows/media-brief` result as a secondary image-plan action; it is not a new
primary sidebar button and it does not write media, metadata, or post content.
The route returns an `editor_content_support_flow` artifact with
suggestion-only sections and no direct WordPress write posture.
Focused summary generation defaults to `summary_generation_mode=fast_brief`:
Toolbox locally compresses the current draft into a source brief before calling
the hosted AI runtime. To keep the first result path interactive, the default
fast brief does not block on a fresh Cloud Site Knowledge vector request; it
only attaches `summary_context` passages when the matching short cache is
already available and reports timing for the vector cache check and hosted AI
call. Those passages can add coverage and site-style hints, but cannot replace
the current draft as the factual source. The result view can run an explicit
advanced `full_context` fallback that sends the bounded full draft context with
a longer timeout. This remains a hosted runtime call pattern, not Cloud
prompt/router truth, local indexing ownership, or a new write path.
The split metadata intents return the same
`article_discoverability_optimization.v1` section shape through lighter
draft/taxonomy fast paths, while the full `summary_terms_optimization` intent
still combines hosted AI summary suggestions, existing category/tag candidates
ranked by `npcink-abilities-toolkit/suggest-post-taxonomy-terms`,
Cloud-managed Site Knowledge related-content evidence, Cloud-managed web-search
evidence, and saved content-context guidance. It is not a term assignment,
excerpt update, SEO mutation, content indexing, or local RAG/index lifecycle
route. The section
keeps summary candidates split by use case, annotates them with related-content
context for duplicate/topic-fit review, marks WordPress taxonomy candidates as
existing terms with match tokens and normalization keys, and boosts existing
categories or tags that already appear on related Site Knowledge posts. That
related-term evidence is passed to Toolkit as ranking context only: it must not
create terms, assign terms, persist feedback, or become an index lifecycle
signal. The section also
returns suggestion-only ranking, dedupe, and review-metric guidance so editors
can judge precision without creating a Toolbox audit store. It also includes a
`content_metadata_delta` P0 artifact for one current post: issue record,
metadata diagnosis, recommended excerpt, existing category/tag deltas,
authorization classification, outcome checks, and future learning candidates.
Operators can scope the input to the full article,
selected text or block, or a topic-only brief. The same section may expose
future taxonomy-gap signals only as deferred governance notes, not as current
editor recommendations, and may include a preview-only Core handoff packet for
accepted summary and existing-term choices. `/flows/content-metadata-apply-plan`
remains the Toolbox product surface for accepted excerpt, existing category,
and existing tag choices. The Core handoff uses
`npcink-abilities-toolkit/build-content-metadata-apply-plan`, which lets
OpenClaw, Adapter, and third-party plugins build the same dry-run
`content_metadata_apply_plan` without depending on Toolbox. The plan targets
only `npcink-abilities-toolkit/update-post` for excerpts and
`npcink-abilities-toolkit/set-post-terms` for existing category or post-tag ids,
always with `create_missing=false`, `dry_run=true`, and `commit=false`. The
handoff packet includes proposal-ready actions for Generate and apply summary,
Recommend and apply tags, and Recommend categories.
Toolbox marks summary application and existing tag assignment as Core
auto-approval candidates, keeps categories recommendation-first by default, and
defers new taxonomy creation to a later governance workflow. The delta artifact
is suggestion-only and does not persist learning/audit state or write WordPress
metadata.

A future local direct-apply path for the same editor metadata values is a
`strong_local_confirmation` candidate, not a Local Admin Consent extension. It
must be designed before implementation with one-current-post scope, exact
final metadata preview, existing terms only, explicit confirmation copy,
old/new audit evidence, actor/source/correlation metadata, recovery evidence,
and fail-closed audit behavior. Until that contract exists, accepted metadata
choices remain Core proposal handoffs.

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

The reviewed-draft write-plan flow remains available through REST and the
`npcink-toolbox/build-article-write-plan` Ability for machine clients, future
Cloud bulk import, and explicit API composition. It is no longer exposed as a
backend admin tool because there is no active external-draft import workflow;
the ordinary operator path is the editor sidebar plus Site Check for
site-level opportunities.

The admin page defaults to an **Overview** surface for ordinary site owners,
with one recommended next action, compact status rows for AI service, Site
Profile, and safe mode, followed by common site/image next steps plus one
folded advanced directory and a collapsed **System status** disclosure. That
disclosure shows **Workflow readiness**, a
read-only summary from `Ability_Surface_Metadata` for site profile readiness,
Cloud runtime availability, default Npcink workflow entries, route-only
compatibility, and Core handoff boundary. This summary is not a generic Abilities Explorer,
provider picker, request log, or connector approval surface. It does not create
proposals or writes. Single-post article
support stays in the post editor sidebar and is not rendered as an Overview
work block. The visible top-level admin tabs after Overview are **Site
Profile**, **Image Handling**, and **Advanced**. **Site Check** remains a
secondary deep-link panel and is the Overview page's recommended site-check
action; it builds a local
`site_ops_insight_pack.v1` from bounded public content, approved comment signal
counts, media metadata, taxonomy summaries, Site Context readiness, and Cloud
availability, then presents it as a current-run decision queue for manual
handling, existing fixed workflows, and optional Cloud detail. Coverage metrics,
lightweight charts, deterministic local summary, content, media, comments,
structure, findings, Cloud detail, and advanced data views are supporting
detail, including site content opportunity findings.
It can also prepare a copyable
`site_ops_cloud_analysis_request.v1` for Cloud runtime/detail analysis. The
local preview does not auto-send the request; when Cloud is ready, the
administrator may explicitly run Cloud detail and render the suggestion-only
`site_ops_cloud_analysis_result.v1`. It is a manual review surface, not a
Cloud batch owner, local queue, Core proposal creator, or WordPress write path.
Nightly Inspection fallback preview settings live in the low-frequency
secondary **Scheduled Review** panel reached from Site Check and the Advanced
directory. Cloud run status, result reads, recent runs, and recovery live in
Cloud Addon Runtime Runs. They do not live inside Cloud Checks. That keeps
recurring inspection preview and Cloud run recovery separate from ordinary
connection diagnostics and from the everyday Site Check decision queue. These
controls do not belong in the visible top-level tabs or default Overview view,
and Site Check remains the ordinary manual
site-maintenance entry. Site Knowledge
connection, refresh, indexing, and deep delivery detail live in
`npcink-cloud-addon`; Toolbox keeps only a secondary **Content Library Usage**
panel for read-only status and best-practice result consumption. Advanced is the
single low-frequency directory and groups secondary links as Setup,
Review, and Planning/Handoff instead of duplicating them on
Overview.

The admin **Image Handling** tab groups image-first buttons by operator job and
defaults to **Image Optimization**, with **Batch Optimize Images** as the first
visible workbench. Single-image actions start from the WordPress
media-library attachment details panel or image row actions, then enter the same
selected Batch Image ALT or Batch Optimize Images workbenches used by bulk
selections.
It no longer exposes a standalone one-image optimization picker or a
single-article image text helper; article-specific image text needs current
editor context in the editor sidebar. The separate **Batch Image ALT** group
builds a small selected media-library review set and can prepare a Core handoff
draft without creating a proposal or writing media metadata. The separate
standalone content opportunity admin tool is retired; site-level opportunities
are reviewed through Site Check.
The old Article Planning Bundle is not an operator-facing admin tool;
`/flows/article-brief` remains available only as a compatibility REST route for
OpenClaw or external AI callers. The old `tool=article-assistant` and
`tool=article-plan` URLs fall back to Site Check instead of restoring
draft-side backend tools.
Batch entry points use `tab=image&tool=bulk-alt` and
`tab=image&tool=batch-optimize`; deprecated `tool=optimize` and legacy
`toolbox_tool=media-derivative` URLs remain accepted only as compatibility
aliases that canonicalize to Batch Optimize Images.
Publish preflight, internal-link candidates, image candidates, and article audio
candidates stay as default post editor buttons. Summary suggestions, category
suggestions, tag suggestions, article checkup, discoverability, current-article
ALT checks, outline, and comment-reply support stay route-compatible but are not
default editor buttons.

Toolbox also renders additive `operator_feedback` payloads from governed
handoff failures, including reasons, revision fields, next steps, retry state,
and Core evidence. This is display-only feedback for the operator; Core remains
the approval and preflight truth, and Adapter remains the OpenClaw execution
channel.

This is a display contract only. It does not add Content Assistant article,
comment, media, preview, confirm, or apply responsibilities to Toolbox.

## Editor Surface

Toolbox registers a **Npcink Content Support** plugin sidebar in the block
editor for users who can run the existing Toolbox REST tools. The sidebar is
opened from the editor top toolbar. It is a high-frequency entrypoint for the
same fixed workflows that the admin surface owns:

- publish/readiness preflight;
- internal-link candidates from `npcink-abilities-toolkit/resolve-internal-link-targets`,
  optionally ranked with Cloud-managed Site Knowledge evidence;
- image-source candidates through the configured Cloud image-source runtime.
- article audio candidates that can prepare a Core-governed audio adoption
  plan.

Generic title, summary, taxonomy/tag, outline, article-checkup,
discoverability, current-article ALT, and comment-reply result views remain in
the editor code as compatible support paths, not as default buttons.

The editor panel reads the current draft title, excerpt, content, terms, status,
and featured image id. It never assigns terms, automatically inserts links,
imports media, publishes content, or writes SEO fields. The internal-link panel
may copy reviewed links or open target articles, but it does not format editor
text selections or run backend post-content patches. Write-like follow-up must
still go through Core proposals and reusable WordPress abilities.
The editor feedback surface is intentionally low-friction. Explicit rating
buttons are folded behind `Report issue` and `Report image issue` details
entries, while successful operator actions can send silent metadata-only
Agent feedback through the existing `/agent-feedback` route. These events
capture bounded action metadata such as handoff type, handoff id, outcome,
fixed labels, run id, and evidence refs. They must not include article body
text, prompts, user email, provider secrets, free-form notes, SEO values,
media write payloads, or WordPress write authorization. Cloud may use the
events for eval and quality rollup only; Toolbox does not persist learning
truth, approval truth, audit truth, prompt/router truth, or final write truth.
The image-source entry opens a Cloud recommendation modal that auto-searches
from the selected paragraph or selected block when available, combines that
focus with the current draft title/excerpt/body context, and also accepts a
manual image query. The interactive path uses `latency_mode=fast_first`: the
initial request sends a short visual query and bounded article context so Cloud
can build a visual brief and return source candidates quickly, while
Cloud-managed site context vectors, candidate rerank, and media SEO enrichment
are treated as deferred enhancement work; in other words, site-context vectors,
candidate rerank, and media SEO stay Cloud-owned. Toolbox only receives the normalized
candidate payload, match reasons, visual brief status, and optional SEO fields;
it does not configure or own image providers, vector indexes, or rerank models.
The modal renders image candidates with previews, source links,
attribution, provider metadata, license-review state, and preserved Unsplash
download tracking when present. It does not upload media, set featured images,
or create a write proposal directly.
The exception is an already-imported image attachment selected in the editor:
that can be set as the current post featured image through the
Local Admin Consent route with Core audit. External URLs, generated URLs,
media import, metadata writes, and multi-image operations still use governed
handoff paths.
The selected-block toolbar exposes selection-only paragraph review and paragraph
image shortcuts. Paragraph review returns clarity, fact-boundary, tone, and
editing-direction notes without replacement copy. The image shortcut opens the
same modal as an image-icon paragraph image suggestion shortcut. In that mode
the selected paragraph or block is the primary image context and the default
reviewed action is media import only; the sidebar image-source entry stays the
article-level featured-image path.
The modal is implemented as a reusable image-source picker. Future settings or
other image fields can open it with a manual query and optional context, then
listen for the selected `image_candidate.v1` and media SEO payload. That
selection-only mode returns data to the caller; any option, theme-mod, media, or
featured-image write must still use the governed Ability/Core path for that
surface.

The picker keeps local behavior small and Cloud behavior richer. Locally it
owns the modal shell, one manual search input, short-lived result caching,
empty-state query suggestions, concise source cards, and the selected-image
inspector. Cloud owns abstract-topic query rewriting, visual brief generation,
site-context vector lookup, candidate rerank, near-duplicate filtering,
watermark/quality filtering, rights/attribution evidence, risk tags, and media
SEO suggestions. This keeps image-source reuse fast for editor and settings
surfaces without turning Toolbox into an image index, provider router, media
registry, or write executor.

`media_optimization_v1` is the architecture name for media-library single-image
actions and the Toolbox Batch Optimize Images workbench. It is
implemented with current admin state, Adapter media derivative routes, Cloud
Addon transport, Core proposal handoff, and Abilities media contracts. It
does not introduce a Toolbox custom table, a /workflow-runs route, queue, scheduler,
retry lease, artifact registry, or direct media writer.
Batch media replacement follows the same dependency direction: OpenClaw/Adapter
must prove selected-batch execution with Core approval, commit preflight,
execution profile allowlist evidence, per-action results, and Abilities media
replacement callbacks before Toolbox presents it as a fixed best-practice
button. Toolbox may render review sets, selected previews, proposal submission,
and returned execution outcomes; it must not own the batch execution semantics.

Toolbox no longer renders a Cloud Checks or Troubleshooting Checks secondary
panel. Cloud connection checks, hosted runtime health, provider/search/image
diagnostics, key verification, entitlement, quota, billing, request logs,
content-operations coverage, and Agent quality summaries belong in
`npcink-cloud-addon` or Cloud service-plane surfaces. Toolbox keeps only
task-owned product panels: Content Library Usage for read-only Site Knowledge
status/result consumption, Site Check for manual site checks and the ordinary
site-maintenance entry, Scheduled Review for Nightly/Morning Brief preview and
local fallback settings, and Image Handling for selected-media review/handoff
flows. Cloud Runtime Runs in Cloud Addon owns Nightly Inspection recent/status/
result/retry detail. Cloud runtime routes may remain bounded call sites for
compatibility, but standalone diagnostics do not live in Toolbox.
Nightly Inspection / Morning Brief controls stay in the separate
`morning-brief` secondary panel, not in local diagnostics or a second
site-check product.

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
