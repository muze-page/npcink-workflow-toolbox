# Media Conversion Review Set

Status: Phase 1 contract for the first local batch operation target.

This contract names the first governed batch operation that belongs in the
`npcink-local-automation-runtime` module while it is bundled with Toolbox:
building a bounded review set for converting existing WordPress media files to
another derivative format.

It is not a queue, worker, scheduler, or WordPress write path.

## Purpose

The review set gives operators a batch-ready view before any preview or final
write can happen:

```text
scope and rule
-> eligibility summary
-> blocked reasons
-> selected media conversion candidates
-> preview/proposal handoff reference
-> Core-governed proposal path
```

The first target is image format conversion, such as JPEG or PNG attachments to
WebP derivatives. Later targets, such as image ALT review and tag optimization,
should copy this review-set shape only after this contract stays stable.

## Contract

The fixture contract is
`npcink_local_automation_media_conversion_review_set.v1`.

Existing `npcink-abilities-toolkit/build-media-derivative-batch-plan` outputs
should be normalized into this contract before Toolbox renders a local
automation batch surface. That normalization is a projection layer only; it
does not move the ability, preview generation, Core proposal handoff, or final
execution into this module.

Required top-level fields:

- `contract_version`;
- `runtime_owner`;
- `operation_family`;
- `mode`;
- `trigger`;
- `scope`;
- `eligibility_summary`;
- `selected_items[]`;
- `blocked_items[]`;
- `operator_next_action`;
- `retryable`;
- `retry_guidance`;
- `safety`.

The current accepted values are:

- `runtime_owner`: `npcink-local-automation-runtime`;
- `operation_family`: `media_conversion`;
- `mode`: `governed_review_set`;
- `trigger`: `operator_manual_review`.

`retryable` means the review set can be rebuilt after the operator changes
scope or filters. It does not mean the local runtime owns execution retries.

## Selected Items

Each selected item must include:

- the source attachment id;
- source MIME type;
- target format;
- `preview_required: true`;
- target ability id
  `npcink-abilities-toolkit/build-media-derivative-cloud-request`;
- `proposal_path: core_proposal_required`;
- `direct_wordpress_write: false`.

Selected items are candidates for a preview/proposal handoff. They are not
queued work and are not approved writes.

## Blocked Items

Each blocked item must carry a stable `blocked_reason` and
`operator_next_action`. Examples:

- already in the target format;
- unsupported MIME type;
- missing local source file;
- exceeds operator-selected scope.

Blocked items are display and operator-decision evidence only. The runtime
must not silently retry, fix, publish, replace, or mutate them.

## Safety

The review set must keep these flags false:

- `direct_wordpress_write`;
- `core_proposal_created`;
- `approval_performed`;
- `preflight_performed`;
- `execution_performed`;
- `action_scheduler_used`;
- `custom_tables_created`;
- `local_queue_created`;
- `lease_store_created`;
- `retry_worker_created`;
- `dead_letter_created`;
- `cloud_scheduler_truth`.

This keeps local batch work separate from Cloud nightly article/data analysis
and from future unattended execution.

## Handoff

The review set may point to the existing media derivative ability and Core
proposal path, but it must not create the proposal itself. The first operator
handoff remains:

```text
review selected candidates
-> generate selected previews
-> submit selected Core reviews
-> Core approval and preflight
-> Adapter allowlisted execution profile
-> Abilities final write callback
```

## Non-Scope

This contract does not add:

- Action Scheduler;
- WP-Cron for media conversion;
- a custom runtime table;
- a local queue;
- leases, retries, or dead letters;
- automatic Core proposal creation;
- unattended approval;
- direct media file replacement;
- media metadata or taxonomy writes.
