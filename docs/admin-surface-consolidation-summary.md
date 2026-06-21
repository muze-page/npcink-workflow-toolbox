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

The admin page now starts from a compact **Start** tab. It summarizes Cloud
runtime readiness, Site Context, Site Knowledge ownership, and final-write
posture, then shows the primary operator entries before folding setup,
diagnostics, fallback previews, and lower-frequency workbench links into an
advanced directory.

The former broad content-support admin area is now **Workflows**. It defaults to
**Media**, with **Optimize Existing Image** as the first visible tool. Site
Helpers remain available for low-frequency AI checks. Reviewed handoffs and
fallback article bundles are folded under an advanced/fallback disclosure so
they do not look like the daily writing path.

High-frequency article support stays in the post editor sidebar. Admin
Workflows no longer expose publish preflight, summary/category/tag support,
internal-link candidates, or image candidates as backend article buttons.

**Cloud Checks** keeps the stable `cloud-checks` deep-link id, but it now
presents search, image, Site Knowledge search verification, and Nightly
Inspection runtime detail as troubleshooting surfaces. Content Operations
coverage and Agent feedback quality summaries link out to Cloud Addon
Monitoring instead of rendering as Toolbox panels. Site Knowledge status and
refresh controls stay in the Site Knowledge tab. Nightly Inspection local
fallback settings, Pro Cloud Runtime quota/detail, and Cloud run recovery stay
here instead of competing with the Start page's primary
operator entries.

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
- `msgfmt languages/npcink-toolbox-zh_CN.po -o languages/npcink-toolbox-zh_CN.mo`
- `composer test:all`
- `git diff --check`

Browser verification during the session confirmed:

- Workflows opened by default to Media and Optimize Existing Image.
- Advanced/fallback Workflows stayed collapsed by default and deep-linked tools
  opened the disclosure correctly.
- Site Knowledge returned `200` after Cloud quota was updated.
- Site Knowledge status rendered as ready with no console warning or error.

The final browser session was no longer logged in, so the last localization and
attached-panel adjustments were verified through static contracts and the full
test gate rather than another authenticated browser pass.

## Remaining Operational Note

Before committing future changes, keep unrelated local edits separate. In this
session `docs/development-workflow.md` was already dirty with WP-CLI/Local.app
workflow notes and should not be staged with the admin-surface change unless it
is intentionally reviewed as a separate documentation update.
