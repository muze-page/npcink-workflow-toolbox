# Media Optimization Operator Trial

Status: validation protocol before expanding media batch capabilities.

Use this trial after the media optimization release gates pass. The goal is to
learn whether real operators understand the fixed flow and trust the rollback
story before Toolbox adds another media or batch feature.

Latest local validation:
[2026-06-18 trial results](media-optimization-operator-trial-results-2026-06-18.md).

## Scope

Run the trial against 5 to 10 real but low-risk media attachments on a local or
staging WordPress site. Prefer a mix of old JPEGs, oversized PNGs, images that
are used in post content, and images that are not referenced by public posts.

This trial validates the current `media_optimization_v1` surface only:

```text
select or resolve attachment
-> generate Cloud derivative preview
-> inspect adoption preflight
-> submit Core optimization review
-> approve and execute through Adapter/Core/Abilities
-> verify evidence
-> restore from backup when needed
```

For selected batch work, keep the review set small. The current default is 5
items and the UI cap is 10 items.

## Required Gates

Run these before starting the operator trial:

```bash
composer test:all
composer smoke:media-derivative-core
composer smoke:media-derivative-batch-execute
```

Do not run the trial if either smoke fails, if Core proposals cannot be created,
if Adapter `approve-and-execute` is unavailable, or if governed restore cannot
restore a smoke attachment.

The smoke scripts are release gates, not the real-attachment trial runner. They
create temporary fixture attachments and clean them up. Do not edit those scripts
to sweep the media library or accept arbitrary production attachment IDs.

## Candidate Packet

Before executing any real attachment, prepare a read-only candidate packet for
operator review. The packet should include attachment id, MIME type, date,
filename, title, whether the file is referenced by published post/page content,
and the proposed action.

Use a read-only WP-CLI query or equivalent admin report. Prefer candidates with
`public_content_refs=0` for the first pass, then add one or two referenced
images only after the operator has seen rollback evidence on media-only items.

Do not start with generated test fixtures, site-logo assets, watermark logos,
brand-critical hero images, or files with unknown ownership. Do not run a
whole-library scan-and-replace. The operator must explicitly choose the 5 to 10
trial attachments before preview generation or Core proposal submission.

## Trial Record

Copy one row per attempted item or selected batch candidate.

| Item | Attachment id | Source use | Action | Preview ok | Preflight clear | Proposal id | Executed | Restored | Blocked or failed reason | Operator note |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 |  | post content / media only / unknown | single / batch | yes / no | yes / no |  | yes / no | yes / no / n/a |  |  |

Track aggregate counts at the end:

- sampled_count:
- previewed_count:
- submitted_count:
- executed_count:
- restored_count:
- blocked_count:
- failed_count:
- partial_success_count:

## Operator Questions

Ask these after the trial:

- Did the operator understand that preview generation is not a WordPress write?
- Did the operator understand that Core review comes before execution?
- Did the operator understand that attachment adoption, post content URL repair,
  and settings URL repair are separate governed actions?
- Did the batch controls feel like a selected review set instead of whole-site
  replacement?
- Did the result evidence make rollback and audit clear enough?
- Were any errors actionable without reading raw JSON?

## Pass Criteria

Treat the surface as accepted only when:

- all required gates pass before the trial;
- operators can complete at least one single-image execution and restore;
- operators can complete one selected batch execution with no more than 10
  candidates;
- every blocked item has a visible reason or next action;
- any partial failure keeps completed Adapter/Core execution evidence and leads
  to a revised proposal only for unresolved items;
- no operator expects Toolbox or Cloud to directly write media outside
  Core/Adapter/Abilities governance.

## Expansion Hold

Do not add video transcoding, global search-replace, a media registry,
unattended whole-library replacement, or another batch write surface during this
trial. Those need a separate boundary decision and release gate.

The next batch candidates are intentionally lower risk:

1. media ALT and caption review set;
2. taxonomy and tag review set;
3. internal-link review set.

Do not add old-article source coverage as a local Toolbox batch surface.
Site-wide or multi-article source coverage belongs in Nightly Inspection Cloud
Batch result detail and reviewed Core handoff.
