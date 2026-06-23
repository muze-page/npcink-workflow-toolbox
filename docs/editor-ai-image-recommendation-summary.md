# Editor AI Image Recommendation Summary

Status: active implementation notes.

Date: 2026-06-20

This note summarizes the product and implementation decisions from the editor
image recommendation iteration. It is meant to preserve the reasoning for future
Toolbox, Cloud, Adapter, and Core work.

## Scope

The editor image recommendation surface has two different jobs:

- **AI recommended featured image**: uses the current article title, excerpt,
  and article context to recommend source images or generate AI image
  candidates for the post featured image.
- **Paragraph image icon**: uses the selected paragraph or block as the primary
  context and recommends source images for paragraph-level placement.

The two jobs must stay visibly different. Featured image can expose source
recommendation and AI generation. Paragraph image stays focused on source image
recommendation from the selected paragraph and must not look like a featured
image generation flow.

## Boundary

Toolbox owns the editor product surface, local modal behavior, short-lived
result display, reviewed prompt selection, and Core/Adapter handoff UI.

Cloud owns provider search, image-source provider routing, query rewriting,
visual brief generation, candidate rerank, quality filtering, and hosted AI
image generation.

Adapter and Core own governed proposal intake, approval, preflight, execution,
and audit for media import, media metadata, SEO fields, and featured-image
changes.

Toolbox must not:

- store provider routing truth;
- import media directly from external URLs;
- write media SEO fields directly;
- set featured images from external/generated URLs directly;
- create a second approval store, image registry, workflow runtime, or audit
  truth.

The only local write exception remains the existing-attachment featured-image
Local Admin Consent path, with Core audit and rollback requirements.

## Speed Strategy

The target interactive behavior is:

- show the first usable image within about five seconds when Cloud can return
  one;
- show partial results immediately instead of waiting for all providers;
- progressively fill the grid until there are up to nine candidates;
- keep slower enrichment, rerank, media SEO, and detailed diagnostics off the
  first visual path.

The agreed Cloud-side shape is parallel provider search for `auto` or `cloud`:

- search two or three configured providers concurrently;
- use a short fast-first budget;
- return partial successful provider results with diagnostics when another
  provider fails or times out;
- merge and de-duplicate candidates;
- preserve `pii` / `no_store` classification when article context may contain
  personal data.

The Toolbox-side shape is:

- request `latency_mode=fast_first` for the initial modal result;
- render whatever image candidates arrive first;
- issue or consume a completion path for the remaining candidates without
  clearing already visible images;
- keep refresh tokens non-secret and avoid fields named like credentials.

## Source Recommendation UI

The source recommendation mode should be image-first:

- left side shows the candidate grid;
- grid target is up to nine images, three columns where space allows;
- cards are image-only by default;
- candidate captions, provider labels, and source text should not sit under
  every image;
- each image may have a top-right zoom button;
- the first returned candidate is selected by default;
- the right inspector shows actions and optional details for the selected
  candidate.

The right inspector should keep primary actions close together:

- `Adopt` applies or submits the selected image through the governed path for
  the current context;
- `Import only` imports media without making it the featured image when the
  selected context allows that path;
- both buttons should remain in one row where possible;
- button loading state must attach to the clicked action, not the neighboring
  action.

Details should be collapsed unless needed:

- media SEO fields are under `More SEO fields`;
- source and attribution details are under `Source details`;
- issue feedback is compact and secondary;
- Core record links should point to Core instead of rendering raw audit JSON in
  Toolbox.

Avoid explanatory text that only repeats implementation boundaries, such as
"Toolbox does not directly write media", inside the primary inspector. Keep
that detail in docs or advanced diagnostics.

## AI Generation UI

AI generation is available from the featured-image entry.

The expected sequence is:

1. Show a prompt textarea.
2. Show generation direction buttons when a visual brief or prompt candidates
   are available.
3. Let the operator click one direction to fill the prompt.
4. Enable `AI generate image` only when a prompt is present.
5. Generate the configured number of AI image candidates.
6. Show generated candidates in the same left grid and reuse the same selected
   image inspector.

The default generated candidate count is two, so the operator can compare
alternatives without starting a batch workflow.

The old `Generate prompt plan` wording was removed because it was unclear. The
UI should use `Generate prompt` only when there is no prompt and no usable
direction yet.

## Generation Directions

Generation directions are not a separate workflow and not a high-value refresh
surface. They are compact prompt-choice helpers.

Current rule:

- display directions as small pill-like buttons;
- show at most four direction buttons;
- use the candidate direction text returned by Cloud when available;
- fall back to localized compact labels only when Cloud does not provide a
  usable display label;
- de-duplicate repeated labels so the user does not see several identical
  buttons;
- clicking a direction fills the AI image prompt.

The visible `Refresh directions` control should not be shown. In practice it
created low confidence because refreshed directions often looked the same. If a
future version needs this function, it should be redesigned as `Change
direction set` and Cloud must return deliberately different families such as
editorial scene, concept metaphor, workflow detail, product scene, and privacy
detail.

## Preview Behavior

The zoom preview should behave like a dedicated image viewer:

- opening preview should not nest another modal inside the recommendation
  modal;
- the preview should replace or temporarily hide the recommendation modal;
- the image should use complete-fit display so the user does not need vertical
  scrolling to inspect the full image;
- close should return to the recommendation modal with the previous selection.

This avoids the previous problem where large images were cropped by modal
height, hidden behind the original modal, or required awkward scrolling.

## Error And Empty States

Common problems and expected handling:

- `runtime input appears to contain personal data and must use
  data_classification=pii`: article context can contain personal data; image
  source and AI image runtime requests must preserve or set `pii`.
- `runtime input contains secret-like data at input.visual_context.refresh_token`:
  do not name non-secret refresh variants as tokens.
- `X-Npcink-Timestamp header is outside the accepted time window`: usually a
  client/server clock or request signing time-window issue, not an image UI
  issue.
- `http://127.0.0.1:8010` timeout: Cloud Addon or local Cloud runtime bridge is
  not responding within the request budget; verify the local service before
  debugging the image grid.
- no image candidates: show short query chips or a concise empty state, not a
  large explanation card.

Loading states should not show a selected-image inspector placeholder before
there is a selected image. The first meaningful visual feedback should be the
grid or a compact loading indicator.

## Layout Decisions

The final layout direction is:

- modal title remains simple: `AI recommended featured image`;
- left side is the image grid;
- right side is the mode switch, input/prompt controls, selected actions, and
  collapsed details;
- source and generation modes share the same selected-image inspector after
  candidates exist;
- source-search input can live in the right inspector because the left side
  should stay focused on images;
- primary buttons should not all look identical when their risk differs.

Do not add cards or explanatory blocks that compete with the images. This is an
operator tool, so the default view should answer: choose which image, inspect if
needed, then adopt or import.

## Quality Rules

For source images:

- prefer concrete editorial imagery over abstract keyword matches;
- avoid watermarks, obvious screenshots, logos, credentials, or irrelevant
  brand marks;
- preserve source attribution and Unsplash download tracking metadata;
- keep provider and review details available but not noisy.

For AI-generated images:

- prompts must be reviewed or selected by the operator before generation;
- generated images are still `image_candidate.v1` suggestions;
- generation prompts and provider metadata should not be reused directly as
  media SEO fields;
- media title, alt, and description should be normalized from reviewed article
  context and candidate content.

## Verification Expectations

Default source-only gate:

```bash
composer test:all
```

Relevant static contracts should cover:

- distinct featured-image and paragraph-image entrypoints;
- fast-first source image loading and progressive completion;
- AI generation being available only for featured-image mode;
- no stale candidate loss when switching between source and generation;
- no visible `Refresh directions` control;
- at most four de-duplicated generation direction buttons;
- `pii` classification for image runtime calls that include article context;
- no direct Toolbox media import, media SEO write, or external featured-image
  write.

## Open Follow-Ups

- Cloud should keep improving diversity of prompt direction candidates, but the
  Toolbox UI should not expose a refresh button unless the result is visibly
  different.
- Cloud should return localized direction labels when possible so Toolbox does
  less inference.
- A browser smoke for the image modal would be useful after the layout stabilizes
  because many regressions are visual rather than PHP-contract failures.
