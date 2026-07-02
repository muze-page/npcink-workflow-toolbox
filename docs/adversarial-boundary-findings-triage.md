# Adversarial Boundary Findings Triage

Status: active review artifact.

This file classifies the provider-backed
`workflow_toolbox_adversarial_boundary_audit` report generated on
2026-07-02 after PR #45. It is human-review evidence, not a Core audit record
or automated acceptance gate.

## Classification Rules

- `accepted_fix`: the finding identified boundary wording that should be
  tightened in docs/tests.
- `accepted_exception`: the finding points at an intentionally accepted
  exception already governed by ADRs and the Boundary Exceptions Registry.
- `rejected_finding`: the finding is not correct after checking the repo, but
  the evidence should be recorded so reviewers know why it was rejected.
- `follow_up`: the finding is directionally useful but larger than this
  cleanup PR.

## Current Triage

| ID | Source | Finding | Classification | Resolution |
| --- | --- | --- | --- | --- |
| F01 | gpt55 | `cloud-web-search` could read as a local web-search ability. | `accepted_fix` | Split connector docs into Toolbox-registered Cloud bridge wrappers and non-owned local provider execution. |
| F02 | gpt55 | Toolkit target ability appears under Toolbox ability list. | `accepted_fix` | Split Toolbox wrapper ids from external Toolkit target abilities in connector docs. |
| F03 | gpt55 | Product positioning omits the Local Fallback WP-Cron exception. | `accepted_fix` | Added explicit accepted-exception language and ADR/registry references. |
| F04 | gpt55 | README `/site-knowledge/sync` lacks refresh-only release qualifier. | `accepted_fix` | Annotated the route list with refresh-only Cloud request and no rebuild/delete/indexing lifecycle/local queue/collection controls. |
| F05 | gpt55 | Smoke checklist uses old menu labels. | `accepted_fix` | Updated development workflow labels to `Npcink -> Workflow Toolbox` and `Tools -> Npcink Workflow Toolbox`. |
| F06 | gpt55 | Static guard inventory is not scan-friendly enough. | `follow_up` | Existing tests cover many redlines; a future dedicated boundary-vocabulary lint can make this easier to audit. |
| F07 | gpt55 | Roadmap local image-source caching could imply durable provider logs. | `accepted_fix` | Constrained the roadmap to non-durable, bounded, non-secret transients/session memory with no provider keys, billing/quota data, durable logs, raw payload retention, or custom tables. |
| F08 | gpt55 | `local vector context` wording could imply local vector/RAG ownership. | `accepted_fix` | Replaced with Cloud-returned Site Knowledge/vector evidence and explicit no local vector DB, embeddings, RAG runtime, or indexing lifecycle. |
| F09 | gpt55 | README exception records look like default runtime ownership. | `accepted_fix` | Moved them under a `Boundary Exceptions Only` subsection with hard-stop terms. |
| F10 | grok43 | README route allowlist is truncated. | `rejected_finding` | The source list is complete in README; this appears to come from context/report rendering truncation. Route entries remain subject to boundary notes. |
| F11 | grok43 | Architecture legacy sync/runtime module needs local no-ownership reminders. | `accepted_fix` | Added explicit compatibility projection and isolated exception cross-reference. |
| F12 | grok43 | Default gates do not enumerate redline checks clearly enough. | `follow_up` | Keep current `tests/run.php` static contracts; consider adding a dedicated `test:boundary-vocabulary` target later. |
| F13 | grok43 | Legacy image-generation naming needs triage cross-link. | `accepted_fix` | Added adversarial review and boundary exception cross-references near the image seam in product positioning. |
| F14 | grok43 | Nightly/Local Fallback roadmap wording appears before exception wording. | `accepted_fix` | Current roadmap and product positioning now point to the isolated accepted exception and Cloud-owned runtime posture. |
| F15 | grok43 | Boundary allowlist and hard blocks are split across files. | `follow_up` | A machine-readable route boundary table is useful, but larger than this narrow triage PR. |
| F16 | grok43 | Cross-repo matrix lacks exact guard docs for Local Admin Consent. | `accepted_fix` | Added guard document references to `docs/boundary-exceptions.md` and `docs/adversarial-boundary-review.md`. |

## Follow-Up Candidates

The remaining useful work should be separate from the current documentation
cleanup:

- add a dedicated `test:boundary-vocabulary` target for redline terms;
- build a machine-readable route boundary table from `docs/boundary.md`;
- classify future provider-backed eval findings directly against this file
  before changing code.
