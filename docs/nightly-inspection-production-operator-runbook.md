# Nightly Inspection Production Operator Runbook

Status: internal production-prep runbook.
Date: 2026-06-18

## Goal

Nightly Inspection should close the morning review loop without moving control
truth out of WordPress/Core:

1. WordPress/Toolbox submits a bounded local snapshot.
2. Cloud owns queue-backed run state, result retention, recent run cards,
   retry, quota, and diagnostics.
3. Toolbox displays Morning Brief review context only.
4. Operators explicitly select review items before Core proposal handoff.
5. Core/Adapter own proposal approval, preflight, execution, audit, and final
   WordPress writes.

## Healthy Run

A healthy run has all of these signals:

- Cloud entitlement is readable and `submit_allowed=true`.
- `Run Cloud inspection` returns a visible Cloud `run_id`.
- status reaches terminal `succeeded`.
- result read returns `cloud_batch_runtime_result.v1`.
- Toolbox returns `nightly_site_inspection_cloud_batch_merge.v1`.
- Morning Brief shows `Cloud run detail`, `Cloud review details`, and
  `Core handoff`.
- `Review in Core` is the next step, but no Core proposal is created until the
  operator selects specific Morning Brief review items.
- `direct_wordpress_write=false` and `cloud_scheduler_truth=false` remain true
  across submit, status, result, recent, and retry surfaces.

## Partial Success

When Cloud returns `partially_succeeded`:

- Treat the run as reviewable but not clean.
- Read `retry_guidance.failed_action_ids`.
- Review failed action reasons before creating any Core proposal.
- Use `Retry run` only after confirming the local bounded snapshot is still
  current enough for the retry.
- Do not create a local queue, repair table, dead-letter processor, or
  server-side Toolbox run history.

## Retry

`Retry run` is a Cloud-owned request bridge:

- Toolbox sends a fresh idempotency key.
- Toolbox sends a new bounded local snapshot as retry input.
- Cloud queues a new run and owns retry state.
- Toolbox displays the new run and follows status/result.
- Retry must not create a Core proposal, approval, preflight, execution, or
  WordPress write.

If Cloud rejects retry:

- Show the Cloud error.
- Keep the original run ID available through recent/status/result controls.
- Do not fallback to plugin-side Action Scheduler or a local retry worker.

## Recent Runs

`Load Cloud recent` reads Cloud-owned
`nightly_site_inspection_recent_runs.v1` cards.

Allowed:

- display latest run state;
- load a run ID into the browser-local recovery entry;
- surface latest failure and retry guidance;
- refresh status or load result from Cloud.

Not allowed:

- server-side Toolbox run history;
- local scheduler truth;
- local retry processing;
- Core proposal creation without selected review items;
- WordPress writes.

## Trial Record Template

Use this for each staging or real-site trial:

```text
Date:
Site:
Cloud run id:
Cloud trace id:
Entitlement before:
Entitlement after:
Recent endpoint contract:
Recent items:
Terminal status:
Result status:
Patch actions:
Merged priorities:
Core proposal created automatically: no
WordPress write occurred: no
Operator-selected Core handoff tested:
Notes:
```

## 2026-06-18 Local Trial

### Initial Gate

- Cloud runtime containers were running.
- Local WordPress path was available.
- Cloud Addon runtime client exposed `get_recent_nightly_inspection_runs` and
  `retry_run`.
- Toolbox Provider read Cloud recent runs successfully:
  `contract=nightly_site_inspection_recent_runs.v1`, `items=3`,
  `direct_write=no`.
- A later same-day read also succeeded with the same contract and
  `direct_write=no`; recent item count had rotated to `items=0`, so item count
  must be treated as current Cloud retention state, not a fixed acceptance
  expectation.
- Full submit/status/result E2E did not run because Cloud entitlement returned
  `submit_allowed=false`.
- No retry was requested because retry would queue a new Cloud run while
  entitlement was not allowing new submits.

### Restored Pro Test Period

- The local Cloud subscription stayed on `pro_v1`.
- The local test subscription period was advanced through Cloud service-plane
  subscription upsert so Pro quota reset from `used=30`, `remaining=0` to
  `used=0`, `remaining=30`.
- After the reset, Cloud entitlement returned `submit_allowed=true`.
- `composer smoke:nightly-inspection-cloud-e2e` passed.
- Healthy run: `run_250cb69809594e66be7e3a25129c2b5b`.
- Result contract: `cloud_batch_runtime_result.v1`.
- Merge patch contract: `nightly_site_inspection_cloud_batch_merge.v1`.
- Retry from the healthy run created
  `run_fe8d2ac6fc084ebebf949f3e7e75dcc1` and reached `succeeded`.
- Retry stayed review-only: `direct_write=no` and `cloud_scheduler_truth=no`.
- Recent endpoint returned `nightly_site_inspection_recent_runs.v1`, `items=5`,
  and latest run detail.

### Partial-Success Retry Trial

- A Cloud service-plane trial run used one valid Nightly item and one invalid
  item to exercise partial-success handling without writing WordPress data.
- Partial run: `run_a2dc643764be40869937f78f7fcea923`.
- Result status: `partially_succeeded`.
- Worker phase: `partial_result_ready`.
- Failed action IDs: `action_002`.
- Recent endpoint surfaced the same run as `latest_failure`.
- Toolbox retry from the partial run created
  `run_cc01abc1f7b54ae99afb0c79e01122ad` and reached `succeeded`.
- Partial retry stayed review-only: `direct_write=no` and
  `cloud_scheduler_truth=no`.
- Core proposal created automatically: no.
- WordPress write occurred: no.

Next production-prep trial should use the admin panel to verify `Load Cloud
recent`, partial-success copy, `Retry run`, and selected-item Core proposal
handoff from the operator surface.
