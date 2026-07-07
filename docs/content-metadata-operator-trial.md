# Content Metadata Delta Operator Trial

Status: P0 trial contract.

Owner: Toolbox operator surface.

Core remains the proposal, approval, and audit owner. Toolkit remains the
reusable WordPress ability owner. This trial does not make Toolbox a content
product, approval store, workflow runtime, queue, or WordPress write executor.

## Purpose

The next stage should prove one real product loop:

1. a real post supplies current content, excerpt, category, and tag signals;
2. Toolbox returns a reviewable `content_metadata_delta` for excerpt and
   existing taxonomy terms;
3. an operator records accept, edit, reject, or not-applicable decisions;
4. accepted values can build a dry-run `content_metadata_apply_plan` for Core
   proposal review;
5. the trial records unchanged WordPress readback and learning fields for
   future prompt or UI adjustment.

The goal is learning whether the loop is useful and governable, not adding a
general automation platform.

## P0 Scope

Included:

- 3 to 5 real posts, reviewed as separate single-post cases;
- excerpt suggestions;
- existing category suggestions;
- existing tag suggestions;
- dry-run apply-plan evidence for Core handoff readiness;
- local JSON and Markdown worksheets under `build/eval/`;
- optional eval-lab review of exported evidence.

Excluded:

- creating taxonomy terms;
- assigning terms directly from Toolbox;
- updating excerpts directly from Toolbox;
- auto-publishing;
- background queues, schedulers, workers, or workflow builders;
- Core final execution;
- Cloud writes to WordPress;
- batch product UX.

The export may contain several trial cases for review convenience. That export
is not a product batch workflow and does not authorize bulk execution.

## Runbook

Default local sample:

```bash
composer smoke:metadata-operator-trial
composer eval:content-metadata:export
```

Explicit post sample:

```bash
CONTENT_METADATA_TRIAL_POST_IDS="123,124,125" \
NPCINK_TOOLBOX_METADATA_TRIAL_MAX_POSTS=3 \
CONTENT_METADATA_CASES="build/eval/content-metadata-delta-cases.json" \
CONTENT_METADATA_CASES_MD="build/eval/content-metadata-delta-cases.md" \
composer eval:content-metadata:export
```

The smoke dispatches the existing
`/wp-json/npcink-toolbox/v1/editor/content-support` route with
`summary_terms_optimization`, extracts `content_metadata_delta`, then calls
`/wp-json/npcink-toolbox/v1/flows/content-metadata-apply-plan` with reviewed
existing-term values. It asserts suggestion-only posture, Core proposal-required
authorization, dry-run apply plans, no proposal creation, no execution, and
unchanged post snapshots.

## Operator Worksheet

The Markdown worksheet asks a human to record one outcome per field:

- `accepted`;
- `edited`;
- `rejected`;
- `not_applicable`.

The JSON artifact uses `content_metadata_delta_operator_trial.v1` and includes:

- Issue Record;
- Diagnosis;
- Delta;
- Review Decision;
- Governance Evidence;
- Outcome Contract;
- Learning Entry.

`npcink-eval-lab` may review the exported JSON as AI-assisted development
evidence. Eval-lab feedback never authorizes a WordPress write and should not be
copied into Core as audit truth.

## Completion Target

The P0 trial is complete when:

- at least 3 real posts have reviewed outcomes;
- every accepted write-like value has a dry-run
  `content_metadata_apply_plan`;
- no new taxonomy term is created or proposed as executable work;
- every sampled post has unchanged readback before and after the trial;
- the team can answer whether the current suggestions are useful enough to
  keep, edit, or defer.

Only after this evidence should we decide whether to expand the loop to more
fields, more posts, or durable learning records.
