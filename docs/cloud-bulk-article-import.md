# Cloud Bulk Article Import

Status: prohibited and deprecated planning guidance.

This document records that Toolbox must not import Cloud-generated article
drafts, Cloud bulk article items, or Cloud-produced `article_write_plan`
candidates.

## Decision

Toolbox article drafting remains local Ability recipe UX. Cloud article import
is not part of the product.

Toolbox must not:

- show Cloud-generated article artifacts for writing review;
- import Cloud `bulk_article_run_v1` items;
- convert Cloud article items into `npcink-toolbox/build-article-write-plan`
  input;
- add a bulk article import panel;
- submit bulk proposals automatically;
- treat Cloud item readiness as proposal readiness;
- publish, schedule, or update WordPress posts directly.

## Replacement

The backend Reviewed Draft Handoff panel is removed while there is no active
external-draft import workflow. The underlying route and Ability remain
available for explicit API composition and any future reviewed bulk-import
contract. In the current product, that contract is still local
`article_draft_v1` recipe UX:

```text
local Ability recipe
  -> operator-reviewed local artifacts
  -> npcink-toolbox/build-article-write-plan
  -> Adapter or Core /proposals/from-plan
  -> Core proposal review
  -> Core approval and commit preflight
  -> Adapter executes npcink-abilities-toolkit/create-draft through WordPress Abilities API
```

## Allowed Toolbox Role

Toolbox may:

- expose fixed local recipe buttons;
- render local research, image-source, vector, context, draft, and risk
  artifacts;
- build a Core-ready `article_write_plan`;
- surface Core handoff guidance and governed `operator_feedback`;
- keep the final action draft-only.

Toolbox must not become a Cloud writing console, queue owner, approval surface,
proposal truth, or WordPress write executor.

## Guardrail Phrase

Toolbox helps operators compose local Ability outputs into governed write
plans. It does not import or publish Cloud-generated article content.
