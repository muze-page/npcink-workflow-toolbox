# Cross-Repo Database Boundary Closeout - 2026-07-08

Status: local closeout record.

This record summarizes the database-boundary review across the current five
WordPress-side projects:

- `npcink-abilities-toolkit`
- `npcink-governance-core`
- `npcink-ai-client-adapter`
- `npcink-workflow-toolbox`
- `npcink-cloud-addon`

The normative rule remains
[Cross-Repo Database Boundary](cross-repo-database-boundary.md). This document
captures how that rule was derived, implemented, and verified so future AI
sessions can continue from the same facts instead of re-opening the same
boundary question.

## Question

The working question was whether the database and persistence choices in the
five current projects were reasonable.

The answer after repo inspection was yes, with one important constraint:
`npcink-governance-core` is the only project in this set that should own custom
WordPress tables. The other four projects should keep their local persistence
small, bounded, non-authoritative, and replaceable by the actual owner when the
data becomes durable product truth.

## Investigation Method

The review started from the repository state, not from a desired architecture.
The pass checked for:

- `dbDelta(` and `CREATE TABLE` usage;
- activation-time schema creation;
- WordPress options, transients, post meta, and bounded buffers;
- whether a stored value represented governance truth, transport/cache state,
  operator settings, or Cloud-owned runtime detail;
- whether any plugin was drifting toward a second ability registry, second
  workflow registry, second approval store, local queue, or write executor.

## Findings

`npcink-governance-core` owns the durable governance data. Its custom tables are
reasonable because proposals, approval records, sensitive read requests, audit
events, app keys, rate limits, and preflight evidence need durable, queryable,
and auditable storage.

`npcink-abilities-toolkit` owns reusable ability definitions and planning
helpers. It does not need custom tables for the current stage.

`npcink-ai-client-adapter` is a channel and handoff layer. Its execution and
preflight state must stay capped, TTL-bound bridge/idempotency state only; it
must not become a durable execution-history database.

`npcink-workflow-toolbox` is the operator-facing product surface. It may keep
settings, content context, temporary previews, and suggestion artifacts, but it
must not own run tables, queue tables, provider request logs, vector/index
tables, approval stores, or workflow registries.

`npcink-cloud-addon` is the WordPress transport and shallow status bridge for
Cloud. Local observability and Site Knowledge buffers support delivery
durability only. Reliable queues, runtime detail, indexing lifecycle, usage,
billing, entitlement, and diagnostics detail belong in Cloud service storage.

## Implementation History

The first closeout converted the review into docs and static contracts:

- Toolbox commit `ff5f450 Document cross-repo database boundary` added
  `docs/cross-repo-database-boundary.md`, indexed it, and asserted the boundary
  in `tests/run.php`.
- Adapter commit `493c6ec Lock Adapter persistence boundary` documented the
  persistence boundary in the Adapter README and OpenClaw adapter contract, and
  added static checks that prevent custom tables and require bounded execution
  and preflight state.
- Cloud Addon commit `515190e Document Cloud Addon persistence boundary`
  documented the local persistence boundary and added static checks that keep
  production code free of custom table creation.

Verification for that closeout:

- Toolbox `composer test:all`: passed.
- Adapter `composer test:all`: passed.
- Cloud Addon `composer test:all`: passed.

`npcink-governance-core` and `npcink-abilities-toolkit` were not changed in
that pass because their existing storage posture already matched the boundary:
Core owns governance tables; Toolkit does not own local durable state for this
question.

## Development Thinking

The practical rule is contract-first, then code. For cross-repo boundary work,
the first implementation should be the smallest change that makes the decision
durable:

- inspect the real repositories before judging the design;
- name the authority owner before naming a storage mechanism;
- document the boundary in the repository where future operators and AI
  sessions will look first;
- add static contracts for the highest-risk drift points;
- keep WordPress options as bounded local state, not hidden databases;
- route any durable governance truth to Core;
- route any durable runtime, queue, indexing, billing, entitlement, or
  diagnostics truth to Cloud service storage;
- stop and write a boundary note before adding custom tables outside Core.

The goal is not "no database". The goal is one durable owner per kind of truth.
Custom tables are correct when the data is authoritative, durable, auditable,
and queryable by that owner. They are wrong when they are a shortcut around
governance, a substitute for Cloud runtime storage, or a local copy of a future
workflow runtime.

## Future Stop Rule

Do not add a custom WordPress table to Toolkit, Adapter, Toolbox, or Cloud Addon as a shortcut.
If a feature needs durable history, queueing, retries, leases, run recovery,
provider logs, vector indexing, billing, entitlement, or approval truth, stop
and classify the owner first.

The expected routing is:

- governance, approval, preflight, audit, and app authorization truth -> Core;
- reusable WordPress ability schemas and dry-run helpers -> Toolkit;
- channel execution profiles, signed handoff, and bounded idempotency -> Adapter;
- operator-facing suggestion and Core-ready plan surfaces -> Toolbox;
- WordPress-to-Cloud transport and shallow local status -> Cloud Addon;
- durable runtime/detail/indexing/usage/billing/diagnostics -> Cloud service.

## Current Closeout State

After the first database-boundary closeout, the three edited local worktrees
were clean. The work was committed locally and not pushed in that closeout.
Future publication should use normal command-line `git`; use `gh` only for
GitHub-specific PR metadata, checks, or PR creation.
