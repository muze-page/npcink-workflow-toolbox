# Media Optimization Release Checklist

Status: release gate for the fixed `media_optimization_v1` flow.

Run this checklist before shipping media optimization changes that touch
Toolbox, Adapter, Cloud Addon, Cloud runtime, Core proposal handoff, or the
Abilities media write contracts.

## Required Smoke

Run:

```bash
composer smoke:media-derivative-core
composer smoke:media-derivative-batch-execute
```

The single-image smoke must pass end to end on a local WordPress site with
Core, Adapter, Cloud Addon, Cloud runtime, Toolbox, and Abilities active. It
creates a temporary image attachment, generates a Cloud derivative, submits a
governed Core proposal, executes it, verifies the result, restores the backup,
and cleans test fixtures.

The selected-batch execution smoke must also pass before the fixed batch
replacement button is treated as release-ready. It creates two temporary JPEG
attachments, builds a selected review plan, generates selected Cloud derivative
artifacts, creates selected Core media optimization proposals, calls Adapter
`approve-and-execute`, verifies execution evidence, and restores the attachments
through governed rollback proposals.

## Pass Criteria

The release is not ready unless all six checks pass:

1. Preview: Toolbox can create a short-lived Cloud derivative preview artifact
   for an existing media attachment.
2. Preflight: the media adoption preflight summary is read-only, creates no
   Core proposal, performs no WordPress write, and marks the reviewed artifact
   Core-proposal ready.
3. Proposal: Adapter/Core create one media optimization proposal from the
   reviewed plan instead of splitting the same user intent into separate
   metadata and derivative proposals.
4. Execution: Adapter `approve-and-execute` applies the Core-approved proposal
   through allowlisted WordPress abilities.
5. Evidence: the executed proposal changes the attachment URL/file pointer,
   changes the attachment MIME to the derivative MIME, applies reviewed ALT or
   metadata, and records backup history.
6. Rollback: a governed restore proposal can restore the original backup, and
   the attachment file pointer and MIME return to their original values.

## Manual UI Check

After the smoke passes, open:

```text
Npcink AI -> Toolbox -> Image Handling -> Batch Optimize Images
```

Confirm the operator-facing path is still understandable:

- single-image flow says generate preview first, then submit Core optimization
  review only after inspecting the derivative and adoption preflight;
- replacement boundary explains that attachment adoption, post content URL
  repair, and settings URL repair are separate governed actions;
- batch flow says bounded review set, selected previews, and selected Core
  reviews, not one-click whole-site replacement;
- batch flow defaults to a small review set and does not ask operators to run
  unattended whole-library replacement;
- the generated preview image loads through the same-origin signed preview
  proxy;
- no button or success state implies Toolbox or Cloud directly writes
  WordPress media.

Before expanding the media surface, complete the
[Media Optimization Operator Trial](archive/2026-06/media-optimization-operator-trial.md) on a
small set of real low-risk attachments. Keep selected batch trials at or below
the visible UI cap of 10 candidates.

## Failure Handling

If either required smoke fails:

- do not ship the media optimization change;
- check whether Cloud run result polling is still pending before treating a
  transient `409` as a hard failure;
- inspect Adapter/Core response bodies for proposal, preflight, execution, or
  restore errors;
- for selected-batch partial failures, keep completed Adapter/Core execution
  evidence, then create a revised proposal only for unresolved items;
- keep temporary test attachments and Core proposal fixtures cleaned up before
  rerunning the smoke.

## Non-Goals

This checklist does not approve broader media features such as video
transcoding, global search-replace, CDN cache invalidation, or arbitrary
third-party option repair. Those need separate contracts and release gates.
