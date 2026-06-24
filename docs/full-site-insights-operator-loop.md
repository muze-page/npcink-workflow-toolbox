# Full-site Insights Operator Loop

Status: active product loop for the current Toolbox stage.

Full-site Insights is the site-level data analysis surface for WordPress
operators. Its job is to turn bounded current-site evidence into a prioritized
review queue, then help the operator decide whether each item stays manual or
should move into a Core-governed handoff path.

## Purpose

The current purpose is not "operations only" and not "article analysis only".
It is whole-site analysis across the data Toolbox can safely read in WordPress:

- public posts and pages;
- approved comment signal counts, without raw private comment detail;
- media metadata and referenced image gaps;
- taxonomy shape and low-use terms;
- Site Context readiness;
- Cloud readiness and optional Cloud runtime/detail output.

The intended effect is a reviewable site analysis snapshot. An operator should
see what matters first, why it matters, what evidence supports it, and what
follow-up path is allowed.

## Operator Loop

1. **Scan local data.** Toolbox builds one current
   `site_ops_insight_pack.v1` from bounded WordPress data. This step is local,
   synchronous, and read-only.
2. **Read the priority queue.** The Overview starts with a plain-language action
   brief, the first few issues, and a treatment path. Scan scope, charts,
   dimensions, evidence, JSON, and Cloud detail stay behind disclosure or
   secondary tabs so ordinary operators can start with the next decision.
3. **Add Cloud detail when useful.** If Cloud is connected and the administrator
   explicitly runs analysis, Toolbox sends a privacy-minimized
   `site_ops_cloud_analysis_request.v1` to Cloud runtime/detail and renders the
   returned `site_ops_cloud_analysis_result.v1`.
4. **Choose the follow-up path.** Simple items remain manual review. Eligible
   items may become Core-governed handoff plans through existing governed
   workflows outside this report.
5. **Preview review candidates when needed.** For issues marked as review
   workflow candidates, the Overview may show a folded handoff draft with
   candidate objects, evidence, and a suggested review note. This preview is
   still read-only and does not create a Core proposal.

## Solved Problems

- Operators no longer need to inspect posts, media, comments, taxonomy, and
  context readiness as separate raw lists before seeing priorities.
- Cloud analysis has a clear job: summarize, rank, explain, and close the
  analysis loop. It does not become a second WordPress control plane.
- The report separates "what is wrong" from "what can be written". Findings
  are suggestions until an allowed manual action or Core-governed handoff is
  selected.
- Advanced JSON remains available for debugging and handoff inspection, but the
  default UI starts with the operational priority queue.
- Coverage metrics and current-run charts remain available, but they are not
  the default first task. They support audit and orientation after the operator
  has seen what to handle first.
- Review-workflow candidate previews reduce the gap between "this needs
  review" and "what should I carry into review" without adding a second
  approval path.

## Boundary

Toolbox does not store a historical run ledger for Full-site Insights. It does
not create local queues, custom run tables, schedulers, retries, Core
proposals, or WordPress writes from this report.

Cloud may return executive summaries, semantic ranking, trend explanations,
dimension summaries, blocked items, next actions, handoff candidates, and
analysis closure detail. Cloud remains runtime/detail only; Core and
WordPress writes stay locally governed.

Historical trend storage, durable comparison across runs, usage/billing
detail, queue-backed execution, and result retention belong to Cloud or a
future governed runtime decision, not to this Toolbox local surface.
