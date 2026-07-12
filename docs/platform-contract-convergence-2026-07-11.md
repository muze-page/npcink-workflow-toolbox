# Npcink Platform Contract Convergence

Status: Toolbox convergence slice complete; sibling owner wording remains tracked.

Snapshot date: 2026-07-11.

Scope: the six Npcink repositories in the central quality matrix. This is a
current-state assessment, not a claim that every runtime already conforms.

## Target Contract

The platform converges on these rules:

1. `native_editor_commit` applies only to visible, editable values on the one
   current article, persisted solely by native WordPress Publish or Update.
2. Plugin-admin batches, external clients, background operations, media
   mutation, cross-object writes, and hidden post-save execution are
   `core_proposal_required`.
3. `npcink-abilities-toolkit` owns reusable, versioned static workflow
   definitions.
4. Toolbox and Adapter are projections of those definitions, not registries.
5. Npcink AI Client Adapter is a generic client contract with OpenClaw as the
   first implementation.
6. Core owns governance truth; Cloud owns hosted runtime/detail; Cloud Addon
   owns signed WordPress-to-Cloud transport. None becomes a second WordPress
   control plane.
7. `wp-magick-toolbox` is independent and excluded from this platform matrix.

## Conformance Scale

- **Conforms**: active owner documents and inspected implementation boundary
  agree with the target contract.
- **Partial**: the underlying boundary is mostly correct, but active wording or
  a consumer projection still needs convergence.
- **Does not conform**: inspected runtime behavior contradicts the accepted
  contract.
- **Not applicable**: the repository does not participate in that concern.

## Six-Repository Matrix

| Repository | Current evidence | Assessment | Required next action |
| --- | --- | --- | --- |
| `npcink-governance-core` | Runtime `/contract` exposes `native_editor_commit` only through `pre_classification_exclusions`, keeps the four governed classifications unchanged, and requires no Core record for the excluded path. | **Conforms** | Preserve the exclusion before Core classification; do not add editor-specific runtime state. |
| `npcink-abilities-toolkit` | `docs/workflow-definition-contract.md` names Toolkit as canonical owner and forbids runtime and governance fields. The production provider and replay fixture now own `media_optimization_v1` as the first cross-surface sample. | **Conforms** | Keep its v1 definition shape authoritative. Consumers project it instead of copying a registry. |
| `npcink-ai-client-adapter` | Contract v4 declares `generic_ai_client`, keeps OpenClaw as the priority channel and compatibility namespace, points workflow discovery to Toolkit, requires parity fields, and fails closed on definition-version mismatch. | **Conforms** | Preserve the thin projection and do not add canonical definition or runtime storage. |
| `npcink-workflow-toolbox` | ADR-006 defines the two write lanes. Native editor ALT updates only Gutenberg state. Media optimization now emits an explicit Toolkit-owned fixed-button projection; SEO, image import/adoption, and audio adoption stop at Core proposal receipt and governance link. | **Conforms** | Keep the zero-legacy-marker and projection-parity gates active. |
| `npcink-cloud-addon` | AGENTS limits the plugin to connector/transport ownership and blocks approval, proposal, workflow runtime, scheduler truth, and WordPress writes. | **Conforms** | Preserve transport-only ownership. Current unrelated dirty worktree files were not evaluated as part of this documentation change. |
| `npcink-ai-cloud` | AGENTS and active runtime contracts block a second WordPress control plane, local ability/workflow registry, governance truth, and WordPress writes; Site Knowledge output remains `suggestion_only`. | **Conforms** | Preserve runtime/detail-only ownership. Current unrelated dirty worktree files and branch work were not evaluated as part of this documentation change. |

## Closed Principal Gap

The Toolbox editor's former proposal-intent bridge was:

```text
review in editor
-> queue Core proposal id in private post meta
-> wait for Publish or Update
-> call Adapter approve-and-execute
```

It has been replaced by:

```text
review in editor
-> place eligible value in visible editor state
-> author uses native Publish or Update
-> end
```

Ineligible operations no longer fall back to hidden post-save execution. They
create a Core proposal and continue on the governance surface.

## Stage Exit Criteria

This convergence stage is complete only when:

- Core and Adapter owner documents contain the accepted generic wording;
- Toolkit remains the only canonical reusable workflow-definition owner;
- Toolbox production code contains no reviewed-action-intent storage or
  publish-triggered `approve-and-execute` path;
- eligible editor fields pass the native-save acceptance cases;
- ineligible editor and all admin batch operations stop after Core proposal
  creation and expose a governance link;
- all six default repository gates pass;
- the central quality matrix contains exactly the six Npcink projects and no
  `wp-magick-toolbox` entry.

Current status: the contract and projection criteria above are implemented.
`media_optimization_v1` is the reference acceptance sample, not a new runtime
or a signal to expand the feature surface.

## Change Discipline

This document coordinates ownership; it does not authorize edits in dirty
sibling worktrees. Implement each repository's convergence slice separately,
under its own AGENTS instructions and default gate.
