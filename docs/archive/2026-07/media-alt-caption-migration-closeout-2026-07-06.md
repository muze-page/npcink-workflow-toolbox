# Media ALT/Caption Migration Closeout - 2026-07-06

Status: current migration complete for this phase; observe before expanding.

## Purpose

This record summarizes the recent cross-repo Media ALT/Caption migration work
so future agents do not re-open the same boundary questions without new
evidence. It also records why the current phase stops at a review-only artifact
and local Toolbox preview instead of moving into media metadata writes.

The first local cleanup in this thread removed an old duplicate local plugin
copy that caused two `Npcink Workflow Toolbox` entries in WordPress admin. That
cleanup was environment hygiene, not a product boundary change. The English
plugin description seen there came from the plugin metadata and localization
state, not from a second active product surface.

## Current Owner Split

| Area | Current owner |
| --- | --- |
| Operator UI, fixed buttons, selection state, preview rendering | `npcink-workflow-toolbox` |
| Reusable review artifact builder | `npcink-abilities-toolkit` |
| Optional hosted visual evidence helper | `npcink-cloud-addon` or host runtime |
| Proposal, approval, preflight, audit truth | `npcink-governance-core` |
| Approved execution relay | `npcink-ai-client-adapter` |
| Final WordPress media metadata callback | Future WordPress ability callback |

The split is intentionally narrow. Toolbox is still the operator-facing product
surface. Toolkit owns only the reusable `media_alt_caption_review_set.v1`
builder. Cloud Addon may provide optional `image_context_evidence.v1` when a
hosted visual helper exists, but it must not become a WordPress write owner or
a second workflow/ability registry.

## What Was Migrated

- `npcink-abilities-toolkit` now owns the reusable
  `build-media-alt-caption-review-set` artifact builder.
- Toolbox delegates to that Toolkit builder when available and keeps its local
  builder as a compatibility fallback.
- Candidate quality classification is now explicit review triage:
  `candidate_quality.*`, flat compatibility aliases, context-confirmation
  fields, caption-only status, and `local_preview_candidate_count`.
- The old `ready_for_handoff_count` and automation wording that could imply
  write readiness were replaced or demoted to preview-only signals.
- The Toolbox follow-up `/flows/media-alt-caption-review-plan` is now a local
  preview route. Its `future_contract_preview` objects are not submittable and
  carry `not_submittable=true`, `submission_status=preview_only_not_submitted`,
  `proposal_created=false`, `execution_created=false`, and
  `direct_wordpress_write=false`.
- Toolbox UI copy moved away from "submit/update" language and toward review,
  confirmation, and preview language.

## What Was Not Migrated

- No media ALT, caption, description, featured-image, file, URL, SEO, post, or
  taxonomy writes moved into Toolbox.
- No Adapter/Core proposal submission is triggered by the Media ALT preview.
- No Core approval, approve-and-execute call, preflight, or audit store moved
  into Toolbox.
- No queue, scheduler, lease, run table, retry worker, or workflow runtime was
  introduced.
- No provider key, billing, quota, prompt/model routing, or request-log
  ownership moved into Toolbox.
- No local image recognition model or bundled vision dataset was added.

## Validation Evidence

Toolbox PR #63, `Surface media ALT caption quality triage`, merged into
`master` as `04a5058` after the Toolkit builder PR #83 merged as `e25e378`.
The relevant Toolbox branch commits were:

- `d133116` - surface Media ALT/Caption quality triage;
- `cf96c64` - keep Media ALT handoff preview-only.

The final real-media checks established the current boundary:

| Gate | Result |
| --- | --- |
| Real WordPress media trial | Passed; 10 scanned, 0 selected, 10 blocked as insufficient quality in the conservative sample. |
| Second explicit attachment sample | Passed; selected rows required human visual review and context confirmation where needed. |
| Browser operator smoke | Passed; rows rendered with context confirmation, caption-only separation, and no Core/Adapter/media-write calls during review. |
| Eval-lab adversarial review | Useful; it exposed old doc/code drift and helped force the path back to preview-only. |
| `composer test:all` in Toolbox and Toolkit | Passed during the migration closeout. |

Eval-lab evidence is adversarial review evidence only. It is not Core approval,
operator acceptance, or write authorization.

## Why Stop Here

Stopping here is intentional and worth it:

- the reusable deterministic part is now shared by Toolkit;
- Toolbox keeps the UI and operator review work where it belongs;
- weak metadata is filtered before it becomes misleading operator work;
- candidate quality signals are available for evaluation without becoming
  proposal or write inputs;
- the write path is not yet justified by usage evidence or cross-repo write
  contracts.

Continuing broad migration now would create risk without a matching product
gain. The most likely failure mode is accidentally turning a review set into a
second media write/control plane.

## Restart Conditions

Restart implementation only when real usage justifies one of these paths:

1. True ALT write path: design `npcink-abilities-toolkit/update-media-details`,
   Core proposal/preflight/audit handling, Adapter execution profile, and final
   WordPress ability callbacks.
2. Visual evidence path: Cloud Addon or a host helper returns bounded
   `image_context_evidence.v1`, and Toolbox consumes it as unconfirmed evidence
   that still requires human visual review.
3. Quality refinement: real samples show useful candidates are being blocked or
   low-quality suggestions are still reaching operators, then refine Toolkit
   scoring with eval-lab and real WordPress samples.

Do not restart by moving Toolbox UI state, provider runtime, Cloud indexing,
approval truth, audit truth, preflight truth, or final writes into Toolkit or
Cloud Addon.

## Next Recommended Phase

The next phase should be observation and sample quality, not more migration:

1. Run more real Media ALT/Caption samples and record accepted, edited,
   rejected, context-confirmed, and blocked rows.
2. Use `npcink-eval-lab` only as adversarial quality review around candidate
   wording and boundary drift.
3. Keep docs and static tests aligned with preview-only behavior.
4. Defer the governed write path until operators repeatedly accept enough ALT
   candidates to justify the Abilities/Core/Adapter work.

