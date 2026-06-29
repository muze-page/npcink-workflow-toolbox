# WordPress.org Release Readiness Closeout - 2026-06-29

## Status

Accepted as the current WordPress.org submission readiness record for
`Npcink Workflow Toolbox` 0.1.1.

The release is ready for manual WordPress.org plugin review submission from the
current GitHub `master` branch, using the generated
`build/npcink-workflow-toolbox.zip` package.

## Public Identity

- Public plugin name: `Npcink Workflow Toolbox`
- WordPress.org requested slug: `npcink-workflow-toolbox`
- GitHub repository: `https://github.com/muze-page/npcink-workflow-toolbox`
- Local repository path: `/Users/muze/gitee/npcink-workflow-toolbox`
- Release package: `build/npcink-workflow-toolbox.zip`
- Release package root directory: `npcink-workflow-toolbox/`
- Main plugin file inside the package:
  `npcink-workflow-toolbox/npcink-workflow-toolbox.php`

This supersedes the earlier public/repository identity `npcink-toolbox` for
repository, package, and WordPress.org slug purposes.

## Compatibility Contracts Kept Intentionally

The rename was a public identity and package-slug change, not a runtime v2 API
migration. The first-version runtime contracts remain stable:

- REST namespace remains `/wp-json/npcink-toolbox/v1`.
- Ability ids remain under `npcink-toolbox/*`.
- Options, hooks, filters, and PHP class prefixes remain
  `npcink_toolbox_*` / `Npcink_Toolbox`.
- Admin page slug remains `npcink-toolbox`, so existing links such as
  `/wp-admin/admin.php?page=npcink-toolbox` continue to work.
- Final WordPress writes remain outside Toolbox except the already documented
  narrow Local Admin Consent featured-image proof.

Do not change these compatibility contracts during the WordPress.org submission
cycle unless a separate v2 migration plan and boundary decision is accepted.

## Work Completed

### Rename And Repository Identity

- Renamed the GitHub repository from `muze-page/npcink-toolbox` to
  `muze-page/npcink-workflow-toolbox`.
- Updated local `origin` to
  `https://github.com/muze-page/npcink-workflow-toolbox.git`.
- Renamed the local working directory to
  `/Users/muze/gitee/npcink-workflow-toolbox`.
- Updated repository path references in release, workflow, and cross-repo
  quality documentation.
- Updated `scripts/cross-repo-quality-matrix.php` to use
  `npcink-workflow-toolbox`.
- Updated the WordPress.org submission pack Development URL.

The repository identity change was merged through PR #30:

```text
8c4be9c Merge pull request #30 from muze-page/codex/repository-identity-rename
994a5a2 Rename repository identity to Npcink Workflow Toolbox
```

### Admin Operator UX Follow-Up Record

The backend operator-experience cleanup discussion was summarized separately in
`docs/admin-operator-ux-cleanup-summary-2026-06-29.md`.

That follow-up document records the next product stage: keep ordinary operators
on task-oriented surfaces, move single-post and single-image actions into their
natural WordPress context, keep Toolbox admin focused on batch work and
advanced diagnostics, and preserve Adapter/Core/Abilities governance for
write-like outcomes.

The UX summary was merged through PR #31:

```text
c2440f1 Merge pull request #31 from muze-page/codex/admin-operator-ux-summary
8d09f37 Document admin operator UX cleanup direction
```

## Release Package

Generate the release package from a clean `master` worktree:

```bash
composer package:release
```

The accepted package path for submission is:

```text
/Users/muze/gitee/npcink-workflow-toolbox/build/npcink-workflow-toolbox.zip
```

Current local package checksum after this documentation update:

```text
3fb55fd0470477cb94ad1283a595b7f963dfda43d95291d7a92207039ce487ad
```

The package should contain the top-level directory
`npcink-workflow-toolbox/`. Remove any stale local `build/npcink-toolbox.zip`
artifact before submission so the old package is not uploaded by mistake.

## Verification Completed

The release-prep branch and final `master` state were verified with:

```bash
composer validate --no-check-publish
composer check:wporg
composer test:all
WP_CLI_BIN=/opt/homebrew/bin/wp composer plugin-check:release
composer package:release
```

Observed results:

- Composer metadata is valid.
- WordPress.org review guard passed.
- Static contract and smoke checks passed through `composer test:all`.
- Plugin Check completed with no errors.
- GitHub required checks passed before both release-readiness PRs were merged.
- Final `master` was clean and synced with `origin/master` after merge.

## Local WordPress Smoke

The final local install/activation smoke used:

- Site URL: `https://magick-ai.local`
- WordPress path: `/Users/muze/Local Sites/magick-ai/app/public`
- WP-CLI: `/opt/homebrew/bin/wp`
- Package: `build/npcink-workflow-toolbox.zip`

Local.app required the active MySQL socket to be passed to PHP for WP-CLI
commands. The working socket for this site was:

```text
/Users/muze/Library/Application Support/Local/run/NPb24Zg9g/mysql/mysqld.sock
```

The smoke installed the zip with `--force`, activated
`npcink-workflow-toolbox`, and confirmed:

- plugin status: `Active`;
- plugin name: `Npcink Workflow Toolbox`;
- version: `0.1.1`;
- active plugin path:
  `npcink-workflow-toolbox/npcink-workflow-toolbox.php`;
- stale local `active_plugins` entry
  `npcink-toolbox/npcink-workflow-toolbox.php` was removed from the test site;
- admin page `/wp-admin/admin.php?page=npcink-toolbox` returned `200` after
  local administrator login;
- page title and H1 rendered `Npcink Workflow Toolbox`;
- anonymous `/wp-json/npcink-toolbox/v1/status` returned `401`, as expected for
  a capability-gated route;
- administrator-context `/wp-json/npcink-toolbox/v1/status` returned `200`.

The local administrator credentials were supplied interactively for this smoke
only and are intentionally not recorded in repository documentation.

## WordPress.org Submission Checklist

Use these values for the plugin review form:

- Plugin name: `Npcink Workflow Toolbox`
- Suggested slug: `npcink-workflow-toolbox`
- Development URL: `https://github.com/muze-page/npcink-workflow-toolbox`
- Zip upload:
  `/Users/muze/gitee/npcink-workflow-toolbox/build/npcink-workflow-toolbox.zip`

Before uploading, run the final narrow gate again from a clean worktree:

```bash
composer check:wporg
WP_CLI_BIN=/opt/homebrew/bin/wp composer plugin-check:release
composer package:release
```

If any source, docs included in the package, `readme.txt`, or translations
change after this closeout, regenerate the zip and record the new checksum in
the submission notes before uploading.

## Next Stage

After the plugin is submitted:

1. Track WordPress.org review feedback and fix whole pattern classes, not only
   cited lines.
2. Keep any review-response changes scoped and submit them through GitHub PRs.
3. Do not expand runtime behavior while review is pending unless the reviewer
   explicitly requires it.
4. Use `docs/admin-operator-ux-cleanup-summary-2026-06-29.md` as the next
   product-stage guide after submission: clean up backend operator surfaces,
   move single-item actions to editor/media-library contexts, and keep batch
   or advanced diagnostics in Toolbox admin.
