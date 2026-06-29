# Roadmap

Status: planning baseline.

## Stage 0 - Project Contract

Goal: make the standalone plugin understandable to future sessions.

Done:

- WordPress plugin scaffold.
- Admin toolbox screen.
- Cloud-managed web search, image-source, and site knowledge status surfaces.
- Content discoverability context setting and read-only Abilities exposure.
- Content discoverability context validation and one-item brief abilities.
- REST routes.
- Abilities registrations.
- Static contract tests.
- Boundary and architecture docs.

Gate:

```bash
composer test:all
composer validate --no-check-publish
```

## Stage 1 - Local Operator MVP

Goal: prove the Toolbox is useful as a manual admin tool.

Target features:

- Cloud-managed web search action with source-aware response display.
- configured image-source action with browser preview instead of raw JSON only.
- Cloud-managed site knowledge search, status, and sync.
- Content support brief button for SEO/AEO/GEO, source coverage, image
  candidates, and internal-link context.
- Media brief button.
- Content Context form for SEO, AEO, and GEO guidance.
- Full-site Insights tab for a manual local `site_ops_insight_pack.v1` that
  presents a current-run site analysis report across content,
  approved-comment, media, taxonomy, Site Context, and Cloud readiness findings
  with coverage metrics, lightweight charts, deterministic local summary, and
  dimension views, without Cloud calls, persistence, Core proposals,
  scheduling, or WordPress writes. It may also prepare
  `site_ops_cloud_analysis_request.v1` as a Cloud runtime/detail contract. When
  Cloud is ready, an administrator may explicitly run Cloud analysis for a
  suggestion-only `site_ops_cloud_analysis_result.v1`, without Toolbox owning a
  local queue, run table, scheduler truth, Core proposal, or WordPress write.
- Post editor Content Support panel for default Npcink review and handoff
  buttons: publish preflight, internal-link candidates, image candidates, and
  article audio candidates. Generic AI-plugin-style intents such as local
  article checkup, title suggestions, outline support, discoverability,
  summary suggestions, category suggestions, tag suggestions, current article
  image ALT suggestions, and comment-reply suggestions remain compatible
  route/result paths, not default visible buttons. Selection-only paragraph
  checks belong in the selected-block toolbar beside paragraph image
  suggestions. Related existing-post review belongs inside publish preflight
  duplicate-risk checks and internal-link candidates rather than a separate
  writing-preparation button. Image candidates may include a secondary
  saved-post media brief action for image planning.
- Frontend single-post article audio playback for already adopted narration or
  audio-summary metadata. This is a playback entry only; generation, adoption,
  proposal review, media import, regeneration, and writes stay in the governed
  path. Lightweight source-content freshness status may tell editors when
  adopted audio is current, lightly drifted, review-recommended, or stale.
- Clear empty/error/loading states.
- Reusable image-source picker with short-lived local result caching,
  empty-state query rewrites, concise candidate cards, and selected-image
  detail review for editor and settings surfaces.
- Local WordPress activation smoke.

Non-goals:

- background jobs;
- final WordPress writes;
- multi-provider routing;
- quota and billing UI.
- WordPress content indexing.

## Stage 2 - Governed Handoffs

Goal: connect useful suggestions to governed WordPress changes.

Target features:

- taxonomy/tag proposal handoff;
- internal-link candidate handoff for operator review;
- image candidate adoption proposal handoff;
- consolidate **Batch Optimize Images** and Media Library image actions as
  `media_optimization_v1`, the fixed governed media optimization workflow over
  the existing media derivative, reviewed metadata, Adapter recipe, and Core
  proposal handoff surface
  (validated and frozen for V1 after the 2026-06-18 real-attachment operator
  trial);
- batch review-set planning for media optimization, with explicit eligibility
  summaries, blocked reasons, selected previews, and selected Core proposal
  submissions;
- selected media ALT/caption review-set planning for recent weak metadata
  images, with operator selection and a Core handoff draft only; Toolbox does
  not create the proposal, approve, execute, or write media metadata;
- OpenClaw/Adapter selected-batch execution proof before any Toolbox batch
  "replace original image" button is treated as product-ready;
- article write plan artifact for one reviewed human draft as a fallback
  off-ramp;
- set featured image proposal handoff;
- update media metadata proposal handoff;
- set SEO meta proposal handoff;
- use content discoverability context when preparing SEO/AEO/GEO proposal
  payloads;
- validate content discoverability context before third-party AI usage;
- handoff status display that points operators to Core review.

Rules:

- every write-like action creates or prepares a Core proposal;
- Toolbox does not bypass Core approval;
- proposal payloads use real WordPress ability ids.
- batch plans are review sets, not Toolbox-owned queues or automation workers.

## Stage 3 - Knowledge Base Operations

Goal: make vector search practical for site content.

Target features:

- Cloud-managed site knowledge Abilities for search, status, and sync;
- Cloud implementation of `site_ops_cloud_analysis_result.v1` for heavier
  Full-site Insights AI summary, semantic ranking, trend explanation, and
  operator next-action detail, using the Toolbox-prepared
  `site_ops_cloud_analysis_request.v1`;
- site-content indexing plan;
- Cloud operator vector provider configuration and migration paths;
- manual re-index button;
- index status display;
- document/source coverage report;
- stale index warnings;
- internal-link and old-article refresh suggestions.

Open decision:

- whether Cloud-owned vector indexing is implemented directly in Cloud service
  APIs or through a future `npcink-knowledge` connector, while Toolbox stays
  the local Ability exposure surface.
- which embedding provider owns text-to-vector conversion.

## Stage 4 - Productized Workflow Buttons

Goal: add repeatable operator flows without creating a workflow runtime.

The productized button layer now includes read-only ability surface metadata and
the Overview **Npcink capability health** summary. This keeps default entries,
route-only compatibility, runtime owner, handoff path, and overlap policy
visible to operators and maintainers without becoming a generic Abilities
Explorer, provider picker, request log, connector approval surface, second
registry, or runtime. It is not a generic Abilities Explorer.

Candidate buttons:

- recommend taxonomy and tags;
- find internal-link opportunities;
- find configured image-source candidates for featured or inline images;
- build content discoverability suggestions from the operator-filled context;
- run current-post publish/readiness preflight for source coverage, duplicate
  risk, and missing media metadata;
- optimize old article;
- complete media alt and caption suggestions for site-level media review;
- build FAQ suggestions;
- check source coverage for the current editor or one explicit operator review.
- generate article outline with references only through bounded editor support
  or reviewed-draft handoff surfaces, not through a restored Article Assistant
  product entry.
- rerank source, image, and vector candidates with Jina Reranker.
- improve Cloud image-source ranking with abstract-query rewriting,
  site-context vector rerank, candidate dedupe, quality/watermark filters,
  license evidence, risk tags, and media SEO suggestions.

The next batch candidate starts with a review-only P0:
`media_alt_caption_review_set.v1`. It extends the existing AI Site Helpers
media ALT suggestions response with bounded eligibility, selected items,
blocked reasons, retry guidance, and an explicit no-write posture. It defaults
to current article used image metadata only; the recent media-library metadata
sample remains an explicit advanced fallback. Every selected item requires
human visual confirmation.
Before extracting any reusable logic to Toolkit, run the
[Media ALT/Caption Toolkit Validation Plan](media-alt-caption-toolkit-validation-plan.md).

The media optimization operator trial has accepted the current low-risk flow.
The preferred follow-up order remains:

1. media ALT and caption review set;
2. taxonomy and tag review set;
3. internal-link review set.

These should remain bounded planning or Core handoff surfaces. The media
ALT/caption P0 must not become direct media metadata writes, automatic proposal
creation, or media-library batch execution until Abilities, Core, and Adapter
have an accepted media metadata update path. Do not add another write-like batch
surface beyond `media_optimization_v1` without a new trial and boundary
decision.

Rule:

Buttons may run bounded synchronous planning actions. Long-running orchestration,
queues, retries, and scheduling require a separate runtime decision.
`media_optimization_v1` should stay a fixed governed workflow over Media
Library image actions and the Batch Optimize Images surface, not a generic
workflow builder or persistent run store.

Batch and automation planning follows
[Batch Automation Governance Plan](batch-automation-governance-plan.md):
Toolbox may adopt rule-first eligibility, blocked-item reporting, selected
previews, and operator recovery guidance, but it must not import local queue
runtime, unauthenticated triggers, administrator impersonation, automatic
publishing, automatic term creation, or direct WordPress writes.
For batch media replacement, the canonical order is OpenClaw first and Toolbox
second: Adapter proves selected-batch execution with Core approval/preflight,
execution profiles, per-action results, and Abilities callbacks; Toolbox then
turns that accepted path into a fixed best-practice button.

The first local automation runtime step is Phase 1 only: Toolbox may bundle
`modules/local-automation-runtime/` for contract docs, deterministic scoring,
Phase 1A Manual Read-Only Preview, a dry-run replay validator, positive smoke
tests, and negative fail-closed replay tests. Phase 1A is a Toolbox-hosted
operator preview, not a runtime execution phase. It must not add workers,
schedulers, runtime job tables, leases, retries, dead-letter processors,
unattended approval, persistence, Cloud calls, Core proposal creation, or
execution buttons in this stage. The first implementation that adds scheduled
or supervised execution belongs to the `npcink-local-automation-runtime`
runtime owner boundary.

Current Nightly Inspection automation should follow ADR-005: no plugin-side
Action Scheduler for Basic or Pro, WP-Cron only as local fallback preview or
future bounded local submit trigger, and Cloud Batch Runtime as the Pro
orchestration path without Cloud scheduler truth or WordPress write authority.
Site-wide or multi-article old-article source coverage overlaps with Nightly Inspection
and should stay in Cloud Batch Runtime result detail and reviewed Core handoff; it should not become a separate Toolbox local batch surface.

## Deferred Decisions

- provider connector split;
- vector store ownership;
- request log ownership;
- cost and quota display;
- multisite behavior;
- scoped non-admin permissions.
