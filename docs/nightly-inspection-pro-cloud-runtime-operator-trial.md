# Nightly Inspection Pro Cloud Runtime Operator Trial

Status: accepted for current internal phase.
Date: 2026-06-16

## Scope

This record captures a real local WordPress plus Cloud Runtime operator trial
for the Pro Nightly Site Inspection surface. It is an operator evidence note,
not a new feature plan.

The trial verifies the current closeout questions:

- Did the Cloud inspection finish?
- What is most worth reviewing?
- Where should approval and write work happen?

## Environment

- Toolbox admin surface: local WordPress admin, `npcink-toolbox`.
- Cloud stack: local Cloud dev stack with API, worker, callback worker,
  ops worker, Postgres, Redis, proxy, and frontend running.
- Entry path: `Run Cloud inspection` from the Toolbox Morning Brief panel.
- Payload mode: `metadata_only`.
- Write boundary: review-only result merge; no Core proposal creation and no
  WordPress write.

## Quota Evidence

Before trial:

- Package: Pro.
- Used: 5.
- Remaining: 25.
- Period limit: 30.
- Submit allowed: true.

After two real runs:

- Used: 7.
- Remaining: 23.
- Period limit: 30.
- Submit allowed: true.

Quota movement matched the two real Cloud inspection submissions.

## Run Evidence

### Run 1

- Run ID: `run_eaefb8aaa35c43e49597863e22d18f36`.
- Result title: `Cloud inspection result`.
- Status: `Succeeded`.
- Worker phase: `Terminal`.
- Patch actions: 20.
- Merged priorities: 15.
- Visible result sections:
  - `Cloud run detail`
  - `Cloud review details`
  - `Core handoff`
- Next step: `Review in Core`.
- Boundary copy confirmed: Toolbox prepares review context only and does not
  create proposals or write content.

### Run 2

- Run ID: `run_be4af1a5435349a49d8e3147dd4d7e66`.
- Result title: `Cloud inspection result`.
- Status: `Succeeded`.
- Worker phase: `Terminal`.
- Patch actions: 20.
- Merged priorities: 15.
- Visible result sections:
  - `Cloud run detail`
  - `Cloud review details`
  - `Core handoff`
- Next step: `Review in Core`.
- Boundary copy confirmed: final approval and WordPress writes stay in Core.

## Findings

- The default surface stayed light: `Run Cloud inspection` and
  `Refresh Cloud quota` remained the only primary row actions.
- Low-frequency status/result controls stayed inside `Advanced details`.
- `Recent run` updated after each run and exposed `Use run`, `Refresh status`,
  and `Load result`.
- Both real runs answered the three closeout questions without opening raw
  payloads.
- Browser console reported no warnings or errors during the operator trial.

## Small Fix Applied

The result meta previously showed zero snapshot items alongside nonzero review
actions. That could make the result look inconsistent. The UI now omits zero
snapshot item counts from the main result and Cloud run detail summaries while
still showing `Patch actions` and `Merged priorities`.

## Non-Goals Reconfirmed

- No plugin-side Action Scheduler.
- No local run table or server-side Toolbox run history.
- No local queue, lease, retry, or dead-letter processor.
- No automatic Core proposal creation.
- No WordPress content, SEO, or media write.
- No Cloud-owned WordPress write path.
