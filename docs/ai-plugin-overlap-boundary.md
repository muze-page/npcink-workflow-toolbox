# AI Plugin Overlap Boundary

Status: active working rule for the current Toolbox stage.

## Purpose

The WordPress AI plugin Settings page at `ai-wp-admin` exposes generic AI
experiments and provider-facing AI administration. Toolbox should not duplicate
those human-visible entry points. Toolbox should keep reusable contracts and
review-only handoff abilities when they are useful to Npcink workflows.

## Generic AI Plugin Territory

Treat these as owned by the generic AI plugin or connector layer when a site
has that product installed:

- provider/model connection setup;
- AI Request Logging;
- Connector Approval;
- generic Abilities Explorer;
- Title Generation;
- Excerpt Generation and Summarization;
- Content Classification;
- Content Resizing;
- Alt Text Generation;
- Meta Description;
- Editorial Notes and Editorial Updates;
- Comment Moderation.

Toolbox must not copy these as parallel default buttons, a second provider
settings page, a second request-log product, or a second connector approval
surface.

## Editor Confirmation Rule

When a generic AI plugin shows title, excerpt, summary, category, tag, ALT, meta
description, or editing suggestions inside the WordPress editor, the author can
review the output in place and use the normal editor save or publish action. In
that path, the author's visible editor action is the human review step.

Do not add a Core proposal hop for this path when all of these are true:

- the source is the WordPress admin editor;
- the author or administrator is present and intentionally clicks to insert,
  save, or publish;
- the AI output is visible before the click;
- WordPress core or the generic AI plugin owns the editor apply/publish action;
- the action is not a hidden batch, scheduled run, external Agent request, Cloud
  callback, or background automation.

Toolbox may record lightweight product activity when that is useful, but it
should not block the author's native editor flow with Npcink Core approval.
Publishing from the editor remains the author's editorial decision, not a Core
proposal workflow.

Core or strong local confirmation is still appropriate when the request is
external, automated, batch-oriented, insufficiently previewed, or attempts to
write high-impact state outside the author's immediate editor action.

## Classification Handoff Rule

Toolbox follows Core's
`docs/operation-classification-contract.md` AI write classification matrix:

- generic AI plugin editor output accepted through normal editor insert, save,
  or publish flow stays in the author's editor workflow and must not create a
  Core proposal;
- Npcink suggestions that do not write WordPress state remain
  `suggestion_only`;
- one visible low-risk admin-present apply action may use
  `local_admin_consent` with activity or Core local-consent audit evidence;
- one visible high-impact action uses `strong_local_confirmation` or a Core
  proposal, depending on reversibility and preview completeness;
- external Agent, Adapter, Cloud callback, scheduled, batch, destructive,
  taxonomy-creation, media-import, settings, permission, or insufficiently
  previewed writes remain `core_proposal_required`.

If a future Toolbox feature mostly needs title, excerpt, summary, category,
tag, ALT, meta description, or editing generation, start by deferring to the
generic AI plugin. Add Toolbox UI only when the task is a Npcink-owned workflow
or a governance handoff that the generic AI plugin does not already own.

## Toolbox Territory

Toolbox keeps the operator-facing Npcink workflow surface:

- Site Profile and suggestion-only SEO/AEO/GEO context;
- Site Check;
- Site Knowledge setup, search, and Cloud bridge health;
- Cloud web search and evidence checks;
- image-source candidates and explicit AI-image candidate checks;
- Publish Preflight;
- Internal Link Candidates;
- Image Candidates and governed image adoption planning;
- Article audio candidates and governed audio adoption planning;
- Batch Image Optimization Review;
- Batch ALT Review Handoff;
- Core/Adapter handoff receipts and failure feedback.

These surfaces are review-only unless a separate Core/Adapter/Abilities path
performs the approved WordPress write. Toolbox does not own final writes,
approval truth, audit truth, provider billing, request logs, connector approval,
or workflow/runtime queues.

## Entry-Point Rule

If a capability mostly looks like a generic AI generation experiment, remove or
demote the default Toolbox entry point while preserving compatible REST,
Abilities, and rendering contracts when existing workflows rely on them.

Examples:

- title, summary, category, tag, outline, article-checkup, selected-text polish,
  current-article ALT, discoverability, and comment-reply support may remain as
  route-only or result-rendering capabilities;
- default editor buttons should prefer Npcink workflow actions such as Publish
  Preflight, Internal Link Candidates, Image Candidates, and Article Audio
  Candidates;
- batch media text work should be named as a review handoff, not as direct ALT
  completion.

## Lessons To Adopt

Toolbox should learn the useful engineering pattern, not the product ownership:

- keep feature metadata explicit: label, description, owner, status, and write
  posture;
- expose bounded workflow parameters with clear defaults instead of free-form
  provider controls;
- show concise ability health summaries for Npcink-owned workflows;
- preserve provider/model/source identity in results without becoming the
  provider control plane;
- keep settings and result copy honest about suggestion-only versus governed
  handoff behavior.

Do not adopt the generic provider/model picker, AI request log, connector
approval UI, generic Abilities Explorer, or direct-write AI feature ownership
inside Toolbox.

## Local Implementation Pattern

Toolbox implements the adopted pattern with `Ability_Surface_Metadata`, a local
read-only projection rather than a registry. Each entry records surface,
`default_visible`, `write_posture`, `runtime_owner`, `handoff_path`, and
`overlap_policy`. Npcink-owned workflow entries stay visible by default; generic
AI-plugin overlap entries stay as route-only compatibility when existing
workflows still rely on their REST or rendering contracts.

The Overview **System status / Workflow readiness** disclosure summarizes that
projection for operators. It can show site profile readiness, Cloud runtime availability,
default Npcink workflow coverage, route-only compatibility, and Core handoff
boundary. It is not a generic Abilities Explorer, provider picker, request log,
or connector approval surface, and it must not create proposals, queues,
provider calls, or WordPress writes.

## 2026-06-29 Closeout

This rule was implemented and merged through PR #33. The closeout record is
[AI Plugin Overlap Closeout - 2026-06-29](archive/2026-06/ai-plugin-overlap-closeout-2026-06-29.md).

The practical result is:

- duplicated generic AI entries are demoted from default visible Toolbox UI;
- compatible REST and rendering paths remain where current workflows still rely
  on them;
- Npcink-owned workflows remain visible as default entries;
- the Overview page shows read-only capability health instead of a generic AI
  administration surface;
- low-frequency setup, diagnostics, review, and handoff details stay behind
  task-owned folded sections such as Site Check scheduled-review controls and
  Overview System status instead of being duplicated on Overview;
- merge commit `f7fd8c798ea9329b1883ad3101bc4e2c0b8c7055` passed the default
  `composer test:all` gate after merge.
