# ADR-008: Freeze Fixed-Button And Generic AI-Client Boundaries

## Status

Accepted

## Date

2026-07-11

## Context

The native editor commit migration, Toolkit workflow-definition ownership,
generic Adapter contract, Toolbox-owned product navigation, and media preview
runtime migration are now implemented and verified across the six Npcink
projects. Continuing broad ownership migrations would create churn and could
reintroduce parallel registries, runtimes, or governance paths.

The next requirement is conformance: every default Toolbox button must have an
explicit source contract, runtime owner, write lane, and external-client parity
status. A generic contract claim also needs an honest non-OpenClaw consumer
probe without claiming that an unlisted channel is already a supported product.

## Decision

Freeze the current ownership model:

- Toolbox owns fixed-button UX and operator-local input projection.
- Abilities Toolkit owns reusable abilities and canonical workflow definitions.
- AI Client Adapter owns the generic external-client contract, with OpenClaw as
  the first and currently supported priority channel.
- Governance Core owns proposal, approval, preflight, and audit truth.
- Cloud Addon owns signed WordPress-to-Cloud transport.
- Npcink Cloud owns hosted runtime, usage, entitlement, and runtime detail.

The machine-readable `fixed-button-contract-table.json` is the admission and
coverage record for default visible buttons. Adding, removing, or materially
changing a default button requires updating that table and its checker in the
same change.

`native_editor_commit` remains a Toolbox editor exception only when ADR-006's
eligibility test is satisfied. External clients do not inherit this exception;
their write-like outcomes remain subject to Core governance.

The non-OpenClaw contract probe is a consumer-conformance test. It may verify
generic discovery, Toolkit workflow-definition reads, and fail-closed write
posture through the compatibility namespace. It must not add a channel to
`supported_channels`, claim production support, or create a new adapter plugin.

No further broad ownership migration should begin without a superseding ADR
that identifies a concrete failed contract or an ownership contradiction.

## Consequences

- Architecture work shifts from migration to coverage and conformance.
- Current partial parity rows remain visible instead of being described as
  complete; they can be closed one contract at a time.
- New buttons cannot silently bypass Toolkit/Core/Adapter ownership decisions.
- The compatibility REST namespace may remain OpenClaw-named while the exposed
  client contract stays generic.
- A materially different channel still uses ADR-006's separate-adapter
  threshold rather than branding or payload wording alone.

## Alternatives Considered

### Continue migrating all runtime calls immediately

Rejected. The principal ownership contradiction is closed, and further broad
migration would add risk without proving a user-facing contract gap.

### Declare every fixed button fully available to every AI client

Rejected. Several buttons currently have ability-level or partial contract
reuse but no proven canonical workflow projection. The audit must report that
honestly.

### Register a fake second supported channel for testing

Rejected. A conformance probe is sufficient to test generic consumption; a
supported-channel declaration is a product and security commitment.
