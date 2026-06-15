# AGENTS.md - Local Automation Runtime Module

## Boundary

This module is the bundled Phase 1 home for the future
`npcink-local-automation-runtime` owner.

It may contain:

- contract documentation;
- dry-run replay fixtures;
- schema/replay validators;
- tests that prove the module remains disabled for execution.

It must not contain:

- background workers;
- scheduler registration;
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

