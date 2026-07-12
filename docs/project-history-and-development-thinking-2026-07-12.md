# Project History And Development Thinking

Status: historical synthesis and current implementation guidance.

Date: 2026-07-12.

## Purpose

This document connects the main development stages of Npcink Workflow Toolbox
with the reasoning that produced the current product and platform boundaries.
It is an orientation record, not a replacement for active contracts, ADRs, or
machine-readable boundary tables.

The shortest summary is:

> Toolbox turns a proven AI-assisted WordPress operation into a fixed,
> reviewable operator flow. It owns the product surface, but reuses the
> platform's existing abilities, workflow definitions, governance, transport,
> and hosted runtime owners.

## How The Project Reached The Current Shape

### 1. Establish An Operator Product Surface

The initial architectural decision was to keep Toolbox separate from both
Governance Core and Abilities Toolkit. External research, image candidates,
semantic context, and repeatable workflow buttons needed a visible WordPress
operator surface, but placing them in Core would mix product UX and provider
execution with governance truth. Placing them in Toolkit would turn reusable
WordPress abilities into a provider and workflow runtime.

ADR-001 therefore established Toolbox as the fixed-button product surface:
Toolbox could gather bounded input, call the appropriate runtime owner, display
suggestions, and prepare handoffs, while Core retained approval and audit truth
and Toolkit retained reusable WordPress ability contracts.

### 2. Simplify The Product Around Repeatable Decisions

The next stage reduced generic or overlapping AI surfaces into concrete
operator jobs. The editor sidebar focused on article-adjacent support such as
publish preflight, internal links, image candidates, current-article ALT review,
and article audio candidates. Site Check evolved into a bounded decision router
rather than a general analytics or automation workspace.

This stage produced an important product rule: a default button must simplify a
repeatable decision with a clear result and next action. A route that exists for
compatibility is not automatically a product feature, and a broad AI workbench
is not justified when a smaller fixed flow gives the operator a more reliable
decision.

The Site Check visibility reassessment later applied the same rule honestly.
The compatibility route and its read-only contract were retained, but the
default entry was hidden when the operator problem and acceptance loop were not
yet strong enough. Implemented capability alone was not treated as proof of
product value.

### 3. Turn Boundary Statements Into Executable Contracts

As routes, abilities, Cloud bridges, and buttons multiplied, prose alone was no
longer enough to prevent ownership drift. The project added machine-readable
route, ability, Cloud bridge, and fixed-button contract tables, together with
static gates that check ownership language and forbidden behavior.

This changed the development method from "remember the boundary" to "state the
boundary in a contract and fail the build when it drifts." High-risk claims now
have executable evidence, including suggestion-only posture, no direct
WordPress write, one runtime owner, one handoff owner, and honest Adapter parity.

### 4. Move Runtime Detail To Its Real Owner

Image generation, audio generation, Site Knowledge, external search, and other
hosted work were progressively routed through Cloud Addon and Cloud-owned
runtime contracts. Toolbox kept the operator interaction and normalized result
presentation, while Cloud Addon kept signed WordPress-to-Cloud transport and
Cloud kept provider/model execution, entitlement, quota, usage, and runtime
detail.

The same separation was applied to automation. Local preview exceptions remain
bounded and documented; queues, leases, retries, run recovery, scheduler truth,
and long-running execution do not become Toolbox ownership. Query-time semantic
search does not imply local indexing lifecycle ownership, and image-source
search does not imply AI image generation or media adoption.

### 5. Reuse Toolkit Contracts Instead Of Growing A Second Registry

Taxonomy review sets, media ALT plans, media derivative plans, and other
reusable operations were delegated to Abilities Toolkit as their contracts
matured. Toolbox remained the fixed-button projection and review surface.

The broader platform cleanup then made the ownership model explicit:

| Component | Durable truth it owns |
| --- | --- |
| Npcink Workflow Toolbox | WordPress operator UX, bounded local input projection, review artifacts, and fixed-button handoff. |
| Npcink Abilities Toolkit | Reusable WordPress abilities and canonical versioned workflow definitions. |
| Npcink AI Client Adapter | Generic external AI-client contract and channel projection, with OpenClaw first. |
| Npcink Governance Core | Proposal, approval, preflight, authorization, and audit truth. |
| Npcink Cloud Addon | Signed WordPress-to-Cloud transport. |
| Npcink Cloud | Hosted runtime, provider/model routing, usage, entitlement, quota, and runtime detail. |

This is the central anti-duplication rule: Toolbox and Adapter may expose the
same operation through different interactions, but they must reuse the same
ability ids, workflow definitions, plan artifacts, and Core governance path.
Neither becomes a second ability registry, workflow registry, approval store,
write executor, or audit source.

### 6. Classify Writes By Effect, Not By The Presence Of AI

Earlier development treated every AI-assisted write as if it required a Core
proposal. ADR-006 replaced that broad rule with two explicit lanes.

`native_editor_commit` applies only when a present author reviews a value in the
current article's visible, editable editor state and WordPress persists it only
through the author's normal Publish or Update action. Toolbox performs no
backend write, so a second Core proposal and audit record would duplicate the
native editor transaction.

Core-governed handoff remains mandatory for plugin-admin batches, media or
cross-object mutation, external clients, hidden or background work, global
settings, publishing, and other consequential writes. Toolbox may preview and
prepare a proposal, then must stop after handoff. Core owns approval and audit;
Abilities own the allowlisted WordPress callback.

The obsolete editor proposal-intent and hidden post-save path was removed
rather than kept as a compatibility layer. This was an early-debt cleanup, not
a relaxation of governance.

### 7. Freeze Ownership And Shift From Migration To Conformance

ADR-008 closed the broad ownership-migration stage. Default buttons now require
an explicit source contract, runtime owner, write lane, handoff owner, and
external-client parity status. Partial parity remains visible instead of being
described as complete.

Further platform-wide ownership migration requires a concrete failed contract
or ownership contradiction and a superseding ADR. Normal development should
now advance one useful product slice at a time.

### 8. Apply The Method To Source-Grounded Writing Support

The URL-reference article writing pack is the latest example of the accepted
method. The operator submits one public source URL. Cloud reads that exact URL;
the operator first reviews bounded extraction evidence; only then may Toolbox
combine source facts with Site Knowledge overlap and style signals into
`article_writing_pack.v1`.

The flow stops at a reviewable planning artifact. It does not generate or
replace article body text, translate and publish, import media, create a Core
proposal, or write WordPress state. Exact-source evidence and Site Knowledge
serve different roles: the former grounds claims about the source; the latter
helps identify overlap, terminology, internal references, and a distinct site
angle.

This slice demonstrates four reusable lessons:

- stage expensive or consequential work behind verifiable evidence;
- treat external content as untrusted input, never as prompt authority;
- design an extensible artifact contract before adding more input modes;
- require operator-trial evidence before admitting article generation or body
  insertion as a product capability.

## Development Principles That Follow From The History

### Start With The Operator Effect

Before choosing a route, screen, or repository, classify the intended outcome:

1. suggestion or review artifact only;
2. visible current-editor state followed by native WordPress save;
3. Core-governed WordPress write;
4. hosted runtime detail with no WordPress authority.

The effect determines ownership and verification. UI placement, AI branding,
or an existing compatibility route does not create a new authorization lane.

### One Durable Truth, One Owner

Every new field or artifact should have one authoritative owner. Toolbox may
project status and prepare handoffs, but it should not mirror provider keys,
workflow lifecycle, proposal state, audit logs, Cloud run history, or indexing
truth. Cross-repository navigation belongs in Toolbox; low-level contracts stay
with the repository that owns them.

### Artifact First, Execution Later

New ideas should normally begin as bounded `suggestion_only` candidates, review
sets, plans, or handoff envelopes. The artifact must name its provenance,
write posture, blocked conditions, and next owner. Execution is added only
after the contract, operator value, and governance lane have been proven.

### Reuse Before Projection

Before adding a default button, identify:

- the reusable Toolkit ability;
- the canonical workflow definition for multi-step work;
- the input and output artifact versions;
- the local or hosted runtime owner;
- the write lane and handoff owner;
- the truthful Adapter parity level.

If the reusable contract is missing, fix that ownership gap before embedding
business logic in Toolbox.

### Make Boundaries Testable

Public behavior should be represented in static contracts and tests. At
minimum, verify permission and sanitization, artifact/version shape, failure
behavior, secret redaction, no-write posture, runtime and handoff ownership,
and the absence of forbidden registries or executors. Use a real WordPress
smoke when the behavior depends on WordPress state rather than source shape
alone.

### Keep Compatibility Honest And Small

Compatibility names may remain when installed integrations depend on them, but
they do not redefine ownership. An OpenClaw-named namespace can expose a
generic Adapter contract; a vector-search route can be a compatibility pointer
without making Toolbox a vector lifecycle owner; an image-generation name can
normalize a hosted candidate without making Toolbox the model runtime.

When no real compatibility obligation exists, remove superseded mechanisms
instead of maintaining two ways to perform the same action.

### Use Operator Evidence As The Admission Gate

Shipping a technically complete surface is not enough. Default visibility and
capability expansion should follow repeated operator use, measurable output
quality, clear recovery, and an accepted next-action loop. Weak evidence means
retain the contract as experimental or compatibility-only, not promote it by
adding more automation.

## Recommended Workflow For The Next Slice

1. Write the operator problem and expected decision in one sentence.
2. Classify the effect and select the existing write lane.
3. Identify the one owner for each ability, workflow definition, runtime,
   transport, governance record, and final WordPress callback.
4. Define or reuse a versioned suggestion/review/plan artifact.
5. Add the smallest Toolbox projection and keep unsupported modes fail-closed.
6. Update the relevant contract table and focused static assertions.
7. Run the narrowest useful test, then `composer test:all` for public contract
   or documentation changes.
8. Run a real operator trial before expanding visibility, automation, or write
   authority.

Stop and write a boundary note instead of implementing when the slice would
introduce a second registry, approval store, audit source, provider secret
store, queue/runtime owner, indexing lifecycle, or direct WordPress writer.

## Current Baseline And Next Direction

The architecture is no longer waiting for another broad redesign. The current
baseline is stable enough to support bounded product work:

- Toolbox remains the fixed-button and review surface;
- Toolkit remains the reusable contract and workflow-definition owner;
- Adapter remains the generic external-client projection;
- Core remains the governance kernel;
- Cloud Addon and Cloud remain transport and hosted runtime owners;
- editor-native and Core-governed writes remain separate lanes;
- machine-readable tables and tests make drift visible.

The next useful work is therefore a small operator-validated slice, not a new
platform layer. For source-grounded writing support specifically, the next
admission decision should come from the documented real-URL operator trial.
Full article generation, translation, or editor-body insertion remains blocked
until extraction coverage, factual preservation, rights review, and operator
usefulness are measurable and repeatable.

## Authoritative Follow-Up Reading

- [Product Positioning](product-positioning.md)
- [Boundary](boundary.md)
- [Architecture](architecture.md)
- [Roadmap](roadmap.md)
- [Platform Governance History](platform/cross-repo-platform-governance-history-2026-07-08.md)
- [Platform Boundary And Development Summary](platform-boundary-and-development-summary-2026-07-12.md)
- [ADR-001: Build Toolbox As A Product Surface](decisions/ADR-001-toolbox-as-product-surface.md)
- [ADR-006: Separate Native Editor Commit From Governed Batch Handoff](decisions/ADR-006-native-editor-commit-and-governed-batch-handoff.md)
- [ADR-008: Freeze Fixed-Button And Generic AI-Client Boundaries](decisions/ADR-008-freeze-fixed-button-and-generic-client-boundary.md)
- [Fixed Button Contract Table](fixed-button-contract-table.json)
- [Source Adaptation Review](source-adaptation-review.md)
- [Article Writing Pack V1](article-writing-pack-v1.md)
- [Source Adaptation Operator Trial](source-adaptation-operator-trial.md)
