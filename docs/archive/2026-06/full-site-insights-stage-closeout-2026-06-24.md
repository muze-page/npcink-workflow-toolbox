# Full-site Insights Stage Closeout - 2026-06-24

Status: local stage closeout for the Full-site Insights product loop.

This document records the recent Full-site Insights discussion and implementation
decisions. The main product concern was not whether Toolbox can show more data;
it was whether a normal WordPress site owner or operator can understand what is
wrong, what matters first, and what safe follow-up path exists.

## Product Positioning

Full-site Insights is not only an operations insight page. It is the WordPress
site-level data analysis surface for Toolbox:

- whole-site data analysis from bounded current WordPress evidence;
- AI-assisted summary and semantic prioritization when Cloud is explicitly run;
- a plain priority queue for site issues;
- evidence that explains why each issue exists;
- a treatment path that separates manual review from Core-governed handoff.

The user-facing promise should stay simple: scan the current site, show the few
issues that matter first, explain why they matter, and tell the operator whether
to handle them manually or move them into a governed review path.

## Current Analysis Scope

The local `site_ops_insight_pack.v1` is built from limited current-site data:

- public posts and pages;
- approved comment signals, not private raw comment detail;
- media metadata, including missing or weak ALT coverage;
- taxonomy shape, including low-use or confusing term structure;
- Site Context readiness;
- Cloud readiness.

The report currently supports Overview, Content, Media, Comments, Structure,
Findings, Evidence, Cloud, and Advanced tabs. It also prepares
`site_ops_cloud_analysis_request.v1`; Cloud can return
`site_ops_cloud_analysis_result.v1` with executive summary, dimension summaries,
semantic ranked findings, trend explanations, and analysis closure detail.

## Problems This Stage Addressed

The main usability risk was "a pile of data". Operators should not have to read
raw charts, JSON, evidence lists, and Cloud fields before knowing what to do.

Recent product decisions therefore changed the default hierarchy:

1. show the action brief first;
2. show the priority decision queue next;
3. show the allowed treatment path for each issue;
4. fold scan scope, charts, coverage detail, JSON, and handoff drafts behind
   disclosures or secondary tabs;
5. keep technical payload names out of ordinary Chinese UI copy.

This makes the first screen answer four operator questions:

- What is the top problem?
- Why does it matter?
- Which examples prove it?
- What is the safe next step?

## Closed Loop Now Supported

The current loop is intentionally lightweight:

```text
Scan local data
-> Read the priority queue
-> Add Cloud detail only when useful
-> Choose manual review or Core-governed follow-up
```

For simple issues, the report can point to manual review. For write-like issues,
the report may show review candidates and evidence, but real changes still need
the governed Core/Abilities path.

This is enough for a basic operator loop because the report now moves from
evidence to decision. It is not a complete automation loop because it does not
create proposals, schedule work, write WordPress data, or persist historical
runs.

## Recent Implementation Notes

The recent UI and contract work included:

- folded scan scope, local coverage summary, and current-run charts by default;
- kept Overview focused on action brief, priority queue, treatment paths, and
  folded supporting detail;
- added clearer treatment path wording for manual review, review workflow
  candidates, blocked items, and watch-only items;
- added folded Core handoff candidate previews for review-workflow candidates;
- kept handoff previews as draft-only, with candidate objects, evidence to
  carry forward, and suggested review notes;
- added Chinese translations for the new operator-facing copy;
- updated static contracts and ran browser smoke checks against
  `https://magick-ai.local/`.

The handoff preview is deliberately not a proposal creator. It helps the
operator understand what would be carried into review, but it does not create a
Core proposal, queue work, approve changes, or write WordPress data.

## Boundary Decisions

The boundaries remain unchanged:

- Toolbox owns the local WordPress operator surface, bounded local reads,
  current snapshot, report UI, evidence display, and suggestion/handoff copy.
- Toolbox must not own heavy analysis, durable history, queueing, scheduling,
  automatic proposals, approval records, audit truth, or final WordPress writes.
- Cloud owns runtime/detail only: summaries, semantic ranking, trend
  explanation, dimension summary, confidence, blockers, and next-action detail.
- Cloud must not become a second WordPress control plane, workflow registry,
  approval store, or write owner.
- Core and Abilities remain the truth for proposal, approval, audit, preflight,
  and final WordPress writes.

Any future change that edits posts, pages, media, SEO metadata, taxonomy, or
site content must leave this report and enter a Core-governed handoff path.

## Current Stop Point

The current stage should stop adding more analysis surfaces by default.

Do not add local historical trend charts, automatic Cloud runs, batch proposal
creation, local run persistence, more dashboards, or more raw dimensions until
real-site validation proves the operator cannot complete the loop without them.

The next product work should be usage validation, not feature expansion.

## Next Validation Checklist

Use the current development site, `https://magick-ai.local/`, and ask whether a
normal site owner can answer these within about 30 seconds:

- What is the first issue I should look at?
- Which post, page, media item, comment signal, or term is affected?
- Why is this issue important for readers, search, or operations?
- Is the next step manual review, a governed review workflow, or watch-only?
- Does the screen make it clear that Toolbox will not change WordPress
  automatically?

If the answer is no, improve copy, ordering, folding, labels, and examples
before adding new analysis capability.

## Deferred Work

These remain intentionally deferred:

- automatic Core proposal creation from the report;
- automatic WordPress writes;
- local historical trend storage;
- queue-backed or scheduled analysis;
- automatic Cloud analysis;
- extra charts that do not change the operator's next decision;
- batch selection and submission flows;
- Cloud becoming an approval or workflow owner.

The only acceptable next feature growth is a small, validated improvement that
helps the operator understand the priority queue or choose the correct governed
follow-up path.

## Verification Record

Recent verification for this stage included PHP syntax checks, Full-site
Insights static contract tests, browser smoke checks on the local WordPress
site, and the repository default `composer test:all` gate.

At the time this closeout was written, recent relevant commits included:

- `b6c7ca2` - Fold site ops scan detail by default.
- `6ca30e2` - Add site ops handoff candidate preview.
- `f3c5df0` - Wire editor audio adoption through Core.

The audio adoption commit is related worktree cleanup and gate restoration, not
part of the Full-site Insights product boundary.
