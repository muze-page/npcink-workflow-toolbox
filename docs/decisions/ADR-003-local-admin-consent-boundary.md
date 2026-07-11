# ADR-003: Local Admin Consent Requires A Separate Write Boundary

## Status

Accepted

Partially superseded by ADR-006 for author-reviewed values committed through
the current article's native WordPress Publish or Update transaction. The
featured-image Local Admin Consent proof remains active.

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

The older post-editor SEO proposal-plus-post-save-execution rule is superseded
by ADR-006. A reviewed value qualifies for `native_editor_commit` only when it
is visible and editable in the current article and is persisted solely by the
author's native WordPress Publish or Update transaction. Hidden post-save SEO
execution remains prohibited; non-native SEO writes use Core proposals.

## Current Mapping

| Operation class | Current Toolbox behavior |
| --- | --- |
| `suggestion_only` | Return candidates or planning artifacts. No proposal required. No WordPress write. |
| `local_admin_consent` | Implemented only for one existing image attachment -> current post featured image, with Core audit and rollback on completion-audit failure. |
| `strong_local_confirmation` | Classification only. Requires a future confirmation and audit contract or Core proposal. |
| `core_proposal_required` | Prepare or submit a Core proposal through the existing governed handoff path. External-image adoption, article-audio adoption, non-native SEO mutation, and other cross-object writes stay governed. Batch admin actions stop after selected Core proposal submission. |

## Future Strong Local Confirmation Candidate

The post-editor summary/category/tag flow is a plausible future
`strong_local_confirmation` proof, but only after it has its own UX and audit
contract. The current recommendation step remains `suggestion_only`: AI may
show summary, existing category, and existing tag candidates without proposal
review because it does not write WordPress state.

If a future proof lets a present administrator apply accepted metadata directly
from the current post editor, it must stay narrower than the existing Core
handoff:

- one current post only;
- excerpt plus existing `category` and `post_tag` ids only;
- no new term creation, SEO meta writes, slug changes, publishing, body
  replacement, deletion, or batch actions;
- exact final metadata values shown before confirmation;
- explicit strong confirmation copy, not a generic apply button;
- audit evidence for actor, source flow, target post, old values, new values,
  AI suggestion summary, confirmation text, result, and correlation id;
- recovery evidence showing that the changed metadata can be restored or
  corrected at low cost;
- fail-closed behavior when audit cannot be recorded.

Until that UX and audit contract exists, accepted metadata choices continue to
use `/flows/content-metadata-apply-plan` and Core proposal review.

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
- current `operation-classification-v1` `decision_envelope` preserved in Core
  audit metadata;
- Core-owned `local_admin_consent.requested` and
  `local_admin_consent.completed` audit records;
- rollback if completion audit fails;
- no proposal creation, approval, preflight, media import, metadata write, or
  batch action.

## High-Risk Contrast Proof

The first contrast proof is
`npcink-toolbox/build-article-media-batch-write-plan`. It includes multiple
reviewed article/image pairs and combines draft creation, media upload, media
metadata, and featured-image actions. This operation is classified as
`core_proposal_required` because it touches multiple actions and includes media
import plus generated output references. The proof submits the plan through
Core `/proposals/from-plan`, expects one `plan_to_proposal_batch`, and verifies
that proposal intake does not create posts, import attachments, set featured
images, or emit `local_admin_consent.*` audit events.

## Consequences

- The classifier can be shared now without weakening current governance.
- Toolbox cannot silently turn a fixed-flow button into a direct WordPress
  write path.
- A future Local Admin Consent implementation must include audit evidence from
  the start.
- Featured-image, media import, publishing, deletion, batch, external agent,
  and incomplete-preview writes continue to use Core proposal review unless a
  later ADR explicitly narrows an exception.
- SEO meta written outside the current article's visible native editor state
  remains Core-governed. Qualifying native editor values follow ADR-006 and do
  not create a Core proposal or hidden post-save execution.
