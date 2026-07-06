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

| Attachment | Reasons | Human outcome |
| ---: | --- | --- |
| `7774` | `missing_caption` | `pending` |
| `769` | `weak_alt` | `pending` |
| `767` | `weak_alt`, `filename_like_title` | `pending` |
| `766` | `weak_alt` | `pending` |
| `765` | `weak_alt` | `pending` |

The exported artifact reports:

- `write_posture=eval_only_no_wordpress_write`;
- `provider_backed=false`;
- `source_policy=media_library_metadata_only_no_pixel_vision`;
- `sample_mode=eval_batch_sample_paged_site_helper`;
- every selected candidate requires human visual review and keeps
  `direct_wordpress_write=false`.

## Decision

Do not migrate implementation to `npcink-abilities-toolkit` in this stage.

The fresh batch export is a useful positive signal because it found five
candidate rows after the stricter metadata-only filter. It is not enough to
approve extraction because all five human outcomes are still `pending`, the
positive count is small, and previous cross-site validation showed a weak
metadata library can correctly produce zero selected candidates.

The next work should validate these five selected candidates before moving any
code:

1. Run human visual review on the five pending rows.
2. If human review is clean, optionally run a small
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
