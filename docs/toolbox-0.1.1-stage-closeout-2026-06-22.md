# Toolbox 0.1.1 Stage Closeout - 2026-06-22

## Status

Accepted as the current local release-prep closeout for `npcink-toolbox` 0.1.1.

The current product position is stable enough for a WordPress.org-style review
package, subject to final human UI confirmation and public listing assets.
Toolbox remains the WordPress operator-facing AI tool surface: fixed buttons,
review panels, suggestion artifacts, and governed handoff plans around
human-written articles.

## Positioning Decisions

1. **Npcink Toolbox is a brand name.** Keep `Npcink Toolbox` untranslated in the
   plugin name and header. Translate action labels such as `Settings` to
   Chinese in the WordPress UI.
2. **Toolbox is the fixed-button surface for OpenClaw best practices.** It
   turns repeatable OpenClaw-style flows into visible WordPress buttons, while
   reusing the same ability ids, plan artifact shapes, Adapter/Core handoff
   posture, and no-write contract.
3. **Cloud features stay Cloud-managed.** Search, image-source runtime, Site
   Knowledge, hosted AI actions, Zhihu managed lanes, and Pro runtime detail
   are surfaced in Toolbox but executed by the connected Cloud Addon or host
   runtime.
4. **Toolbox does not become a second control plane.** It must not own Core
   governance truth, final WordPress writes, approval records, queues,
   schedulers, provider billing, key rotation, request logs, vector indexing,
   collection lifecycle, or OpenClaw projection truth.
5. **Outputs are suggestions and review artifacts.** Hot topics, writing
   support, media ALT/caption suggestions, image candidates, SEO/AEO/GEO
   guidance, and accepted choices remain review-only until a governed Core or
   Abilities path applies them.

## Work Completed In This Stage

### Product Boundary And Release Readiness

- Clarified the plugin's public positioning as review-only WordPress content
  support rather than an article generator, SEO autopilot, media import bot, or
  workflow scheduler.
- Updated WordPress.org-facing `readme.txt` for `0.1.1`, including the
  Dashboard Zhihu hot-topic pool, read-only Zhihu capability checks, and
  current-article media ALT/caption review positioning.
- Added `.DS_Store` to the release package exclusion list and regenerated the
  release zip without macOS hidden files.
- Fixed Plugin Check internationalization feedback by adding translator context
  for Dashboard widget placeholders.

### Cloud And Addon Boundary

- Moved cloud-owned execution assumptions out of Toolbox product ownership.
  Toolbox exposes Cloud runtime availability, advanced checks, and fail-closed
  diagnostics, but it does not store local Zhihu/Search provider keys or own
  Cloud execution state.
- Treated advanced Cloud checks as operator diagnostics. The Zhihu capability
  panel verifies connected Cloud managed lanes only; it does not generate,
  rewrite, publish, or write WordPress content.
- Preserved the rule that Cloud may return evidence and suggestion artifacts,
  but not WordPress write authority.

### Content Support And OpenClaw Projection

- Reframed Toolbox as the fixed-button projection of accepted OpenClaw best
  practices. Natural-language OpenClaw flows and Toolbox buttons should converge
  on the same review artifacts and governed handoff contracts.
- Kept article writing support around human-written posts: preparation, checks,
  title/outline/summary suggestions, taxonomy/tag review, internal-link
  candidates, image candidates, and publish-readiness guidance.
- Avoided productizing one-click article generation or automatic content
  mutation.

### Media ALT/Caption Review

- Kept media ALT/caption as a review set, not a media metadata writer.
- Defaulted the operator path to images already used by the selected/current
  article. Recent media-library sampling remains an explicit advanced fallback.
- Used eval-lab as an evidence accelerator for media ALT/caption candidate
  quality, including provider-backed multi-judge review and CSV-style
  human-confirmation output.
- Recorded that AI vision can be useful as optional Cloud-owned image context
  evidence, but local Toolbox should not add local vision model weight or claim
  pixel inspection when the product route only uses metadata.

### Zhihu Hot Topic Pool

- Added the WordPress Dashboard widget `知乎热榜选题` as a low-friction daily
  topic selection surface.
- The widget calls the existing Cloud-managed `zhihu_hot_topics` lane and uses
  cached Cloud hot-list output when available.
- Tightened the display table so machine identifiers and URL-like values do not
  appear as operator-facing topic signals.
- Kept the promise explicit: the widget helps decide "what may be worth
  researching today"; it does not generate drafts, rewrite content, or publish.

### Local Validation Environment

- `npcink.local` was used as a temporary test site only.
- Formal local development and final smoke confirmation should use
  `https://npcink.local/`.
- On `npcink.local`, the plugin is mounted as `npcink-toolbox`, active, and
  visible under the Npcink admin menu. The plugin list Settings action is
  translated as `设置`.

## Verification Completed

The 0.1.1 release-prep state was verified with:

```bash
composer validate --no-check-publish
WP_CLI_BIN=/opt/homebrew/bin/wp composer release:verify
composer package:release
```

Observed results:

- Composer metadata is valid.
- `composer test:all` passes through the release verify script.
- Static contract checks passed with `2157 passed`.
- WordPress.org review guard passed.
- Plugin Check completed with no errors when using `/opt/homebrew/bin/wp`.
- Release package was generated at `build/npcink-toolbox.zip`.
- The generated zip contains `npcink-toolbox/npcink-toolbox.php` and
  `npcink-toolbox/readme.txt`, with no `.DS_Store`.
- `npcink.local` reports `npcink-toolbox` active at version `0.1.1`.
- Admin HTML smoke confirmed:
  - plugin list contains `Npcink Toolbox`;
  - plugin Settings link is shown as `设置`;
  - Toolbox page includes current-article media scope
    `current_article_used_images`;
  - Dashboard contains `知乎热榜选题`;
  - Dashboard copy includes `不生成、不改写、不发布文章`.

## Current Git Baseline

At this closeout point, the release-prep commit is:

```text
37683a0 Prepare Toolbox 0.1.1 release
```

The local `master` branch is ahead of `origin/master` by one release-prep
commit before this documentation commit.

## Do Not Reopen By Default

Do not continue broad migration or feature expansion just because more code can
be moved:

- Do not move provider runtime, Cloud indexing, Site Knowledge vector lifecycle,
  OpenClaw projection truth, Core approval/audit truth, or final WordPress write
  paths into Toolbox.
- Do not turn media ALT/caption review into automatic proposal creation or
  direct media metadata writes without a separate Abilities/Core/Adapter apply
  path.
- Do not add local batch queues, Action Scheduler workers, leases, retries, or
  persistent run stores inside Toolbox for the current release stage.
- Do not make AI vision mandatory for ordinary installs; use it only as bounded
  Cloud/host evidence if a workflow-level contract exists.

## Recommended Next Stage

1. Human-confirm the final admin UX on `https://npcink.local/`.
2. Push the local commits when the visual confirmation is accepted.
3. Add WordPress.org listing assets: icon, banner, and screenshots for the
   Toolbox admin page, editor Content Support panel, Dashboard Zhihu topic
   pool, and media ALT/caption review set.
4. Run a final release package check after assets are added.
5. Defer new product work until public listing readiness is complete.
