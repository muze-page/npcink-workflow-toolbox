# Media ALT Governed Workflow Closeout - 2026-07-11

Status: complete on the four local WordPress repositories; feature expansion
stopped.

This record summarizes the investigation, product decisions, cross-repository
implementation, verification, and lessons from the image-processing and media
ALT stage. It is the preferred starting point before reopening this area.

## Executive Result

The stage delivered one small but complete proof:

> Toolbox and OpenClaw use the same Toolkit missing-ALT plan, the same Core
> governance contract, and the same Adapter execution profile. Toolbox stops
> after proposal submission; final WordPress mutation remains inside Toolkit
> after Core approval and Adapter live-value verification.

The implemented batch ALT scope is deliberately narrow:

- image attachments only;
- current attachment ALT must be empty;
- one reviewed ALT value per attachment;
- explicit operator visual confirmation;
- expected-old-value drift protection;
- one Core proposal per image;
- no caption, title, description, source, or file update;
- no Toolbox approval, execution, polling, retry runtime, or direct media write.

The work is merged into each repository's `master`, the post-merge CI runs are
green, and the four local worktrees are clean and synchronized.

## How The Problem Evolved

The work started from two operator-facing symptoms under Image Handling:

1. **Image Optimization Review** and **Batch Image ALT Review** did not provide
   a clear, reliable end-to-end result.
2. The admin URL contained page, tab, tool, one-time preview, and nonce query
   parameters, making temporary state look like a stable product route.

The stable image-task route is the page/tab/tool combination. One-time preview
and nonce parameters are request state and must not be treated as durable links
or shared product identity.

The initial ALT implementation was a local, non-submittable preview. At the
other extreme, older committed media-optimization behavior and some editor
flows appeared to call Adapter `approve-and-execute` directly. That created an
overly broad question: should every Toolbox action stop at proposal creation?

The answer was **no**. Two different operator contracts were separated:

- **Batch admin actions** stop after selected Core proposal submission.
- **Author-reviewed editor actions** for SEO, external-image adoption, and
  article-audio adoption queue the approved proposal id and execute only after
  the next successful native Publish or Update.

Deleting the editor behavior would have removed a valid author-review contract.
Keeping the batch execution behavior would have weakened the batch boundary.
The final design preserves both rules explicitly.

## ALT Content Strategy

ALT exists primarily to describe the image's role in the article for users and
search engines. Therefore the evidence order is:

1. nearest article heading;
2. adjacent paragraph or list text;
3. caption and image role in the current article;
4. current block/attachment metadata;
5. AI vision only when the local article context is insufficient.

For the editor flow, AI vision is a silent, non-blocking fallback. Existing ALT
is never overwritten, and missing `core/image` ALT is applied only to the
in-memory Gutenberg draft as Native Commit state, without a Core proposal or
audit; native WordPress Save or Update persists it.

For the backend media-library flow, visual confirmation is explicit because the
result targets attachment-global metadata and may affect every occurrence of
that image. Context and AI evidence remain suggestions; the operator confirms
the final wording before Core proposal creation.

## Why A Cross-Repository Contract Was Necessary

Toolkit already had the broad `update-media-details` write ability. Reusing it
without a narrower contract would have allowed unrelated media fields and had no
ALT-specific old-value guard. Creating a second media writer in Toolbox would
have duplicated permissions, write behavior, and rollback truth.

The accepted solution was to add a read-only ALT apply-plan while reusing the
existing final write callback:

```text
Toolbox or OpenClaw
  -> Adapter run-read-ability
  -> Toolkit build-media-alt-apply-plan
  -> Adapter proposals/from-plan
  -> Core proposal review and approval
  -> Core commit preflight
  -> Adapter immediate Toolkit dry-run
  -> Toolkit update-media-details commit
```

This gives both entry points the same Ability ID, plan contract, proposal
evidence, drift behavior, and final write implementation.

## Repository Responsibilities And Landed Changes

| Repository | Responsibility in this stage | Landed evidence |
| --- | --- | --- |
| `npcink-abilities-toolkit` | Define the reusable read/write contracts and own live WordPress validation/mutation. | `build-media-alt-apply-plan`; guarded `update-media-details`; image, missing-only, visual-confirmation, idempotency, and stale-value checks. PR #87, merge `0e738bc`. |
| `npcink-governance-core` | Validate plans, preserve approval/audit truth, and issue commit-preflight evidence. | Strict `media_alt_apply_plan.v1` validation, default manual approval, optional guarded policy, `media_alt_guard`, and bounded lifecycle evidence. PR #59, merge `afecf95`. |
| `npcink-ai-client-adapter` | Expose the shared plan to AI clients and execute only after Core preflight. | Narrow guarded fields, immediate Toolkit dry-run before commit, stable drift rejection, execution evidence, and duplicate-execution protection. PR #31, merge `f72730a`. |
| `npcink-workflow-toolbox` | Provide the operator review UI and stop after proposal creation. | Missing-only rows, explicit visual confirmation, one plan/proposal per image, Core receipts, successful-row lock, no approve/execute/poll/media REST write. PR #84, merge `cf15da8`. |

Cloud and Cloud Addon did not receive a new control plane. Existing optional
visual evidence remains `suggestion_only`; Cloud does not become another ability
registry, workflow registry, approval store, or WordPress write surface.

## Implementation Sequence

### Phase 0 - Close The Existing Toolbox Stage

- removed batch media optimization `approve-and-execute` behavior from the
  product workbench and made it stop after proposal submission;
- preserved the reviewed editor SEO/image/audio publish-time contract;
- reconciled product-positioning and media-specific boundary documents;
- fixed the media-conversion browser smoke login fixture, which had mixed the
  Magick AI database/socket with a different local site URL and WordPress path;
- committed and pushed the previously local-only boundary correction so the
  remote branch no longer contradicted the working tree.

### Phase 1 - Toolkit Contract

- added `media_alt_apply_plan.v1` as a single-action, read-only plan;
- required `attachment_id`, final `alt`, `expected_current_alt=""`,
  `operator_visual_review_confirmed=true`, review-set version, evidence refs,
  and a stable idempotency key;
- extended `update-media-details` additively with the two ALT guard fields;
- when guard fields are present, rejected every non-ALT media detail field.

### Phase 2 - Core Governance

- allowlisted the new plan ability and rejected widened or inconsistent plans;
- stored old value, proposed value, visual confirmation, source item, evidence
  refs, and idempotency evidence;
- kept manual approval as the default;
- made preflight declare that Adapter and Toolkit own the required live-value
  check instead of making Core read attachment metadata.

### Phase 3 - Adapter Execution

- exposed the plan through the existing read-ability and from-plan routes;
- kept generic media-detail updates intact for other governed workflows;
- activated the strict ALT path only when Core returns `media_alt_guard`;
- ran Toolkit dry-run immediately before commit and returned 409 on drift;
- recorded successful or failed live-preflight evidence and prevented replay of
  a completed proposal.

### Phase 4 - Toolbox Product Surface

- defaulted the scan to missing ALT and excluded weak/non-empty ALT from apply;
- required both row selection and explicit image review confirmation;
- created a separate Toolkit plan and Core proposal for each confirmed image;
- rendered ephemeral receipts linking to Core as canonical truth;
- locked successful rows until re-scan to avoid accidental duplicate proposals;
- retained the old local handoff-preview route only as diagnostic compatibility,
  not as the product proposal contract.

## Verification Evidence

The stage used proportional gates at each layer:

- Toolkit: static tests, PHPStan, boundary checks, full/light real WordPress
  smoke, package checks, and bootstrap performance budgets;
- Core: static tests, fail-closed fault injection, real WordPress proposal,
  audit, approval, and preflight smoke;
- Adapter: static contracts and real WordPress success, drift, generic-media,
  and duplicate-execution smoke;
- Toolbox: `composer test:all` with 3132 static contracts, real attachment
  no-mutation trial, media conversion browser smoke, contextual editor ALT
  browser smoke, and batch ALT browser proposal-handoff smoke.

The key acceptance results were:

1. Toolbox and OpenClaw/Adapter use the same Toolkit plan and final write
   ability.
2. Planning and dry-run do not change attachment metadata.
3. A live ALT change after review blocks final commit.
4. Missing visual confirmation cannot enter the governed plan.
5. Core approval plus Adapter preflight can complete one missing ALT write.
6. Audit evidence preserves old value, new value, actor/context, contract, and
   idempotency information.
7. Repeated execution does not create a second change.
8. Toolbox sends no `approve-and-execute`, execution polling, local-consent, or
   `/wp/v2/media` request for the batch flow.

All four PRs were merged in dependency order: Toolkit, Core, Adapter, Toolbox.
Post-merge Actions passed on all four `master` branches. A scoped strict quality
matrix then confirmed all four repositories were clean, synchronized, and
passing their configured gates.

Toolkit `0.5.3` Release Candidate workflow run `29137876218` also passed and
uploaded `npcink-abilities-toolkit-0.5.3`. The formal tag/release was not created
because the complete seven-repository release-cleanliness gate still detected
17 unrelated uncommitted files in `npcink-ai-cloud`. That external dirty state
was left untouched rather than bypassing the documented release rule.

## Problems And Lessons

### 1. Do Not Confuse Working Tree State With Shipped State

The local Toolbox implementation had already removed a batch execution call,
but committed `HEAD` and the remote branch had not. A boundary is not delivered
until it is tested, committed, pushed, merged, and verified on the target
branch.

### 2. Do Not Apply One Boundary Rule To Every Entry Point

“Toolbox always stops at proposals” was too broad. Batch operator workflows and
author-reviewed editor/publish workflows have different user contracts. State
the actor, review moment, persistence event, and execution moment before
removing behavior.

### 3. Reuse A Broad Ability Only Through A Narrow Plan

The broad write callback was useful infrastructure, but not a safe product
contract. The plan constrained fields and evidence without duplicating the
final write implementation.

### 4. Keep Live Truth At The Layer That Owns It

Core records approved evidence; it does not need attachment mutation logic.
Toolkit reads the live attachment immediately before write, while Adapter binds
that check to the consumed Core preflight.

### 5. Browser Fixtures Must Be Deterministic

Fixed real attachment IDs drifted as ALT values changed, causing the browser
smoke to stop rendering eligible rows. The UI test now uses a deterministic
review-set fixture while separate real-attachment tests prove metadata behavior.

### 6. Small Features Need A Stop Rule

This ALT task consumed more design effort than its surface area suggested. The
investment became worthwhile only because it proved a reusable four-layer
pattern. Continuing into existing-ALT overwrite, caption synchronization,
decorative-image automation, sensitive-image description, or unattended
library processing would now have diminishing value and materially higher risk.

## Durable Rules For Future Work

- Start from an operator problem, not from another button.
- Use article context before vision for occurrence-level ALT.
- Use explicit visual confirmation for attachment-global ALT changes.
- Keep candidate quality scores out of approval policy.
- Keep Cloud output suggestion-only and avoid Cloud/local duplicate registries.
- Reuse Toolkit abilities and Core governance rather than creating Toolbox
  writers.
- Make old-value drift and idempotency part of the contract, not UI convention.
- Keep batch Toolbox actions at proposal submission unless a separate accepted
  author/save contract explicitly says otherwise.
- Verify local behavior, committed state, remote PR state, merge state, and
  post-merge CI separately.
- Stop expanding a workflow after its narrow acceptance proof passes.

## What Not To Build Next

Do not reopen this stage merely to add:

- overwrite-existing-ALT mode;
- caption/title/description coupling;
- automatic decorative-image decisions;
- sensitive-person or identity descriptions;
- an unattended full-media-library runner;
- a Toolbox workflow registry, queue, scheduler, or approval store;
- a Cloud workflow control panel for this local write.

If future evidence justifies one of these, it requires a new contract and a new
boundary decision rather than an extension hidden inside this flow.

## Recommended Next Product Work

The next higher-value review sets remain:

1. taxonomy/tag review using existing terms and governed new-term policy;
2. internal-link review using bounded candidate evidence and human placement;
3. only then, another mature OpenClaw workflow projected as a fixed Toolbox
   button.

The media ALT stage should remain closed while those higher-value workflows are
validated.
