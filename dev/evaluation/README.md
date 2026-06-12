# Development Evaluation Lab

Status: development-only debugging workspace.

This folder contains local evaluation tools for AI recommendation quality. It
is intentionally outside the production plugin runtime and is excluded from
release packages through `.distignore`.

## Scope

Use this workspace for:

- exporting real local WordPress article samples;
- running title, summary, category, and tag recommendation batches;
- comparing generator/reviewer/repair model profiles;
- exporting Markdown, JSON, CSV, or XLSX worksheets for human spot checks;
- keeping regression fixtures for prompt and ranking changes.

Do not use this workspace for:

- WordPress writes;
- Core proposal creation, approval, execution, or audit truth;
- provider key storage;
- request log, quota, billing, queue, or workflow runtime ownership.

## Folders

- `summary/` keeps the excerpt-specific hard gate, generated summary candidate
  checks, Promptfoo cases, LLM judge comparison, opening analysis, and human
  review worksheets.
- `recommendation/` keeps the broader real-article recommendation cycle for
  titles, excerpts, existing categories, existing tags, and review-only
  vocabulary gaps.

Composer entrypoints remain at the repository root:

```bash
composer eval:summary
composer eval:summary:export
composer eval:summary:generate
composer eval:summary:review

composer eval:recommendation:export
composer eval:recommendation:cycle -- dry_run=1 limit=2
composer eval:recommendation:review
composer eval:recommendation:triad
composer eval:recommendation:diff
```
