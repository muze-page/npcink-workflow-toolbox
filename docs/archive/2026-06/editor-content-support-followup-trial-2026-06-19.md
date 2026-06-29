# Editor Content Support Follow-up Trial - 2026-06-19

Status: local operator trial evidence and next-stage checklist for the editor
follow-up workflow slice.

This trial validates the post-editor follow-up changes after the discoverability
and article-checkup UI update. The goal is to prove the current slice is usable
enough to pause feature expansion and move to real-article review.

## Scope

Covered:

- editor review artifacts for internal links, publish preflight, duplicate
  evidence, and SEO handoff preview;
- discoverability SEO apply through Adapter/Core `approve-and-execute`;
- editor browser smoke for the Content Support sidebar and local progressive
  recommendation panel;
- hosted AI no-result diagnostics for selected-paragraph checks;
- eval-lab quality posture for the Toolbox project and eval wrapper.

Not covered:

- manual visual rating of long-form copy on multiple real articles;
- AI judge scoring of article-checkup issue usefulness;
- FAQ, GEO, schema, or crawler-note proposal execution;
- production site redirect/indexing behavior after slug changes.
- production Cloud runtime sampling for every hosted no-result variant.

## Commands Run

```bash
composer smoke:editor-seo-apply
composer smoke:editor-review-artifacts
composer smoke:editor-hosted-ai-no-result
NODE_PATH="${NODE_PATH:-/Users/muze/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules}" composer smoke:editor-progressive-browser
composer eval:editor-followup:trial
composer eval:lab -- task=project_quality_gate project=/Users/muze/gitee/npcink-workflow-toolbox mode=head output_json=project-review/generated/toolbox-editor-followup-quality-gate.json output_md=project-review/generated/toolbox-editor-followup-quality-gate.md
```

Previous implementation gate for the committed slice:

```bash
composer test:all
```

## Results

| Area | Result | Evidence |
| --- | --- | --- |
| SEO apply loop | Pass | `composer smoke:editor-seo-apply` created a temporary post, got `seo_meta_handoff_preview.v1`, created an executable Core proposal, called Adapter `approve-and-execute`, and verified the SEO title and description were written by the Core-approved ability. |
| Preflight/review boundary | Pass | `composer smoke:editor-review-artifacts` verified publish preflight, internal-link review-only candidates, duplicate evidence, dry-run SEO handoff template, pending Core proposal detail, audit timeline, and no sampled-post mutation. |
| Hosted AI no-result diagnostics | Pass | `composer smoke:editor-hosted-ai-no-result` simulated Cloud omitted, zero provider-call, and idempotent replay empty responses; selected paragraph checks preserved local fallback items, Cloud runtime diagnostics, and no replacement text. |
| Browser sidebar path | Pass | `composer smoke:editor-progressive-browser` verified local progressive prefetch, hidden-by-default success state, Refresh behavior, no Cloud/Adapter/Core route calls, candidate source/action labels, and no generic Post Formats noise. |
| Real article follow-up trial | Pass | `composer eval:editor-followup:trial` evaluated 5 local WordPress posts with 45 pass, 0 warn, 0 fail, `human_review_required=false`, and no WordPress mutation. |
| Eval-lab project quality gate | Pass | `project_quality_gate` wrote `../npcink-eval-lab/project-review/generated/toolbox-editor-followup-quality-gate.{json,md}` with `Human review required: false` and `Checks needing review: 0`. |

Eval-lab checks passed:

- test-like Composer commands are declared;
- local test files are present;
- default tests stay independent from eval-lab;
- eval-lab wrapper uses the task registry;
- no long `sk-...` shaped secret markers were detected in tracked files;
- tracked generated artifact count is low or absent.

## Interpretation

The current follow-up workflow has enough proof to stop this development slice:

- SEO title and description now have a real application path, but still stay
  behind Adapter/Core/Abilities governance.
- Publish preflight remains non-mutating and review-oriented.
- The browser-level sidebar path still behaves as a lightweight local
  recommendation entry instead of opening noisy default panels.
- Eval-lab integration is available as a project-quality evidence layer without
  becoming part of the default test gate.

The next UI follow-up narrows the remaining ambiguity:

- the ordinary result view labels the arrow as a return to the tool list, not a
  hidden undo or navigation away from the editor;
- contextual selected-paragraph checks keep the inline-result posture and do
  not show the ordinary tool-list return;
- hosted AI no-result states render runtime diagnostics in the result surface,
  including Cloud status, storage mode, data classification, provider call
  count, idempotent replay, and any local no-rewrite fallback reason;
- article checkup empty states avoid overclaiming success by saying that the
  local heuristic did not find high-confidence issues.

## Real Article Review Checklist

Use this checklist before adding any new editor content-support ability:

| Check | Pass condition | Notes |
| --- | --- | --- |
| Article checkup on a short draft | Shows either concrete review items or a clear high-confidence-empty local message. | It must not rewrite, insert, or replace body text. |
| Article checkup on a long draft | Flags dense paragraphs, long sentences, missing structure, or factual-claim review points without overwhelming the panel. | False positives should be recorded by paragraph number. |
| Selected paragraph check | Opens from the selected-block toolbar and keeps the selected paragraph context when the sidebar opens. | If hosted AI omits text, local fallback and runtime diagnostics must be visible. |
| Summary/title hosted AI result | Displays useful candidates when runtime output exists. | If no candidates appear, diagnostics must distinguish Cloud omitted, zero provider calls, replay, and local fallback. |
| Discoverability action | Keeps SEO apply behind Adapter/Core and keeps FAQ/GEO/schema notes collapsed. | No direct SEO/schema writes from Toolbox. |
| Back/tool-list control | On ordinary sidebar results, the arrow clearly returns to the fixed-flow tool list. | On contextual paragraph checks, the result should not imply a separate navigation stack. |

Record each article with:

```text
Post:
Status: draft | published | imported test
Flow tested:
Result useful: yes | no | mixed
False positives:
No-result diagnostic seen: none | omitted | zero-provider-call | replay | local-fallback
Write boundary preserved: yes | no
Decision:
```

## Remaining Product Questions

Before adding more crawler-facing write paths, run a small real-article review
set:

1. Review 3-5 real drafts or recently published posts in the editor.
2. Record whether article checkup catches actionable issues or too many
   heuristic false positives.
3. Record whether discoverability suggestions are concise enough to scan.
4. Record whether SEO apply success/blocked feedback is clear without opening
   Core.
5. Record whether slug confirmation copy prevents accidental URL changes.

## Recommendation

Do not expand FAQ/GEO/schema into one-click application yet. Keep them as
collapsed review notes until at least one real-article review set shows that
the suggested fields are consistently useful, source-backed, and not redundant
with the excerpt.
