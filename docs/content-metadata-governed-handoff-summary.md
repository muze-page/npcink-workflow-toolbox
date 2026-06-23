# Content Metadata Governed Handoff Summary

Status: local handoff summary for future AI sessions.

Date: 2026-06-09

## Product Thesis

The goal is not simply to make WordPress "write articles with AI". The better
first-principles framing is that WordPress is an observable publishing and site
operation surface, while AI is the reasoning layer that helps detect vague
editorial problems, propose bounded improvements, and hand write-like changes
to governed review.

The path-dependence risk is treating a new production tool as an old article
editor with an AI button. The new operating pattern should be:

1. observe the current post and related site context;
2. identify the specific metadata problem;
3. produce a small delta, not a broad rewrite;
4. let the operator accept concrete choices;
5. send accepted choices through Core governance;
6. measure results later and only then build feedback learning.

This is why the first narrow proof is Content Metadata Delta: one post,
article-context and related-content evidence, excerpt/category/tag
recommendations, operator selection, Core proposal handoff, and no direct
WordPress write from Toolbox.

## Loop Status At Close

Content Metadata Delta now forms a P0 governed handoff loop, not a full
self-learning loop.

The closed P0 loop is:

```text
Current post and related site context
-> content_metadata_delta issue/diagnosis/delta artifact
-> operator-reviewed excerpt/category/tag choices
-> Toolbox content_metadata_apply_plan
-> Core /proposals/from-plan
-> one pending plan_to_proposal_batch review proposal
```

This proves the new operating pattern through the governance handoff boundary:
Toolbox can detect and frame a metadata problem, produce a bounded delta,
package accepted choices, and hand the write-like change to Core without
mutating WordPress state directly.

The full feedback loop is intentionally not complete yet:

```text
Proposal approval
-> Adapter/Abilities execution
-> post-apply measurement
-> durable feedback records
-> learning-store updates
```

That second half should wait until real operator usage produces accepted,
edited, rejected, and measured outcomes. Adding a learning store before then
would create artificial signal and extra state without proving product value.

## Implemented Scope

### Toolbox

Implemented in `npcink-toolbox`.

- Related Site Knowledge can influence summary/category/tag ranking as
  evidence only.
- The editor content-support flow returns a `content_metadata_delta` artifact
  with issue, diagnosis, delta, authorization, outcome checks, and future
  learning candidates.
- Accepted excerpt, existing category, and existing tag selections are converted
  into a dry-run `content_metadata_apply_plan`.
- The apply plan route is:
  `/wp-json/npcink-toolbox/v1/flows/content-metadata-apply-plan`.
- The apply plan ability is:
  `npcink-abilities-toolkit/build-content-metadata-apply-plan`.
- The editor submits the apply plan to Adapter `/proposals/from-plan` using
  `npcink-abilities-toolkit/build-content-metadata-apply-plan`.
- Proposed new terms are preserved as manual-review vocabulary-gap notes only.
- Legacy metadata `proposal_targets` preview scaffolding was removed after the
  dedicated apply-plan path became the real handoff contract.

Relevant commits:

- `47be90f Use related content for metadata suggestions`
- `09a1a7e Add governed content metadata apply handoff`
- `7c50108 Remove legacy metadata proposal target preview`
- `9821e1a Document content metadata handoff summary`
- `e4471d7 Update metadata handoff status summary`
- `bb68798 Extend metadata delta Core proposal smoke`

### Core

Implemented in `npcink-governance-core`.

- Core accepts `npcink-abilities-toolkit/build-content-metadata-apply-plan` as a bounded
  plan-to-proposal source.
- Core validates the plan fail-closed before proposal creation:
  - `artifact_type=content_metadata_apply_plan`;
  - `proposal_mode=batch` and `batch_approval=true`;
  - one target post;
  - one to three write actions;
  - all actions explicitly `dry_run=true` and `commit=false`;
  - `update-post` may update only `excerpt`;
  - `set-post-terms` may target only `category` or `post_tag`;
  - `term_ids` must be reviewed existing ids;
  - `create_missing=false`;
  - no named missing terms, title updates, content updates, SEO writes, or
    unsupported taxonomies.
- Core preserves `preview.content_metadata_apply` for review evidence.

Relevant commits:

- `802a158 Record toolbox metadata ranking handoff`
- `42b56e2 Accept content metadata apply plans`
- `20db401 Align operation classification proof docs`

### Boundary Proofs Around The Loop

Two adjacent authorization proofs are now in place:

- Low-risk Local Admin Consent proof:
  `8a51c37 Add local featured image consent proof` and
  `49a00e6 Add local admin consent audit hook`.
- High-risk Core proposal proof:
  `49158fd Add article media batch proposal smoke` and
  `487e983 Document high-risk batch proposal proof`.

These prove the operation classifier is not just a label:

- one visible, low-risk, single-object existing attachment -> featured image
  action may use Local Admin Consent with Core audit;
- high-risk article/media batch plans remain Core proposal review;
- metadata excerpt/category/tag changes remain Core apply-plan handoffs, not
  Local Admin Consent expansion targets.
- a future single-post direct apply proof for accepted excerpt/category/tag
  values would be `strong_local_confirmation`, not Local Admin Consent, and
  must first define exact-value preview, old/new audit evidence,
  actor/source/correlation evidence, explicit confirmation copy, recovery
  evidence, and fail-closed audit behavior.

## Verification Already Run

Toolbox:

```bash
composer test:all
composer smoke:metadata-delta
node --check assets/editor-content-support.js
git diff --check
```

Core:

```bash
composer test:all
composer smoke:wp
git diff --check
```

The metadata smoke verifies that the editor content-support REST flow remains
suggestion-only, that the apply-plan route produces a dry-run handoff, that Core
`/proposals/from-plan` creates one pending `plan_to_proposal_batch` review
proposal with `preview.content_metadata_apply`, that term creation is not
included, and that the sampled WordPress post is not mutated.

## Deferred Work

These are intentionally not implemented in the current slice:

- Core approval and Adapter/Abilities final execution for accepted metadata
  proposals;
- persistent feedback store;
- self-learning loop;
- automatic measurement of accepted metadata results;
- automatic creation of new taxonomy terms;
- direct excerpt/category/tag writes from Toolbox;
- `strong_local_confirmation` direct apply for single-post metadata;
- Toolbox-owned approval, audit, or final WordPress write execution.

The feedback loop should wait for real usage data. Until then, adding a learning
store would produce artificial signal and extra state without proving that the
recommendations are useful.

## Current Status Note

The earlier local uncommitted `local-admin-consent/featured-image` branch has
since been landed as a separate proof. It is limited to setting one existing
WordPress image attachment as the current post featured image with present
administrator consent, classifier approval, Core audit, and rollback on
completion-audit failure.

Do not treat that local-admin-consent proof as part of Content Metadata Delta.
Metadata excerpt/category/tag writes still use the governed apply-plan handoff
and Core proposal path.

## Stage Closeout Decision

Stop feature expansion for this stage. The current state is intentionally a
contract and validation milestone, not a prompt to add more direct-write paths.

What is complete:

- pure suggestions are `suggestion_only`: image candidates, title/summary
  suggestions, SEO/AEO/GEO suggestions, content optimization notes, and
  category/tag candidates need normal permission checks, not Core proposals,
  as long as they do not write WordPress state;
- one narrow Local Admin Consent proof exists: a present WordPress
  administrator can set one existing image attachment as the current post's
  featured image with Core-owned audit, no Core proposal, and rollback on audit
  failure;
- high-risk batch proof exists: article/media batch plans remain
  `core_proposal_required`, become one Core `plan_to_proposal_batch`, and do
  not use Local Admin Consent;
- `strong_local_confirmation` is reserved as a future path for single-object
  high-impact admin actions, but no UX, audit route, or direct write executor
  is implemented.

What is deliberately not complete:

- Local Admin Consent is not expanded to excerpt/category/tag direct apply,
  media metadata updates, SEO meta writes, draft creation, publishing, slug
  changes, deletion, or generated/external image adoption;
- `strong_local_confirmation` is not implemented for post metadata or any
  other write path;
- persistent learning, automatic measurement, self-learning, and feedback-store
  behavior are deferred until real operator usage exists.

The next phase should be a usage validation period, not another implementation
push. Use the editor panel in real article work, collect where operators feel
Core review is too heavy, and only then decide whether one very narrow
`strong_local_confirmation` proof is justified. The likely candidate, if usage
supports it, is single-post excerpt plus existing category/tag direct apply
from the current editor, after a separate UX and audit contract is written.

## Hosted AI Routing Closeout

Summary/Terms optimization now has a verified hosted AI path through the same
governed content-support boundary:

```text
Toolbox /ai/content-support
-> Cloud Addon runtime client
-> Cloud /v1/runtime/execute
-> hosted text routing profile text.ai
-> review-only hosted AI output
-> Toolbox editor content_metadata_delta
-> dry-run content_metadata_apply_plan
-> Core /proposals/from-plan
```

The important routing decision is that `text.ai` is a stable hosted text entry
profile, not a fixed model id. Cloud may currently resolve it to `gpt-5.5`
because that is the current hosted-free catalog candidate, but Toolbox must not
treat `gpt-5.5` as the durable contract. The durable contract is:

- `profile_id=text.ai`;
- `execution_kind=text`;
- non-empty `model_id` returned by Cloud for honest UI/debug display;
- `direct_wordpress_write=false`;
- `final_write_path=core_proposal_required`.

Cloud keeps the model-specific compatibility profile separate as
`text.free-gpt55`. That profile may remain useful for current/free-package
compatibility, but product callers should use `text.ai` when they need the
stable hosted text entry point.

The Toolbox fix was intentionally small: hosted AI content support and hosted
AI site helper now include `execution_kind => text` in their runtime payloads.
No workflow runtime, Agent surface, approval store, prompt registry, or direct
WordPress write path was added to Toolbox.

Verification performed during closeout:

```bash
composer test:all
composer smoke:metadata-delta
git diff --check
```

A real local WordPress REST dispatch also verified:

```text
POST /wp-json/npcink-toolbox/v1/ai/content-support
intent=summary_terms_optimization
status=200
hosted_profile=text.ai
direct_wordpress_write=false
final_write_path=core_proposal_required
```

The local smoke environment required reseeding the Cloud site key to match the
WordPress Cloud Addon setting after catalog refresh. That was an environment
alignment step, not a code or product contract change.

Do not continue feature expansion from this point. The only useful follow-up is
test infrastructure: add a dedicated hosted AI content-support smoke that
captures the real REST assertion above so future changes catch Cloud/Toolbox
contract drift early.

## 2026-06-09 Editor Content Support Acceptance Closeout

The editor Content Support slice is accepted for the current phase. The product
surface is intentionally limited to fixed visible editor actions, with split
metadata editor actions for the high-frequency narrow panel:

- writing preparation;
- publish preflight;
- summary suggestions;
- category suggestions;
- tag suggestions;
- internal-link candidates;
- image candidates.

`taxonomy_tags` remains a supported lower-level REST intent for internal and
admin flows, but it is not a separate default editor button. The editor sidebar
is now the focused writing-support entrypoint: it helps the operator prepare,
check, enrich, and hand off article metadata while the article body remains a
human editing responsibility.

Acceptance found one UI issue: the third editor button still rendered in
English in a zh_CN WordPress admin session. The closeout fix localized the
button label and description to `优化摘要与分类标签` and added a regression
assertion for the editor script translation JSON. This was a localization
completion, not a behavior or routing change.

Verification performed during acceptance:

```bash
composer test:all
composer smoke:metadata-delta
composer smoke:ai-image-media-seo
git diff --check
```

Browser acceptance against the local WordPress editor verified that
`Npcink 内容支持` opens from the editor toolbar and shows exactly the five
visible entries above. The same manual pass verified that the old English
`Optimize summary and terms` copy no longer appears in the zh_CN sidebar.

The runtime and governance acceptance remains unchanged:

- `summary_terms_optimization` returns suggestion-only metadata artifacts;
- accepted metadata choices go through
  `/flows/content-metadata-apply-plan`;
- Core receives one pending review proposal through Adapter/Core from-plan
  intake;
- Toolbox does not mutate the sampled post, assign terms, write excerpts,
  import media, insert links, publish content, or own final write approval.

The current phase should stop here. Additional work should be driven by real
article-writing usage feedback, not by adding more default editor buttons or
local execution paths.

## Recommended Next Step

Treat Content Metadata Delta P0 as complete for the current phase. The next
useful step is not another metadata implementation layer; it is real editor
review QA:

- confirm the editor panel copy makes the operator path obvious;
- confirm the Core proposal review detail shows the before/after metadata
  evidence clearly;
- only after real usage, design measurement and feedback storage.

## Prompt For Another AI

Use this prompt when handing the work to another AI:

```text
You are continuing Npcink Toolbox/Core work. Inspect the real repo state before
changing anything. Content Metadata Delta P0 is already implemented:

- Toolbox commit 09a1a7e builds the governed content metadata apply handoff.
- Toolbox commit 7c50108 removes legacy metadata proposal target preview
  scaffolding.
- Toolbox commit bb68798 extends the metadata smoke through Core
  /proposals/from-plan and verifies one pending plan_to_proposal_batch review
  proposal.
- Core commit 42b56e2 accepts content_metadata_apply_plan fail-closed.

Do not reintroduce direct Toolbox writes for excerpts, categories, tags, SEO, or
new term creation. Accepted metadata choices must go through
/flows/content-metadata-apply-plan and then Adapter/Core from-plan intake.
If asked to explore direct apply for one current post's accepted excerpt,
category, or tag choices, treat it as a future `strong_local_confirmation`
proof. Do not implement it before writing a separate UX and audit contract for
exact-value preview, old/new evidence, actor/source/correlation evidence,
explicit confirmation copy, recovery evidence, and fail-closed audit behavior.

This is a P0 governed handoff loop, not a full self-learning loop. Do not add a
persistent learning store, automatic measurement system, or self-training path
until there is real operator usage data.

If you work on local-admin-consent/featured-image, treat it as a separate
boundary-sensitive proof that already exists. First read docs/decisions/
ADR-003-local-admin-consent-boundary.md, docs/boundary.md, and the Core
operation classification contract. Preserve Core audit, rollback, permissions,
exact preview, and one-object scope, and do not generalize that proof to
metadata, SEO, media import, generated images, or batch writes without a new
boundary decision.

Run composer test:all and the narrow smoke relevant to the changed behavior.
Stage only files changed for the current task.
```
