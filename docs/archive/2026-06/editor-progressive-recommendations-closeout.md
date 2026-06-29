# Editor Progressive Recommendations Closeout

Status: accepted local slice after the 2026-06-15 implementation and smoke
checks.

This document summarizes the progressive recommendation work added to the
post-editor Npcink Content Support sidebar. It records the product reason,
implementation boundary, verification evidence, and recommended next move.

## Product Intent

The accepted recommendation was to make editor recommendations progressive
instead of one large AI request. The first 2-3 seconds should give the operator a
small set of useful, high-confidence local suggestions, not a complete research
report.

The implemented pattern is:

```text
Editor opens or draft stabilizes
-> local-only progressive prefetch
-> compact recommendation set in the sidebar
-> operator reviews local suggestions or runs a focused tool
-> slower Cloud, image-source, Site Knowledge, and Core handoff actions remain explicit
```

## Implemented Behavior

The editor Content Support route now accepts `progressive_recommendations`.

That intent returns:

- `editor_progressive_recommendations.v1`;
- an additive `editor_recommendation_set.v1` wrapper;
- a stable `content_fingerprint`;
- local taxonomy candidates when the draft gives meaningful evidence;
- bounded recent media-library review items;
- local publish preflight checks and preflight review candidates;
- no Cloud calls, no async queue, no workflow runtime, and no WordPress writes.

The editor sidebar now:

- prefetches the progressive result after the sidebar has editor context;
- applies a 2.5 second timeout fallback;
- keeps successful local suggestions hidden by default behind a compact `Local
  suggestions` entry;
- automatically expands only for warning, error, or empty-context states;
- opens a `progressive recommendations` review view without running a new Cloud
  request.

## Quality Rules

The progressive layer is intentionally conservative:

- the default review list is capped at 8 candidates;
- empty editor context may expose taxonomy as local profile context, but does
  not promote it into high-confidence taxonomy candidates;
- English stopword-only overlap is ignored;
- Chinese title and draft tokens can match existing Chinese taxonomy terms;
- recent media without text overlap is downgraded to
  `operator_review_only_no_write`;
- preflight warnings are review-only candidates and never write actions;
- every write-like final action still belongs to Core proposals, Adapter, or an
  explicit future local-consent contract.

## Boundary

This slice stays inside the Toolbox product boundary.

Toolbox owns:

- fixed editor button UX;
- local progressive recommendation projection;
- candidate rendering and focused follow-up entry points;
- suggestion-only handoff metadata.

Toolbox does not own:

- final WordPress writes;
- approval truth, audit truth, or Core proposal state;
- provider runtime ownership;
- queues, schedulers, leases, retries, or workflow run storage;
- content indexing, re-indexing, stale-index detection, or vector collection
  lifecycle;
- direct media import, SEO mutation, taxonomy assignment, or publish execution.

The slice does not introduce `confirm_token`, `write_confirmed`, a second
approval store, a second workflow registry, or a second ability registry.

## Verification Evidence

The implementation was verified with these gates:

| Gate | Result |
| --- | --- |
| `composer test:all` | Passed. Static contract checks: `1192 passed`; progressive behavior checks passed. |
| `composer smoke:editor-progressive-recommendations` | Passed. Local progressive smoke returned `Progressive candidates=4 elapsed_ms=17`. |
| Browser editor smoke | Passed on `https://npcink.local/wp-admin/post.php?post=19025&action=edit` after administrator login. |
| `git diff --check` | Passed. |

Browser smoke confirmed:

- the `Npcink 内容支持` toolbar button opens the sidebar;
- the automatic progressive request completes without showing a default success
  card;
- the compact `Local suggestions` entry expands the local detail panel;
- `View suggestions` opens the result view;
- result copy does not contain `Matched tokens: .`;
- result copy does not contain `Matched tokens: this`.

Known local editor noise during browser smoke:

- media `19026` returned WordPress REST 404;
- Gutenberg reported existing `core/group` block validation warnings.

Those console errors were present in the sampled local article context and were
not caused by the progressive recommendation slice.

## Changed Files

Primary implementation:

- `includes/Rest_Controller.php`
- `assets/editor-content-support.js`
- `assets/editor-content-support.css`

Verification:

- `tests/progressive-recommendations-behavior.php`
- `tests/smoke-editor-progressive-recommendations.php`
- `tests/run.php`
- `composer.json`

Documentation:

- `README.md`
- `docs/editor-recommendation-logic.md`
- `docs/recommendation-candidate-contract.md`
- `docs/development-workflow.md`

Commit:

- `1aa4cd3 Add progressive editor recommendations`

## Closeout Decision

Stop feature expansion for this slice. The local progressive recommendation
loop is now useful, bounded, and covered by repeatable tests and smoke checks.

The next work should be a separate slice, not more code in this one.

Recommended next slice:

- run a short editorial trial on 1-2 real human-written posts;
- record accept/edit/reject outcomes for local taxonomy, media, and preflight
  recommendations;
- use [Editor Progressive Recommendations Trial](../../editor-progressive-recommendations-trial.md)
  as the trial log and triage boundary;
- only then decide whether to add richer ranking signals or a slow second-stage
  research package.

Do not add a large AI "all recommendations" request until the local progressive
layer proves which recommendation categories operators actually accept.
