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

## Toolkit Migration Closeout - 2026-06-21

This follow-up closes the Content Support Toolkit migration slice. Stop Toolkit
migration here: the remaining work is release validation, operator trial
evidence, and OpenClaw reuse checks, not more capability movement.

Migration implementation commits from `ba8027b` through `4d16755` moved the
reusable WordPress ability-shaped artifacts to Toolkit-backed execution:

- `npcink-abilities-toolkit/build-content-metadata-apply-plan`
- `npcink-abilities-toolkit/suggest-post-taxonomy-terms`
- `npcink-abilities-toolkit/build-comment-mention-reply-suggest`
- `npcink-abilities-toolkit/resolve-internal-link-targets`
- `npcink-abilities-toolkit/build-image-candidate-review-artifact`
- `npcink-abilities-toolkit/build-image-candidate-adoption-plan`

The closeout decision is intentionally conservative:

- no broad migration should continue by default;
- provider/runtime/Site Knowledge/vector/OpenClaw projection remain outside
  Toolkit;
- Toolbox keeps the editor and operator surface, fixed buttons, candidate
  display, feedback cues, and Core handoff UX;
- Cloud keeps hosted AI, image-source lookup, Site Knowledge runtime, vector
  search, and provider execution;
- Core and Adapter keep proposal, approval, preflight, audit, and final
  execution ownership;
- internal-link success responses must preserve
  `operator_review_only_no_insert`, `direct_wordpress_write=false`, and
  `no_link_insertion_in_toolbox`.

Verification run during closeout:

| Repository | Gate | Result |
| --- | --- | --- |
| Toolbox | `composer smoke:editor-review-artifacts` | Passed |
| Toolbox | `composer test:progressive-recommendations` | Passed |
| Toolbox | `composer test:editor-progressive-js` | Passed |
| Toolbox | `composer test:all` | Passed |

The field-trial posture after this closeout is: use the existing Content
Support surface on real human-written posts, verify that OpenClaw/Adapter reuse
the same Toolkit artifacts and Core proposal paths, and do not add new Toolkit
abilities unless a repeated, host-reusable WordPress artifact or callback
contract emerges.

## PR Closeout Record - 2026-06-10

Pull request:
https://gitee.com/gitgreat/npcink-toolbox/pulls/3

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
5. Run Image Candidates, and use the Media Library image action or Batch
   Optimize Images only when an existing media item needs a reviewed derivative.
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

## Trial Results - 2026-06-10

Local WordPress trial was run after PR `!3` merged into `master` at
`941a82e`. Trial 1 used the only published, human-written article with enough
body content at the time. Trial 2 was rerun after a second human-written draft
article was prepared locally. The local `npcink-openclaw-adapter` plugin was
activated before the Trial 2 SEO proposal check so the Adapter proposal route
was available.

| Field | Trial 1 | Trial 2 |
| --- | --- | --- |
| Post ID / title | `4312` / `SEO、AEO、GEO 怎么喂给 AI：用内容上下文约束文章建议，而不是让模型自由发挥` | `4310` / `为什么 AI 生文不该自动发文：Magick AI 的本地文章助手工作台设计` |
| Writing preparation useful? | Endpoint passed. `writing_support` returned `ready`, stayed suggestion-only, and did not write WordPress data. Site Knowledge returned no related results in this local one-post corpus. | Endpoint passed. `writing_support` returned `ready`, stayed suggestion-only, did not write WordPress data, and returned 6 related/context results. |
| Summary/category/tag useful? | Summary suggestions returned one `article_discoverability_optimization.v1` summary layer and `content_metadata_delta`. Category and tag candidate counts were `0` because the post only had the default category and no tags in the local corpus. | Summary suggestions returned one summary layer. Category and tag candidate counts remained `0` because this draft still only had the default category and no tags. |
| Internal-link candidates useful? | Endpoint passed and returned `internal_link_candidates.v1` with `operator_review_only_no_insert`, but candidate count was `0` because the local site had no second published related post. | Endpoint passed and returned `internal_link_candidates.v1` with `operator_review_only_no_insert`; candidate count was 6 after a related article was available. |
| Image candidate or media optimization used? | Image candidates returned 6 stock image-source candidates; the first candidate source was Unsplash. Media optimization was not used because the existing featured image did not require a reviewed derivative for this trial. | Image candidates returned 6 stock image-source candidates; the first candidate source was Unsplash. Media optimization was not used because this draft had no featured media selected. |
| Publish preflight found actionable issues? | Publish preflight returned `pre_publish_review.v1` with 7 review items and a proposal-ready SEO handoff. | Publish preflight returned `pre_publish_review.v1` with 7 review items and a proposal-ready SEO handoff. |
| SEO Core proposal created? | Yes. Pending Core proposal `10fb7ac3-f105-4885-bac0-7c12c6615221` was created for `npcink-abilities-toolkit/set-post-seo-meta`. | Yes. Pending Core proposal `80f53bfa-ffc4-415d-8462-ad812950d592` was created for `npcink-abilities-toolkit/set-post-seo-meta`. |
| Core proposal reviewed, approved, or rejected? | Reviewed only. Core detail returned status `pending`, 2 field patches (`seo_title`, `seo_description`), and an audit timeline. It was not approved during trial. | Reviewed only. Core detail returned status `pending`, 2 field patches, and 3 audit timeline entries. It was not approved during trial. |
| Article body remained human-owned? | Yes. Post title, excerpt, content hash, status, and published-post count were unchanged after the trial. | Yes. Post title, excerpt, content hash, status, and post count were unchanged after the trial. |
| Any confusing UI copy? | None found through the REST-level trial. Results consistently reported `direct_wordpress_write=false`. | None found through the REST-level trial. Results consistently reported `direct_wordpress_write=false`. |
| Any missing evidence or context? | Local Site Knowledge coverage was too small for writing preparation, category/tag, and internal-link relevance. A wider editorial trial needs at least two related published posts and richer taxonomy/tag data. | Internal-link and writing-preparation evidence improved. Taxonomy/tag usefulness still needs richer assigned categories/tags or reviewed term data. |
| Follow-up required before wider use? | Seed or select a second real related article before the next trial, then rerun internal links and taxonomy/tag checks. Keep the SEO proposal pending unless a human reviewer verifies and approves the final field values. | Add reviewed categories/tags to the trial posts before the next taxonomy/tag-focused trial. Keep both SEO proposals pending unless a human reviewer verifies and approves the final field values. |

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
