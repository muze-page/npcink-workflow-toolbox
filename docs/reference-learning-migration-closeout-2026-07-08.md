# Reference Learning Migration Closeout - 2026-07-08

Status: active Toolbox closeout note.

## Purpose

This note records the Toolbox-side outcome of the reference-plugin learning
work. The learning loop is useful, but the canonical learning library does not
belong inside the WordPress operator plugin. It now lives in `npcink-eval-lab`
so it can support multiple repositories without making Toolbox heavier.

Toolbox keeps only migrated pointer docs and this local closeout. Future
product work should return to Toolbox only after the eval-lab evidence has
selected a concrete candidate that fits the Toolbox boundary.

## What We Learned

The reference-plugin process turned ad hoc plugin watching into a repeatable
loop:

1. Break down an external plugin capability into inputs, outputs, user
   actions, write actions, runtime dependencies, storage, and permissions.
2. Decide which repository owns the reusable contract, product surface,
   governed handoff, runtime detail, or historical reference.
3. Reject or defer anything that would create a second ability registry, second
   workflow registry, approval store, local runtime queue, provider billing/log
   owner, or bypass around the Core governed approval/write path.
4. Store reusable ideas in a learning library so later feature work can borrow
   mature patterns faster.
5. Promote only selected P1 candidates into repository-specific implementation
   work.

## Current Ownership

Canonical reference-learning assets now live in `npcink-eval-lab`:

- reference plugin evaluation checklist;
- evaluation record template;
- individual evaluation records;
- reference capability library;
- selection rubric for whether a finding is P0, P1, P2, or rejected.

Toolbox keeps migrated pointers for historical discoverability:

- `docs/reference-plugin-evaluation-checklist.md`
- `docs/reference-plugin-evaluation-record-template.md`
- `docs/reference-plugin-evaluations/`
- `docs/reference-capability-library/`

Those pointer docs are development evidence only. They do not add a customer
feature, admin screen, REST route, Ability, queue, provider connector,
approval path, or WordPress write path.

## Toolbox Intake Rule

Toolbox may receive follow-up work from the learning library only when all of
these are true:

- the finding is selected as a candidate worth productizing;
- the user or maintainer explicitly approves the Toolbox implementation slice;
- the implementation is an operator-facing suggestion-only surface or a
  Core-governed handoff;
- the change does not add a second registry, approval store, local runtime
  queue, provider billing/log owner, or direct WordPress write executor;
- `composer test:all` remains the default Toolbox gate.

When a finding needs reusable WordPress ability contracts, it should go to
`npcink-abilities-toolkit`. When it needs proposal, approval, preflight, or
audit truth, it should go to `npcink-governance-core`. When it needs hosted
runtime/detail, datasets, batch learning, or comparative evaluation, it should
stay in `npcink-eval-lab` or `npcink-ai-cloud`, depending on whether the work
is dev-only evidence or production hosted service behavior.

## Development Loop

The intended future loop is:

```text
external plugin capability
-> npcink-eval-lab evaluation record
-> reference capability library entry
-> selection rubric
-> user-approved P1 candidate
-> repo-specific implementation PR
-> narrow repository gate
```

For Toolbox, the repo-specific implementation PR should usually be one of:

- a better suggestion-only operator panel;
- a clearer readiness/status surface;
- a static product contract or copyable handoff artifact;
- a Core-governed handoff preview that still leaves approval and writes outside
  Toolbox.

It should not be a learning database, plugin marketplace, workflow runtime,
provider billing console, approval system, local queue, content indexing
owner, or WordPress write owner.

## Gate Guidance

Use `npcink-eval-lab` gates for learning-library changes. Use Toolbox gates
only when a selected idea changes Toolbox docs, contracts, tests, or product
surface.

| Situation | Gate |
| --- | --- |
| Eval-lab learning record or capability-library note only | Eval-lab docs/static gate |
| Toolbox pointer or closeout documentation only | `composer test:all` |
| Toolbox suggestion-only UI, readiness, REST contract, or Ability surface change | `composer test:all` plus the narrow focused smoke if available |
| Cross-repo ownership, Core handoff, Cloud Addon, or hosted runtime/detail change | `composer quality:observe` before planning and `composer quality:matrix:run` for milestone closeout |

## Next Step

Do not continue expanding Toolbox learning docs by default. The next useful
step is to run the first batch of reference-plugin learning inside
`npcink-eval-lab`, select a small number of P1 candidates, and bring only the
approved Toolbox-shaped candidates back here as scoped implementation work.
