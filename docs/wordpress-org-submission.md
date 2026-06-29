# WordPress.org Submission Pack

Status: prepared for the Npcink Workflow Toolbox 0.1.1 WordPress.org plugin submission.

## Plugin Details

- Plugin name: Npcink Workflow Toolbox
- Suggested slug: npcink-workflow-toolbox
- Version: 0.1.1
- Requires at least: 6.9
- Tested up to: 7.0
- Requires PHP: 8.0
- License: GPLv2 or later
- Tags: ai, seo, editorial-workflow, media, content
- Development URL: https://github.com/muze-page/npcink-toolbox

## Short Description

Fixed AI workflow buttons for WordPress operators, with review-only suggestions and governed handoff plans.

## Reviewer Note

Npcink Workflow Toolbox is a review-only WordPress operator surface for fixed AI-assisted content and site-operations workflows. It returns suggestions, candidates, previews, and governed handoff plans. It does not publish posts, approve proposals, import media, create terms, update SEO metadata, mutate media metadata, or run a local workflow queue as part of the default content-support flow.

Cloud-backed features require a connected Npcink Cloud Addon or compatible host runtime. Npcink Cloud is the official Npcink hosted runtime service. The plugin does not include or hard-code a Cloud service endpoint; administrators connect the runtime through a companion connector or host-provided filters.

## External Services Disclosure

The WordPress.org-facing `readme.txt` includes the external services disclosure for:

- Npcink Cloud runtime
- Unsplash
- Pixabay
- Pexels

Npcink Cloud service documents:

- Terms of Service: https://cloud.npc.ink/terms/en/terms.html
- Privacy Policy: https://cloud.npc.ink/terms/en/privacy.html
- Data Retention: https://cloud.npc.ink/terms/en/data-retention.html

## Directory Assets

Source files are kept in `sj/`. WordPress.org-ready PNGs are prepared in `wporg-assets/`.

Upload or copy these files to the WordPress.org plugin SVN top-level `assets/` directory:

- `wporg-assets/icon-128x128.png` -> `assets/icon-128x128.png`
- `wporg-assets/icon-256x256.png` -> `assets/icon-256x256.png`
- `wporg-assets/banner-772x250.png` -> `assets/banner-772x250.png`
- `wporg-assets/banner-1544x500.png` -> `assets/banner-1544x500.png`

These assets are intentionally excluded from the plugin release zip by `.distignore`.

## Release Package

Use the release zip generated from the intended release commit:

```bash
composer package:release
```

For a mixed worktree, generate the release package from a clean worktree or release tag so unrelated local edits do not enter the zip.

## Validation Commands

Before submitting the plugin zip:

```bash
composer check:wporg
WP_CLI_BIN=/opt/homebrew/bin/wp composer plugin-check:release
composer test:all
```
