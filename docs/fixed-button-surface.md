# Fixed Button Surface

Npcink Workflow Toolbox is the fixed-button projection of accepted Npcink and
OpenClaw operating practices. The V1 default surface is intentionally small:
operators should see a few repeatable review buttons, not a generic AI console,
workflow builder, provider playground, or runtime dashboard.

Default buttons may collect bounded context, call suggestion tools, render
candidates, and prepare governed handoff artifacts. They must not introduce a write executor, local queue, indexing lifecycle control, provider control plane,
approval store, request log, or scheduler truth.

## V1 Default Buttons

| Button | Surface | Input | Output artifact | Runtime | Handoff | Boundary |
| --- | --- | --- | --- | --- | --- | --- |
| Site Check | Admin Site Check | bounded public site snapshot | `site_ops_insight_pack.v1` plus optional `site_ops_cloud_analysis_request.v1` / `site_ops_cloud_analysis_result.v1` detail | local deterministic scan; optional Cloud runtime/detail | operator chooses manual action or later Core-ready handoff candidate | `default_button_v1`; no local run table, queue, retry owner, proposal creation, or WordPress write |
| Publish Preflight | Editor Content Support | current draft title, content, excerpt, terms, media, and source context | `pre_publish_review.v1` and optional `seo_meta_handoff_preview.v1` | local and hosted suggestion support | Core/Adapter/Abilities for any accepted SEO or publish-related write | `default_button_v1`; no publishing, no SEO mutation, no body replacement |
| Internal Link Candidates | Editor Content Support | current draft context plus optional related Site Knowledge evidence | `internal_link_candidates.v1` | Toolkit candidate assembly with optional Cloud-managed Site Knowledge evidence | manual editor placement or governed future handoff | `default_button_v1`; no automatic insertion, no link graph control plane, no backend post-content patch |
| Image Candidates | Editor Content Support | current draft context and visual brief | `image_candidate_review.v1` or image candidate adoption plan | Cloud-managed image-source runtime; explicit AI image candidates remain reviewed candidates | Core-governed media or featured-image adoption path | `default_button_v1`; no media import, no provider picker, no prompt/model routing ownership, no direct featured-image batch replacement |
| Article Audio Candidates | Editor Content Support | reviewed article text or summary script | `audio_generation_request.v1` candidate and `article_audio_adoption_plan.v1` | Cloud audio generation runtime | Core/Adapter/Toolkit audio adoption path | `default_button_v1`; no post-content insertion, no local audio queue, no media import or playback metadata write in Toolbox |

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
- `image_alt_suggestions`;
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
