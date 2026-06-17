# Nightly Inspection Pro Cloud Runtime Release Prep

Status: ready for review packaging.
Date: 2026-06-16

## Release Slice

This slice packages the accepted Pro Cloud Runtime path for Nightly Site
Inspection. It should be reviewed as a bounded bridge from Toolbox to Cloud
Batch Runtime, not as a new local scheduler or autonomous writing runtime.

When Cloud, Core, Abilities, or Adapter are being changed in parallel, use
[Nightly Inspection Cross-Repo Handoff Checklist](nightly-inspection-cross-repo-handoff-checklist.md)
as the coordination contract before wiring new endpoints or payload fields.

The review target is:

- submit one bounded local Nightly Site Inspection snapshot to Cloud;
- read Cloud entitlement, status, and result detail back into Toolbox;
- merge Cloud scoring into the local Morning Brief preview for review;
- show a clear Core handoff when proposal or write work is needed;
- preserve the local fallback preview and Core write-governance boundaries.

## Suggested Review Stack

### 1. Boundary and product-positioning docs

Purpose: make the decision reviewable before code review.

Files:

- `README.md`
- `docs/architecture.md`
- `docs/boundary.md`
- `docs/development-workflow.md`
- `docs/decisions/ADR-004-bundle-local-automation-runtime-as-isolated-module.md`
- `modules/local-automation-runtime/README.md`
- `modules/local-automation-runtime/docs/basic-wp-cron-dry-run.md`
- `modules/local-automation-runtime/docs/boundary.md`
- `modules/local-automation-runtime/docs/nightly-site-inspection-morning-brief.md`
- `docs/nightly-inspection-pro-cloud-runtime-acceptance.md`
- `docs/nightly-inspection-pro-cloud-runtime-operator-trial.md`
- `docs/nightly-inspection-pro-cloud-runtime-release-prep.md`

Reviewer question:

- Does the documentation keep Pro execution in Cloud and local unattended
  automation ownership in `npcink-local-automation-runtime`?

### 2. Cloud bridge, entitlement, and result merge

Purpose: review the PHP contract and REST shape independently from UI polish.

Files:

- `includes/Provider_Client.php`
- `includes/Rest_Controller.php`
- `includes/Settings.php`
- `includes/Admin_Page.php`
- `npcink-toolbox.php`
- `modules/local-automation-runtime/src/NightlyInspection/Cloud_Batch_Result_Merger.php`

Reviewer questions:

- Are Cloud runtime calls still detail/result reads rather than scheduler truth?
- Are payload mode and retention settings bounded and sanitized?
- Does the merge remain review-only, with no Core proposal creation and no
  WordPress writes?

### 3. Admin operator surface

Purpose: review the operator flow separately from backend contracts.

Files:

- `assets/admin.js`
- `includes/Admin_Page.php`

Reviewer questions:

- Is the default surface limited to `Run Cloud inspection` and
  `Refresh Cloud quota`?
- Are manual status/result controls kept in `Advanced details`?
- Can the operator answer whether the run finished, what to review, and where
  approval/write work happens without opening raw payloads?

### 4. Smoke coverage and default gate

Purpose: prove the new behavior is guarded without adding Cloud dependency to
the default test gate.

Files:

- `composer.json`
- `tests/run.php`
- `tests/smoke-nightly-inspection-cloud-batch-merge.php`
- `tests/smoke-nightly-inspection-cloud-e2e.php`
- `tests/smoke-nightly-inspection-cloud-ui-contract.php`

Reviewer questions:

- Does `composer test:all` remain local and deterministic?
- Is the real Cloud E2E smoke documented and kept outside the default gate?
- Do tests assert the no-Action-Scheduler, no-local-queue, no-write boundary?

## Verification Before Review

Run the default local gate:

```bash
node --check assets/admin.js
php -l tests/run.php
php -l tests/smoke-nightly-inspection-cloud-ui-contract.php
composer validate --no-check-publish
composer smoke:nightly-inspection-cloud-ui
composer test:quiet
composer test:all
git diff --check
```

Run the real Cloud proof only when local WordPress and Cloud Runtime are
available:

```bash
cd /Users/muze/gitee/magick-ai-cloud
docker compose -f docker-compose.dev.yml --profile runtime up -d worker

cd /Users/muze/gitee/magick-ai-toolbox
composer smoke:nightly-inspection-cloud-e2e
```

Manual admin proof:

```text
https://magick-ai.local/wp-admin/admin.php?page=npcink-toolbox
```

Expected manual result:

- Cloud quota can be refreshed;
- a Pro run can be submitted;
- a succeeded run displays `Cloud run detail`, `Cloud review details`, and
  `Core handoff`;
- result detail shows `Review in Core`;
- zero snapshot item counts are not shown as misleading `Items0` text;
- browser console has no warnings or errors.

## PR Description Draft

Title:

```text
Add Pro Cloud Runtime bridge for Nightly Inspection review
```

Summary:

```text
- Add Pro Cloud Runtime entitlement, submit, status, and result-read paths for
  Nightly Site Inspection.
- Merge Cloud scoring into the local Morning Brief preview as review-only
  context.
- Add an operator-focused admin flow with recent-run recovery, Cloud run detail,
  and Core handoff.
- Document the Cloud-first product boundary and keep Action Scheduler, local
  queues, Core proposal creation, and WordPress writes out of Toolbox.
- Add source-only and local smoke coverage while keeping real Cloud E2E outside
  the default test gate.
```

Verification:

```text
- node --check assets/admin.js
- php -l tests/run.php
- php -l tests/smoke-nightly-inspection-cloud-ui-contract.php
- composer validate --no-check-publish
- composer smoke:nightly-inspection-cloud-ui
- composer test:quiet
- composer test:all
- git diff --check
- Manual local WordPress + Cloud Runtime trial: 2 succeeded runs, quota moved
  from Used 5 / Remaining 25 to Used 7 / Remaining 23.
```

## Merge Readiness

Ready when:

- default gate passes;
- operator trial evidence is current;
- reviewer accepts that Cloud owns runtime processing and Toolbox only bridges
  review context;
- No plugin-side Action Scheduler, custom table, queue, lease, retry, or
  dead-letter processor has been introduced;
- no automatic Core proposal creation or WordPress write path has been added.

Do not expand this slice into autonomous article drafting, local batch
execution, multi-site management, Cloud-owned WordPress writes, or Core approval
automation. Those require separate contracts and release gates.
