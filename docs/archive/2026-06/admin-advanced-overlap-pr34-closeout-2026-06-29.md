# Admin Advanced Overlap PR #34 Closeout - 2026-06-29

Status: merged into `master` through PR #34.

## Context

This session started from a review of the Toolbox admin page at:

`/wp-admin/admin.php?page=npcink-toolbox&tab=advanced`

The practical question was whether some functions were duplicated. The answer
was yes at the operator-entry level, not at the underlying contract level:

- Overview repeated low-frequency Advanced links that already belonged in the
  Advanced directory.
- The visible label **AI Service Checks** made connection diagnostics look like
  another AI feature surface.
- Some old tool URLs and route-compatible intents still existed, but those were
  compatibility paths, not current default product entries.

The boundary remained unchanged: Toolbox is the WordPress operator-facing
surface for review-only suggestions and handoffs. It must not become a generic
AI admin product, provider control plane, request log, connector approval UI,
workflow runtime, approval store, or direct WordPress write executor.

## Decisions

The approved next-step sequence was:

1. Remove duplicate Advanced links from Overview.
2. Make Advanced the single low-frequency directory.
3. Rename visible **AI Service Checks** copy to **Connection Diagnostics** while
   preserving the stable `toolbox_tab=cloud-checks` deep link.
4. Verify existing media-library entries and old URL fallback instead of
   rebuilding them.
5. Record the AI-plugin overlap and admin-surface closeout in local docs.
6. Commit, push through a protected-branch PR, and merge after required checks.

## What Changed

PR #34 contains two local commits:

- `49dbc90 Consolidate advanced toolbox directory`
- `c2b9882 Document AI overlap closeout`

The implementation changed the admin surface as follows:

- Overview keeps one primary site-check action, one media-library image entry,
  one site-profile entry, and one **Open advanced tools** link.
- Overview no longer renders a duplicate folded Advanced directory.
- Advanced now groups low-frequency entries by operator job:
  - Setup
  - Diagnostics
  - Review
  - Planning and handoff
- The old Cloud Checks surface is presented to ordinary operators as
  **Connection Diagnostics**.
- The internal/deep-link id `cloud-checks` remains stable.
- Content Library Setup, Full-site Insights detail, and Morning Brief preview
  remain secondary/read-only entries.
- Media Library single-image and bulk image actions remain the productized image
  workflow entry points, avoiding a duplicate backend one-image picker.

Docs, translations, and static contracts were updated alongside the UI change.

## Verification

Local verification before the PR:

- `php -l includes/Admin_Page.php`
- `composer test:all`
- WP-CLI plugin status on `https://magick-ai.local`
- authenticated WordPress admin render checks for:
  - Overview
  - `toolbox_tab=advanced`
  - `toolbox_tab=cloud-checks`

During the local admin render smoke, the `magick-ai.local` plugin copy was first
synchronized to this worktree because the Local.app plugin directory was not a
symlink to the repo.

GitHub verification on PR #34:

- `PHP contracts`: passed
- `PR body contract`: passed after the PR body was corrected to use Markdown
  headings for `Scope`, `Boundary`, `Verification`, and `Risk`

PR #34 was merged into `master` with merge commit:

`e4e99fecc3ef37fb6ca69974db940fff0ea27254`

After merge:

- local `master` was fast-forwarded to `origin/master`;
- the temporary branch `codex/advanced-overlap-closeout` was deleted remotely
  and pruned locally;
- the worktree was clean.

## Current State

The local and remote repository are synchronized at:

`e4e99fe Merge pull request #34 from muze-page/codex/advanced-overlap-closeout`

No further action is required for this stage.

## Follow-Up Rule

For future Toolbox admin work:

1. Put common operator actions on Overview.
2. Put low-frequency setup, diagnostics, review detail, and handoff previews in
   Advanced.
3. Preserve stable deep links when demoting or renaming visible entries.
4. Keep generic AI-plugin-style abilities as route/rendering compatibility
   unless they are reclassified as Npcink workflow entries.
5. If a feature needs provider configuration, request logs, connector approval,
   queues, schedulers, approval truth, or direct writes, write a boundary note
   instead of implementing it inside Toolbox.
