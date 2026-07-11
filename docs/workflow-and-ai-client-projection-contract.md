# Workflow Definition And AI Client Projection Contract

Status: active platform coordination contract.

This document defines ownership and projection parity. It does not copy the
Toolkit workflow-definition schema or replace Adapter's channel API contract.

## Authoritative Owners

| Concern | Owner |
| --- | --- |
| Reusable, versioned static workflow definition and its field schema | `npcink-abilities-toolkit` |
| Fixed-button UI projection and operator-local inputs | `npcink-workflow-toolbox` |
| Generic external AI-client contract and channel projection | `npcink-ai-client-adapter` |
| Proposal, approval, preflight, and audit truth | `npcink-governance-core` |
| Hosted model/runtime execution, usage, entitlement, and runtime detail | `npcink-ai-cloud` |
| Signed WordPress-to-Cloud transport and shallow connector settings | `npcink-cloud-addon` |

Toolkit's `docs/workflow-definition-contract.md` is authoritative for fields
such as `recipe_id`, `contract_version`, ability references, handoff shape, and
forbidden runtime/governance fields. Consumers must discover that definition;
they must not maintain copied canonical definitions.

## Projection Parity

A Toolbox button and an Adapter client action represent the same workflow only
when they preserve:

- Toolkit `recipe_id` and `contract_version`;
- the same entrypoint and expanded ability ids;
- required inputs and scopes;
- artifact and handoff posture;
- write-boundary classification;
- Core proposal target when a governed write is required.

Projection-specific data may include button labels, field layout, client tool
names, transport metadata, and compatibility aliases. It must not change the
canonical ability chain or authorization posture.

## Generic Adapter Baseline

Npcink AI Client Adapter is the common contract. OpenClaw is the first and
priority implementation, and the current `npcink-openclaw-adapter/v1`
namespace remains a compatibility contract.

The common client surface must cover:

- health and capability discovery;
- authenticated read-ability invocation;
- read-request governance when sensitive reads require it;
- Toolkit workflow-definition discovery or projection;
- Core proposal creation and status;
- explicit commit preflight and allowlisted post-Core execution where the
  Adapter owner contract permits it;
- correlation and operator-feedback propagation.

It must not own approval truth, ability callbacks, canonical workflow
definitions, model/provider execution, prompt registries, workflow runtime,
queues, or client credential storage outside its channel connection contract.

## Separate Adapter Threshold

A channel should become a separate plugin only when at least one material
difference cannot be represented cleanly as a profile:

- authentication or trust model;
- transport protocol or deployment topology;
- connection lifecycle and credential custody;
- durable channel-owned state;
- packaging, dependencies, or release lifecycle;
- security review and permission surface.

Different branding, prompt wording, tool labels, or a few payload aliases are
not sufficient reasons to create a new adapter.

## Consumer Acceptance

For every reusable workflow exposed by both Toolbox and Adapter, verify:

1. one Toolkit definition id and version;
2. no copied canonical definition in either consumer;
3. equivalent required input and ability references;
4. the same write posture and Core handoff;
5. projection-specific fields cannot weaken permissions;
6. a definition version mismatch fails closed or is shown as unsupported;
7. no consumer persists workflow execution state merely to display the
   definition.

The first enforced sample is
`npcink-abilities-toolkit/recipes/media-optimization`, with compatibility alias
`media_optimization_v1`. Toolkit owns its definition. Toolbox emits a
`fixed_button` projection in the media derivative handoff, while Adapter
contract v4 exposes the generic client projection rules. The central platform
checker reads the Toolkit replay fixture and verifies both consumers without
creating a second runtime registry.
