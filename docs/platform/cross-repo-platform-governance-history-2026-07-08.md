# Cross-Repo Platform Governance History - 2026-07-08

Status: historical closeout and future-session guidance.

This record summarizes the July 8, 2026 platform-governance cleanup across the
five local Npcink WordPress projects:

- `npcink-workflow-toolbox`
- `npcink-governance-core`
- `npcink-abilities-toolkit`
- `npcink-ai-client-adapter`
- `npcink-cloud-addon`

It is a history and orientation document. The active coordination entry remains
[Npcink Platform Governance Index](README.md). Low-level contracts remain in
their owner repositories.

## Why This Was Done

The projects had useful norms spread across plugin docs, Core docs, PR notes,
and session guidance. The risk was not missing documentation; the risk was
putting too much cross-project authority into the wrong repository.

The main decision was:

- keep `npcink-governance-core` as governance truth only;
- use `npcink-workflow-toolbox` as the platform coordination index because it
  is the operator-facing product surface where repeatable workflows, fixed
  buttons, handoff artifacts, and cross-repo quality gates converge;
- keep detailed runtime, ability, adapter, connector, and governance contracts
  with their actual owners.

This avoids turning Core into a suite control plane, product-surface owner,
workflow runtime, second registry, or implementation checklist.

## Repository Roles

| Repository | Stable role after cleanup |
| --- | --- |
| `npcink-governance-core` | Governance truth: proposal records, approval policy, commit preflight, operation classification, app-key governance, and audit evidence. |
| `npcink-abilities-toolkit` | Reusable WordPress ability contracts, schemas, dry-run previews, and host-governed callbacks. |
| `npcink-ai-client-adapter` | Thin channel adapter and OpenClaw recipe projection into existing governance contracts. |
| `npcink-workflow-toolbox` | Operator product surface, fixed buttons, suggestion artifacts, Core-ready plans, platform coordination index, and cross-repo quality gates. |
| `npcink-cloud-addon` | Bounded signed transport, Cloud Base URL/API key settings, entitlement reads, and runtime detail handoff. |

The practical rule is one owner per durable truth. Toolbox may point to an
owner, summarize a boundary, and provide a decision index, but it must not fork
the owner's low-level contract.

## Development Thinking Preserved

### Centralize Navigation, Not Authority

Move scattered cross-repo norms into Toolbox only when the item is a
coordination index, owner map, intake rule, release gate, quality-matrix rule,
or stop rule. Do not migrate detailed governance lifecycle, ability schema,
adapter recipe, signed transport, provider runtime, queue, entitlement, billing,
or indexing rules away from the repository that owns them.

### Default To Suggestion Artifacts

New product ideas should start as `suggestion_only` artifacts: candidates,
review sets, handoff notes, planning envelopes, or Core-ready plans. Escalate to
`core_proposal_required` when a durable WordPress write, batch operation,
external channel, insufficient preview, or high-impact operation appears.

### Keep Core Narrow

Core should stay boring and strict. It should answer whether a proposed
operation is allowed, how it is approved, what preflight evidence is required,
and what audit trail proves it happened. It should not become the home for
product UX, feature placement, provider routing, workflow runtime, queue
ownership, or cross-repo implementation planning.

### Keep Toolbox Product-Facing

Toolbox is the best home for platform coordination because its product surface
is where operators see fixed workflows and where future sessions need a
practical entry point. That does not make Toolbox a write executor. Toolbox
returns suggestions, candidates, review sets, and Core-ready plans.

### Treat PR Management As A Gate

The PR closeout exposed a useful operating pattern:

1. Update PR title and body so the GitHub record matches the actual branch.
2. Require `Scope`, `Boundary`, `Verification`, and `Risk` sections.
3. Confirm CI/checks before moving draft PRs to ready.
4. Merge the platform index owner first, then merge repository pointers that
   reference it.
5. Delete remote PR branches after merge.
6. Report local worktree leftovers separately from merged PR state.

## PR Closeout Record

All five PRs were made ready, passed their checks, and were squash-merged on
July 8, 2026.

| Repository | PR | Result |
| --- | --- | --- |
| `npcink-workflow-toolbox` | [#73](https://github.com/muze-page/npcink-workflow-toolbox/pull/73) | Merged platform governance index and reference-plugin intake documentation. |
| `npcink-governance-core` | [#57](https://github.com/muze-page/npcink-governance-core/pull/57) | Merged Core docs authority classification and Toolbox platform-index pointers. |
| `npcink-abilities-toolkit` | [#86](https://github.com/muze-page/npcink-abilities-toolkit/pull/86) | Merged Toolkit platform-index pointers while preserving ability-contract ownership. |
| `npcink-ai-client-adapter` | [#29](https://github.com/muze-page/npcink-ai-client-adapter/pull/29) | Merged Adapter platform-index pointers while preserving thin-channel ownership. |
| `npcink-cloud-addon` | [#30](https://github.com/muze-page/npcink-cloud-addon/pull/30) | Merged Cloud Addon platform-index pointers while preserving signed-transport ownership. |

The code checks were green before merge. The only recurring CI issue was PR
metadata: draft PR bodies initially missed the required AI change-envelope
section headings. Updating the PR body fixed the contract checks.

## Operational Notes

- Use command-line `git` for ordinary local Git work in this repo family.
- Use `gh` only for GitHub-specific PR metadata, checks, PR editing, PR merge,
  or API fallback work that plain `git` cannot perform.
- GitHub HTTPS was unreliable during the closeout. Do not treat GitHub Git Data
  API publication as the normal path; it was an emergency fallback for the
  Adapter PR after local HTTPS push failed.
- Do not call a milestone closed until the final answer names remaining dirty
  files, ahead/behind state, and any local branch tracking mismatch.
- If a PR branch has been squash-merged and deleted remotely, a stale local
  branch may still appear until `git fetch --prune origin` succeeds.

## Next-Stage Guidance

Use this sequence before moving another scattered norm:

1. Classify the norm: governance, ability contract, adapter channel, product
   surface, connector transport, Cloud runtime/detail, or release quality.
2. Identify the one truth owner.
3. If the norm is cross-repo navigation, add or update the Toolbox platform
   index.
4. If the norm is low-level behavior, keep it in the owner repository and add a
   Toolbox pointer only.
5. Add static coverage when the wording protects a boundary or future session
   behavior.
6. Run the narrowest useful gate, then `composer test:all` for public docs or
   contract changes.

Stop and write a boundary note before implementing if a proposal would create a
second ability registry, second workflow registry, second approval store,
workflow runtime, queue, scheduler truth, provider secret store, or
direct WordPress write executor outside its accepted owner.
