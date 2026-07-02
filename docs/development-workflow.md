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
- `docs/cross-repo-boundary-matrix.md`
- `docs/boundary-exceptions.md`
- `docs/adversarial-boundary-review.md`
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

## Commit Scope Gate

Before staging, inspect the worktree:

```bash
git status --short --branch
git diff --stat
```

If unrelated local edits already exist, name them and leave them unstaged. Do
not use `git add -A` for mixed worktrees. When a file contains both current-task
and unrelated hunks, stage only the intended hunk with `git add -p` or
`git apply --cached`.

Before committing, verify the staged scope:

```bash
git diff --cached --stat
git diff --cached --name-only
```

After committing, verify the actual commit:

```bash
git show --name-status --stat HEAD
```

If unexpected files or hunks entered the commit, immediately run
`git reset --mixed HEAD~1` and recommit the correct scope. This keeps the
working tree changes intact while fixing the commit boundary.

## AI Development Quality Gate

For AI-assisted changes, start with the
[AI Development Quality Workflow](ai-development-quality-workflow.md) and fill
the [AI Change Envelope Template](ai-change-envelope-template.md) before
editing. The envelope must name the target repositories, focused module,
non-goals, expected files, areas that must not change, required gates, and
rollback plan.

Run a fast cross-repo status matrix before and after multi-repo work:

```bash
composer quality:matrix
```

Before a multi-repo release or milestone closeout, run the gate matrix:

```bash
composer quality:matrix:run
```

`composer quality:matrix` is status-only. `composer quality:matrix:run` runs the
configured default gates. Add `--fail-on-dirty` for release closeouts that must
prove no repository has hidden local edits:

```bash
php scripts/cross-repo-quality-matrix.php --run-gates --fail-on-dirty
```

Both commands are read-only except when an explicit `--output=PATH` report file
is requested. They must not fetch, stage, reset, or mutate WordPress.

AI agents must not run `git reset --hard`, `git checkout -- .`, or use
`git add -A` in mixed worktrees unless the user explicitly requests that exact
operation. If cleanup is needed, prefer a scoped reverse patch or
`git reset --mixed HEAD~1` only for correcting the latest bad local commit while
preserving the working tree.

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

For the detailed GitHub CLI/Git CLI publication path and timeout diagnostics,
use the [GitHub Publishing Runbook](github-publishing-runbook.md).

## Publication Status Gate

Local commit cleanup is not the same as publishing. Before calling a milestone
closed, run:

```bash
git status --short --branch
```

If the branch is ahead of its upstream, decide one of these outcomes:

- push the branch and open or update the PR;
- keep the commits local intentionally and record why in the final response;
- split or move the commits to a dedicated branch before publishing.

Do not report a stage as fully closed while omitting the branch ahead/behind
state or hiding remaining modified/untracked files.

## WordPress Smoke Gate

When a local WordPress site and WP-CLI are available, mount or install the
plugin and activate it:

```bash
command -v wp
wp --info

WP_PATH="/path/to/wordpress"
wp --path="$WP_PATH" plugin activate npcink-workflow-toolbox
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
    "$(command -v wp)" --path="$WP_PATH" plugin status npcink-workflow-toolbox
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
the automatic local progressive request stays hidden on success, confirms it
does not add a default `Local suggestions` button, and confirms no Cloud,
Adapter, or Core proposal route is called. It is intentionally outside
`composer test:all` because it depends on a running local WordPress site,
WP-CLI login-cookie generation, Playwright, and a local browser.

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

For the Toolbox/Core handoff receipt UI, run:

```bash
NODE_PATH="${NODE_PATH:-/Users/muze/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules}" composer smoke:core-handoff-receipt-ui
```

This optional browser fixture loads the real admin JavaScript with mocked REST
responses, verifies success and failure receipt rendering, checks the Core
review link, and confirms failed handoffs show operator recovery feedback. It
does not create WordPress, Adapter, or Core records.

For the post-editor SEO apply loop, run:

```bash
composer smoke:editor-seo-apply
```

This creates a temporary draft and asks Toolbox only for the `discoverability`
SEO handoff preview. The fixture then uses Adapter/Core/Abilities to create an
executable `npcink-abilities-toolkit/set-post-seo-meta` Core proposal and call
Adapter `approve-and-execute`. Toolbox does not approve, execute, or write SEO
meta in this smoke; it proves only that a Toolbox suggestion/handoff can be
carried into the governed Core/Adapter/Abilities path. If local Core policy
allows execution, the smoke verifies the SEO title and description meta were
written by the Core-approved ability. If policy blocks automatic execution, it
verifies the Core proposal remains reviewable and the temporary post SEO meta
is unchanged. It is intentionally outside `composer test:all` because it
depends on a running local WordPress site with Adapter, Core, and Abilities
active.

For hosted AI no-result editor diagnostics, run:

```bash
composer smoke:editor-hosted-ai-no-result
```

This dispatches the selected-paragraph check through the editor REST route while
mocking Cloud omitted, zero provider-call, and idempotent replay empty responses.
It verifies that the editor keeps the suggestion-only local paragraph-check
fallback, preserves runtime diagnostics, and does not replace selected text. It
is intentionally outside `composer test:all` because it depends on a running
local WordPress site and WP-CLI.

For scoped permission and debug payload hardening, run:

```bash
composer smoke:security-permission-debug
```

This source-only smoke proves route scopes and ability scopes reach the host
permission filters, unknown REST routes fall back to `cap.toolbox.admin`, and
`NPCINK_TOOLBOX_DISABLE_RAW_RESPONSES` suppresses redacted raw payloads even
when a local debug option would otherwise include them. It is part of the
default `composer test:all` gate.

For an authenticated REST latency baseline, run:

```bash
NPCINK_TOOLBOX_BASE_URL="https://example.local" \
NPCINK_TOOLBOX_AUTH_COOKIE="wordpress_logged_in_..." \
NPCINK_TOOLBOX_NONCE="..." \
NPCINK_TOOLBOX_PERF_OUTPUT="var/perf/toolbox-baseline.jsonl" \
composer perf:baseline
```

Add `NPCINK_TOOLBOX_PERF_INCLUDE_CLOUD=1` only when Cloud Addon/runtime
availability is part of the proof. For local self-signed HTTPS only, add
`NPCINK_TOOLBOX_PERF_INSECURE_TLS=1`. To measure a known Cloud unavailable or
no-candidate failure path, add `NPCINK_TOOLBOX_PERF_ALLOW_ERROR_STATUS=1` and
record the status in the trial notes. This records JSONL timing for the status
and Site Knowledge status routes, plus Cloud-backed probes when enabled. Any
probe without an HTTP status, unexpected error status, or over 2500ms exits
non-zero so the release owner investigates the path instead of shipping an
unmeasured slowdown.

For the post-editor follow-up quality trial through eval-lab, run:

```bash
composer eval:editor-followup:trial
```

This proxies to the sibling `npcink-eval-lab` task registry and runs
`editor_followup_trial` against recent local WordPress posts. The trial checks
article checkup, discoverability, publish preflight, SEO handoff preview, slug
candidate visibility, and no WordPress mutation. It is intentionally outside
`composer test:all` because it depends on a local WordPress site and the
development-only eval-lab checkout.

For a Workflow Toolbox adversarial boundary audit through eval-lab, run:

```bash
composer eval:workflow-toolbox:adversarial-boundary -- dry_run=true
```

Remove `dry_run=true` only when local provider profiles are configured in the
development-only eval-lab checkout and a model-backed review is intentional.
This proxies to task `workflow_toolbox_adversarial_boundary_audit`, reads the
Toolbox positioning, boundary, architecture, roadmap, development workflow, and
ADR-001 documents, and asks reviewer profiles to flag drift toward direct
WordPress writes, second registries/stores, provider secret exposure,
queue/runtime ownership, content indexing ownership, full-RAG claims,
image-source/AI-generation confusion, or premature Jina runtime claims. The
report is local development evidence under eval-lab `project-review/generated/`;
it is not a Core audit record, approval decision, CI-required gate, or product
runtime.

For boundary-sensitive work, use
[Adversarial Boundary Review](adversarial-boundary-review.md) as the triage
ledger after model-backed review. Every finding must be classified as
`accepted_fix`, `accepted_exception`, or `rejected_finding` before it becomes
implementation work. Accepted exceptions must point to
[Boundary Exceptions Registry](boundary-exceptions.md) and an ADR; accepted
fixes must get a doc or test guard in the same scope.

For the Site Knowledge review handoff UI, run:

```bash
composer smoke:site-knowledge-review-ui
```

This is a source-only smoke. It verifies that the Site Knowledge admin surface
keeps review proposals operator-triggered, routes the handoff through
`/flows/site-knowledge-review-plan` and Adapter/Core from-plan intake, preserves
evidence refs, keeps Agent feedback as Cloud eval metadata, and does not call
REST, Adapter, Core proposal intake, or WordPress write paths.

For the Cloud Addon-owned Site Knowledge change bridge, run:

```bash
composer smoke:site-knowledge-cloud-addon-bridge
```

This is a local WordPress smoke. It temporarily activates Cloud Addon before
Toolbox, verifies Toolbox reports `owner=cloud_addon`, confirms the Cloud Addon
public post hook is registered, and confirms the retired Toolbox legacy
auto-sync cron hook is not registered.
It restores the original plugin activation state.
It is intentionally outside `composer test:all` because it depends on a local
WordPress site with both plugins symlinked or installed.

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
composer smoke:site-ops-insights-builder
composer smoke:site-ops-cloud-request
composer smoke:site-ops-cloud-e2e
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
The Site Ops smoke validates deterministic `site_ops_insight_pack.v1` output,
comment privacy omissions, and no-write/no-Cloud posture without requiring
WordPress.
The Site Ops Cloud request smoke validates the contract-only
`site_ops_cloud_analysis_request.v1` packet for Cloud runtime/detail analysis.
It proves the local builder does not call Cloud, create a local runtime,
schedule work, create Core proposals, write WordPress data, or expose comment
text, author email, IP address, or user agent. The manual admin bridge may send
that prepared request only when Cloud is ready and must not create local
queues, local run tables, scheduler truth, Core proposals, or WordPress writes.
The Site Ops Cloud E2E smoke runs against a real local WordPress site with a
verified Cloud Addon connection and a running Cloud runtime. It submits the
prepared public aggregate request through `Provider_Client` and verifies the
`site_ops_cloud_analysis_result.v1` result, Cloud runtime/detail ownership,
priority and trend detail, no direct WordPress writes, no scheduler truth, no
local run tables, and no Core proposal creation. It is intentionally outside
`composer test:all`.

For the optional real admin UI browser check, run:

```bash
NODE_PATH="${NODE_PATH:-/Users/muze/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules}" composer smoke:site-ops-insights-browser
```

This opens the local Site Check wp-admin panel, generates the local report,
verifies the summary-first overview and priority decision queue,
switches the content, media, comments, structure, findings, evidence, Cloud,
and advanced sub-tabs, and captures a screenshot under `build/smoke/`. It uses
a short-lived local login helper so WordPress sets the administrator auth
cookie in the same Web request context as the browser. It does not click Cloud
detail, create Core proposals, call execute routes, store a local run, or
write WordPress data. It is intentionally outside `composer test:all` because
it depends on a running local WordPress site, a writable local WordPress root,
Playwright, and a local browser.
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
The Cloud Runtime UI smoke is a source-only contract check. It verifies Pro
Cloud Runtime quota hooks in Cloud Checks, short automatic submit/status/result follow-up,
not-ready guidance, and no local execution or write ownership. It is
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
The production-prep operator checklist for healthy, partial-success, retry,
recent-run, and trial-record handling is recorded in
[`Nightly Inspection Production Operator Runbook`](nightly-inspection-production-operator-runbook.md).

For the real local WordPress + Cloud integration proof, first make sure the
Cloud API and runtime worker are running:

```bash
cd /Users/muze/gitee/npcink-ai-cloud
docker compose -f docker-compose.dev.yml --profile runtime up -d worker

cd /Users/muze/gitee/npcink-workflow-toolbox
composer smoke:site-ops-cloud-e2e
composer smoke:nightly-inspection-cloud-e2e
```

The Site Check command submits a manual Site Ops Cloud detail request through
the verified Cloud Addon, reads the immediate Cloud runtime detail, and
verifies the result remains suggestion-only review output. It must still prove
Cloud does not become scheduler truth, does not create local run tables or Core
proposals, and does not grant direct WordPress writes.

The Nightly Inspection command submits a metadata-only Pro Nightly Inspection
Cloud Batch through the
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

For the fixed media optimization flow, run:

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

For the selected batch execution proof behind the fixed "Submit and execute"
button, run:

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

Before adding another media or batch surface, run the
[Media Optimization Operator Trial](archive/2026-06/media-optimization-operator-trial.md). The
trial records 5 to 10 low-risk real attachments, checks that selected batch
work stays at or below the UI cap of 10 candidates, and verifies that operators
understand preview, Core review, execution, partial failure recovery, and
governed restore.

For the media ALT/caption Toolkit extraction gate, run:

```bash
composer smoke:media-alt-caption-trial
composer eval:media-alt-caption:export
composer eval:media-alt-caption:judge-cross
composer eval:media-alt-caption:export-batch
composer eval:media-alt-caption:judge-cross-batch
composer eval:media-alt-caption:open-samples
```

This uses real local image attachments and the existing `/ai/site-helpers`
route to validate `media_alt_caption_review_set.v1` as a metadata-only
review-set artifact. It uses a local host filter for the site-helper runtime
response, stays outside `composer test:all`, creates no fixture media, creates
no Core proposal, does not call a Cloud runtime, and verifies attachment
metadata snapshots remain unchanged. The export command writes the selected
`media_alt_caption_operator_trial.v1` cases under local `build/eval/` so they
can be checked by a human or passed to the development-only eval-lab checkout.
The judge-cross command calls eval-lab task `media_alt_caption_judge_cross`
with the exported file; it is AI-assisted review evidence only and never
authorizes media metadata writes.

Use the `*-batch` commands when you need more sample volume for extraction
confidence. The batch exporter pages real media-library metadata through the
same `/ai/site-helpers` route with `MEDIA_ALT_CAPTION_PAGE_SIZE` capped at 10,
then aggregates a local `media_alt_caption_operator_trial.v1` file for
eval-lab. This is an eval-only accelerator; it does not raise the product UI
cap, create a queue/runtime, call Cloud, create Core proposals, or write media
metadata. Tune the sample size with `MEDIA_ALT_CAPTION_SAMPLE_LIMIT`, default
50. `composer eval:media-alt-caption:judge-cross-batch` reuses the existing
batch file by default so the eval-lab input fingerprint stays stable for
resume; set `MEDIA_ALT_CAPTION_FORCE_EXPORT=1` only when you intentionally want
to refresh the sampled cases. Provider-backed eval-lab review can be resumed
with `MEDIA_ALT_CAPTION_JUDGE_RESUME=1`; use
`MEDIA_ALT_CAPTION_JUDGE_OFFSET` to split long runs and
`MEDIA_ALT_CAPTION_CHECKPOINT_EVERY` to control how often the eval report is
rewritten. The default checkpoint interval is 1 completed case so interrupted
36+ case runs do not have to restart from the beginning. Use
`MEDIA_ALT_CAPTION_JUDGE_OUTPUT_JSON`, `MEDIA_ALT_CAPTION_JUDGE_OUTPUT_MD`, and
`MEDIA_ALT_CAPTION_JUDGE_OUTPUT_CSV` when a long eval needs dedicated report
paths instead of replacing the default generated report.

Use `composer eval:media-alt-caption:open-samples` when the local media library
is too small to calibrate reviewer prompts or candidate filters. The command is
an eval-lab proxy that fetches bounded public Wikimedia Commons metadata and
writes local generated JSON/Markdown/CSV under the eval-lab checkout by default.
It does not download image files, bundle WIT/LAION-scale datasets, touch
WordPress, create Core proposals, or change the Toolbox product route cap. Tune
the public query with `MEDIA_ALT_CAPTION_OPEN_QUERY` and the public page request
count with `MEDIA_ALT_CAPTION_OPEN_LIMIT` capped in eval-lab. Feed the generated
`media_alt_caption_operator_trial.v1` JSON into
`media_alt_caption_judge_cross` for three-model calibration only; real
WordPress media acceptance still requires local export and human visual
confirmation.

For weak metadata libraries, `media_alt_caption_review_set.v1` may also return
an `image_context_evidence_request.v1` packet. Treat it as a bounded request for
Cloud-owned or host-owned visual evidence only. It is not a local vision model,
not a queue/runtime, not a Core proposal, and not write authorization. A returned
`image_context_evidence.v1` packet can improve candidate basis, but selected
items still require human visual confirmation and unchanged media metadata
snapshots. When the Cloud Addon exposes `request_image_context_evidence()`,
Toolbox may call that named helper once and rebuild the local review set with
the returned evidence; helper absence or Cloud failure must fall back to the
visible request packet without blocking the metadata-only review flow.

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
- Treat media optimization as a governed fixed flow: Toolbox builds the
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
