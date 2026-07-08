# Reference Plugin Evaluation Checklist

Status: active intake checklist for reference-plugin learning.

Use this checklist when a mature WordPress plugin, AI plugin, connector, SEO
tool, workflow tool, media tool, or hosted runtime suggests a capability that
Npcink might borrow. The goal is to identify the useful pattern without
importing the other plugin's ownership model.

This checklist is a documentation and decision aid. It is not a runtime,
registry, queue, approval store, provider console, or WordPress write path.

## Evaluation Entry

Use this checklist before writing code when any of these are true:

- an external plugin has a UI pattern, status pattern, workflow, connector, AI
  feature, media feature, SEO feature, or governance pattern that looks useful;
- a proposed Toolbox button resembles a generic AI, SEO, media, connector, or
  workflow plugin capability;
- a feature could become documentation only, a static contract, a
  suggestion-only Toolbox surface, or a Core-governed handoff;
- a proposal could cross repository ownership by adding runtime, registry,
  approval, provider, storage, or WordPress write behavior;
- the next step needs a gate decision before the work is kept, deferred, or
  rejected.

Do not use the checklist to justify broad product expansion. If the useful
lesson cannot be expressed without adding forbidden ownership, stop with a
boundary note.

## Capability Breakdown

Record the observed capability in small parts before deciding ownership:

| Question | What to capture |
| --- | --- |
| Input | What explicit user, site, post, media, provider, or runtime data enters the feature? Is it bounded and non-secret? |
| Output | What artifact comes out: label, status, source list, candidate, plan, proposal packet, runtime detail, audit row, or final write? |
| User action | What does the operator actually click, review, copy, approve, open, or hand off? |
| Write action | Does the feature write WordPress content, media, terms, SEO metadata, settings, schedules, logs, queues, approvals, or provider records? |
| Runtime dependency | Does it require hosted execution, background work, retries, leases, schedulers, workers, model routing, or long-running jobs? |
| Data storage | Does it need an option, custom table, log, cache, queue, retained artifact, vector collection, approval record, or provider usage record? |
| Permission model | Which local capability, app key, signed transport, Core policy, Adapter profile, or Cloud entitlement gates the action? |

If any answer is unknown, keep the result as documentation only until the
missing boundary fact is checked.

## Repository Ownership

Use the existing role split as the first ownership test:

| Repository | Owns | Borrow only when the feature maps to |
| --- | --- | --- |
| `npcink-abilities-toolkit` | Reusable WordPress ability contracts. | Stable ability ids, JSON schemas, permission callbacks, dry-run previews, reusable read/write callbacks, and metadata that other surfaces can call. |
| `npcink-governance-core` | Proposal, approval, preflight, and audit truth. | Proposal intake, approval state, rejection state, policy checks, audit evidence, and governed write authorization. |
| `npcink-ai-client-adapter` | Thin OpenClaw/channel execution profile layer. | Signed channel execution, Core proposal/status proxying, payload discipline, operator feedback, and allowlisted post-Core execution profiles. |
| `npcink-workflow-toolbox` | Operator-facing suggestion and handoff surface. | Fixed buttons, reviewable candidates, readiness rows, blocked states, copy/open/review actions, and Core-ready handoff plans. |
| `npcink-cloud-addon` | Signed Cloud connector. | Cloud URL/API key settings, HMAC signing, entitlement reads, narrow transport tests, and read-only Cloud status/detail projection. |
| `npcink-ai-cloud` | Hosted runtime/detail. | Provider execution, runtime detail, usage/entitlement evidence, health diagnostics, artifacts, Site Knowledge runtime/detail, and suggestion-only hosted results. |
| `wp-magick-toolbox` | Current GitHub-managed legacy/current toolbox reference. | Historical or compatibility reference for existing toolbox behavior, labels, UX lessons, and migration evidence only. |

If a capability needs two roles, split the artifact. For example, Toolbox may
own the review screen and Core-ready plan, while Core owns approval truth and
Toolkit owns the reusable write callback.

## Boundary Red Lines

Reject or defer the feature inside Toolbox if it requires any of these:

- a second ability registry;
- a second workflow registry;
- a second approval store;
- a local runtime queue, retry worker, lease store, or scheduler truth;
- a new provider billing, quota, request-log, key-rotation, prompt-router, or
  model-routing owner in Toolbox or Cloud Addon;
- bypassing the Core-governed approval and WordPress write path;
- direct publish, media import, media metadata write, SEO write, taxonomy
  write, post-content write, or featured-image write outside an accepted
  documented exception;
- treating image-source search as AI image generation;
- treating query embedding or vector query as local content indexing or full
  RAG lifecycle ownership;
- treating Cloud or Cloud Addon as a second WordPress control plane.

When a red line appears, write the boundary conflict clearly and stop before
implementation.

## Decision Result Template

Copy this template into an issue, PR body, planning note, or closeout:

```text
Reference capability:
Observed useful pattern:

Capability breakdown:
- Input:
- Output:
- User action:
- Write action:
- Runtime dependency:
- Data storage:
- Permission model:

Repository owner:
Supporting repositories:

Decision:
- Borrow as documentation only
- Borrow as static contract
- Borrow as suggestion-only Toolbox surface
- Borrow as Core-governed handoff
- Reject / defer because boundary conflict

Boundary result:
- Adds second ability registry: no
- Adds second workflow registry: no
- Adds approval store: no
- Adds local runtime queue: no
- Adds provider billing/log owner: no
- Bypasses Core governed write path: no

Required gate:
Next step:
Rollback / stop condition:
```

## Decision Outcomes

Use the smallest outcome that preserves the useful lesson:

| Outcome | Use when | Allowed next step |
| --- | --- | --- |
| Borrow as documentation only | The lesson clarifies language, operator expectations, or risk, but no public contract is ready. | Add or update a doc and run docs/static checks. |
| Borrow as static contract | The lesson freezes names, roles, payload markers, red lines, or gate expectations without runtime behavior. | Update docs and `tests/run.php` static contracts. |
| Borrow as suggestion-only Toolbox surface | The lesson improves a fixed button, checklist row, candidate card, blocked state, or handoff preview without writes. | Update Toolbox UI/REST only within existing routes and run `composer test:all`. |
| Borrow as Core-governed handoff | The lesson prepares a write-like change that must remain reviewable, approvable, and auditable. | Define the Toolkit/Core/Adapter handoff path before any Toolbox productization. |
| Reject / defer because boundary conflict | The lesson requires forbidden registry, runtime, approval, provider, storage, or write ownership. | Write a boundary note; do not implement in Toolbox. |

## Verification Matrix

Choose the narrowest gate that proves the decision, then run the broader gate
when the change touches public contracts.

| Situation | Minimum gate | Notes |
| --- | --- | --- |
| Documentation-only note with no public contract change | Docs/static test when one exists; otherwise `composer test:all` before closeout. | Keep the change in docs and indexes only. |
| Static contract, role map, boundary vocabulary, or public checklist change | `composer test:all` | This runs PHP linting, static contracts, boundary vocabulary, and local deterministic smokes. |
| Starting a new narrow slice after a multi-repo closeout | `composer quality:observe` | Status-only decision queue; does not run gates or mutate repos. |
| Multi-repo acceptance, release closeout, or proof that all configured repos still pass their default gates | `composer quality:matrix:run` | Runs configured gates across the repo family. Use `--fail-on-dirty` only for release closeouts that must prove no hidden edits. |

Do not use a passing gate to expand ownership. Gates prove that a chosen
boundary was preserved; they do not grant new runtime or write authority.

## Example

Hypothetical external plugin capability: an SEO plugin shows a "missing image
metadata" checklist and offers to apply generated ALT text.

Capability breakdown:

- Input: current post images, existing attachment metadata, optional source
  context.
- Output: missing-metadata rows, suggested ALT/caption text, confidence labels,
  and evidence notes.
- User action: review image rows, copy text, or request a governed handoff.
- Write action: applying ALT/caption would update media metadata, so it is
  write-like.
- Runtime dependency: local deterministic metadata checks may be synchronous;
  visual evidence or richer suggestions may be Cloud/host detail.
- Data storage: no Toolbox store is needed for checklist display; Core owns
  proposal/audit records if a write handoff is created.
- Permission model: Toolbox display remains `manage_options`; any write-like
  adoption must use Core policy and Toolkit media ability contracts.

Ownership judgment:

- Toolbox may borrow the checklist shape, blocked-state language, source labels,
  and review-only rows.
- Toolkit owns any reusable media metadata update ability contract.
- Core owns proposal, approval, preflight, and audit truth for accepted writes.
- Cloud may provide optional suggestion-only image context detail.

Decision:

```text
Borrow as suggestion-only Toolbox surface first.
Borrow as Core-governed handoff only after the Toolkit/Core media metadata
contract is accepted.
Reject any one-click local media metadata write in Toolbox.
```

Gate:

- docs/static contract for the checklist wording and red lines;
- `composer test:all` for any Toolbox contract or UI change;
- `composer quality:matrix:run` only if the work changes multiple repositories
  or is closing a cross-repo milestone.
