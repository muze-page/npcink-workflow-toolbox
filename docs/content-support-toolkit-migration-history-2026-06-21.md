# Content Support Toolkit Migration History - 2026-06-21

Status: historical summary and handoff note.

This document summarizes the Content Support migration discussion and
implementation that moved reusable WordPress ability-shaped logic from Toolbox
surfaces into `npcink-abilities-toolkit`. It records why the migration happened,
what moved, what intentionally stayed out, and why the migration should stop
here.

## Starting Question

The starting product question was whether the Npcink Content Support buttons
were really based on `npcink-abilities-toolkit`, and whether OpenClaw could use
the same capabilities through Adapter. The desired direction was:

- keep basic reusable capabilities in Toolkit so third-party plugins can
  benefit;
- let Toolbox fixed buttons remain the WordPress operator/editor surface;
- let OpenClaw use the same artifacts through Adapter instead of creating a
  parallel path;
- avoid turning Toolkit into a hosted AI, provider, vector, UI, or governance
  runtime.

## Boundary That Guided The Work

The migration used the existing cross-repo split:

| Owner | Role During This Migration |
| --- | --- |
| `npcink-abilities-toolkit` | Reusable WordPress ability artifacts, schemas, dry-run plans, and approved callbacks. |
| `npcink-toolbox` | Editor/admin UI, fixed buttons, candidate display, operator review, and Core handoff UX. |
| `npcink-ai-cloud` | Hosted AI runtime, provider execution, image-source lookup, Site Knowledge, vector, and rerank runtime. |
| `npcink-governance-core` | Proposal records, approval, preflight, audit, and governance truth. |
| `npcink-ai-client-adapter` / OpenClaw | Natural-language projection, proposal transport, and approved execution channel. |

The rule that emerged: move to Toolkit only when the logic is a repeated,
host-reusable WordPress artifact, plan, dry-run, or callback contract. Do not
move provider/runtime, Cloud indexing, editor UI state, proposal approval,
audit, preflight, OpenClaw projection, or final WordPress writes into Toolkit.

## Migration Timeline

| Area | Toolkit Commit | Toolbox Commit | Result |
| --- | --- | --- | --- |
| Content metadata apply plan | Existing Toolkit plan ability | `ba8027b` | Toolbox delegates reviewed excerpt/category/tag apply plans to `npcink-abilities-toolkit/build-content-metadata-apply-plan`. |
| Taxonomy/tag candidates | `7b156ed` | `a06d452` | Toolkit owns existing term candidate ranking through `npcink-abilities-toolkit/suggest-post-taxonomy-terms`; Toolbox supplies editor context and review UI. |
| Comment reply suggestions | `a4a499a` | `4d591b3` | Toolkit owns review-only comment reply suggestion artifacts through `npcink-abilities-toolkit/build-comment-mention-reply-suggest`. |
| Internal-link candidates | `c550109` | `ec4177d` | Toolkit owns `internal_link_candidates.v1` through `npcink-abilities-toolkit/resolve-internal-link-targets`; Toolbox supplies bounded context and optional Site Knowledge evidence. |
| Image candidate review | `4e192b6` | `4e9e900` | Toolkit owns `image_candidate_review.v1` and recommendation projections through `npcink-abilities-toolkit/build-image-candidate-review-artifact`. |
| Image candidate adoption plan | Existing Toolkit plan ability | Covered by earlier Toolbox migration | Toolkit owns `npcink-abilities-toolkit/build-image-candidate-adoption-plan`; Core/Adapter govern final media actions. |
| Migration audit | N/A | `b204af3` | Toolbox records the remaining migration audit in `docs/cross-repo-boundary-matrix.md`. |
| No-insert correction | N/A | `4d16755` | Toolbox success responses for internal links preserve `operator_review_only_no_insert`, `direct_wordpress_write=false`, and `no_link_insertion_in_toolbox`. |
| Final closeout | Toolkit release `98974bd` (`0.5.2`) | `89498c6` | Toolbox records that Toolkit migration stops here and pushes the closeout to `origin/master`. |

## What Moved To Toolkit

These are now treated as Toolkit-backed reusable capabilities:

- `npcink-abilities-toolkit/build-content-metadata-apply-plan`
- `npcink-abilities-toolkit/suggest-post-taxonomy-terms`
- `npcink-abilities-toolkit/build-comment-mention-reply-suggest`
- `npcink-abilities-toolkit/resolve-internal-link-targets`
- `npcink-abilities-toolkit/build-image-candidate-review-artifact`
- `npcink-abilities-toolkit/build-image-candidate-adoption-plan`

Toolbox still normalizes UI-facing payloads, shows candidates, submits Core
handoffs, and records operator-facing source labels. It should not reimplement
the migrated candidate assembly or ranking logic as a hidden fallback.

## What Did Not Move

The following stayed out of Toolkit by design:

- hosted AI text outputs: title suggestions, summary suggestions, outline,
  polish, article checkup, and writing support;
- image-source search and AI image generation provider runtime;
- Site Knowledge search, vector indexing, rerank, collection lifecycle, and
  content sync;
- progressive local recommendation aggregation and editor timing/cache state;
- OpenClaw natural-language projection and Adapter recipe transport;
- Core proposal creation, approval, preflight, audit, and final execution;
- provider billing, quota, request logs, key rotation, and model routing.

Moving these into Toolkit would make Toolkit too heavy and would blur the
WordPress ability package boundary.

## Important Correction Found During Review

After the internal-link migration, `composer smoke:editor-review-artifacts`
found a real boundary gap. The Toolkit success path returned candidates
correctly, but the Toolbox response did not always project the no-insert
handoff fields that the error fallback already carried.

The fix in `4d16755` made the successful internal-link response explicitly
preserve:

- `final_write_path=operator_review_only_no_insert`
- `direct_wordpress_write=false`
- `review_policy.automatic_anchor_insert=false`
- `review_policy.post_content_patch_handoff=false`
- `handoff.blocked_actions[]=no_link_insertion_in_toolbox`
- `handoff.blocked_actions[]=no_patch_post_content_handoff_yet`
- `handoff.blocked_actions[]=no_automatic_anchor_insertion`

This kept candidate generation in Toolkit while preserving Toolbox's
operator-review-only UI boundary.

## Verification History

The closeout ran these gates successfully after the final correction:

- `composer smoke:editor-review-artifacts`
- `composer test:progressive-recommendations`
- `composer test:editor-progressive-js`
- `composer test:all`

The final Toolbox push placed `origin/master` at `89498c6`. The Toolkit release
line has `origin/master` at `98974bd` with tag `0.5.2`.

## Final Decision

Stop Toolkit migration here.

Future work should be called validation or release hardening, not migration,
unless a new repeated, host-reusable WordPress artifact or callback contract is
identified. The next useful work is:

1. run real editor operator trials on human-written posts;
2. verify OpenClaw/Adapter reuse the same Toolkit artifacts and Core proposal
   paths;
3. record any operator trial evidence and UX issues;
4. avoid adding new Toolkit abilities for provider/runtime/UI aggregation work.

This is the intended steady state: Toolkit is reusable and light, Toolbox is the
operator surface, Cloud is the runtime, Core/Adapter govern writes, and OpenClaw
uses the same artifacts instead of inventing a second path.
