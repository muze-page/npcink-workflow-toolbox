# Media ALT Governed Write Implementation Gate

Status: contract handoff only; no write path is enabled in Toolbox.

## Decision

The current Batch Image ALT Review remains a local, non-submittable dry run.
Actual ALT updates may be enabled only after Toolkit, Core, and Adapter prove a
shared write contract. Toolbox must not fill that gap with a direct media write,
Local Admin Consent, or an `approve-and-execute` call.

## Repository Order

Implement and validate the path in this order:

1. `npcink-abilities-toolkit`: safely narrow and test the existing reusable
   `update-media-details` WordPress write ability for the ALT-only contract.
2. `npcink-governance-core`: accept, review, preflight, and audit proposals for
   the exact ability contract.
3. `npcink-ai-client-adapter`: allowlist the accepted ability/version and relay
   only Core-authorized execution.
4. `npcink-workflow-toolbox`: convert reviewed rows into proposal-ready plans
   and stop after Core proposal submission.

Do not start step 4 while any earlier gate is incomplete.

## Toolkit Contract Gate

The reusable write ability already exists as
`npcink-abilities-toolkit/update-media-details`. Its current public input is
intentionally broader than this flow because other governed media operations
also use it. What is missing is a published, tested ALT-only plan and execution
profile that safely constrains calls to the fields below. Toolbox must continue
to treat the ALT-only contract as `future_or_unavailable` until those narrower
Toolkit, Core, and Adapter gates pass.

Minimum input:

```json
{
  "attachment_id": 123,
  "alt": "Reviewed concise alternative text",
  "expected_current_alt": "",
  "operator_visual_review_confirmed": true,
  "dry_run": true,
  "commit": false,
  "idempotency_key": "stable-per-attachment-and-value"
}
```

Required behavior:

- accept image attachments only;
- reject caption, title, description, file, URL, and featured-image fields;
- reject stale writes when `expected_current_alt` no longer matches;
- validate and sanitize the final ALT value without inventing image facts;
- return dry-run before/after evidence without a write;
- on approved commit, return old value, final value, actor/source evidence,
  idempotency evidence, and a bounded rollback instruction;
- keep permission checks and final WordPress mutation inside the ability
  callback.

The first accepted policy should fill missing ALT only. Overwriting non-empty
ALT, people or sensitive-image descriptions, decorative-image classification,
and caption updates remain manual-review or later-contract work.

## Core Gate

Core must classify the action as `core_proposal_required`. The proposal must
record the attachment id, expected old value, reviewed final value, visual
confirmation, evidence refs, ability/version, actor, and idempotency key.

Core acceptance requires:

- schema validation against the published Toolkit contract;
- stale-value and capability preflight before commit;
- an audit trail for proposal, decision, preflight, execution result, and
  rollback result;
- no Local Admin Consent shortcut;
- manual approval under Core's default `manual` policy mode.

Core already has an optional `smart_guarded` policy for narrowly reviewed
ALT-only proposals. That capability is not removed by this contract, but it may
apply only when the site operator explicitly enables it and all missing-ALT,
old-value, field-allowlist, visual-confirmation, quota, and audit checks pass.
Toolbox never selects the approval mode, and Toolbox quality scores must never
be treated as approval input by themselves.

## Adapter Gate

Adapter may relay this action only after Core authorization. Its execution
profile must pin the Toolkit ability id and contract version, reject unlisted
fields, preserve the Core preflight context, and return per-action execution
and audit evidence.

Adapter must not own suggestion quality, approval policy, old-value truth,
rollback truth, batch queues, or retries.

## Toolbox Re-entry Gate

Only after all three upstream gates pass may Toolbox change the current
`media_alt_caption_core_handoff_plan.v1` preview into a proposal-ready plan.
At that point Toolbox may:

- collect reviewed ALT-only rows and visual-confirmation state;
- request the Toolkit dry-run preview;
- show stale-value or validation failures per row;
- submit selected proposal-ready rows to Core;
- show proposal receipts and links to Core review.

Toolbox must still not approve, execute, poll execution, write media metadata,
or maintain a batch runtime. Approval, execution, and rollback remain on the
governed Core/Adapter surface.

## Acceptance Evidence

Before enabling proposal submission in Toolbox, all of the following must pass:

- static contract tests in Toolkit, Core, Adapter, and Toolbox;
- a real WordPress dry run proving attachment metadata is unchanged;
- one approved missing-ALT write on a temporary attachment;
- stale-old-value rejection;
- invalid or non-image attachment rejection;
- idempotent replay evidence;
- audit evidence containing old and final ALT values;
- governed rollback restoring the exact prior value;
- a browser smoke proving Toolbox stops after proposal creation and makes no
  `approve-and-execute` or `/wp/v2/media` request.

Until that evidence exists, the current local dry-run summary is the complete
and honest product behavior.
