# Content Support Release And Trial Closeout

Status: active release/trial gate for the 2026-06-10 content-support slice.

This document closes the current Toolbox content-support release slice and
defines the limited field trial. It is a product release gate, not a request to
add more editor buttons.

## Release Baseline

- Toolbox baseline: `e60aa02`
- Core baseline: `fd1937a`
- Primary Toolbox surface: WordPress post editor Content Support panel.
- Primary governance surface: `npcink-governance-core` proposal review.
- Trial scope: 1-2 human-written posts.

## Release Note

This slice ships content support around human-written WordPress articles:

- writing preparation through `writing_support` and Cloud Site Knowledge
  `writing_support_plan`;
- summary, category, and tag recommendations for editor review;
- internal-link candidates as manual review aids;
- image candidates plus the fixed `media_optimization_v1` Optimize Existing
  Image flow;
- publish preflight through `pre_publish_review.v1`;
- SEO metadata handoff through `seo_meta_handoff_preview.v1` and the governed
  `npcink-abilities-toolkit/set-post-seo-meta` ability.

The release also hardens the review boundary:

- Toolbox creates only pending Core proposals for SEO metadata handoff;
- Core proposal detail surfaces reviewable `field_patch` values for SEO title
  and description before raw JSON;
- editor-to-Core smoke coverage reads the created Core proposal detail and
  verifies the stored field patches and audit timeline;
- media derivative smoke coverage verifies Core-governed media proposal,
  execution, and restore paths.

## Explicit Non-Promises

Do not describe this release as:

- an autonomous article writer;
- one-click long-form article generation;
- a publishing automation surface;
- a direct SEO metadata writer inside Toolbox;
- a media registry, workflow runner, prompt/model control plane, or approval
  store.

Human editors own the article body. Core owns proposal review, approval,
preflight, and audit. Adapter/Abilities own approved execution paths.

## Release Verification

Before using this slice for editorial trial, these gates must pass on master:

- `composer test:all`
- `composer smoke:editor-review-artifacts`
- `composer smoke:media-derivative-core`
- Core `composer test:all`
- Core `composer smoke:wp`

The 2026-06-10 closeout passed all five gates on local master before this
document was written.

## PR Closeout Record - 2026-06-10

Pull request:
https://gitee.com/gitgreat/magick-ai-toolbox/pulls/3

Branch and commit state during closeout:

- Toolbox branch: `codex/release-trial-closeout`
- Toolbox PR commit: `08bf1e10cbe56547b0eb281eaea65bc0896a800a`
- Target `master` commit at the time of check:
  `e60aa02ef21d984505f5b279114c85d429766e6d`
- Core verification branch: `master`

Public PR review state during closeout:

- No visible review comments or code-review blocker were present on the public
  PR page.
- The PR checks tab reported no available check details.
- The PR was not mergeable yet because the Gitee review gates were still
  pending: review `0/1` and test `0/1`.

Verification run during closeout:

| Repository | Gate | Result |
| --- | --- | --- |
| Toolbox | `composer test:all` | Passed |
| Toolbox | `composer smoke:editor-review-artifacts` | Passed |
| Toolbox | `composer smoke:media-derivative-core` | Passed |
| Core | `composer test:all` | Passed |
| Core | `composer smoke:wp` | Passed |

Closeout decision:

- No blocker fix was required because no review blocker or verification
  regression was found.
- No product scope was added during closeout.
- The real-article trial remains blocked until the PR is merged.
- After merge, run this document's Trial Protocol on 1-2 real human-written
  posts and record accept/edit/reject results for each suggestion category.

## Trial Protocol

Run the trial on 1-2 real human-written posts. Do not use generated filler as
the article body.

For each post:

1. Open the post in the WordPress editor.
2. Run Writing Preparation.
3. Run Summary / Category / Tag support.
4. Run Internal Link Candidates.
5. Run Image Candidates, and use Optimize Existing Image only when an existing
   media item needs a reviewed derivative.
6. Run Publish Preflight.
7. If SEO title and description candidates are useful, create the Core SEO
   proposal and review it in Core.
8. Record whether the editor accepted, edited, or rejected each suggestion.

Do not approve a Core proposal during trial unless the reviewer has verified
the target field values and understands the final WordPress write path.

## Trial Log Template

| Field | Trial 1 | Trial 2 |
| --- | --- | --- |
| Post ID / title |  |  |
| Writing preparation useful? |  |  |
| Summary/category/tag useful? |  |  |
| Internal-link candidates useful? |  |  |
| Image candidate or media optimization used? |  |  |
| Publish preflight found actionable issues? |  |  |
| SEO Core proposal created? |  |  |
| Core proposal reviewed, approved, or rejected? |  |  |
| Article body remained human-owned? |  |  |
| Any confusing UI copy? |  |  |
| Any missing evidence or context? |  |  |
| Follow-up required before wider use? |  |  |

## Triage Rules During Trial

Fix only issues that affect the verified release slice:

- broken editor entry points;
- missing or misleading handoff evidence;
- Core proposal detail not showing reviewable fields;
- direct-write or approval-boundary regressions;
- smoke failures for editor review or media derivative paths;
- confusing copy that makes Toolbox look like the final writer or approver.

Do not add new product scope during this trial:

- no new editor buttons;
- no article-body generation default;
- no automatic metadata writes;
- no direct internal-link insertion;
- no new media registry or workflow run store;
- no prompt/model/provider control surface.

## Exit Criteria

The trial is accepted when:

- both trial posts keep article text under human control;
- operators can complete the six current content-support checks without
  boundary confusion;
- any created Core SEO proposal shows field-level values clearly enough for a
  reviewer to approve or reject;
- media optimization, when used, stays on the Core-governed proposal path;
- no new direct write path is added to Toolbox;
- the five release verification gates still pass.

If these criteria fail, fix the narrow regression and rerun the affected smoke.
Do not widen the feature set to compensate for trial friction.
