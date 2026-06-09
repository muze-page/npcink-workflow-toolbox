# Content Metadata Governed Handoff Summary

Status: local handoff summary for future AI sessions.

Date: 2026-06-09

## Product Thesis

The goal is not simply to make WordPress "write articles with AI". The better
first-principles framing is that WordPress is an observable publishing and site
operation surface, while AI is the reasoning layer that helps detect vague
editorial problems, propose bounded improvements, and hand write-like changes
to governed review.

The path-dependence risk is treating a new production tool as an old article
editor with an AI button. The new operating pattern should be:

1. observe the current post and related site context;
2. identify the specific metadata problem;
3. produce a small delta, not a broad rewrite;
4. let the operator accept concrete choices;
5. send accepted choices through Core governance;
6. measure results later and only then build feedback learning.

This is why the first narrow proof is Content Metadata Delta: one post,
article-context and related-content evidence, excerpt/category/tag
recommendations, operator selection, Core proposal handoff, and no direct
WordPress write from Toolbox.

## Implemented Scope

### Toolbox

Implemented in `magick-ai-toolbox`.

- Related Site Knowledge can influence summary/category/tag ranking as
  evidence only.
- The editor content-support flow returns a `content_metadata_delta` artifact
  with issue, diagnosis, delta, authorization, outcome checks, and future
  learning candidates.
- Accepted excerpt, existing category, and existing tag selections are converted
  into a dry-run `content_metadata_apply_plan`.
- The apply plan route is:
  `/wp-json/npcink-toolbox/v1/flows/content-metadata-apply-plan`.
- The apply plan ability is:
  `npcink-toolbox/build-content-metadata-apply-plan`.
- The editor submits the apply plan to Adapter `/proposals/from-plan` using
  `npcink-toolbox/build-content-metadata-apply-plan`.
- Proposed new terms are preserved as manual-review vocabulary-gap notes only.
- Legacy metadata `proposal_targets` preview scaffolding was removed after the
  dedicated apply-plan path became the real handoff contract.

Relevant commits:

- `47be90f Use related content for metadata suggestions`
- `09a1a7e Add governed content metadata apply handoff`
- `7c50108 Remove legacy metadata proposal target preview`

### Core

Implemented in `magick-ai-core`.

- Core accepts `npcink-toolbox/build-content-metadata-apply-plan` as a bounded
  plan-to-proposal source.
- Core validates the plan fail-closed before proposal creation:
  - `artifact_type=content_metadata_apply_plan`;
  - `proposal_mode=batch` and `batch_approval=true`;
  - one target post;
  - one to three write actions;
  - all actions explicitly `dry_run=true` and `commit=false`;
  - `update-post` may update only `excerpt`;
  - `set-post-terms` may target only `category` or `post_tag`;
  - `term_ids` must be reviewed existing ids;
  - `create_missing=false`;
  - no named missing terms, title updates, content updates, SEO writes, or
    unsupported taxonomies.
- Core preserves `preview.content_metadata_apply` for review evidence.

Relevant commits:

- `802a158 Record toolbox metadata ranking handoff`
- `42b56e2 Accept content metadata apply plans`

## Verification Already Run

Toolbox:

```bash
composer test:all
composer smoke:metadata-delta
node --check assets/editor-content-support.js
git diff --check
```

Core:

```bash
composer test:all
composer smoke:wp
git diff --check
```

The metadata smoke verifies that the editor content-support REST flow remains
suggestion-only, that the apply-plan route produces a dry-run handoff, that Core
`/proposals/from-plan` creates one pending `plan_to_proposal_batch` review
proposal with `preview.content_metadata_apply`, that term creation is not
included, and that the sampled WordPress post is not mutated.

## Deferred Work

These are intentionally not implemented in the current slice:

- persistent feedback store;
- self-learning loop;
- automatic measurement of accepted metadata results;
- automatic creation of new taxonomy terms;
- direct excerpt/category/tag writes from Toolbox;
- Toolbox-owned approval, audit, or final WordPress write execution.

The feedback loop should wait for real usage data. Until then, adding a learning
store would produce artificial signal and extra state without proving that the
recommendations are useful.

## Current Status Note

The earlier local uncommitted `local-admin-consent/featured-image` branch has
since been landed as a separate proof. It is limited to setting one existing
WordPress image attachment as the current post featured image with present
administrator consent, classifier approval, Core audit, and rollback on
completion-audit failure.

Do not treat that local-admin-consent proof as part of Content Metadata Delta.
Metadata excerpt/category/tag writes still use the governed apply-plan handoff
and Core proposal path.

## Recommended Next Step

Treat Content Metadata Delta P0 as complete for the current phase. The next
useful step is not another metadata implementation layer; it is contract
alignment and real editor end-to-end QA so future agents do not confuse
suggestion-only metadata deltas, Core proposal handoffs, and the single
local-admin-consent featured-image proof.

## Prompt For Another AI

Use this prompt when handing the work to another AI:

```text
You are continuing Npcink Toolbox/Core work. Inspect the real repo state before
changing anything. Content Metadata Delta P0 is already implemented:

- Toolbox commit 09a1a7e builds the governed content metadata apply handoff.
- Toolbox commit 7c50108 removes legacy metadata proposal target preview
  scaffolding.
- Core commit 42b56e2 accepts content_metadata_apply_plan fail-closed.

Do not reintroduce direct Toolbox writes for excerpts, categories, tags, SEO, or
new term creation. Accepted metadata choices must go through
/flows/content-metadata-apply-plan and then Adapter/Core from-plan intake.

If you work on local-admin-consent/featured-image, treat it as a separate
boundary-sensitive proof that already exists. First read docs/decisions/
ADR-003-local-admin-consent-boundary.md, docs/boundary.md, and the Core
operation classification contract. Preserve Core audit, rollback, permissions,
exact preview, and one-object scope, and do not generalize that proof to
metadata, SEO, media import, generated images, or batch writes without a new
boundary decision.

Run composer test:all and the narrow smoke relevant to the changed behavior.
Stage only files changed for the current task.
```
