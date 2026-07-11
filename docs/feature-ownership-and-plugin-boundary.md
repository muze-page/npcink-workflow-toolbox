# Feature Ownership And Plugin Boundary

Status: active.

Scope: Npcink Core, Abilities Toolkit, Cloud, Toolbox, Adapter/OpenClaw, and
future channel surfaces.

## Purpose

Npcink should grow as an AI capability platform, not as a scattered plugin
collection.

New features must be assigned by responsibility boundary, not by feature name.
A new feature does not automatically require a new WordPress plugin. Most
features should reuse the existing platform layers:

- Core for governance;
- Abilities Toolkit for reusable WordPress operations;
- Cloud for AI and runtime capability;
- Toolbox for operator-facing product surfaces;
- Adapter, OpenClaw, and channel surfaces for request entry points.

## Core Principle

Do not create one plugin per feature.

Create a new plugin only when the feature has an independent responsibility
boundary, independent lifecycle, heavy dependencies, separate data ownership, or
a distinct channel or product package.

A normal AI-assisted feature should use this pattern:

```text
channel or Toolbox entry
-> ability or capability discovery
-> Cloud AI runtime or local read ability
-> suggestion artifact or reviewed plan
-> Core proposal when a WordPress write is needed
-> Abilities Toolkit callback execution after approval
```

## Layer Ownership

| Layer | Owns | Does not own |
| --- | --- | --- |
| Core | Proposal records, approval, preflight, audit, governance truth | Provider execution, product UI, model routing |
| Abilities Toolkit | Reusable WordPress read/write ability definitions and callbacks; reusable static workflow definitions | AI reasoning, workflow UI, workflow runtime, approval truth |
| Cloud | Hosted AI runtime, model routing, provider adapters, Site Knowledge, indexing, queues, quotas, long-running execution | WordPress write authority, local approval store, WordPress control plane |
| Toolbox | WordPress operator UI, fixed buttons, review surfaces, planning artifacts, suggestion workflows | Final writes, approval truth, provider billing, workflow runtime |
| Adapter / OpenClaw | Generic external AI-client contract and channel orchestration into existing contracts; OpenClaw first | Core truth, duplicated ability registry, duplicated workflow registry |
| Future channel plugins | New entry points such as public widget, browser extension, Slack, WeChat, or SaaS console | Reimplementing Core, Abilities, Cloud, or Toolbox responsibilities |

## Feature Placement Rules

| Feature shape | Default home |
| --- | --- |
| Operator clicks a button and reviews suggestions | Toolbox |
| AI analyzes, summarizes, ranks, rewrites, searches, or plans | Cloud capability |
| WordPress reads or writes need reusable schema and callbacks | Abilities Toolkit |
| A versioned static workflow definition is reused by more than one channel | Abilities Toolkit |
| Anything approves, audits, preflights, or commits writes | Core |
| Natural-language request maps to existing governed workflows | Adapter/OpenClaw |
| Public website chat widget or customer-facing assistant | New channel surface, backed by Cloud |
| Background queue, long job, retry, provider cost, or quota | Cloud |
| Local WordPress scheduled preview only | Toolbox only when explicitly bounded and no write or runtime ownership is added |

## New Plugin Threshold

Create a new plugin only if at least one of these is true:

1. The feature is a different entry channel, such as public chat widget, browser
   extension, Slack, WeChat, or SaaS console.
2. The feature has heavy optional dependencies that should not ship with
   Toolbox.
3. The feature needs independent install/uninstall, migrations, custom tables,
   or data retention ownership.
4. The feature has a separate commercial package or entitlement boundary.
5. The feature must work without Toolbox as a first-class product.
6. Keeping it inside Toolbox would force Toolbox to own runtime, billing,
   provider keys, queues, approval, or final WordPress writes.

If none of these are true, implement it as a module, capability, or surface
inside the existing platform.

## Standard Feature Pattern

Every new AI feature should define these artifacts before implementation:

- `capability_id`: Cloud or local capability name.
- `entry_surface`: Toolbox, OpenClaw, public assistant, or another channel.
- `artifact_type`: suggestion, candidate, review set, handoff plan, or Core
  proposal plan.
- `write_posture`: `suggestion_only`, `local_admin_consent`,
  `strong_local_confirmation`, or `core_proposal_required`.
- `ability_ids`: reusable WordPress abilities consumed or targeted.
- `core_handoff`: whether final writes require Core proposal intake.
- `runtime_owner`: Cloud, local read-only Toolbox, or none.
- `data_owner`: which layer stores any durable state.
- `verification_gate`: static contract, smoke test, or integration test.

## Default Write Classifications

| Classification | Meaning | Owner |
| --- | --- | --- |
| `suggestion_only` | Produces advice, candidates, summaries, scores, plans, or source evidence only | Toolbox/Cloud |
| `local_admin_consent` | Narrow present-admin local write with explicit audit contract | Only allowed by prior ADR |
| `strong_local_confirmation` | Future narrow direct apply class requiring exact preview and fail-closed audit | Requires separate design |
| `core_proposal_required` | Any durable WordPress write, batch write, media import, SEO mutation, publishing, or taxonomy mutation | Core + Abilities |

Default to `suggestion_only`. Escalate to `core_proposal_required` when a
WordPress write is involved.

ADR-006 narrows one pre-classification case: a reviewed value placed into the
current article's visible editor state and persisted only by native WordPress
Publish or Update is `native_editor_commit`, not a Toolbox write and not a Core
operation. Plugin-admin batches, external/background actions, hidden post-save
execution, media mutation, and cross-object writes still escalate to Core.

`wp-magick-toolbox` is a separate, unrelated plugin. It is not a legacy name,
feature source, compatibility target, or release dependency of this platform.

## Hard Blocks

Do not let any new feature introduce:

- a second ability registry;
- a second workflow registry;
- a second approval store;
- direct publish or direct WordPress writes that bypass Core;
- provider key leakage into WordPress UI, REST responses, logs, proposals, or
  docs;
- Toolbox-owned provider billing, quota, request logs, model routing, or queue
  runtime;
- Cloud-owned WordPress approval or final write authority;
- feature-specific plugins created only because the feature has a new name.

## Examples

### AI Site Assistant

- Public widget or channel surface: create a channel surface only if it is
  public or customer-facing.
- Cloud: hosted conversation, Site Knowledge retrieval, model routing, quota.
- Toolbox: setup, status, admin configuration, and preview.
- Core: only if the assistant can create proposed WordPress changes.
- Abilities: reusable WordPress read/write callbacks.

### Old Article Optimization

- Toolbox: fixed review button and result UI.
- Cloud: content analysis, source coverage, related content, suggestions.
- Abilities: update post, set terms, SEO metadata, internal-link patch when
  available.
- Core: proposal, approval, preflight, audit.
- New plugin: no.

### Media Optimization

- Toolbox: select media, preview, review set, handoff surface.
- Cloud: derivative generation and runtime processing.
- Abilities: media replacement and metadata callbacks.
- Core: proposal and execution governance.
- New plugin: only if a separate media product lifecycle or heavy local
  processor is introduced.

### Public GPT Promotion Assistant

- Public channel/site surface: yes, as a channel or Cloud-hosted widget.
- Cloud: runtime, limits, model routing, Site Knowledge.
- Toolbox: WordPress setup and content sync/status.
- Core: not involved unless the assistant proposes WordPress writes.
- New plugin: only if packaged as an installable WordPress public widget.

## Feature Intake Checklist

Before implementing a new feature, answer:

1. Is this a suggestion, a plan, or a final write?
2. Which existing ability ids can it reuse?
3. Does it require Cloud runtime, Site Knowledge, indexing, queue, or quota?
4. Does it require Core proposal approval?
5. Is Toolbox only rendering/reviewing, or is it being asked to own runtime
   state?
6. Is the entry point operator-facing, public-facing, or channel-facing?
7. Is a new plugin justified by lifecycle, dependency, data, or channel
   boundaries?
8. What static contract or smoke test will prevent future boundary drift?

## Decision Rule

When uncertain, keep the feature inside the existing platform:

```text
Cloud capability + Ability contract + Core-governed recipe + Toolbox surface
```

Only split a plugin after the boundary is proven, not before.
