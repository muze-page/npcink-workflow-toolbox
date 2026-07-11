# Platform Boundary And Development Summary

Status: current development baseline.

Date: 2026-07-12.

## Purpose

Npcink Workflow Toolbox turns complex, repeatable WordPress operations that
would otherwise require a conversational AI flow into fixed buttons with
bounded inputs, predictable outputs, and an explicit write posture.

The project does not try to solve every concrete content, media, SEO, or site
operations problem itself. Other AI systems and hosted runtimes may produce
research, analysis, candidates, or plans. Toolbox productizes operations only
after their contracts and ownership are stable enough to be executed safely by
an ordinary WordPress operator.

The same operation may also be exposed to conversational clients through
Npcink AI Client Adapter. Fixed-button and conversational entry points should
reuse the same ability ids, workflow definitions, plan artifacts, and Core
governance rules instead of developing separate business logic.

## Final Product Direction

The desired platform has two equivalent ways to start a proven operation:

1. A WordPress operator clicks a fixed Toolbox button.
2. A third-party AI client invokes the generic Adapter contract, with OpenClaw
   as the first and priority implementation.

Both paths converge on Npcink Abilities Toolkit and Npcink Governance Core.
They may differ in interaction style, but they must not create separate ability
registries, workflow registries, approval stores, write executors, or audit
truth.

The long-term goal is therefore not a second chat product. It is a dependable
WordPress action layer in which a validated AI-assisted operation can be used
through a simple button or a compatible external AI client without changing
its ownership, safety, or governance meaning.

## Accepted Project Boundaries

### Npcink Workflow Toolbox

Owns:

- WordPress operator-facing fixed-button UX;
- bounded local context collection and result presentation;
- review artifacts and handoff preparation;
- native editor-state updates that satisfy the narrow Native Commit rule;
- read-only projections of capability and workflow readiness.

Does not own reusable workflow definitions, final write authorization,
governance truth, queues, schedulers, retries, provider control planes, or a
second runtime registry.

### Npcink Abilities Toolkit

Owns reusable first-party WordPress abilities and reusable, versioned workflow
definitions. Toolbox and Adapter consume those definitions. Neither consumer
may become a parallel definition owner.

### Npcink AI Client Adapter

Owns the generic external AI-client contract. Its positioning is:

> 通用契约、OpenClaw 优先实现；差异过大的通道再独立成插件。

OpenClaw naming may remain in compatibility REST namespaces, but the exposed
contract stays generic. A test client does not become a supported channel merely
because it can consume the contract. A materially different channel requires a
separate boundary decision and may justify a separate adapter plugin.

### Npcink Governance Core

Owns proposal records, approval, preflight, execution authorization, and audit
truth. Core is a governance kernel, not a Toolbox UI, workflow registry,
provider gateway, or hosted runtime.

### Npcink Cloud Addon And Npcink Cloud

Cloud Addon owns signed WordPress-to-Cloud transport. Cloud owns hosted runtime
execution, provider/model routing, usage, entitlement, quota, and runtime
detail. Neither component owns final WordPress write authority or becomes a
second WordPress control plane.

### Independent Products

`wp-magick-toolbox` and `npcink-workflow-toolbox` are independent plugins with
no ownership or runtime relationship. Similar names or capabilities do not
justify coupling their registries, navigation, release process, or contracts.

## Two Accepted Write Lanes

The earlier assumption that every AI-assisted write must create a Core proposal
was too broad. The accepted model distinguishes two lanes.

### Native Editor Commit

Use this lane only when the author has already reviewed the value in the current
article editor and Toolbox changes visible, editable editor state. WordPress
persists it only when the author uses the normal Publish or Update action.

Toolbox performs no backend WordPress write in this lane, so no Core proposal or
Core audit record is required. The current-article sidebar is the natural
surface for this exception.

This lane must not be extended to media-library metadata, cross-object changes,
hidden post-save execution, background work, batch work, external-client
writes, publishing, or global settings.

### Core-Governed Handoff

Plugin-page batch operations and other consequential writes prepare a Toolkit
plan, submit a proposal through Adapter, and send the operator to Core
governance. Toolbox stops after proposal creation and does not approve or
execute the write.

External AI clients do not inherit the Native Editor Commit exception. Their
write-like outcomes remain proposal-only unless a future ADR defines a
different, equally explicit host authorization model.

## Why The Native Commit Migration Was Worth Doing

The project was still under active development and had no meaningful historical
compatibility burden. Keeping the old editor proposal-intent and post-save
executor beside the accepted Native Commit path would have created two
mechanisms for the same author-reviewed action.

The migration removed that future debt early:

- editor behavior now matches the actual user decision point;
- Core is not filled with records for values the author already reviewed in the
  native editor;
- hidden post-save execution is eliminated;
- batch and external-client writes remain visibly governed;
- future buttons can be classified by effect instead of by whether AI was
  involved.

The migration was an efficiency decision, not a relaxation of governance.

## Work Completed In This Stage

The platform-boundary stage established and verified the following baseline:

- Toolbox is the fixed-button product surface and optional Npcink suite
  navigation owner;
- reusable workflow definitions are held by Abilities Toolkit;
- Adapter exposes a generic AI-client contract while keeping OpenClaw first;
- native editor commit and Core-governed batch handoff are separate write lanes;
- the obsolete editor proposal-intent/post-save path is removed;
- media derivative preview is reached through the Toolbox product surface while
  hosted processing remains Cloud-owned;
- default fixed buttons have a machine-readable ownership, write-lane, handoff,
  and Adapter-parity table;
- a non-OpenClaw consumer probe proves generic contract consumption without
  declaring a fake supported channel;
- cross-repository convergence and fixed-button boundary gates prevent the main
  ownership decisions from silently drifting.

ADR-008 freezes this model. A broad ownership migration now requires a concrete
failed contract or ownership contradiction and a superseding ADR.

## Development Method Derived From The Decisions

### Start With The Operator Effect

Classify the intended effect before choosing a route or UI:

- suggestion or review only;
- visible current-editor state followed by native WordPress save;
- Core-governed WordPress write;
- hosted runtime detail with no WordPress authority.

The classification determines ownership. The implementation should not invent
a new lane for convenience.

### Reuse Contracts Before Adding Product Surface

For a new fixed button, first identify:

- the reusable ability owner;
- the canonical workflow definition, when the operation is multi-step;
- the input and output artifact schemas;
- the runtime owner;
- the write lane and handoff owner;
- the expected Adapter parity level.

Only then should Toolbox add the button. If the reusable contract is missing,
add it to Abilities Toolkit rather than hiding a second definition in Toolbox.

### Keep Partial Parity Honest

Not every existing button is already a complete external-client workflow.
The contract table distinguishes proven workflow projection, ability-level
readiness, and partial contract reuse. Close these gaps one bounded workflow at
a time; do not restart a platform-wide migration merely to make every row look
uniform.

### Prove Boundaries With Executable Gates

Documentation records intent, while tests must verify high-risk claims:

- the production button has a contract-table row;
- Toolbox contains no direct backend WordPress writer for the flow;
- Toolkit remains the workflow-definition owner;
- Adapter projection fails closed on version mismatch;
- external writes remain proposal-only;
- supported channels are not expanded by tests or branding;
- Core and Cloud ownership wording stays consistent across repositories.

### Prefer Early Clean Migration Over Compatibility Layers

While the project has no external compatibility obligation, remove superseded
mechanisms instead of running old and new paths together. Compatibility seams
should exist only when a real installed integration depends on them, and their
ownership must remain documented.

## Admission Checklist For Future Buttons

A new default fixed button is ready only when all of the following are answered:

1. What repeatable operator problem does the button simplify?
2. Is its output review-only, Native Commit, or Core-governed?
3. Which Toolkit abilities and workflow definition does it reuse?
4. Which component owns any hosted runtime or provider execution?
5. What exact artifact is rendered or handed off?
6. Can the same contract be consumed by Adapter without copying business logic?
7. Does the contract table state its current parity honestly?
8. Are permission, sanitization, failure, and no-write assertions executable?
9. Has the change avoided a second registry, runtime, approval store, or write
   executor?
10. Has the narrowest real WordPress smoke been run where the effect warrants
    it?

If these questions cannot be answered, the operation is still an experiment or
contract-design task and should not become a default button.

## Next-Stage Direction

The next stage is not another broad architecture migration. Work should proceed
as small product slices:

1. validate that a repeatable operator problem is worth fixing as a button;
2. fill the missing Toolkit ability or workflow contract;
3. implement the smallest Toolbox projection;
4. preserve the correct Native Commit or Core-governed write lane;
5. prove generic Adapter consumption where useful;
6. run focused and cross-repository gates;
7. expand only after the real operator loop is useful.

This keeps the final goal stable: complex AI-assisted WordPress operations
become simple to execute, while every entry point continues to share one
ability, workflow, governance, and runtime ownership model.

## Related Decisions And Contracts

- [ADR-001: Build Toolbox As A Product Surface](decisions/ADR-001-toolbox-as-product-surface.md)
- [ADR-006: Separate Native Editor Commit From Governed Batch Handoff](decisions/ADR-006-native-editor-commit-and-governed-batch-handoff.md)
- [ADR-008: Freeze Fixed-Button And Generic AI-Client Boundaries](decisions/ADR-008-freeze-fixed-button-and-generic-client-boundary.md)
- [Product Positioning](product-positioning.md)
- [Boundary](boundary.md)
- [Workflow Definition And AI Client Projection Contract](workflow-and-ai-client-projection-contract.md)
- [Fixed Button Surface](fixed-button-surface.md)
- [Fixed Button Contract Table](fixed-button-contract-table.json)
- [Platform Contract Convergence Baseline](platform-contract-convergence-2026-07-11.md)
