# Local Automation Runtime Module

Status: Phase 1 bundled skeleton.

This module carries the future `npcink-local-automation-runtime` contract inside
the Toolbox release package without making Toolbox a runtime owner.

Current scope:

- validate `npcink_local_automation_runtime.v1` dry-run replay fixtures;
- keep runtime execution disabled;
- provide a stable module path for future isolated development:
  `modules/local-automation-runtime/`.

Current non-scope:

- no WordPress hooks;
- no REST routes;
- no admin execution buttons;
- no scheduler;
- no worker;
- no job table;
- no lease store;
- no retry or dead-letter processor;
- no unattended approval;
- no final WordPress writes.

The module namespace is `Npcink\LocalAutomationRuntime`. If later phases add a
runtime console or worker, they must keep this module isolated from Toolbox
fixed-flow buttons and must continue to use Core proposal approval and commit
preflight before any WordPress write.

