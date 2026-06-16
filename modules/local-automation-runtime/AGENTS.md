# AGENTS.md - Local Automation Runtime Module

## Boundary

This module is the bundled home for the future
`npcink-local-automation-runtime` owner.

It may contain:

- contract documentation;
- dry-run replay fixtures;
- schema/replay validators;
- tests that prove the module remains disabled for execution.
- Phase 2 Basic WP-Cron dry-run scheduling, when the implementation is
  explicitly disabled by default, stores only a single latest-preview option,
  and does not call Cloud, create Core proposals, write WordPress content,
  create custom runtime tables, acquire leases, retry actions, or process dead
  letters.

It must not contain:

- background workers outside the named Basic WP-Cron dry-run path;
- scheduler registration outside the named Basic WP-Cron dry-run path;
- custom runtime job tables;
- lease stores;
- retry processors;
- dead-letter processors;
- unattended approval;
- WordPress writes;
- Core proposal, approval, preflight, or audit truth.

## Phase 1 Rule

Phase 1 is schema and dry-run replay only. Any request to add execution,
scheduling, leases, retries, or persistent job state must first update the Core
runtime ADRs and this module boundary.

## Phase 2 Basic Rule

Phase 2 Basic may register one WP-Cron hook only for Nightly Site Inspection
dry-run preview generation. This is runtime implementation work owned by
`npcink-local-automation-runtime`, not a Toolbox fixed-button runtime. The first
Basic implementation must remain disabled by default and may only overwrite the
bounded latest-preview option for operator review.
