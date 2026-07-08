# Cross-Repo Database Boundary

Status: active boundary note.

This note freezes the current WordPress-side persistence split across the
Npcink plugin family. It is a navigation and review guard, not a migration
plan.

## Decision

`npcink-governance-core` is the only WordPress plugin in this set that owns custom governance tables. The other WordPress plugins should keep their local
storage limited to options, transients, post meta, or bounded buffers that
match their product role.

## Project Ownership

| Project | Current storage shape | Boundary |
| --- | --- | --- |
| `npcink-governance-core` | Custom tables for proposals, sensitive read requests, audit log, app keys, and app rate limits. | Owns local governance truth, approval lifecycle, commit preflight evidence, app authentication, and audit evidence. |
| `npcink-abilities-toolkit` | WordPress callbacks, schemas, catalog state option, transient/read caches, and normal WordPress object writes through approved abilities. | Must not own proposal, approval, audit, app-auth, workflow-run, queue, or provider-runtime tables. |
| `npcink-ai-client-adapter` | Bounded options for device pairing, client keys, short preflight handoffs, short execution records, and transient rate/nonce caches. | These records are bridge/idempotency state only. Core remains audit and lifecycle truth; Adapter must not become a durable execution-history database. |
| `npcink-workflow-toolbox` | Settings, content context, media settings, local preview/backup options, and transients. | Must not create run tables, queue tables, provider request logs, vector/index tables, approval stores, or workflow registries. |
| `npcink-cloud-addon` | Cloud connection settings plus bounded observability and Site Knowledge delivery buffers/status options. | Local buffers support delivery durability only. reliable queues, run detail, indexing lifecycle, usage, billing, entitlement, and diagnostics detail belong in Cloud service storage. |

## Escalation Rules

Use this routing before adding any new persistent store:

- Governance proposal, approval, sensitive read, app-auth, preflight, or audit truth belongs in `npcink-governance-core`.
- Reusable WordPress read/write callbacks and dry-run previews belong in
  `npcink-abilities-toolkit`, using native WordPress storage only when an
  approved ability performs the final write.
- Channel handoffs, client pairing, and idempotency guards may stay in
  `npcink-ai-client-adapter` only when bounded by count and TTL.
- Operator settings, suggestion context, and current preview state may stay in
  `npcink-workflow-toolbox`; histories, queues, and final writes may not.
- Cloud connection, local delivery buffer, and status projection may stay in
  `npcink-cloud-addon`; durable queue, retry history, run recovery, vector
  indexing, and Cloud diagnostics detail belong in Cloud.

## Upgrade Triggers

An option or transient must not be expanded further when the feature needs:

- long-term queryable history;
- multi-worker queue semantics, leases, retry workers, or dead-letter handling;
- approval, authorization, or audit truth;
- provider billing, quota, request-log, key-rotation, or usage truth;
- embedding, vector storage, stale-index policy, or collection lifecycle;
- WordPress write authority or final commit truth.

When any trigger appears, stop and write a boundary decision that routes the
state to Core or Cloud. Do not add a second WordPress table in Toolbox, Adapter, Cloud Addon, or Toolkit as a shortcut.
