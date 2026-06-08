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

That contract now exists for one proof only: setting one existing WordPress
image attachment as the current post's featured image from the editor image
modal. All other write-like Toolbox actions must continue to use Core proposal
handoff, Adapter unified user actions, and reusable WordPress abilities until
their own boundary decision exists.

## Current Mapping

| Operation class | Current Toolbox behavior |
| --- | --- |
| `suggestion_only` | Return candidates or planning artifacts. No proposal required. No WordPress write. |
| `local_admin_consent` | Implemented only for one existing image attachment -> current post featured image, with Core audit and rollback on completion-audit failure. |
| `strong_local_confirmation` | Classification only. Requires a future confirmation and audit contract or Core proposal. |
| `core_proposal_required` | Prepare or submit a Core proposal through the existing governed handoff path. |

## First Proof

The first Local Admin Consent proof is intentionally narrower than full
featured-image adoption. It accepts only an existing WordPress image attachment
and sets it as the current post's featured image. It does not import media,
write media metadata, adopt an external URL, or combine multiple actions.

Required evidence and constraints:

- logged-in WordPress administrator in the editor;
- one current post and one existing image attachment;
- exact visible selected image before the click;
- operation classifier result `local_admin_consent`;
- Core-owned `local_admin_consent.requested` and
  `local_admin_consent.completed` audit records;
- rollback if completion audit fails;
- no proposal creation, approval, preflight, media import, metadata write, or
  batch action.

## Consequences

- The classifier can be shared now without weakening current governance.
- Toolbox cannot silently turn a fixed-flow button into a direct WordPress
  write path.
- A future Local Admin Consent implementation must include audit evidence from
  the start.
- Featured-image, media import, SEO meta, publishing, deletion, batch, external
  agent, and incomplete-preview writes continue to use Core proposal review
  unless a later ADR explicitly narrows an exception.
