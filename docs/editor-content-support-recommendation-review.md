# Editor Content Support Recommendation Review

Status: active implementation guidance after the 2026-06-12 editor-sidebar
review.

This document records the product boundary, current recommendation flows,
observed UI issues, fixes, and next implementation guidance for the Npcink
Content Support editor sidebar.

## Product Goal

The editor sidebar should improve writing efficiency before publication without
taking writing authority away from the human editor.

The useful product shape is not an autonomous article writer. It is a set of
small, repeatable recommendation tools that help the editor review draft
metadata, supporting evidence, images, links, and publish readiness.

The target operating pattern is:

```text
Current draft context
-> hosted AI / Site Knowledge / image-source recommendation
-> structured candidates in the editor sidebar
-> human chooses, edits, rejects, or asks AI to regenerate
-> accepted write-like changes go through Core review or explicit local consent
```

## Non-Negotiable Boundary

AI output is advisory. Toolbox may generate candidates, explain evidence, and
package reviewed handoffs. It must not silently apply final article, taxonomy,
media, SEO, or link changes.

The editor can choose a concrete candidate, such as "use this title" or "use
this summary", because that is an explicit human action in the editor. Write-like
metadata, taxonomy, SEO, media import, and other durable changes remain
Core-governed unless a future strong-local-confirmation contract is explicitly
defined and tested.

Boundary rules:

- AI can suggest; humans decide.
- Regeneration may accept operator preferences such as tone, length, angle, or
  taxonomy preference.
- Operator instructions are preference context, not factual source material and
  not authorization to write.
- Raw provider output is diagnostic material, not the primary result surface.
- Cloud owns hosted AI, image-source, Site Knowledge runtime detail, vector
  infrastructure, and search availability.
- Toolbox owns the fixed editor buttons, result rendering, review selection,
  and Core handoff packaging.
- Core and Adapter own proposal approval, preflight, audit, and final execution
  policy.

## Recommended Entry Points

The sidebar should keep the high-frequency tools separate. A single all-in-one
button can exist later as a package, but the author needs fast focused entry
points first.

| Entry point | Current purpose | Expected editor action | Write boundary |
| --- | --- | --- | --- |
| Title suggestions | Generate reviewable title options from current draft context. | Choose one title or regenerate with a preference. | Explicit editor title update only after user click. |
| AI generate summary | Generate two or three excerpt candidates. | Choose one summary or regenerate with a preference. | Explicit editor excerpt update only after user click; broader metadata handoff remains Core-governed. |
| Tag suggestions | Recommend matching existing tags and preserve vocabulary gaps. | Review existing tags; treat proposed new tags as notes. | No direct term creation. Accepted taxonomy changes go through Core handoff or future confirmed path. |
| Category suggestions | Recommend matching existing categories. | Review existing categories in the editor context. | No automatic assignment. Accepted taxonomy changes go through Core handoff or future confirmed path. |
| Find image candidates | Search or generate candidate images with source/model context. | Select, inspect, or hand off adoption/import. | Media import and featured-image adoption remain Core/Adapter governed unless local consent explicitly applies to an existing attachment. |
| Find internal links | Search Site Knowledge for related public content. | Manually review relevance and insert links if appropriate. | No automatic anchor insertion or block mutation. |

## Result Surface Rules

Each result view should show only the result for the active focused tool unless
the active tool is intentionally a combined metadata package.

Default order:

1. Focused result title and short review instruction.
2. Optional "my request for this suggestion" input for regeneration.
3. Candidate list or image cards.
4. Human action buttons for explicit local editor changes when safe.
5. Evidence and diagnostics behind an explicit disclosure or modal.
6. Core handoff controls only where write-like changes are being packaged for
   review.

The result surface should avoid:

- showing raw JSON as a candidate card;
- repeating the same heading inside the focused result;
- mixing stale results from a previous button into the current result;
- leaving newly added editor strings untranslated;
- using button copy that implies AI has approved or executed a final write.

## Issues Found And Fixed

### Title Suggestions

Problem: hosted AI could return a JSON object containing `title_options`,
`excerpt`, and other fields, but the sidebar rendered the whole JSON object as
one "Hosted AI suggestion" card. Some labels were also untranslated.

Fix: parse hosted JSON from `output_json`, nested runtime result objects, fenced
JSON, or embedded JSON text. Render only title options as separate candidates
and hide the duplicate inner result heading in the focused title view.

Relevant commits:

- `7c192e7 Fix title suggestion rendering`

### Other Recommendation Entrypoints

Problem: category/tag/image ALT focused views could repeat headings or display
structured hosted output as generic text. Internal-link and image controls also
had missing Chinese translations.

Fix: add structured image ALT rendering, hide duplicate headings for focused
category/tag/image ALT result views, and add Chinese translations for internal
link, image candidate, image ALT, and summary quality UI strings.

Relevant commits:

- `11372c6 Fix editor recommendation rendering gaps`

### Cross-Flow Result Mixing

Problem: clicking title suggestions, then tag suggestions, could show both
title and tag results in one focused view. The metadata merge function preserved
all previous `sections`, so stale non-metadata sections leaked into the next
metadata result.

Fix: metadata result merging now keeps only the incoming sections plus the
merged `summary_terms_optimization` section. It still allows summary, category,
and tag metadata choices to compose, but it no longer carries old title, link,
image, or other unrelated sections into the active result.

Relevant commit:

- `d8add79 Improve AI summary full-draft quality gates`

## Current Implementation Shape

The important local contracts are:

- `assets/editor-content-support.js`
  - fixed editor sidebar buttons;
  - focused result rendering;
  - title/excerpt explicit editor update buttons;
  - structured hosted JSON parsing;
  - metadata merge behavior;
  - image-source and image ALT result rendering.
- `includes/Rest_Controller.php`
  - `/editor/content-support` intent routing;
  - summary/category/tag metadata artifacts;
  - internal-link candidate construction;
  - current-article image ALT snapshot routing.
- `includes/Provider_Client.php`
  - hosted AI content-support runtime payloads;
  - summary quality contract;
  - Cloud-managed image-source and site-helper boundaries.
- `tests/run.php`
  - static regression checks for fixed flow labels, structured rendering,
    suggestion-only posture, and stale-section merge prevention.

## What Is Good Enough Now

The current editor support surface is enough for a first usable "pre-publish
assistant" slice:

- authors can ask for focused title, summary, tag, category, image, and internal
  link recommendations;
- authors can add a short preference and rerun;
- title and summary candidates can be explicitly inserted by the author;
- taxonomy, SEO, media, and other write-like changes stay governed;
- raw AI or provider payloads no longer need to be the default display surface;
- the sidebar has regression checks for the stale-result and raw-JSON classes
  of bugs.

It is not yet a complete editorial automation system. That is intentional.

## Remaining Gaps

The next useful work is not adding more buttons. It is hardening this surface
around real editor behavior.

Priority gaps:

- Manual browser smoke: run each focused tool in a real editor session and
  capture before/after screenshots for title, summary, tag, category, image,
  and internal links.
- Translation coverage: add a script or test that compares editor
  `__('...', 'npcink-toolbox')` strings with the bundled editor script JSON.
- Result fixtures: add representative mocked Cloud/Site Knowledge responses for
  title JSON, summary JSON, empty tags, image ALT JSON, and internal links.
- UX copy pass: keep action buttons explicit, for example "Use this title",
  "Use this summary", "Create Core review proposal", and avoid wording that
  sounds like automatic approval.
- Handoff review: keep Core proposal payloads compact and reviewable before
  adding broader one-click packages.

## Next Implementation Recommendation

Build a "pre-publish package" only after the six focused tools remain stable.
That package should compose existing outputs into a review dashboard rather than
performing new writes.

The package can include:

- missing title/summary/category/tag/image/link checks;
- suggested title and summary candidates;
- existing category/tag candidates and proposed new tag gaps;
- image-source candidates or current-image ALT suggestions;
- internal-link candidates;
- SEO/AEO/GEO notes;
- one Core handoff preview for selected metadata or SEO fields.

The package must keep the same boundary: candidates first, human selection
second, governed handoff third.
