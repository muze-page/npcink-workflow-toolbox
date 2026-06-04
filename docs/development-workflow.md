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
wp --path="/path/to/wordpress" plugin activate magick-ai-toolbox
```

Then verify:

- the plugin activates without fatal errors;
- `Magick AI -> Toolbox` loads when a Magick AI parent menu exists;
- `Tools -> Magick AI Toolbox` loads when installed standalone without a
  Magick AI parent menu;
- settings save;
- `/wp-json/magick-ai-toolbox/v1/status` returns the expected capability-gated
  status for an authenticated administrator;
- Abilities API discovery includes the Toolbox ability ids when the Abilities
  API is available.

## Coding Rules

- Keep admin UI server-rendered unless a real build need appears.
- Keep JavaScript dependency-free in the current stage.
- Escape output late and sanitize input early.
- Never return provider keys in REST responses.
- Never write provider keys into docs or tests.
- Treat Toolbox abilities as server-side provider wrappers; AI callers pass task
  input and receive normalized suggestions, not provider credentials.
- Keep content context separate from connector settings so Abilities exposure
  never returns provider keys or private credentials.
- Keep provider output as suggestions unless a governed handoff is implemented.
- Keep web research provider output as source candidates, not verified truth.
  Jina Reader may enhance selected search result URLs but must not become a
  search provider, crawler, or write path.
- Preserve image-source provider attribution and source metadata. Unsplash
  responses must also preserve `download_location` metadata.
- Keep SiliconFlow/Jina query embedding separate from WordPress content
  indexing and Qdrant collection lifecycle until those stages have their own
  contract.
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
