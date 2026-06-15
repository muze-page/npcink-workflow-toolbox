# Local Automation Runtime Boundary

Status: Phase 1 contract-only boundary.

The local automation runtime is independently owned as
`npcink-local-automation-runtime` and may be bundled in Toolbox for release as
`modules/local-automation-runtime/`.

Toolbox may host the module and later expose an operator console, but Toolbox
fixed-flow buttons must not become the runtime state machine, scheduler, lease
manager, retry processor, dead-letter processor, approval path, or final write
executor.

## Phase 1 Allowed

- dry-run replay fixture validation;
- contract docs;
- no-write smoke tests;
- static contract checks.

## Phase 1 Blocked

- registering a cron schedule or Action Scheduler job;
- creating runtime custom tables;
- acquiring leases;
- retrying action execution;
- dead-letter processing;
- approving Core proposals;
- calling Adapter approve-and-execute;
- calling WordPress write abilities;
- publishing, importing media, mutating SEO, or changing settings.

## Handoff Rule

Future execution phases must use this sequence:

```text
runtime job
-> Core proposal
-> Core approval
-> Core commit preflight
-> Adapter allowlisted execution profile
-> WordPress Abilities API callback
-> Core execution-result record
```

