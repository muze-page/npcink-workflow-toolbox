# Fixed Button Surface

Npcink Workflow Toolbox is the fixed-button projection of accepted Npcink and
OpenClaw operating practices. The V1 default surface is intentionally small:
operators should see a few repeatable review buttons, not a generic AI console,
workflow builder, provider playground, or runtime dashboard.

Default buttons may collect bounded context, call suggestion tools, render
candidates, and prepare governed handoff artifacts. They must not introduce a write executor, local queue, indexing lifecycle control, provider control plane,
approval store, request log, or scheduler truth.

This matrix is the human-readable product contract for default visible buttons.
The machine-readable coverage and ownership gate is
[`fixed-button-contract-table.json`](fixed-button-contract-table.json).
`Ability_Surface_Metadata` is only the read-only Workflow readiness projection
used by the admin UI; it is not an exhaustive button catalog, ability registry,
workflow registry, or route-compatibility registry. Do not add metadata entries
only to mirror every route-compatible support path.

Workflow readiness may show contract reuse status when it helps operators and
support verify that fixed buttons stand on existing project contracts: Toolkit
ability ids, Core proposal handoff, Adapter execution profiles, and Cloud
runtime/detail. That status must remain read-only and must not become a second
registry, runtime, approval store, queue, or write executor.

## V1 Default Buttons

| Button | Surface | Input | Output artifact | Runtime | Handoff | Boundary |
| --- | --- | --- | --- | --- | --- | --- |
| Adapt External Source | Editor Content Support | one public article URL plus optional editor direction | `source_extraction_preview.v1` then `source_adaptation_review.v1` | Cloud exact-URL reader, Cloud Site Knowledge vectors, and hosted suggestion runtime | human verifies extraction, then reviews and writes; native WordPress save only after manual editing | `default_button_v1`; no full translation, article-body generation/insertion, local URL fetch, media import, Core proposal, or publish |
| Site Check | Admin Site Check | bounded public site snapshot | `site_ops_insight_pack.v1` plus optional `site_ops_cloud_analysis_request.v1` / `site_ops_cloud_analysis_result.v1` detail | local deterministic scan; optional Cloud runtime/detail | operator chooses manual action or later Core-ready handoff candidate | `default_button_v1`; no local run table, queue, retry owner, proposal creation, or WordPress write |
| Publish Preflight | Editor Content Support | current draft title, content, excerpt, terms, media, and source context | `pre_publish_review.v1` and optional `seo_meta_handoff_preview.v1` | local and hosted suggestion support | Core/Adapter/Abilities for any accepted SEO or publish-related write | `default_button_v1`; no publishing, no SEO mutation, no body replacement |
| Internal Link Candidates | Editor Content Support | current draft context plus optional related Site Knowledge evidence | `internal_link_candidates.v1` | Toolkit candidate assembly with optional Cloud-managed Site Knowledge evidence | manual editor placement or governed future handoff | `default_button_v1`; no automatic insertion, no link graph control plane, no backend post-content patch |
| Article Image ALT (SEO) | Editor Content Support | each current-article image occurrence, nearest heading, adjacent text, caption, and current block ALT | `current_article_image_alt_context_review.v1` plus Native Commit editor state | local context first; existing Cloud visual evidence only when context is absent | missing ALT is automatically applied only to Gutenberg editor state; native WordPress save persists and no Core trace is created | `default_button_v1`; silent Cloud failure, no extra confirmation UI, existing ALT preserved, no attachment-global ALT write, Toolbox post write, Adapter execution, proposal, or audit |
| Image Candidates | Editor Content Support | current draft context and visual brief | `image_candidate_review.v1` or image candidate adoption plan | Cloud-managed image-source runtime; explicit AI image candidates remain reviewed candidates | Core-governed media or featured-image adoption path | `default_button_v1`; no media import, no provider picker, no prompt/model routing ownership, no direct featured-image batch replacement |
| Article Narration | Editor Content Support | reviewed full article text | `audio_generation_request.v1` narration candidate and `article_audio_adoption_plan.v1` | Cloud audio generation runtime | Core/Adapter/Toolkit audio adoption path | `default_button_v1`; no post-content insertion, no local audio queue, no media import or playback metadata write in Toolbox |
| Audio Summary | Editor Content Support | reviewed article summary context | concise summary script, `audio_generation_request.v1` candidate, and `article_audio_adoption_plan.v1` | hosted summary support plus Cloud audio generation runtime | Core/Adapter/Toolkit audio adoption path | `default_button_v1`; no article rewrite, media import, post-meta write, or playback adoption in Toolbox |
| Batch Image Optimization Review | Admin Image Handling | selected or bounded eligible media attachments plus reviewed output policy | Toolkit `media-optimization` recipe, derivative previews, and `media_optimization_plan` | Toolkit request contracts plus Cloud Addon/Cloud derivative runtime | Adapter `/proposals/from-plan` to Core; Toolbox stops after proposal submission | `default_button_v1`; no Toolbox run store, approval, execution, attachment replacement, or metadata write |
| Review ALT Suggestions | Admin Image Handling | bounded media review set plus operator-confirmed ALT drafts | Toolkit media ALT review set and `media_alt_apply_plan` | Toolbox review projection, Toolkit contracts, optional Cloud visual evidence | Adapter `/proposals/from-plan` to Core; Toolbox stops after proposal submission | `default_button_v1`; no attachment metadata write, approval, execution, or batch writer in Toolbox |

## Adapter Parity Audit

The current ten-button audit deliberately distinguishes three levels instead
of claiming universal parity prematurely:

- `workflow_projection_proven`: the Toolkit workflow definition, Toolbox
  projection, and Adapter projection have enforced field parity;
- `ability_parity_ready`: the reusable ability and governed handoff exist, but
  there is not yet a separately enforced canonical workflow projection;
- `partial_contract_reuse`: important source artifacts are reusable, but one
  coherent external-client workflow contract is not yet proven.

Only Batch Image Optimization currently claims `workflow_projection_proven`.
This is an audit result, not a product defect or permission to start another
broad migration. Partial rows should be closed one bounded contract at a time.

## Route-Compatible Support Only

The following capabilities may remain available through REST routes, editor
rendering paths, toolbar actions, or compatibility workbenches, but they are
not V1 default visible buttons:

- `writing_support`;
- `article_checkup`;
- `title_suggestions`;
- `article_outline`;
- `polish_notes`;
- `summary_suggestions`;
- `category_suggestions`;
- `tag_suggestions`;
- `summary_terms_optimization`;
- `taxonomy_tags`;
- `discoverability`;
- `comment_reply_suggestion`;
- `article_assistant`.

Keeping these paths route-compatible preserves existing workflows without
turning Toolbox into a generic AI writing suite. If one of these becomes a
default button later, the change must update this matrix, the product boundary,
and static contracts in the same PR.

## Admission Rule For New Buttons

Before a new default button is added, it must answer all of these questions in
the matrix:

- Which exact operator surface owns the click?
- Which bounded input is collected?
- Which artifact contract is returned?
- Which component owns runtime/detail work?
- Which governed handoff path owns any accepted WordPress write?
- Which explicit non-goals prevent drift into writes, queues, indexing,
  provider control, approval, or audit ownership?

The default answer for a new idea is route-compatible support or documentation,
not a new default button. Batch or write-like buttons require an accepted Core/Adapter/Abilities proof before Toolbox can productize them.
