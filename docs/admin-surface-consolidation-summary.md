# Admin Surface Consolidation Summary

Date: 2026-06-11

## Context

The Toolbox admin page was confusing because high-frequency article work,
fallback bundles, Cloud checks, Site Knowledge status, and media operations all
competed for attention on the same surface. The work in this pass keeps Toolbox
as an operator-facing product surface while preserving the existing boundary:
Toolbox prepares suggestions, plans, checks, and handoff artifacts; final
WordPress writes remain with Core governance, Adapter preflight, and approved
Abilities.

## What Changed

The admin page now starts from a compact **Overview** tab for ordinary site
owners. It shows one plain-language recommended action (**Check my site**),
compact AI service / site profile / safe mode rows, and common task entries
before sending setup, diagnostics, fallback previews, and lower-frequency
workbench links to the single **Advanced** directory. The visible top-level tabs
are **Overview**, **Site Profile**, **Image Handling**, and **Advanced**.

The former broad content-support admin area is split into focused work
surfaces. **Image Handling** defaults to image tools, with **Batch Optimize
Images** as the first visible workbench. Single-image actions start from the
Media Library attachment details panel or image row actions and reuse the same
selected-image workbenches. The former single-article image text helper is
removed from the backend because it needs current editor context. A separate
**Batch Image Text Review** tool now builds a small selected media-library
review set and can prepare a Core handoff draft without creating proposals or
writing media metadata. Full-site Insights owns site content opportunity
review; the standalone Content Review tab and content opportunity tool are
retired. The reviewed draft handoff is no longer a backend tool and remains
available only through REST/Abilities for future import workflows. The retired
article assistant and article-plan URLs fall back to Full-site Insights instead
of restoring a daily writing path.
Deprecated `tab=image&tool=optimize` and
`toolbox_tab=tools&toolbox_tool=media-derivative` links now canonicalize to
Batch Optimize Images.

High-frequency article support stays in the post editor sidebar. Admin
Workflows no longer expose publish preflight, summary/category/tag support,
internal-link candidates, or image candidates as backend article buttons.
After comparing the local generic AI plugin Settings page, the editor sidebar
also demotes generic AI generation and diagnosis entries from the default
button list. Title, summary, taxonomy/tag, outline, article-checkup,
discoverability, current-article ALT, and comment-reply paths remain compatible
capabilities, while default visible buttons stay focused on Npcink review and
handoff work.

**Full-site Insights** is now the Overview page's primary site-check action
instead of a visible top-level tab. It keeps the stable `operations-insights`
deep-link panel for report URLs, nonce-protected scan links, and Cloud analysis
review detail.

**Connection Diagnostics** keeps the stable `cloud-checks` deep-link id as a
secondary diagnostics panel instead of a visible top-level tab. It now defaults
to one basic read-only connection check for ordinary operators. Search, image,
content library, and scheduled review diagnostics stay folded under detailed
diagnostics and open automatically for old deep links. Content Operations
coverage and Agent feedback quality summaries link out to Cloud Addon
Monitoring instead of rendering as Toolbox panels. Site Knowledge status and
refresh controls stay in the secondary **Content Library Setup** panel reached
from Advanced. The Advanced page is now the single low-frequency directory and
groups entries as Setup, Diagnostics, Review, and Planning/Handoff instead of
duplicating secondary links on Overview. Nightly Inspection local fallback
settings, Pro Cloud Runtime
quota/detail, and Cloud run recovery stay in detailed checks instead of
competing with the Start page's primary operator entries.

Site Profile now defaults to the four-field site brief: positioning, audience,
voice, and primary keywords. SEO, AEO, GEO, claim boundaries, and the read-only
ability payload preview remain available under one folded advanced guidance
area.

Site Knowledge status now distinguishes the Cloud index state from automatic
public-change delivery health. When Cloud Addon exposes the Site Knowledge
change bridge, Toolbox displays that bridge health and does not register its
legacy local auto-sync hooks. Standalone installs without the bridge now show a
Cloud Addon install-and-verify requirement instead of running a Toolbox-owned
fallback queue. Status values and common Cloud status messages are localized for
the zh_CN admin surface.

Media optimization defaults remain an independent settings form because they
submit to `options.php`, but they are now an attached settings panel for the
media tool rather than a second primary Workflow panel. The main operator flow
stays first: select media, generate preview, review metadata and preflight, then
submit one Core optimization review.

## Boundary Notes

This pass did not add a second workflow runtime, approval store, media registry,
or direct WordPress writer. It did not add `confirm_token`, `write_confirmed`,
direct publish, direct media mutation, or direct SEO writes.

Cloud remains the owner of hosted runtime detail, Site Knowledge embedding and
index detail, web search execution, image-source execution, and service-plane
quota/diagnostic detail. Toolbox displays read-only summaries and prepares
bounded handoff artifacts.

The XHTheme automation notice that appeared above the Toolbox page was caused by
the separate `xhtheme-ai-toolbox` plugin in the local WordPress install, not by
this repository.

## Verification

The following gates passed after the changes:

- `node --check assets/admin.js`
- `php -l includes/Admin_Page.php`
- `php -l tests/run.php`
- `msgfmt languages/npcink-workflow-toolbox-zh_CN.po -o languages/npcink-workflow-toolbox-zh_CN.mo`
- `composer test:all`
- `git diff --check`

Browser verification during the earlier consolidation pass confirmed:

- Image Handling opens by default to Image Tools and Batch Optimize Images.
- Full-site Insights owns site content opportunity review; reviewed draft write
  plans remain route/Ability-only.
- Site Knowledge returned `200` after Cloud quota was updated.
- Content Library Setup status rendered as ready with no console warning or
  error.

The final browser session was no longer logged in, so the last localization and
attached-panel adjustments were verified through static contracts and the full
test gate rather than another authenticated browser pass.

## Remaining Operational Note

Before committing future changes, keep unrelated local edits separate. In this
session `docs/development-workflow.md` was already dirty with WP-CLI/Local.app
workflow notes and should not be staged with the admin-surface change unless it
is intentionally reviewed as a separate documentation update.
