# ADR-003: Local Admin Consent Requires A Separate Write Boundary

## Status

Accepted

## Date

2026-06-09

## Context

The operation classifier now distinguishes four authorization postures:

- `suggestion_only`
- `local_admin_consent`
- `strong_local_confirmation`
- `core_proposal_required`

This creates a useful policy vocabulary, but it does not by itself authorize
Toolbox to execute WordPress writes. Toolbox currently remains an operator
surface for suggestions, planning artifacts, and Core/Adapter handoffs.

The desired future shape is:

- pure suggestions require normal permission checks only;
- one visible, low-risk, single-object write may be allowed through Local Admin
  Consent when a present WordPress administrator clicks a specific apply button;
- single high-impact writes require stronger local confirmation or Core review;
- batch, background, external-agent, destructive, or incomplete-preview writes
  require Core proposals.

## Decision

Local Admin Consent is a classification and future execution contract, not a
Toolbox-owned direct-write permission in the current stage.

Toolbox may classify and display `local_admin_consent` eligibility, but it must
not add a direct local write executor until a separate boundary decision defines:

- the write owner for the specific operation;
- the exact WordPress ability or callback that performs the write;
- the audit-log owner and schema;
- the required preview evidence;
- the required actor evidence;
- rollback or low-cost recovery evidence;
- which operation kinds are allowed to bypass Core proposal review;
- which operation kinds remain Core-only.

Until that contract exists, write-like Toolbox actions must continue to use
Core proposal handoff, Adapter unified user actions, and reusable WordPress
abilities.

## Current Mapping

| Operation class | Current Toolbox behavior |
| --- | --- |
| `suggestion_only` | Return candidates or planning artifacts. No proposal required. No WordPress write. |
| `local_admin_consent` | Classification only. No direct Toolbox executor yet. |
| `strong_local_confirmation` | Classification only. Requires a future confirmation and audit contract or Core proposal. |
| `core_proposal_required` | Prepare or submit a Core proposal through the existing governed handoff path. |

## First Candidate For A Future Proof

The first Local Admin Consent proof should be a narrow single-object write with
an exact preview, low recovery cost, and no public-state change. It should not
start with featured-image adoption, because current image adoption may include
media import, media metadata writes, and featured-image assignment, and already
uses Adapter/Core/Abilities as the governed execution path.

Safer first candidates include:

- a single admin-only note or local review marker;
- one non-public draft metadata field that is fully previewed;
- one low-risk existing-object field where the before and after values are
  shown and audit evidence is written.

## Consequences

- The classifier can be shared now without weakening current governance.
- Toolbox cannot silently turn a fixed-flow button into a direct WordPress
  write path.
- A future Local Admin Consent implementation must include audit evidence from
  the start.
- Featured-image, media import, SEO meta, publishing, deletion, batch, external
  agent, and incomplete-preview writes continue to use Core proposal review
  unless a later ADR explicitly narrows an exception.
