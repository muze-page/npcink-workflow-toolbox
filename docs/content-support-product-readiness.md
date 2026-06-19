# Content Support Product Readiness

Status: active current-phase acceptance matrix as of 2026-06-10.

This document records how the current Toolbox product surface supports article
work outside the article body. The default product is not autonomous article
writing. It is a set of fixed editor tools that help a human prepare, review,
enrich, and hand off reviewable changes through the existing WordPress
governance boundary.

## Product Rule

Toolbox may recommend, preview, and package reviewed handoffs. Toolbox must not
approve Core proposals, execute final WordPress writes, create a second media
registry, or become a prompt/model control plane.

Human editors own the article text. Article Assistant remains a fallback
workbench for reviewed local artifacts, not the default editor button that
promises to write the article body.

## Feedback Observation Loop

The editor feedback loop is designed for operators who often use Toolbox like
an email or editorial assistant: they click a useful result, copy or apply the
candidate, then leave the panel. A large always-visible rating panel creates
friction and is unlikely to receive meaningful clicks after the user already
got the answer.

The current implementation therefore separates explicit issue reporting from
implicit behavior observation:

- explicit feedback is folded behind low-friction `Report issue` and
  `Report image issue` entries;
- successful result actions send silent `metadata_only`
  `cloud_agent_feedback.v1` events through the existing `/agent-feedback`
  route;
- observed actions include internal-link copy/open, title and excerpt apply,
  image candidate selection, selection-only image return, governed image
  adoption, AI image regeneration, suggested image query clicks, and reruns;
- payloads use existing fixed outcomes and label vocabulary, plus bounded
  run ids, handoff ids, handoff types, local surface names, and evidence refs;
- payloads must not include article body text, prompts, free-form operator
  notes, user email, provider secrets, approval records, SEO values, media
  write payloads, or WordPress content writes.

This keeps the observation loop useful for Cloud eval and quality rollups
without turning Toolbox into a learning store, prompt/router owner, approval
truth, audit truth, or final WordPress write owner.

## Acceptance Matrix

| Product focus | Current implementation | Acceptance state | Boundary |
| --- | --- | --- | --- |
| Writing preparation | Editor Content Support exposes `writing_support` and calls Cloud Site Knowledge through `writing_support_plan`. | Accepted for current phase. It prepares context, angles, gaps, and evidence prompts around the article. | Suggestion-only. It does not generate or insert the article body. |
| Selected paragraph checks | The selected-block toolbar exposes a local paragraph check entry beside paragraph image suggestions and routes it through `polish_notes` with selected text only. | Accepted as a contextual paragraph-review tool, not a default article-level writing button. | Returns clarity, fact-gap, tone, and editing-direction notes only. It does not replace block text or generate insert-ready copy. |
| Summary, category, and tag recommendations | Editor Content Support exposes summary/category/tag flows, including `summary_suggestions`, `category_suggestions`, `tag_suggestions`, and `summary_terms_optimization`, and can return reviewed metadata apply handoff artifacts. | Accepted for current phase. High-frequency metadata review stays in the editor surface; summary generation defaults to a fast brief, uses cached Cloud Site Knowledge vector context only when already available, reports timing, and exposes an advanced full-context rerun. | New vocabulary and accepted metadata writes stay Core-governed or future strong-local-confirmation only. |
| Internal-link candidates | Editor Content Support exposes `internal_links` over bounded article and related-content context. | Accepted for current phase. The surface returns compact manual review candidates with copy-link and open-target actions. | No automatic insertion, no backend post-content patch, and no link graph control plane. The editor owns where reviewed links are placed. |
| Image candidates and media optimization | Editor Content Support exposes `image_candidates`; Toolbox admin owns the fixed `media_optimization_v1` Optimize Existing Image flow with media derivative preview and Core proposal handoff. | Accepted for current phase. Crop override controls, preview-only Cloud Checks, and Core media proposal proof are implemented. | Image-source candidates remain candidates; media derivative adoption remains one reviewed Core proposal, not direct media writes. |
| Publish preflight and SEO handoff | Editor Content Support exposes `publish_preflight`, returns `pre_publish_review.v1`, and packages `seo_meta_handoff_preview.v1` for `npcink-abilities-toolkit/set-post-seo-meta`. | Accepted for current phase. Browser validation created a pending Core SEO proposal from the editor, and Core review now surfaces `field_patch` values before raw JSON. | Toolbox creates only a pending proposal. Approval, preflight, audit, and execution authorization stay in Core/Adapter/Abilities. |
| Operator feedback loop | Editor Content Support, image candidates, and Site Knowledge review surfaces can send fixed-label, metadata-only `cloud_agent_feedback.v1` events through the shared `/agent-feedback` route. Explicit editor feedback is hidden behind issue-report entries; useful behavior is captured from successful result actions. | Accepted as a narrow observation loop. Feedback is for Cloud eval and quality rollup only. The default editor view should not show a large always-visible rating panel. | Feedback does not mutate prompts, routers, profiles, proposals, audit truth, media, SEO fields, posts, or WordPress content. |
| Article body generation | Article Assistant Workbench exists for broad fallback packaging and reviewed local draft artifacts. | Intentionally not the primary product. Do not promote this as a default article generator. | No Cloud article generation, no autonomous writer, no one-click long-form writing promise. |

## Verification Evidence

- `composer test:all`
- `composer smoke:editor-review-artifacts`
- `composer smoke:media-derivative-core`
- Browser check: editor publish preflight created one pending Core SEO proposal
  from `seo_meta_handoff_preview.v1`.
- Browser check: Core proposal detail shows `字段变更`, `seo_title`, and
  `seo_description` in review context before the raw proposal payload.
- Editor Content Support feedback uses fixed local outcomes and labels only;
  it does not introduce free-form learning, automatic strategy changes, or
  WordPress write authority.
- Editor Content Support hides explicit feedback behind issue-report entries
  and sends silent metadata-only feedback for useful result actions.

## Next Gate

Stop expanding the editor surface until the six rows above remain stable in
review. The next useful work should be regression hardening: keep smoke coverage
for real editor-to-Core handoffs and only add new buttons when they reuse the
same fixed ability ids, artifact shapes, and Core-governed write paths.
