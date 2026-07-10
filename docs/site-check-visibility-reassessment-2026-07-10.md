# Site Check Visibility Reassessment - 2026-07-10

Status: accepted visibility decision; product re-entry is pending a new
operator-loop review.

## Decision

Hide **Site Check** from the Toolbox Overview and the visible top-level admin
tabs. Keep the stable, capability-gated `operations-insights` deep link and its
existing read-only report for compatibility and future product review.

This decision supersedes earlier documents only where they describe Site Check
as the default site-maintenance entry. It does not supersede the read-only scan,
privacy, Cloud, Core, approval, or WordPress-write boundaries documented there.

## History

The initial Site Check decision treated the surface as a site-level decision
router: turn bounded public-site evidence into a ranked review list, show the
first safe action, then route the operator to manual handling, an existing
workflow, or optional Cloud detail. The 2026-06-30 decision closeout recorded
that direction and deliberately rejected a local runtime, queue, task system,
approval store, proposal creator, and WordPress write path.

The subsequent implementation work improved the report without widening that
boundary:

- `eb76e9a` made Content analysis more compact and scannable so severity,
  finding, fact summary, handling label, next step, and first-action button are
  adjacent.
- `e87ebba` added a bounded rescan acceptance check. It tells an operator how
  to verify that a current finding no longer matches, without creating a task,
  completion record, or cross-run history.
- `1bf225c` removed the default Overview promotion and top-level tab while
  retaining the compatibility route.

These changes made the existing report easier to read, but they did not answer
the more important product question: a new operator still cannot reliably say
which concrete business problem the surface solves, what to do first, and what
counts as a successful outcome across the report.

## Why Visibility Is Removed Now

The problem is not a missing metric, a missing Cloud capability, or insufficient
card density. It is an incomplete operator loop:

1. The entry promise is too broad: "check my site" does not identify a
   specific operating moment or decision.
2. Findings can describe evidence and safe next steps, but the product has not
   selected a small, repeatable set of operator outcomes that make the report
   worth opening.
3. A local rescan can verify a bounded finding, but it cannot by itself prove
   that the operator solved the intended site-level problem.
4. Promoting an unclear report as the default entry would make the Toolbox look
   like a dashboard rather than a focused operator tool.

Hiding the default entry is therefore a product-scope correction, not a claim
that the scan is unsafe or technically invalid.

## Preserved Contract

The compatibility route continues to use the existing bounded current-site
snapshot and report. This decision does **not** change:

- scan scope or business facts: public posts/pages, approved-comment signals,
  media metadata, taxonomy summaries, Site Context readiness, and Cloud
  readiness remain the existing bounded inputs;
- permissions, data attributes, report URLs, or existing manual-review links;
- the meaning of manual review, review-workflow candidates, or human-only
  checks;
- the optional, administrator-triggered Cloud-detail boundary; or
- the prohibition on direct WordPress writes.

It adds no Cloud request, Core proposal, execute request, queue, persistent
diagnostic history, ability registry, workflow registry, approval store, or
WordPress write.

## Re-entry Criteria

Do not restore Site Check to the default UI until a real operator workflow can
answer all of the following before implementation resumes:

1. **Who opens it, and when?** Define one primary operator and a concrete
   trigger, rather than a generic need to inspect a site.
2. **What limited problems does it solve?** Choose a small set of repeatable
   issue classes and state why each matters to that operator.
3. **What is the allowed next action for each problem?** Every default finding
   must map to one manual action, one existing review workflow, or an explicit
   "observe" decision. It must not imply a new local task runner or write path.
4. **How is success observed?** Define a visible acceptance signal for each
   outcome. A local rescan is allowed only as evidence for a bounded finding;
   durable completion or history needs an owner outside this Toolbox surface.
5. **What operator evidence supports restoration?** Test the proposed loop with
   real operators and confirm they can state the problem, next action, owner,
   and acceptance signal in one pass without interpreting raw metrics.

The smallest acceptable re-entry artifact is a short problem-to-action-to-
acceptance matrix, followed by a narrow prototype or existing-path trial. Only
then should the compatibility panel be considered for default visibility again.

## Development Guardrails

While the panel is hidden, future work may maintain compatibility, correct a
bug, or improve a proven operator step. It must not use the hidden state as a
reason to add speculative scope such as historical analytics, automatic
rescans, task assignment, batch execution, local run storage, Cloud-run
recovery, proposal creation, or direct WordPress mutation.

If a future proposal needs durable tasks, cross-run acceptance, or execution,
write a separate cross-project contract first. Toolbox remains the
operator-facing, suggestion-only surface; Cloud remains runtime/detail and
Core remains the governance truth.

## Verification Record

The visibility change was verified with:

- `composer validate --no-check-publish`;
- `composer test:all`; and
- a real local wp-admin `composer smoke:site-ops-insights-browser` run with the
  bundled Playwright runtime loaded.

The browser smoke confirms that Site Check is absent from normal top-level
navigation, the compatibility deep link still renders, and the report path does
not call Cloud detail, Core proposal, or execute routes automatically.
