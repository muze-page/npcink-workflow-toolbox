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

Human editors own the article text. Article Assistant is retired from the
operator-facing and public Ability surface; the legacy route remains only as a
compatibility path for older callers.

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
| Zhihu global search atom | Reusable Cloud web-search calls may pass `managed_source=zhihu_global_search`, which maps to Cloud `source_type=zhihu_global_search` and consumes the `source_evidence.v1` atom when configured. | Accepted as a reusable full-web evidence atom for fact checks, citation discovery, comparisons, FAQ/AEO research, and article background packs. | Suggestion-only. No local Zhihu keys, no provider picker UI, no official-source bypass for publishable claims, no automatic draft generation, and no WordPress write authority. |
| Zhihu hot topics | The WordPress Dashboard exposes `知乎热榜选题`, which calls Cloud-managed web search with a fixed Zhihu hot-list source served from Cloud cache when fresh and consumes the `topic_candidate.v1` atom when present. | Accepted as a first-version daily topic-pool lane. It helps editors decide what may be worth researching today before running focused research. | Suggestion-only. No local Zhihu keys, no per-panel provider calls when cache is fresh, no automatic draft generation, no publishing, and no WordPress write authority. |
| Zhihu capability checks | Standalone Zhihu and Cloud web-search diagnostics belong in Cloud Addon or Cloud service-plane surfaces, not Toolbox. Toolbox may still call the managed lanes from fixed product flows. | Accepted as Cloud-owned diagnostics and Toolbox-owned suggestion flows. | No local Zhihu keys, no local provider routing ownership, no diagnostic console in Toolbox, no automatic draft generation, no publishing, and no WordPress write authority. |
| Zhihu research | Editor Content Support exposes `zhihu_research`, which calls Cloud-managed web search with a fixed Zhihu managed source for the current query and consumes `source_evidence.v1` / `topic_candidate.v1` atoms when present. | Accepted as a first-version pre-writing research lane. It helps editors inspect audience questions, viewpoints, objections, and citation candidates before drafting. | Suggestion-only. No local Zhihu keys, no generic provider routing UI, no default hot-list mixing, no copying/rewrite/publish flow, and no WordPress write authority. |
| Zhihu direct answer atoms | Reusable Cloud web-search calls may pass `managed_source=zhida_simple`, `managed_source=zhida_deep`, or `managed_source=zhida_deepsearch`, which map to Cloud direct-answer source types and consume `grounded_answer.v1` when configured. | Accepted as reusable answer-preview atoms for FAQ/AEO candidates, short answer previews, and research conclusion previews. | Suggestion-only. Not final article text, no insertion, no publishing, no WordPress write authority, and source references still require local review. |
| Selected paragraph review | The selected-block toolbar exposes a local paragraph review entry beside paragraph image suggestions and routes it through `polish_notes` with selected text only. | Accepted as a contextual paragraph-review tool, not a default article-level writing button. | Returns clarity, fact-boundary, tone, and editing-direction notes only. It does not replace block text or generate insert-ready copy. |
| Summary, category, and tag recommendations | Editor Content Support exposes summary/category/tag flows, including `summary_suggestions`, `category_suggestions`, `tag_suggestions`, and `summary_terms_optimization`, and can return reviewed metadata apply handoff artifacts. | Accepted for current phase. High-frequency metadata review stays in the editor surface; summary generation defaults to a fast brief, uses cached Cloud Site Knowledge vector context only when already available, reports timing, and exposes an advanced full-context rerun. | New vocabulary and accepted metadata writes stay Core-governed or future strong-local-confirmation only. |
| Article audio candidates | Editor Content Support exposes `article_narration` and `article_audio_summary`. Narration sends bounded article text to Cloud audio generation; audio summary first asks hosted text runtime for an `audio_summary_script`, then sends that script to Cloud `audio_generation_request.v1`. | Accepted as a first MiniMax-backed listening lane. It solves read-later and mobile listening friction without turning Toolbox into a media library importer or podcast workflow runtime. | Suggestion-only audio candidates until adoption. `Use audio` submits a Core-governed adoption plan; Adapter/Toolkit may import the reviewed audio into the local WordPress media library and write playback metadata only after Core approval/preflight/audit. No post-content insertion, no local audio queue, no provider key ownership, and no Toolbox-owned WordPress write authority. |
| Internal-link candidates | Editor Content Support exposes `internal_links` over bounded article context and optional Cloud Site Knowledge related-content evidence, then delegates candidate assembly to `npcink-abilities-toolkit/resolve-internal-link-targets`. | Accepted for current phase. The surface returns compact manual review candidates with copy-link and open-target actions. Third-party plugins can reuse the Toolkit `internal_link_candidates.v1` artifact. | No automatic insertion, no backend post-content patch, and no link graph control plane. The editor owns where reviewed links are placed. |
| Image candidates and media optimization | Editor Content Support exposes `image_candidates`; Toolbox owns image-source UX and Cloud/provider requests, then delegates review artifact projection to `npcink-abilities-toolkit/build-image-candidate-review-artifact`. Toolbox admin owns the fixed `media_optimization_v1` flow through Media Library image actions and Batch Optimize Images, with media derivative preview and Core proposal handoff. | Accepted for current phase. Crop override controls, preview-only Cloud Checks, and Core media proposal proof are implemented. Third-party plugins can reuse the Toolkit `image_candidate_review.v1` artifact before adoption planning. | Image-source candidates remain candidates; media derivative adoption remains one reviewed Core proposal, not direct media writes. |
| Publish preflight and SEO handoff | Editor Content Support exposes `publish_preflight`, returns `pre_publish_review.v1`, and packages `seo_meta_handoff_preview.v1` for `npcink-abilities-toolkit/set-post-seo-meta`. | Accepted for current phase. Browser validation created a pending Core SEO proposal from the editor, and Core review now surfaces `field_patch` values before raw JSON. | Toolbox creates only a pending proposal. Approval, preflight, audit, and execution authorization stay in Core/Adapter/Abilities. |
| Operator feedback loop | Editor Content Support, image candidates, and Site Knowledge review surfaces can send fixed-label, metadata-only `cloud_agent_feedback.v1` events through the shared `/agent-feedback` route. Explicit editor feedback is hidden behind issue-report entries; useful behavior is captured from successful result actions. | Accepted as a narrow observation loop. Feedback is for Cloud eval and quality rollup only. The default editor view should not show a large always-visible rating panel. | Feedback does not mutate prompts, routers, profiles, proposals, audit truth, media, SEO fields, posts, or WordPress content. |
| Article body generation | The retired Article Assistant route remains compatibility-only for older workbench callers. | Do not promote it as a product entry or public Ability. Use editor support or reviewed-draft handoff instead. | No Cloud article generation, no autonomous writer, no one-click long-form writing promise. |

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
