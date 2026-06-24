# Editor Quality Session Closeout - 2026-06-24

Status: local documentation note for the completed AI development session.

## Scope

This session focused on keeping AI-assisted development quality high without
adding more governance complexity to the product. The working module was
`npcink-toolbox`, specifically the editor content-support experience and its
browser smoke coverage.

The active product boundary remained unchanged:

- Toolbox is the WordPress operator-facing suggestion and planning surface.
- Toolbox must not become a write executor, queue/runtime owner, second
  approval store, media registry, or workflow control plane.
- Editor features should remain review-only unless a separate Core/Abilities
  handoff or explicit Local Admin Consent boundary applies.

## Completed Work

### Editor Image Search Latency Bound

Commit: `38f8292 Improve editor image search latency bounds`

Files changed:

- `assets/editor-content-support.js`
- `tests/run.php`

What changed:

- Added an 8 second client-side timeout for editor image-source fast-first
  Cloud requests.
- Added a dedicated image search timeout code:
  `npcink_toolbox_image_source_timeout`.
- Capped automatic empty-result fallback image-source retries to 2 queries.
- Preserved manual query suggestions so operators can continue searching after
  an empty result.
- Added static contracts so the image picker does not regress into unbounded
  Cloud waiting.

Verification performed:

- `composer test:all` passed at the time of the commit.
- `git diff --check` passed.
- `composer smoke:editor-progressive-recommendations` passed.
- `composer smoke:editor-progressive-local-matrix` passed.
- `composer test:editor-progressive-js` passed.
- `WP_CLI_BIN=/opt/homebrew/bin/wp composer smoke:ai-image-media-seo` passed.

### Editor Progressive Browser Smoke Reliability

Commit: `3814140 Fix editor progressive browser smoke reliability`

Files changed:

- `tests/smoke-editor-progressive-browser.mjs`
- `tests/run.php`

What changed:

- Replaced brittle hand-built WordPress auth cookies with a temporary local Web
  login helper that calls `wp_set_auth_cookie()` in the same Web context as the
  browser test.
- Let the helper create a temporary draft post in the Web context when `POST_ID`
  is not provided, then clean it up after the smoke.
- Added `core/interface` as a sidebar-opening fallback, matching the product
  code path more closely.
- Dismissed WordPress editor welcome modal overlays before clicking Toolbox
  controls.
- Switched button interaction to text-filtered button locators to handle
  WordPress component output across editor versions.
- Added failure diagnostics: screenshot path, current URL, page title, REST
  request count, visible text sample, and the original error.

Verification performed:

- `NODE_PATH="/Users/muze/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules" composer smoke:editor-progressive-browser`
  passed.
- `node --check tests/smoke-editor-progressive-browser.mjs` passed.
- `git diff --check` passed.
- Static contracts for the browser smoke were updated and passed when run
  against this change.

## Important Findings

The browser smoke failure was not caused by the image-search latency change.
The failures were test-environment issues:

1. The initial Playwright run did not have `NODE_PATH` pointing to the bundled
   runtime, so `playwright` could not be resolved.
2. After that was fixed, the test was redirected to `wp-login.php?reauth=1`
   because manually generated auth cookies were not accepted in the Web
   request path.
3. A WP-CLI-created post could be missing from the browser-visible Web site,
   indicating the local WP-CLI socket/path can drift from the Web runtime.
4. The WordPress welcome modal could intercept clicks on the Toolbox sidebar.

The repaired smoke now creates and authenticates through the same Web context
that the browser uses, which makes it a better end-to-end editor check.

## Follow-Up Work Resolved

The later audio-candidate work was completed as a separate commit:

Commit: `fea8ba7 Add editor article audio candidates`

Files changed:

- `assets/editor-content-support.css`
- `assets/editor-content-support.js`
- `docs/content-support-product-readiness.md`
- `includes/Provider_Client.php`
- `includes/Rest_Controller.php`
- `languages/npcink-toolbox-zh_CN-npcink-toolbox-editor-content-support.json`
- `languages/npcink-toolbox-zh_CN.mo`
- `languages/npcink-toolbox-zh_CN.po`

What changed:

- Added `article_narration` and `article_audio_summary` editor content-support
  flows.
- Routed bounded article text or an AI-generated listening summary script to
  Cloud audio generation through `audio_generation_request.v1`.
- Rendered review-only audio candidates in the editor with script preview,
  provider/model detail, and audio playback links.
- Added Chinese UI translations for the new audio entries.
- Documented the product boundary in
  `docs/content-support-product-readiness.md`.

Boundary kept:

- No media upload or import from Toolbox.
- No post-content insertion.
- No local audio queue or scheduler.
- No provider key ownership.
- No direct WordPress write authority.

Verification performed after the audio commit:

- `php -l includes/Provider_Client.php` passed.
- `php -l includes/Rest_Controller.php` passed.
- `node --check assets/editor-content-support.js` passed.
- Editor content-support translation JSON parsed successfully.
- `composer test:all` passed.

## Recommendation

Stop this session here.

The editor image-search reliability work and the article-audio candidate work
are now separated into their own commits. Future editor work can continue from a
clean worktree, but should keep the same boundary: editor outputs remain
review-only unless a separate Core/Abilities handoff or explicit Local Admin
Consent contract exists.
