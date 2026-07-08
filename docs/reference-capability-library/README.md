# Reference Capability Library

Status: active development-time learning library.

This library turns mature plugin learning into reusable capability patterns for
Npcink development. It is not a runtime registry, product roadmap, competitor
analysis archive, approval store, queue, provider console, or WordPress write
path.

Use it before designing a similar capability in any Npcink repository. The goal
is to reuse mature problem shapes, operator language, boundary checks, security
notes, and verification gates without copying another plugin's ownership model.

## How To Use This Library

1. Learn: observe one mature plugin capability or product pattern.
2. Evaluate: write a small record under
   [Reference Plugin Evaluation Records](../reference-plugin-evaluations/README.md)
   that captures source facts, ownership, red lines, and a decision.
3. Distill: update or add a capability pattern in this library.
4. Candidate: map the pattern to a concrete optimization candidate, naming the
   target repository, implementation shape, non-goals, and gate.
5. Evolve: implement at most one small PR when the boundary and gate are clear;
   otherwise defer or reject.

Every learning record does not need to become code. Every useful learning
record should either update this library, create a bounded candidate, or record
why the pattern is rejected.

## Capability Entries

- [Connection Readiness](connection-readiness.md)

## Entry Requirements

Each capability entry should include:

- problem;
- mature reference patterns;
- borrowable patterns;
- Npcink repository mapping;
- recommended implementation shape;
- do-not-borrow list;
- security and performance notes;
- verification gates;
- sources.

## Repository Mapping Baseline

| Repository | Library role |
| --- | --- |
| `npcink-workflow-toolbox` | Operator-facing suggestion surface, readiness rows, blocked states, owner labels, next-action hints, and Core-ready handoff previews. |
| `npcink-cloud-addon` | Signed Cloud connector, local connection verification, bounded status projection, and transport readiness. |
| `npcink-ai-cloud` | Hosted runtime/detail, provider execution, entitlement, usage, service health, diagnostics evidence, and artifacts. |
| `npcink-governance-core` | Proposal, approval, preflight, policy, operation classification, and audit truth. |
| `npcink-abilities-toolkit` | Reusable WordPress ability contracts, schemas, permission callbacks, dry-run previews, and write callbacks. |
| `npcink-ai-client-adapter` | Thin channel/execution profile layer, Core proposal/status forwarding, and approved execution posture. |
| `wp-magick-toolbox` | Legacy/current reference only; useful for labels, migration evidence, and compatibility lessons. |

## Boundary Rules

Capability entries must not authorize:

- a second ability registry;
- a second workflow registry;
- an approval store outside Core;
- a local runtime queue, retry worker, lease store, scheduler truth, or run
  recovery workspace in Toolbox;
- a provider billing, quota, key-rotation, request-log, prompt-router, or
  model-routing owner in Toolbox;
- direct WordPress writes outside the accepted governed path;
- a second WordPress write path;
- bypassing Core-governed approval, preflight, and audit for write-like actions.

## Batch Learning Rule

After this seed library exists, batch learning should stay small:

- at most three external plugins per batch;
- one or two capability patterns per plugin;
- one evaluation record per capability question;
- one capability-library update per reusable pattern;
- at most one P1 implementation slice per batch.

This keeps learning useful for product evolution without turning the library
into a backlog, runtime registry, or broad competitive research archive.
