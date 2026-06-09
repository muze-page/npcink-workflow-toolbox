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

## WordPress Smoke Gate

When a local WordPress site and WP-CLI are available, mount or install the
plugin and activate it:

```bash
wp --path="/path/to/wordpress" plugin activate npcink-toolbox
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

For AI-generated image media SEO normalization, run:

```bash
composer smoke:ai-image-media-seo
```

This mocks the Cloud image-generation response and verifies that prompt-like
candidate title, ALT, and description text are replaced with reviewed article
context before the candidate reaches Core adoption.

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
- Treat article/media batch plans as the high-risk contrast: draft creation,
  media upload, metadata, and featured-image actions must stay in
  `core_proposal_required` and be verified with
  `composer smoke:article-media-batch-core`.
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
