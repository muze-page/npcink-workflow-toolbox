# Npcink Platform Governance Index

Status: active coordination index.

This index is the cross-project entry point for Npcink platform norms. It
centralizes navigation, owner mapping, and boundary checks for the five local
WordPress projects, but it is not a replacement for each repository's own
runtime, governance, ability, or transport contracts.

## Decision

Use `npcink-workflow-toolbox` as the platform coordination index because
Toolbox is the operator-facing product surface where repeatable workflows,
fixed buttons, handoff artifacts, and cross-repository quality gates converge.

Keep `npcink-governance-core` as the governance truth source only. Core remains
the owner for proposal records, approval policy, commit preflight, operation
classification, app-key governance, and audit evidence. Putting all platform
norms inside Core would make future sessions more likely to treat Core as the
suite control plane, product owner, workflow runtime, or implementation
registry. That conflicts with Core's governance-only boundary.

## Authority Rule

Each rule must have one authoritative owner. This index may summarize a rule
and link to the source, but it must not fork or restate low-level contracts in
a way that can drift from the owner repository.

| Norm type | Authoritative owner | This index may contain |
| --- | --- | --- |
| Proposal lifecycle, approval policy, commit preflight, audit, app keys, operation classification | `npcink-governance-core` | A summary and link to Core contracts. |
| WordPress ability ids, schemas, dry-run previews, host-approved callbacks | `npcink-abilities-toolkit` | Reuse guidance and migration criteria. |
| Channel adapter entry, OpenClaw recipes, signed client handoff, execution profiles | `npcink-ai-client-adapter` | Channel placement and handoff guidance. |
| Operator UI, fixed buttons, suggestion artifacts, Core-ready plans, cross-repo quality gates | `npcink-workflow-toolbox` | Full local product-surface guidance. |
| Cloud Base URL/API key settings, signed transport, entitlement and runtime detail reads | `npcink-cloud-addon` | Shallow transport ownership and handoff guidance. |

## Platform Boundaries

- Core governs; it does not plan, execute, route models, run workflows, or own
  product UX.
- Toolkit defines reusable WordPress ability contracts; it does not decide
  whether a write is authorized.
- Adapter projects channels into existing governance contracts; it does not
  store private approval truth or create a second governance path.
- Toolbox presents repeatable operator workflows; it returns suggestions,
  candidates, review sets, and Core-ready plans rather than final WordPress
  writes.
- Cloud Addon transports bounded Cloud runtime requests; it does not own
  proposal truth, approval truth, WordPress writes, billing truth, provider
  routing truth, prompt truth, or workflow runtime truth.

## Current Source Map

Start here before multi-repository design or implementation:

- [Feature Ownership And Plugin Boundary](../feature-ownership-and-plugin-boundary.md)
- [Cross-Repo Boundary Matrix](../cross-repo-boundary-matrix.md)
- [Cross-Repo Contract Reuse Acceptance](../cross-repo-contract-reuse-acceptance.md)
- [AI Development Quality Workflow](../ai-development-quality-workflow.md)
- [AI Change Envelope Template](../ai-change-envelope-template.md)
- [Development Workflow](../development-workflow.md)

Use Core docs only for governance-specific truth:

- `npcink-governance-core/docs/governance-contract.md`
- `npcink-governance-core/docs/operation-classification-contract.md`
- `npcink-governance-core/docs/approval-commit-contract.md`
- `npcink-governance-core/docs/plan-to-proposal-governance.md`
- `npcink-governance-core/docs/decisions/ADR-001-rebuild-core-as-governance-layer.md`
- `npcink-governance-core/docs/decisions/ADR-005-keep-core-independent-and-standardize-channel-adapters.md`

## Feature Intake Rule

Before adding or moving a platform capability, classify it with these fields:

- `entry_surface`: Toolbox, Adapter/OpenClaw, Cloud Addon, or another channel.
- `artifact_type`: suggestion, candidate, review set, handoff plan, proposal
  plan, or governed callback.
- `write_posture`: `suggestion_only`, `local_admin_consent`,
  `strong_local_confirmation`, or `core_proposal_required`.
- `runtime_owner`: local read-only Toolbox, Cloud runtime/detail, Toolkit
  callback, Adapter channel, or none.
- `truth_owner`: the one repository that owns the durable contract.
- `handoff_owner`: the repository that receives the artifact next.
- `verification_gate`: the static, smoke, or cross-repo gate that proves the
  boundary.

Default to `suggestion_only`. Escalate to `core_proposal_required` whenever a
durable WordPress write, batch action, external channel, insufficient preview,
or high-impact operation appears.

## Migration Rule

Do not migrate a scattered norm into this repository just because it is useful
to more than one project. Migrate only the coordination index and owner link.
The detailed rule stays with the repository that owns the underlying authority.

Safe to centralize here:

- cross-repo role maps;
- feature placement rules;
- multi-repo AI session protocol;
- release and quality matrix guidance;
- stop rules that prevent second registries, second approval stores, workflow
  runtimes, provider secret stores, or direct WordPress writes.

Keep in the owner repository:

- Core governance data and lifecycle contracts;
- Toolkit ability schemas and callback contracts;
- Adapter recipe and execution-profile details;
- Cloud Addon credential and signed transport details;
- Cloud hosted runtime, entitlement, provider, Site Knowledge, queue, and
  billing contracts.

## Cleanup Rule For Existing Core Docs

Existing Core documents that are governance-specific should remain in Core.
Existing Core documents that describe broader product placement or platform
coordination should not keep expanding there. Convert them gradually into
summaries that point back to this platform index or to the specific product
owner. Do not delete historical Core records; downgrade them to history or
consumer notes when their authority has moved.

## Stop Rules

Stop and write or update a boundary note before implementing if a proposed
change would make any project look like one of these owners:

- second ability registry;
- second workflow registry;
- second approval store;
- second WordPress write executor;
- provider secret store outside its owner;
- workflow runtime, queue, scheduler truth, lease store, or retry owner outside
  its accepted runtime owner;
- Cloud, Cloud Addon, Adapter, or Toolbox as a replacement for Core governance
  truth.
