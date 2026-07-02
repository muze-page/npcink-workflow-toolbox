# Adversarial Boundary Review

Status: active development quality mechanism.

This document records how Toolbox uses the development-only `npcink-eval-lab`
to challenge its own product boundary. The goal is not to make model reports
quiet. The goal is to keep proving that Toolbox remains the WordPress
operator-facing fixed-button and suggestion surface, not a second governance,
runtime, provider, indexing, or write-control plane.

## Purpose

The boundary proof mechanism serves three jobs:

1. Guard the Toolbox positioning: fixed buttons, read-only evidence,
   suggestion artifacts, and governed handoffs.
2. Detect boundary drift early: direct writes, second registries/stores,
   provider secret ownership, local runtime ownership, content indexing
   lifecycle ownership, full-RAG claims, and image-source versus AI-generation
   confusion.
3. Turn disputes into reviewable evidence: each model finding must be
   classified as `accepted_fix`, `accepted_exception`, or `rejected_finding`
   before implementation work proceeds.

Eval-lab output is local development evidence only. It is not a Core audit
record, an approval decision, a release gate by itself, or product runtime
state.

## Command

Run the dry-run wiring check first:

```bash
composer eval:workflow-toolbox:adversarial-boundary -- dry_run=true
```

Run the provider-backed audit only when local eval-lab provider profiles are
configured and the reviewer explicitly wants model findings:

```bash
composer eval:workflow-toolbox:adversarial-boundary
```

The wrapper calls eval-lab task `workflow_toolbox_adversarial_boundary_audit`
with this repository as `project`. Reports are written under the sibling
eval-lab checkout at `project-review/generated/` and stay ignored by git.

## Current Triage

The model-backed audit run on 2026-07-02 produced 19 findings. The triage below
keeps those findings actionable without treating the model as the final
authority.

| Topic | Decision | Rationale | Follow-up |
| --- | --- | --- | --- |
| Local Admin Consent featured-image write | `accepted_exception` | ADR-003 and the Boundary Exceptions Registry intentionally allow exactly one present-admin, one-post, existing-attachment featured-image proof with Core-owned audit and rollback. It is not a precedent for other direct writes. | Keep static contracts proving only this route exists and high-risk article/media batches never use Local Admin Consent. |
| Local fallback WP-Cron preview and bundled runtime module | `accepted_exception` | ADR-004 and ADR-005 intentionally allow an isolated `npcink-local-automation-runtime` module and one disabled-by-default latest-preview WP-Cron path. It must not become Toolbox-owned Pro scheduler truth, job storage, leases, retry, dead-letter, Core proposals, Cloud calls, or WordPress writes. | Keep boundary-exception wording and fail-closed replay/scheduler tests. |
| `/ai/image-generation` and `npcink-toolbox/generate-image` naming | `accepted_fix` | The feature is candidate normalization or host-provided generated-image seam, not Toolbox-owned model routing, prompt management, billing, media import, or generation runtime. Ambiguous docs can mislead future work. | Keep docs and tests distinguishing image-source search, host-generated image candidates, and AI generation runtime ownership. |
| Cloud Batch compatibility routes | `accepted_exception` | The routes are compatibility bridges for existing callers; Cloud Addon owns entitlement, quota, run status, results, retry, and recovery detail. | Keep docs saying these routes do not store Toolbox run history, scheduler truth, retry ownership, Core proposals, or WordPress writes. |
| Site Check "priority queue" wording | `accepted_fix` | Product docs should not imply local queue/runtime ownership. The UI/data may carry ranked priority fields, but the product concept is a read-only ranked review list. | Use "ranked review list" in positioning docs and reserve queue wording for Cloud/runtime contracts only. |
| Stage 3 indexing and re-index roadmap wording | `accepted_fix` | Site Knowledge indexing, stale detection, rebuild/delete, vector collection lifecycle, embeddings, and rerank policy are Cloud-owned. Toolbox may display status and request explicit sync/search through a contract. | Keep Stage 3 focused on Cloud-managed Site Knowledge status/search/sync and Cloud-returned coverage/freshness. |
| Jina Reader/Reranker candidate button | `accepted_fix` | Jina Reader/Reranker must not appear as an active Toolbox runtime feature before a workflow-level Cloud/runtime contract exists. | Document only Cloud-returned ranking after a separate Cloud-owned reranking contract. |
| Provider secret storage wording | `accepted_fix` | Toolbox must not become durable provider key, billing, quota, request-log, or key-rotation owner. | Keep settings docs limited to non-secret context and explicitly reject provider secrets in local options unless a later connector contract owns them. |
| Ability ownership wording | `accepted_fix` | Toolbox may register Toolbox ability wrappers, while Toolkit owns reusable WordPress ability definitions and write/planner targets. Mixed lists can look like a second registry. | Separate "Toolbox-registered abilities" from "Toolkit target ability ids" in future doc cleanup. |
| Deprecated compatibility routes | `accepted_fix` | Route-only compatibility entries are allowed, but docs must keep them visibly deprecated and non-product. | Keep `/vector-search` and `/flows/article-assistant` described as compatibility only, not new public surfaces. |
| Need stronger static guard coverage | `accepted_fix` | The mechanism should fail when documentation loses the proof vocabulary or when new exceptions bypass the registry/ADR path. | Static tests must cover this document, the eval-lab wrapper, the exception registry, and the highest-risk redline vocabulary. |
| Mandatory pre-edit reads of boundary matrix and exceptions | `accepted_fix` | Boundary-sensitive sessions should read current split and accepted exceptions before changing behavior. | Add these docs to the development workflow startup list. |

## Static Guard Policy

Static guards should prove the mechanism, not freeze every wording choice. The
default checks should cover:

- Toolbox positioning remains `suggestion_only` by default.
- `confirm_token` and `write_confirmed` stay absent.
- Accepted exceptions are listed in `docs/boundary-exceptions.md` and backed by
  ADRs.
- New direct writes, schedulers, queues, approval stores, provider secret
  stores, indexing lifecycle controls, and image-generation runtime ownership
  require a new ADR and static contract.
- Eval-lab remains a development-only proxy and is outside `composer test:all`.
- Model-backed findings are triaged before becoming implementation work.

## Completion Standard

A boundary review cycle is complete only when:

1. `composer eval:workflow-toolbox:adversarial-boundary -- dry_run=true`
   proves the wrapper still works.
2. Provider-backed eval-lab review has either been run or intentionally skipped
   with the reason recorded.
3. Every model finding is classified as `accepted_fix`,
   `accepted_exception`, or `rejected_finding`.
4. All accepted fixes in the current scope have docs or tests attached.
5. Existing exceptions still point to ADRs and static contracts.
6. `composer test:all` passes after the scoped work.

