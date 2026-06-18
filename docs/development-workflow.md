# Development Workflow

Status: active for the first Toolbox build.

## Start A Session

Run:

```bash
git status --short --branch
```

Read:

- `README.md`
- `docs/product-positioning.md`
- `docs/boundary.md`
- `docs/architecture.md`
- `docs/roadmap.md`
- `docs/decisions/ADR-001-toolbox-as-product-surface.md`
- `docs/decisions/ADR-003-local-admin-consent-boundary.md`

Then state the focused module and boundary before editing.

## Default Gate

Run:

```bash
composer test:all
```

This runs PHP syntax linting and static contract checks.

## Metadata Gate

Run when changing Composer metadata:

```bash
composer validate --no-check-publish
```

## Git Remote Gate

Before creating, pushing, or updating a PR branch, verify that local Git can
reach the configured remote without opening an interactive credential prompt:

```bash
gh auth status
gh auth setup-git
composer git:remote-check
```

This check runs `git ls-remote origin HEAD` with `GIT_TERMINAL_PROMPT=0` and a
30 second alarm. If it fails or times out, fix the Git credential or network
path before creating commits for a PR. Do not use GitHub's Git Data API for
normal branch publishing; it is only an emergency fallback and can create commit
objects that do not match the local commit SHA.

## WordPress Smoke Gate

When a local WordPress site and WP-CLI are available, mount or install the
plugin and activate it:

```bash
command -v wp
wp --info

WP_PATH="/path/to/wordpress"
wp --path="$WP_PATH" plugin activate npcink-toolbox
```

On this workstation the preferred global WP-CLI is `/opt/homebrew/bin/wp`.
Always confirm it with `command -v wp` and `wp --info` before plugin smoke
tests, Plugin Check, activation, or status checks. Do not assume the current
directory is a WordPress root; set `WP_PATH` explicitly or pass `--path` on
every `wp` command.

For Local.app sites, if `DB_HOST=localhost` and WP-CLI cannot connect to the
database, find the active Local MySQL socket and inject it through
`WP_CLI_MYSQL_SOCKET`:

```bash
find "$HOME/Library/Application Support/Local/run" -path '*/mysql/mysqld.sock' -print

WP_CLI_MYSQL_SOCKET="/path/to/Local/run/site-id/mysql/mysqld.sock"
php -d mysqli.default_socket="$WP_CLI_MYSQL_SOCKET" \
    -d pdo_mysql.default_socket="$WP_CLI_MYSQL_SOCKET" \
    "$(command -v wp)" --path="$WP_PATH" plugin status npcink-toolbox
```

Then verify:

- the plugin activates without fatal errors;
- `Npcink -> Toolbox` loads when a Npcink parent menu exists;
- `Tools -> Npcink Toolbox` loads when installed standalone without a
  Npcink parent menu;
- settings save;
- `/wp-json/npcink-toolbox/v1/status` returns the expected capability-gated
  status for an authenticated administrator;
- Abilities API discovery includes the Toolbox ability ids when the Abilities
  API is available.

For the post-editor metadata feedback loop, run:

```bash
composer smoke:metadata-delta
```

This dispatches `/wp-json/npcink-toolbox/v1/editor/content-support` with the
`summary_terms_optimization` intent against a local post and verifies that the
returned `content_metadata_delta` remains suggestion-only, points final writes
to Core proposals, that accepted metadata choices can build a dry-run
`/wp-json/npcink-toolbox/v1/flows/content-metadata-apply-plan` handoff without
term creation, that Core `/wp-json/npcink-governance-core/v1/proposals/from-plan`
creates one pending `plan_to_proposal_batch` review proposal, and that the
smoke does not mutate the sampled post.

For the post-editor progressive recommendation surface, run:

```bash
composer test:editor-progressive-js
composer smoke:editor-progressive-recommendations
composer smoke:editor-progressive-local-matrix
```

The source-only JavaScript contract verifies that automatic prefetch sends only
`progressive_recommendations`, does not trigger Cloud-backed writing support or
proposal handoff, keeps the 2.5 second fallback, uses the loaded-key cache, does
not let late responses overwrite newer fingerprints, and invalidates in-flight
requests on unmount.

The REST smoke dispatches `/wp-json/npcink-toolbox/v1/editor/content-support`
with the `progressive_recommendations` intent against a local post and verifies
the local-only recommendation set, 2.5 second UX budget, content fingerprint,
candidate cap, no empty matched-token copy, no stopword-only taxonomy evidence,
no direct WordPress writes, and no sampled post mutation.

The local matrix smoke adds a temporary draft fixture for the new-article path,
checks an existing post without mutating it, verifies title/body/excerpt/selected
block fingerprint changes, confirms the no-Cloud source layer, and checks that
Core handoff targets remain definition-only even when Core routes are present.

For a real editor lifecycle smoke with iframe editor and Block API behavior,
run the optional browser gate:

```bash
NODE_PATH="${NODE_PATH:-/Users/muze/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules}" composer smoke:editor-progressive-browser
```

This opens the local editor, opens the Npcink Content Support sidebar, verifies
the automatic local progressive request stays hidden on success, opens the
compact `Local suggestions` entry, checks candidate source/action labels,
clicks Refresh, and confirms no Cloud, Adapter, or Core proposal route is
called. It is intentionally outside `composer test:all` because it depends on a
running local WordPress site, WP-CLI login-cookie generation, Playwright, and a
local browser.

For the post-editor review artifact surface, run:

```bash
composer smoke:editor-review-artifacts
```

This dispatches `/wp-json/npcink-toolbox/v1/editor/content-support` with
`internal_links` and `publish_preflight` intents against a local post. It mocks
Cloud Site Knowledge, verifies `internal_link_candidates.v1`,
`pre_publish_review.v1`, duplicate-check evidence, and
`seo_meta_handoff_preview.v1`, creates one pending SEO Core review proposal
through Adapter `/proposals`, purges that fixture, and proves the sampled post
is not mutated.

For the Site Knowledge review handoff UI, run:

```bash
composer smoke:site-knowledge-review-ui
```

This is a source-only smoke. It verifies that the Site Knowledge admin surface
keeps review proposals operator-triggered, routes the handoff through
`/flows/site-knowledge-review-plan` and Adapter/Core from-plan intake, preserves
evidence refs, keeps Agent feedback as Cloud eval metadata, and does not call
REST, Adapter, Core proposal intake, or WordPress write paths.

For the bundled local automation runtime Phase 1 skeleton, run:

```bash
composer smoke:local-automation-runtime-replay
composer smoke:local-automation-runtime-negative-replay
composer smoke:local-automation-media-conversion-review-set
composer smoke:nightly-inspection-builder
composer smoke:nightly-inspection-manual-planner
composer smoke:nightly-inspection-snapshot-preview
composer smoke:nightly-inspection-basic-cron
composer smoke:nightly-inspection-cloud-batch-merge
composer smoke:nightly-inspection-cloud-ui
composer smoke:nightly-inspection-orchestration-boundary
```

This validates the `modules/local-automation-runtime/` dry-run replay fixture
against the `npcink_local_automation_runtime.v1` contract. It does not register
hooks, create runtime tables, schedule workers, acquire leases, retry actions,
process dead letters, approve Core proposals, call Adapter execution routes, or
write WordPress data.
The negative replay smoke mutates the fixture to prove scheduler, worker,
lease, direct-write, execution-status, and blocked-count drift fail closed.
The media conversion review-set smoke validates the first non-nightly governed
batch contract and proves that local queues and direct WordPress writes fail
closed.
The Nightly Inspection builder smoke validates deterministic scoring against a
fixture snapshot and confirms the `nightly_site_inspection_result.v1` preview
stays local, review-only, no-write, no-Cloud, and no-copy-generation.
The manual planner smoke wraps the same preview in a
`npcink_local_automation_runtime.v1` dry-run replay with
`manual_dry_run_preview_only` actions and verifies it still creates no cron,
worker, scheduler, lease store, Core proposal, or WordPress write.
The snapshot preview smoke verifies the administrator-started local collector
can read bounded public post/page and image evidence, feed the manual planner,
and still avoid REST routes, cron, Cloud calls, Core proposals, persistence, and
WordPress writes.
The Basic WP-Cron dry-run smoke verifies the disabled-by-default scheduler
settings, bounded latest-preview option, no Cloud calls, no Core proposal, no Action Scheduler, no custom tables, and no WordPress content writes.
The Cloud Batch merge smoke verifies that Cloud runtime detail and
`pro_cloud_runtime` quota display remain review-only: Cloud may return scoring,
status, result, and entitlement detail, while local scheduling, Core proposals,
and WordPress writes stay outside Toolbox.
The Cloud Runtime UI smoke is a source-only contract check. It verifies the
Start panel's Pro Cloud Runtime quota hooks, short automatic submit/status/result
follow-up, not-ready guidance, and no local execution or write ownership. It is
safe for the default `composer test:all` gate because it does not open a browser
or require Cloud credentials.
The orchestration boundary smoke is also source-only. It verifies ADR-005,
rejects plugin-side Action Scheduler and local runtime tables in production
code, and keeps WP-Cron as local fallback preview while Cloud Batch Runtime
stays runtime/detail only.

The current phase acceptance summary is recorded in
[`Nightly Inspection Pro Cloud Runtime Acceptance`](nightly-inspection-pro-cloud-runtime-acceptance.md).
The review packaging and PR split are recorded in
[`Nightly Inspection Pro Cloud Runtime Release Prep`](nightly-inspection-pro-cloud-runtime-release-prep.md).

For the real local WordPress + Cloud integration proof, first make sure the
Cloud API and runtime worker are running:

```bash
cd /Users/muze/gitee/magick-ai-cloud
docker compose -f docker-compose.dev.yml --profile runtime up -d worker

cd /Users/muze/gitee/magick-ai-toolbox
composer smoke:nightly-inspection-cloud-e2e
```

This submits a metadata-only Pro Nightly Inspection Cloud Batch through the
verified Cloud Addon, polls until the Cloud runtime worker reaches a terminal
status, reads the result, and verifies Toolbox can merge the Cloud detail into
the local Morning Brief. It is intentionally not part of `composer test:all`
because it depends on a real WordPress site, verified Cloud credentials, the
Cloud API, Redis/Postgres, and the Cloud runtime worker. It must still prove
that Cloud does not become scheduler truth and does not grant direct WordPress
writes.

For AI-generated image media SEO normalization, run:

```bash
composer smoke:ai-image-media-seo
```

This mocks the Cloud image-generation response and verifies that prompt-like
candidate title, ALT, and description text are replaced with reviewed article
context before the candidate reaches Core adoption.

For the fixed Optimize Existing Image flow, run:

```bash
composer smoke:media-derivative-core
```

This starts at Toolbox `/media-derivative-handoff`, generates a Cloud derivative
through Adapter, submits the returned optimization plan to Core, executes it
through Adapter approve-and-execute, verifies the attachment file replacement,
and restores the original backup.
Before release, also follow
[Media Optimization Release Checklist](media-optimization-release-checklist.md):
preview, preflight, proposal creation, approve-and-execute, URL/MIME/ALT
evidence, and rollback must all pass.

For the batch media optimization review-set plan, run:

```bash
composer smoke:media-derivative-batch-plan
```

This creates two temporary image attachments, calls
`npcink-abilities-toolkit/build-media-derivative-batch-plan`, verifies
`eligibility_summary`, `blocked_items`, `operator_next_action`, `retryable`,
and `retry_guidance`, and proves planning does not change the fixture media
files. It does not call Cloud, create Core proposals, approve, preflight, or
execute WordPress writes.

For the optional browser check of that review-set UI, run:

```bash
NODE_PATH="${NODE_PATH:-/Users/muze/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules}" composer smoke:media-conversion-review-set-browser
```

This opens the Toolbox media derivative surface, builds the read-only batch
plan, confirms the UI renders
`npcink_local_automation_media_conversion_review_set.v1`,
`npcink-local-automation-runtime`, and `governed_review_set`, and verifies the
browser does not call preview, Core proposal, or execute routes. It is outside
`composer test:all` because it needs a running local WordPress site, WP-CLI
login-cookie generation, Playwright, and a local browser.

When Adapter, Cloud Addon, and Core are available, run the selected-preview
Core proposal smoke:

```bash
composer smoke:media-derivative-batch-core
```

This creates two temporary JPEG attachments, builds the batch plan through
Adapter `run-read-ability`, generates selected Cloud previews, builds reviewed
media optimization proposal payloads, and creates two Core review proposals. It
does not approve, preflight, execute, or replace media files.

For the selected batch execution proof behind the fixed "replace original
image" button, run:

```bash
composer smoke:media-derivative-batch-execute
```

This extends the single-image `composer smoke:media-derivative-core` proof:
it creates two temporary JPEG attachments, builds the selected batch plan,
generates selected Cloud derivative artifacts, creates selected Core media
optimization proposals, calls Adapter `approve-and-execute` for each selected
proposal, verifies file pointer/MIME/ALT/backup evidence, and restores each
attachment through governed restore proposals. Keep it outside `composer
test:all` because it performs real local media replacements and requires
Adapter, Core, Abilities, Cloud Addon, and Cloud runtime availability.

## Coding Rules

- Keep admin UI server-rendered unless a real build need appears.
- Keep JavaScript dependency-free in the current stage.
- Escape output late and sanitize input early.
- Keep machine timestamps unchanged in REST payloads, raw result details, cache
  contracts, and Cloud/Adapter correlation fields. Any timestamp shown in the
  Toolbox wp-admin UI must be formatted through the WordPress site timezone as
  `Y-m-d H:i:s`.
- Never return provider keys in REST responses.
- Never write provider keys into docs or tests.
- Treat Toolbox abilities as server-side provider wrappers; AI callers pass task
  input and receive normalized suggestions, not provider credentials.
- Keep content context separate from connector settings so Abilities exposure
  never returns provider keys or private credentials.
- Keep provider output as suggestions unless a governed handoff is implemented.
- Treat `local_admin_consent` as executable only for the existing attachment ->
  current post featured-image proof. It must record Core audit before and after
  the write and roll back if completion audit fails. All other write-like
  operations still require a governed handoff unless a separate ADR defines
  their write owner, audit owner, preview evidence, and rollback evidence.
- Treat post-editor excerpt/category/tag direct apply as a future
  `strong_local_confirmation` candidate only. Do not implement it until a
  separate UX and audit contract defines exact final metadata preview, old/new
  evidence, actor/source/correlation evidence, confirmation copy, recovery
  evidence, and fail-closed audit behavior. Current accepted metadata choices
  remain Core proposal handoffs.
- Treat article/media batch plans as the high-risk contrast: draft creation,
  media upload, metadata, and featured-image actions must stay in
  `core_proposal_required` and be verified with
  `composer smoke:article-media-batch-core`.
- Treat Optimize Existing Image as a governed fixed flow: Toolbox builds the
  operator handoff, Adapter/Cloud generates the short-lived preview, Core owns
  proposal approval, and release checks should run
  `composer smoke:media-derivative-core`.
- Keep Cloud-managed web search output as source candidates, not verified truth.
  Toolbox does not own web search provider configuration, local key storage, or
  local search execution.
- Preserve image-source provider attribution and source metadata. Unsplash
  responses must also preserve `download_location` metadata.
- Keep vector provider configuration, WordPress content indexing, and vector
  collection lifecycle in Cloud-managed Site Knowledge contracts, not local
  Toolbox settings.
- Keep `cap.toolbox.*` scope names stable unless Core explicitly changes the
  contract.
- Update `tests/run.php` when adding public REST routes or ability ids.

## Release Notes

Update `readme.txt` and `README.md` when:

- a route is added or removed;
- an ability id changes;
- a setting is added;
- a public workflow button changes behavior;
- the boundary changes.
