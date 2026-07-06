# WordPress.org Publication And Translation Closeout - 2026-07-03

## Status

Accepted as the current post-approval publication and zh_CN translation
preparation record for `Npcink Workflow Toolbox` 0.1.1.

The plugin has been published to WordPress.org under the intended public slug:

```text
npcink-workflow-toolbox
```

The first public SVN publish completed at:

```text
https://plugins.svn.wordpress.org/npcink-workflow-toolbox/
Revision: 3594652
Author: muze233
Date: 2026-07-03 09:35:02 +0800
```

Published SVN areas:

- `trunk/`
- `tags/0.1.1/`
- `assets/`

## Scope And Boundary

This closeout records release operations only. It does not change the Toolbox
runtime boundary:

- the public WordPress.org slug is `npcink-workflow-toolbox`;
- the runtime REST namespace remains `/wp-json/npcink-toolbox/v1`;
- ability ids remain under `npcink-toolbox/*`;
- options, hooks, filters, and PHP class prefixes remain
  `npcink_toolbox_*` / `Npcink_Toolbox`;
- Toolbox remains a review-only operator surface and does not own final
  WordPress write approval, provider billing, queues, approval truth, or
  long-term runtime state.

## Work Completed

### Review Feedback And Resubmission

WordPress.org pre-review feedback for the 0.1.1 submission raised three main
classes of issues:

1. brand ownership clarity for the `Npcink` public name when submitted by
   `muze233`;
2. request-read patterns using unsafe raw input reads;
3. the WordPress.org-facing `readme.txt` Contributors line missing `muze233`.

The fixes were handled as pattern corrections, not one-off line edits:

- the WordPress.org review guard was updated to block recurring review-visible
  request and admin-output patterns;
- Plugin Check was made an explicit release gate;
- the `readme.txt` Contributors line was aligned with the submitting account;
- the reviewer reply clarified that `Npcink Workflow Toolbox` is an official
  Npcink product and should keep the requested slug.

The important process correction is captured in
`docs/wordpress-org-release-gate.md`: `composer test:all` and local static
contracts are not enough for directory review readiness. Plugin Check and a
manual brand/submitter gate are required before upload.

### Slug Correction

During review, WordPress.org temporarily showed the assigned slug as:

```text
npcink-toolbox
```

That was not the intended public release slug. The requested public identity
remained:

```text
Plugin name: Npcink Workflow Toolbox
Slug: npcink-workflow-toolbox
```

The likely source of confusion was the deliberate compatibility split:

- public repository/package/WordPress.org identity moved to
  `npcink-workflow-toolbox`;
- runtime contracts intentionally kept `npcink-toolbox` for REST routes,
  abilities, options, hooks, and existing integrations.

The accepted outcome is that WordPress.org, SVN, and translate.wordpress.org
now use `npcink-workflow-toolbox`. Future reviewer notes must state this split
explicitly so compatibility names are not mistaken for the requested public
slug.

### Listing Assets And Submission Materials

The WordPress.org listing material was prepared and checked before publication:

- description and short description;
- FAQ;
- screenshots;
- icons and banners;
- external-services disclosure for Npcink Cloud, Unsplash, Pixabay, and Pexels;
- package root and plugin header alignment.

The screenshot asset update was committed locally as:

```text
88e5eed Add WordPress.org release screenshots
```

That commit remains local at this closeout point unless it is pushed in a later
publication step.

### Package And Plugin Check Verification

The release package and directory-review gates were checked before SVN
publication:

```bash
composer release:verify
WP_CLI_BIN=/opt/homebrew/bin/wp composer plugin-check:release
```

Observed result:

```text
Success: Checks complete. No errors found.
```

The final package used the intended root directory:

```text
npcink-workflow-toolbox/
```

### SVN Publication

The accepted plugin was published through WordPress.org SVN under the intended
slug:

```text
https://plugins.svn.wordpress.org/npcink-workflow-toolbox/
```

The first public release tag is:

```text
tags/0.1.1/
```

Do not mix the WordPress.org SVN release path with the older compatibility
runtime name `npcink-toolbox`.

## Translation Status

The zh_CN translation project is available at:

```text
https://translate.wordpress.org/projects/wp-plugins/npcink-workflow-toolbox/stable/zh-cn/default/
```

After the initial manual import, GlotPress showed:

```text
Translated (Current): 0
Waiting: 1,339
Untranslated: 515
Fuzzy: 0
Submitter: Npcink (muze233)
```

This means the import succeeded, but the account did not yet have effective
permission for the imported strings to become current translations. Until PTE
approval is active, imported translations land in `Waiting`.

## Local Translation Preparation

A full stable-project zh_CN import file was prepared locally from the
translate.wordpress.org stable export.

Initial GlotPress stable export:

```text
1339 translated messages, 515 untranslated messages.
```

After merging existing local translation sources:

- `languages/npcink-workflow-toolbox-zh_CN.po`;
- `languages/npcink-workflow-toolbox-zh_CN-npcink-toolbox-admin.json`;
- `languages/npcink-workflow-toolbox-zh_CN-npcink-toolbox-editor-content-support.json`;

the intermediate prepared PO reached:

```text
1507 translated messages, 347 untranslated messages.
```

The remaining untranslated strings were mostly newer editor, Core handoff,
Site Check, Cloud Addon, hosted image, article audio, and taxonomy guidance
copy. Those were filled locally, and Chinese source strings were copied through
as same-language translations where appropriate.

Final local prepared files:

```text
build/i18n/npcink-workflow-toolbox-zh_CN.wordpress-org-stable.po
build/i18n/npcink-workflow-toolbox-zh_CN.wordpress-org-stable.mo
```

Final local gettext statistics:

```text
1854 translated messages.
0 untranslated.
0 fuzzy.
```

These files are release-preparation artifacts under ignored `build/` output.
They are intentionally not committed and do not change the bundled runtime
translation files. Upload the `.po` file to GlotPress after PTE permission is
active, or import it earlier only if `Waiting` translations are acceptable.

## Automation Notes

The in-app browser could not automate the GlotPress file upload reliably:

- the upload input did not expose the expected file-setting method;
- the page sandbox did not expose `FormData`, `fetch`, or
  `XMLHttpRequest` for a scripted import fallback.

The practical path is:

1. prepare and validate the PO locally;
2. upload it manually through the GlotPress import page;
3. confirm whether strings become `Current` or remain `Waiting` depending on
   PTE status.

## Root Causes

### Review Gate Was Too Narrow

The earlier release closeout treated internal tests and static contracts as
sufficient. They were not enough for WordPress.org review because directory
review catches policy and presentation issues that are outside normal runtime
contracts.

Correction:

- keep `composer test:all` for internal product contracts;
- keep `composer check:wporg` for recurring static review patterns;
- always run `WP_CLI_BIN=/opt/homebrew/bin/wp composer plugin-check:release`
  before upload;
- keep brand ownership and submitter identity as a manual release gate.

### Public Slug And Runtime Compatibility Names Were Easy To Confuse

The product intentionally kept `npcink-toolbox` runtime contracts while moving
public WordPress.org identity to `npcink-workflow-toolbox`. That split is valid,
but it must be stated clearly in reviewer replies and release docs.

Correction:

- use `npcink-workflow-toolbox` for WordPress.org package, SVN, translations,
  and public listing;
- use `npcink-toolbox` only for existing runtime contracts;
- do not rename runtime contracts during a WordPress.org slug correction.

### GlotPress Stable Strings Did Not Match The Bundled Local PO One-To-One

The stable project included strings that were already translated in local
JavaScript JSON files but not present as current GlotPress stable translations.

Correction:

- export the current GlotPress stable PO first;
- merge in local `.po` and JavaScript JSON translation sources;
- compile with `msgfmt`;
- check untranslated and fuzzy counts before import.

### PTE Status Controls Whether Imported Strings Become Current

Import success does not mean the strings are live. Without active PTE
permission, GlotPress keeps imported strings in `Waiting`.

Correction:

- submit or confirm the PTE request first;
- after PTE approval, re-import the prepared PO if needed so translations can
  become `Current`;
- verify the stable zh_CN project after import.

## Future Release Checklist

Before the next WordPress.org upload:

```bash
git status --short --branch
composer validate --no-check-publish
composer check:wporg
composer test:all
WP_CLI_BIN=/opt/homebrew/bin/wp composer plugin-check:release
composer package:release
```

Before translation import:

```bash
curl -L 'https://translate.wordpress.org/projects/wp-plugins/npcink-workflow-toolbox/stable/zh-cn/default/export-translations/?filters%5Bstatus%5D=current_or_waiting_or_fuzzy_or_untranslated&format=po' \
  -o /tmp/npcink-workflow-toolbox-zh_CN.wporg-stable.po
msgfmt --statistics -o /tmp/npcink-workflow-toolbox-zh_CN.wporg-stable.mo \
  /tmp/npcink-workflow-toolbox-zh_CN.wporg-stable.po
```

Then merge any missing local JSON/PO translations, validate again with
`msgfmt`, and import the final `.po` through GlotPress.

## Current Follow-Up

- Push the local release screenshot commit when ready.
- Confirm PTE approval status for
  `wp-plugins/npcink-workflow-toolbox/stable/zh-cn/default`.
- Re-import
  `build/i18n/npcink-workflow-toolbox-zh_CN.wordpress-org-stable.po` after PTE
  approval if the existing imported strings remain in `Waiting`.
- Verify the zh_CN project shows the expected `Current` translation count.
