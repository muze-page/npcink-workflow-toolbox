# Site Check Operator Loop

Status: active product loop for the current Toolbox stage.

Site Check is the site-level fixed-workflow decision router for WordPress
operators. Its job is to turn bounded current-site evidence into a prioritized
review queue, then help the operator decide whether each item stays manual,
moves into an existing Toolbox workflow, needs optional Cloud detail, or should
later move into a Core-governed handoff path.

It is also the ordinary site-maintenance starting point. Low-frequency
scheduled review and Nightly/Morning Brief preview remain available as a folded
Scheduled Review path. Cloud run recovery is linked from that path to Cloud
Addon Runtime Runs, but operators should not need to understand that runtime
path before using Site Check.

## Purpose

The current purpose is not "operations only", not "article analysis only", and
not a general analytics dashboard. It is a manual site check across the data
Toolbox can safely read in WordPress:

- public posts and pages;
- approved comment signal counts, without raw private comment detail;
- media metadata and referenced image gaps;
- taxonomy shape and low-use terms;
- Site Context readiness;
- Cloud readiness and optional Cloud runtime/detail output.

The intended effect is a reviewable site-check snapshot. An operator should see
what matters first, why it matters, what evidence supports it, and which fixed
workflow or manual path is allowed.

## Operator Loop

1. **Scan local data.** Toolbox builds one current
   `site_ops_insight_pack.v1` from bounded WordPress data. This step is local,
   synchronous, and read-only.
2. **Pick the next fixed workflow.** The Overview starts with a plain-language
   action brief, the first few issues, direct first-action links, and a
   treatment path. Scan scope, charts, dimensions, evidence, JSON, and Cloud
   detail stay behind disclosure or secondary tabs so ordinary operators can
   start with the next decision rather than browsing an analytics workspace.
3. **Add Cloud detail only when useful.** If Cloud is connected and the administrator
   explicitly asks for deeper detail, Toolbox sends a privacy-minimized
   `site_ops_cloud_analysis_request.v1` to Cloud runtime/detail and renders the
   returned `site_ops_cloud_analysis_result.v1`.
4. **Choose the follow-up path.** Simple items remain manual review. Eligible
   items should point to existing fixed workflows first; write-like follow-up
   may later become Core-governed handoff plans through existing governed
   workflows outside this report.
5. **Preview review candidates when needed.** For issues marked as review
   workflow candidates, the Overview may show a folded handoff draft with
   candidate objects, evidence, and a suggested review note. This preview is
   still read-only and does not create a Core proposal.

## Solved Problems

- Operators no longer need to inspect posts, media, comments, taxonomy, and
  context readiness as separate raw lists before seeing the next recommended
  fixed workflow.
- Cloud detail has a clear job: summarize, rank, explain, and close the analysis
  loop when local rules are not enough. It does not become a second WordPress
  control plane.
- The report separates "what is wrong" from "what can be written". Findings
  are suggestions until an allowed manual action or Core-governed handoff is
  selected.
- Advanced JSON remains available for debugging and handoff inspection, but the
  default UI starts with the site-check decision queue.
- Coverage metrics and current-run charts remain available, but they are not
  the default first task. They support audit and orientation after the operator
  has seen what to handle first.
- First-action links take the operator to the affected post, media item,
  comments list, site profile, content library usage, or Cloud detail without
  creating proposals, queues, approvals, or writes.
- Review-workflow candidate previews reduce the gap between "this needs
  review" and "what should I carry into review" without adding a second
  approval path.

## Boundary

Toolbox does not store a historical run ledger for Site Check. It does
not create local queues, custom run tables, schedulers, retries, Core
proposals, or WordPress writes from this report.

Cloud may return executive summaries, semantic ranking, trend explanations,
dimension summaries, blocked items, next actions, handoff candidates, and
analysis closure detail. Cloud remains runtime/detail only; Core and
WordPress writes stay locally governed.

Historical trend storage, durable comparison across runs, usage/billing
detail, queue-backed execution, and result retention belong to Cloud or a
future governed runtime decision, not to this Toolbox local surface.
