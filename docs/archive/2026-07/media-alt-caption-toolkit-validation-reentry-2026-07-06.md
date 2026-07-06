# Media ALT/Caption Toolkit Validation Reentry - 2026-07-06

Status: validation reopened; no Toolkit migration yet.

## Purpose

This record reopens the `media_alt_caption_review_set.v1` Toolkit extraction
question after the Cloud Addon transport release gate was merged. It records
fresh real-media evidence from the current Toolbox branch before any code is
moved to `npcink-abilities-toolkit`.

This is not a migration approval. The current implementation remains a
Toolbox-owned operator review surface until the validation plan's acceptance
criteria are met across more real samples and human review.

## Baseline

| Field | Value |
| --- | --- |
| Branch | `codex/media-alt-caption-validation-gate` |
| Baseline commit | `b8944c4` |
| Baseline source | PR #55 merge, Cloud Addon transport product acceptance |
| Validation plan | `docs/media-alt-caption-toolkit-validation-plan.md` |
| Review artifact | `media_alt_caption_review_set.v1` |
| Trial artifact | `media_alt_caption_operator_trial.v1` |

## Commands

```bash
composer smoke:media-alt-caption-trial
composer eval:media-alt-caption:export-batch
```

The commands ran against the configured local WordPress site through WP-CLI.
Both commands used the existing `/ai/site-helpers` path with a local host
filter. They did not require Cloud runtime availability.

## Boundary Checked

- real local image attachments only;
- metadata-only source policy, no image-pixel inspection;
- local host filter instead of Cloud runtime;
- `suggestion_only`;
- no direct WordPress media metadata write;
- no Core proposal creation;
- no execution creation;
- no media derivative run;
- no local queue, scheduler, or runtime ownership;
- attachment metadata snapshots remain unchanged.

## Narrow Smoke Result

Command result: pass.

| Metric | Result |
| --- | ---: |
| Scanned image attachments | 10 |
| Eligible items | 0 |
| Selected items | 0 |
| Blocked items | 10 |
| Max items | 10 |
| Blocked as candidate-quality insufficient | 10 |

Interpretation:

- The default sample remains conservative.
- The local candidate-quality filter correctly blocks metadata-only cases that
  do not have enough descriptive signal.
- The smoke proves the no-write and no-proposal posture, but by itself does
  not produce positive migration evidence.

## Batch Export Result

Command result: pass.

The batch exporter wrote ignored local evidence under `build/eval/`:

- `build/eval/media-alt-caption-batch-cases.json`
- `build/eval/media-alt-caption-batch-cases.md`

| Metric | Result |
| --- | ---: |
| Attachments sampled | 50 |
| Pages | 5 |
| Page size | 10 |
| Scanned image attachments | 50 |
| Eligible items | 5 |
| Selected items | 5 |
| Blocked items | 45 |
| Max items per request | 10 |
| Product route cap | 10 |

Reason counts:

| Reason | Count |
| --- | ---: |
| `blocked_candidate_quality_insufficient` | 30 |
| `blocked_metadata_complete_for_p0` | 15 |
| `weak_alt` | 4 |
| `missing_caption` | 1 |
| `filename_like_title` | 1 |

Selected candidates:

| Attachment | Reasons | Visual review outcome |
| ---: | --- | --- |
| `7774` | `missing_caption` | `accepted_with_context_caveat` |
| `769` | `weak_alt` | `needs_edit_location_context` |
| `767` | `weak_alt`, `filename_like_title` | `accepted_with_context_caveat` |
| `766` | `weak_alt` | `needs_edit_location_context` |
| `765` | `weak_alt` | `needs_edit_location_context` |

The exported artifact reports:

- `write_posture=eval_only_no_wordpress_write`;
- `provider_backed=false`;
- `source_policy=media_library_metadata_only_no_pixel_vision`;
- `sample_mode=eval_batch_sample_paged_site_helper`;
- every selected candidate requires human visual review and keeps
  `direct_wordpress_write=false`.

## Visual Review Result

Visual review in this Codex session inspected the five selected local upload
files. This is validation evidence, not operator acceptance and not write
authorization.

| Metric | Result |
| --- | ---: |
| Candidates reviewed | 5 |
| Accepted with context caveat | 2 |
| Needs edit or location-context confirmation | 3 |
| Rejected | 0 |
| Misleading | 0 |

Attachment notes:

- `7774`: the caption matches the abstract card/checkmark approval-workflow
  visual, but "WordPress AI workflow" comes from existing site context rather
  than pixels alone.
- `769`: the image shows a sunset rocky beach with an arch formation; the
  proposed location text `Jericoacoara Ceara Brasil` needs operator context
  confirmation or a more visual ALT.
- `767`: the image shows a windmill in fog over a farm or rural field; the
  location phrase "Walker, Iowa" needs context confirmation, but the visual
  description is materially stronger than the current `Windmill` ALT.
- `766`: the image shows a rocky coast, beach, waterfall, and ocean; `Big Sur,
  CA` needs context confirmation or a more visual ALT.
- `765`: the image shows clear sea water with rocks and distant coastline;
  `Plimmerton, New Zealand` needs context confirmation or a more visual ALT.

## Implementation Follow-Up

The validation finding above produced one local implementation change before
any Toolkit migration:

- selected candidates now carry `candidate_fact_types`,
  `candidate_confidence`, `candidate_review_status`, and
  `needs_context_confirmation`;
- title/filename-derived location or proper-name phrases are marked as
  `context_only` and require operator confirmation;
- the admin UI does not select those rows by default and exposes an explicit
  context confirmation checkbox;
- the handoff planner blocks unconfirmed rows as
  `context_confirmation_required` before building any Core proposal payload;
- smoke and batch eval exports include the same fields for follow-up review.

This is a quality gate inside the existing Toolbox review surface. It is not
Toolkit migration approval and does not add a direct media write path.

## Decision

Do not migrate implementation to `npcink-abilities-toolkit` in this stage.

The fresh batch export is a useful positive signal because it found five
candidate rows after the stricter metadata-only filter. It is not enough to
approve extraction because the positive count is small, three rows need edit or
location-context confirmation, and previous cross-site validation showed a weak
metadata library can correctly produce zero selected candidates.

The next work should validate operator acceptance before moving any code:

1. Re-run the batch export and verify that location-bearing ALT candidates are
   marked `needs_context_confirmation` instead of appearing as ordinary ready
   rows.
2. Optionally run a small
   `composer eval:media-alt-caption:judge-cross-batch` window against the same
   exported input to compare reviewer consistency.
3. Repeat on at least one more real media library or a deliberate sample window
   before approving a Toolkit extraction branch.

## Migration Gate

Toolkit extraction remains blocked until the evidence proves all of these:

- selected candidates are repeatedly useful after human visual review;
- blocked reasons remain explainable instead of hiding useful work;
- the reusable part can accept supplied media metadata and return the same
  `media_alt_caption_review_set.v1` artifact without Toolbox UI state;
- the contract remains review-only and testable without Cloud runtime;
- no proposal, execution, queue, scheduler, media derivative run, or direct
  media metadata write is introduced.

If that gate passes, extract only the artifact normalizer or planner helper to
`npcink-abilities-toolkit`. Toolbox must continue to own the operator UI,
selection state, review affordances, and any later Core handoff presentation.
