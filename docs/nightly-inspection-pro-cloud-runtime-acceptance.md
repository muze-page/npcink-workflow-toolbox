# Nightly Inspection Pro Cloud Runtime Acceptance

Status: accepted for the current internal phase.
Date: 2026-06-16

## Scope

This phase closes the first Pro Cloud Runtime path for Nightly Site
Inspection. The operator can submit the current bounded local snapshot to Cloud
Batch Runtime, let the admin panel perform a short status follow-up, and read
Cloud scoring back into the local Morning Brief preview.

The product shape is Cloud-first, not cloud-only:

- Pro users use Cloud Batch Runtime for commercial execution, quota, queue,
  retry, status, result retention, and usage detail.
- WordPress keeps a disabled-by-default WP-Cron fallback preview for local
  fail-closed visibility.
- Toolbox only bridges local bounded snapshots and read-only Cloud results.
- `npcink-local-automation-runtime` remains the future owner for unattended or
  batch local automation runtime behavior.
- Governance Core remains the only proposal, approval, preflight, and final
  WordPress write path.

## Accepted Behavior

- The Cloud Checks Nightly Inspection panel exposes `Pro Cloud Runtime`
  controls only when Cloud is configured and the local Pro control is enabled.
- `Refresh Cloud quota` reads `pro_cloud_runtime` entitlement detail from Cloud
  and disables new submits when Cloud reports exhausted quota.
- `Run Cloud inspection` is the single primary action. It sends metadata-only
  or excerpt-limited bounded snapshot payloads through the Cloud Addon runtime
  seam.
- After submit, the panel performs a short automatic status follow-up. If the
  Cloud run reaches `succeeded`, the panel automatically reads the result and
  merges Cloud scoring into the local Morning Brief preview.
- If the run is still processing after the short follow-up, the `Recent run`
  entry can refresh status or load the result later. Manual run-ID recovery is
  kept inside `Advanced details`.
- `Load Cloud recent` reads Cloud-owned `nightly_site_inspection_recent_runs.v1`
  cards and can load the latest run ID into the browser-local convenience
  entry. This is display/recovery detail only, not server-side Toolbox run
  history.
- `Retry run` asks Cloud to queue a retry for the selected terminal run with a
  new idempotency key and a new bounded local snapshot. Toolbox shows the new
  Cloud run and does not own retry processing.
- Result merging is review-only. It may add Cloud runtime details and writing
  preparation signals to the Morning Brief, but it must not create Core
  proposals automatically or write WordPress content.
- When Cloud returns a `priority_queue`, Toolbox may expose a bounded,
  sanitized review queue in the merged Morning Brief so the operator can see
  what to review first. This is Cloud result detail only; it is not a local
  queue, worker, lease, retry, or scheduling surface.
- The operator may explicitly select Morning Brief review items and submit one
  blocked Core review proposal through Adapter/Core `from-plan` intake. The
  generated `nightly_site_inspection_review_plan` keeps `proposal_ready=false`
  and requires human title/content input before approval, preflight, or final
  execution can proceed.
- The panel keeps a browser-local `Recent run` entry so an operator can reload
  the last visible run ID, check Cloud status, or read Cloud result without
  creating a server-side run table.
- Raw Cloud payloads and merged Morning Brief payload detail stay collapsed
  under advanced details; the main result surface shows status, merge state,
  quota, and next action only.
- The main result surface shows `Cloud run detail` for read-only run state and
  `Core handoff` for review direction. Merged results use `Review in Core` as
  the next-step entry. Toolbox may submit a proposal only after the operator
  selects specific review items; it does not approve, preflight, execute, or
  write content.
- Partial-success results must show retry guidance and failed-action context
  without turning Toolbox into a retry queue or repair console.
- When quota is exhausted, new runs are disabled while existing run IDs can
  still be refreshed or loaded.

## Closeout Questions

The result surface is accepted only if an operator can answer three questions
without opening raw payloads:

- Did the Cloud inspection finish? `Cloud run detail` and the run summary must
  show run state, worker phase, and whether Cloud is still processing.
- What is most worth reviewing? `Core handoff` must summarize matched local
  priorities, Cloud review items, or the bounded Cloud `priority_queue` before
  showing raw payload details.
- Where should approval and write work happen? The next-step action must be
  `Review in Core`, and the copy must state that Toolbox only creates selected,
  blocked review proposals while Core/Adapter own approval, preflight,
  execution, and final write boundaries.

## Explicit Non-Goals

- No plugin-side Action Scheduler integration for the current Basic or Pro
  path.
- No local job table, lease store, retry processor, dead-letter processor, or
  local queue for Pro Cloud Runtime.
- No Cloud scheduler truth in Toolbox.
- No Cloud-owned WordPress writes.
- No unattended article drafting, publishing, SEO write, media import, or Core
  approval.
- No server-side Toolbox run history for Pro Cloud Runtime. The current recent
  run entry is browser-local convenience only; Cloud remains the run-state
  owner.
- No new JavaScript build chain or Playwright dependency for the current UI
  contract smoke.

## Verification

Default static and bounded smokes:

```bash
composer test:all
composer smoke:nightly-inspection-cloud-ui
```

Real local WordPress plus Cloud worker proof:

```bash
cd /Users/muze/gitee/npcink-ai-cloud
docker compose -f docker-compose.dev.yml --profile runtime up -d worker

cd /Users/muze/gitee/npcink-toolbox
composer smoke:nightly-inspection-cloud-e2e
```

Manual admin surface check:

```text
https://npcink.local/wp-admin/admin.php?page=npcink-toolbox
```

Expected visible result:

- quota summary shows package, used, remaining, run limit, batch limit,
  retention, payload modes, and Cloud role;
- submit creates a visible Cloud run ID;
- `Load Cloud recent` displays Cloud recent run cards and can load a known run
  ID without creating server-side Toolbox history;
- status summary shows run status, worker phase, and merge state;
- partial-success or retryable runs show retry guidance and can request a
  Cloud-owned retry with a fresh idempotency key;
- succeeded runs can show `Merged preview` with local review still required;
- a `Recent run` entry can reload the last browser-local run ID and refresh
  status/result from Cloud;
- low-frequency manual status/result controls are in `Advanced details`;
- merged results expose a `Review in Core` link as the next-step entry, but
- only selected Morning Brief items can create one blocked Core review proposal;
  no Core proposal is created automatically.

## Boundary Check

Local truth stayed intact: schedule enablement, fallback preview, local Morning
Brief, Core proposal handoff, and final writes remain local.

Cloud stayed runtime/detail only: it owns queue-backed processing, entitlement,
quota, status, result retention, and runtime diagnostics.

Forbidden drift prevented: no second scheduler, no Action Scheduler adoption,
no local queue tables, no workflow engine, and no direct WordPress write owner
were added.
