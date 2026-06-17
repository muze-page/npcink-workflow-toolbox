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
- Post editor Content Support panel for fixed flows: writing preparation,
  title suggestions, outline support, polish notes, discoverability
  suggestions, publish preflight, summary suggestions, category suggestions,
  tag suggestions, internal-link candidates, image candidates, and current
  article image ALT suggestions. Image candidates may include a secondary
  saved-post media brief action for image planning.
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
- consolidate **Optimize Existing Image** as `media_optimization_v1`, the fixed
  governed media optimization workflow over the existing media derivative,
  reviewed metadata, Adapter recipe, and Core proposal handoff surface;
- batch review-set planning for media optimization, with explicit eligibility
  summaries, blocked reasons, selected previews, and selected Core proposal
  submissions;
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
- generate article outline with references only as an Article Assistant
  fallback after the operator chooses a writing-support route.
- rerank source, image, and vector candidates with Jina Reranker.
- improve Cloud image-source ranking with abstract-query rewriting,
  site-context vector rerank, candidate dedupe, quality/watermark filters,
  license evidence, risk tags, and media SEO suggestions.

Rule:

Buttons may run bounded synchronous planning actions. Long-running orchestration,
queues, retries, and scheduling require a separate runtime decision.
`media_optimization_v1` should stay a fixed governed workflow over the existing
Optimize Existing Image surface, not a generic workflow builder or persistent
run store.

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
