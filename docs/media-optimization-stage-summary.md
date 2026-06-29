# Media Optimization Stage Summary

Status: V1 validated and frozen for follow-up planning.

This summary records the accepted shape of the Cloud-backed media optimization
work after the first closed-loop proof. It is meant for future maintainers and
AI agents that need the current product boundary before adding more media
features.

## Stage Decision

Freeze the first media optimization slice as a governed fixed flow. Real
operator validation has passed for one single-image execution plus one selected
batch review set on low-risk local attachments.

The current slice is not a generic workflow runner, a whole-site replacement
tool, a Cloud-controlled media registry, or a second WordPress control plane.
It is a fixed Toolbox button flow over existing local WordPress abilities,
Adapter/Core proposal governance, Cloud Addon transport, and Cloud runtime
derivative processing.

## Closed Loop

The verified loop is:

1. Select or resolve one existing WordPress media attachment.
2. Generate a short-lived Cloud derivative preview.
3. Inspect derivative evidence and read-only adoption preflight.
4. Submit one Core media optimization review.
5. Approve and execute through Adapter/Core.
6. Verify attachment URL, file pointer, MIME type, reviewed ALT or metadata, and
   backup history.
7. Restore through a governed rollback proposal when needed.

This is covered by `composer smoke:media-derivative-core` and by the release
gate in [Media Optimization Release Checklist](media-optimization-release-checklist.md).
The selected batch path is covered separately by
`composer smoke:media-derivative-batch-execute`; it proves selected review-set
execution only, not unattended whole-library automation.
The first real-attachment validation is recorded in
[Media Optimization Operator Trial Results - 2026-06-18](archive/2026-06/media-optimization-operator-trial-results-2026-06-18.md).

## Ownership Boundary

| Area | Owner |
| --- | --- |
| Fixed operator UX, media defaults, one-run overrides, preview display, reviewed metadata fields | Toolbox |
| WordPress proposal truth, approval, preflight, and audit | Core |
| Plan-to-proposal relay, approved execution orchestration, same-origin preview proxy | Adapter |
| Local-to-Cloud signing and transport | Cloud Addon |
| Derivative processing runtime, short-lived artifacts, processing evidence | Cloud |
| WordPress media read/write ability contracts and rollback primitives | Abilities Toolkit |

Cloud returns derivative artifacts and processing evidence only. It does not
own WordPress write decisions, canonical media truth, or approval truth.
Toolbox does not approve, execute, or directly write media files.

## User-Facing Flow

The product should continue to present:

- single image: select image, generate preview, review preflight, submit Core
  optimization review;
- content URL repair: separate governed action for hard-coded post content
  references;
- settings URL repair: separate governed action for theme settings, plugin
  options, and other settings values;
- batch media optimization: bounded review set, selected previews, selected
  Core reviews, and explicit selected replacement execution after proposal
  submission.

Batch work must not be presented as one-click whole-site replacement. Broad
scopes can find candidates, but visible language should keep the operator in a
review-set workflow. The default review set should stay small, with an explicit
upper bound, so the product feels like governed operator work instead of
background automation.

## Why Not One-Click Whole-Site Replacement

WordPress image references are distributed across attachment metadata, post
content, block attributes, theme options, plugin settings, page builders, CDN
caches, and sometimes custom tables. The current system governs known write
paths, not arbitrary site-wide storage.

A one-click whole-site replacement label would imply more coverage and write
authority than the current contracts provide. It would also make rollback,
audit, and operator confidence worse. The safer product promise is controlled
batch optimization: plan, preview, select, submit, approve, execute, audit, and
roll back.

## Release Gate

Before shipping any media optimization change, run:

```bash
php tests/run.php
composer smoke:media-derivative-core
composer smoke:media-derivative-batch-execute
```

The release checklist must pass the six closed-loop criteria: preview,
preflight, proposal, execution, evidence, and rollback.

## Current Closeout

`media_optimization_v1` should now be treated as done for this stage. Do not add
more replacement mechanics, background execution, or broader media mutation to
this V1. Future work should maintain the existing contract and fix defects only.

## Next Stage

Do not add video transcoding, global search-replace, complex media registry
features, unattended whole-library replacement, or more image operations by
default. The
[Media Optimization Operator Trial](archive/2026-06/media-optimization-operator-trial.md) has
accepted the fixed flow for the current low-risk scope:

- Do users know to generate preview before submitting Core review?
- Do they understand attachment adoption versus content URL repair versus
  settings URL repair?
- Do they trust the rollback story?
- Do batch controls feel like selected review sets rather than whole-site
  replacement?

The next batch capability is media ALT/caption review sets. Keep it as bounded
suggestion/planning output or Core handoff evidence; it must not become a
direct media metadata writer. Taxonomy/tag review sets remain second, and
internal-link review sets remain third. Do not add old-article source coverage
as a local Toolbox batch surface; site-wide or multi-article source coverage
belongs in Nightly Inspection Cloud Batch result detail and reviewed Core
handoff.
