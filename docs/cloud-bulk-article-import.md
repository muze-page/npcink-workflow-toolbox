# Cloud Bulk Article Import

Status: active planning guidance.

This document defines how Toolbox may use selected Cloud bulk article artifacts
without becoming a bulk publishing console, queue owner, approval surface, or
WordPress write executor.

## Position

Toolbox may help an operator review and import selected items from a Cloud
`bulk_article_run_v1` result. The imported item must become the same local
`article_write_plan` shape already used by the Article Write Plan panel.

Cloud bulk production is a scale benefit for research and draft preparation.
It is not a publish shortcut.

## Allowed Toolbox Role

Toolbox may:

- show selected Cloud article artifacts for operator review;
- preserve `article_goal_brief`, `research_evidence_pack`, `article_outline`,
  `article_draft_candidate`, `discoverability_pack`, and
  `article_risk_report`;
- convert one selected ready item into
  `magick-ai-toolbox/build-article-write-plan` input;
- surface Core handoff guidance and governed `operator_feedback`;
- keep the final action draft-only.

Toolbox must not:

- own Cloud run state, queues, retries, or worker recovery;
- submit bulk proposals automatically;
- approve Core proposals;
- publish, schedule, or update WordPress posts directly;
- add a local bulk publish console;
- treat Cloud item readiness as approval or preflight.

## Import Rules

P0 import accepts one selected item at a time.

The imported item must produce:

- `artifact_type=article_write_plan`;
- `version>=1`;
- `proposal_mode=single`;
- `requires_approval=true`;
- `dry_run=true`;
- `commit_execution=false`;
- exactly one draft-only `magick-ai/create-draft` write action.

Toolbox should block or request revision when:

- `article_risk_report.ready_for_proposal` is not true;
- `article_risk_report.risk_level` is `high`;
- `article_risk_report.blocked_claims` is not empty;
- the draft asks for `status=publish` or `post_status=publish`;
- the plan asks for `commit=true` or `dry_run=false`;
- the item is expired or missing required artifacts.

## Handoff

After import and review, the local path remains:

```text
Toolbox article_write_plan
  -> Adapter or Core /proposals/from-plan
  -> Core proposal review
  -> Core approval and commit preflight
  -> Adapter executes magick-ai/create-draft through WordPress Abilities API
```

The operator should see Cloud provenance as context, not as write authority.
