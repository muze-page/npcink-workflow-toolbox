# SEO/Checklist Media Metadata Recommendation Evaluation - 2026-07-08

Status: accepted as first checklist trial.

## Scope

Reference capability: mature SEO and publish-checklist plugins often show
editor-side readiness rows for missing image metadata, weak media context, or
publish-blocking content quality gaps.

Reference source: existing local reference notes for SEO/checklist style
plugins, especially the fixed-button reference notes and reference-learning
synthesis. This record evaluates the capability pattern only; it does not
claim the external plugin implementation or ownership model.

Evaluation date: 2026-07-08.

Evaluator: AI development session.

Current Npcink question: should Toolbox borrow a "missing image metadata"
recommendation pattern, and if yes, where should the write-like parts live?

## Observed Useful Pattern

The useful pattern is not the SEO suite itself. The useful pattern is a compact
readiness row that tells the editor what is missing, why it matters, what
evidence was inspected, and which action is safe next.

This is valuable for Toolbox because it matches current operator expectations:
source labels, evidence counts, blocked states, and reviewable suggestions
before any governed handoff.

## Capability Breakdown

| Question | Answer |
| --- | --- |
| Input | Current post image references, existing attachment title/caption/ALT metadata, optional media library fallback rows, optional Cloud/host image-context evidence. Inputs must be bounded and non-secret. |
| Output | `media_alt_caption_review_set.v1` rows, readiness labels, evidence notes, candidate quality flags, blocked reasons, and optional Core-ready handoff notes. |
| User action | Review rows, inspect evidence, copy a suggestion, or request a governed handoff when the write path exists. |
| Write action | Updating attachment ALT, caption, description, featured image, or SEO fields is write-like and must not happen directly in Toolbox. |
| Runtime dependency | Local metadata checks may be synchronous. Rich image-context evidence may come from Cloud/host detail. No local vision model, queue, scheduler, or run table is needed. |
| Data storage | No new Toolbox storage. Existing trial/export artifacts are development evidence only. Core owns proposal and audit records if a governed write handoff is later created. |
| Permission model | Toolbox UI remains operator/admin gated. Final metadata writes require Toolkit ability contracts plus Core approval/preflight/audit and Adapter execution profile when applicable. |

## Repository Ownership

Primary owner: `npcink-workflow-toolbox` owns the operator-facing readiness row,
review labels, blocked state, and suggestion-only preview.

Supporting owners:

- `npcink-abilities-toolkit` owns reusable media metadata review-set and future
  media metadata write ability contracts.
- `npcink-governance-core` owns proposal, approval, preflight, and audit truth
  for any accepted metadata write.
- `npcink-ai-client-adapter` owns the channel/execution profile when an
  approved Core proposal is executed through the adapter path.
- `npcink-ai-cloud` may provide optional suggestion-only image-context detail.
- `npcink-cloud-addon` may transport signed Cloud runtime requests or surface
  runtime/detail status, but it does not own approval or writes.

Rejected owners:

- Toolbox must not own media metadata write execution.
- Cloud Addon must not become a media workflow runtime, approval store, or
  provider billing/log owner for this pattern.
- Cloud must not become a WordPress write authority.

## Boundary Result

| Boundary check | Result |
| --- | --- |
| Adds second ability registry | no |
| Adds second workflow registry | no |
| Adds approval store | no |
| Adds local runtime queue | no |
| Adds provider billing/log owner | no |
| Bypasses Core governed write path | no |
| Adds direct WordPress write | no |

Additional red lines:

- Do not expand Local Admin Consent from featured image to media metadata.
- Do not create media metadata proposals automatically from a background check.
- Do not add image indexing, local RAG, a vision runtime, or provider key
  storage in Toolbox.
- Do not treat image-source search or image-context evidence as media import.

## Decision

Decision: Borrow as suggestion-only Toolbox surface now.

Also accepted as a static contract pattern: future docs and tests should keep
the safe split visible:

```text
Toolbox readiness/review row
-> Toolkit reusable review/write contract
-> Core proposal/preflight/audit
-> Adapter execution profile
-> WordPress ability callback
```

Core-governed handoff remains deferred until the Toolkit/Core/Adapter media
metadata write path is accepted. Reject any one-click local metadata write in
Toolbox.

## Verification Gate

Minimum gate for this record:

```bash
php tests/run.php --quiet --filter='Reference plugin evaluation'
```

Required gate for this documentation/static-contract slice:

```bash
composer test:all
```

Broader gate only if this becomes multi-repo implementation:

```bash
composer quality:matrix:run
```

No browser smoke or WordPress activation smoke is needed for this record
because it adds no product UI, route, ability, runtime, or write behavior.

## Next Step

Next action: use this record as the first worked example when evaluating the
next external plugin capability. If a future implementation is proposed, start
from the existing media ALT/caption review-set path rather than a new Toolbox
button.

Stop condition: stop and write a boundary note if the proposed next step
requires direct media metadata writes, automatic proposal creation, local
queues, local vision runtime, provider-key storage, or a second approval store.

Rollback: revert this record and its static-test/index references; no runtime
state exists.
