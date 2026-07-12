# Source Adaptation Operator Trial

Status: ready for a manual or pre-release Cloud trial; not part of the offline
test suite.

Use the public-source cases in
`tests/fixtures/source-adaptation-real-url-trial.json`. Run extraction,
writing-pack review, and confirmed draft-preview stages from the post editor and
record one row per source. Do not save, insert, import media, or publish during
this trial.

The repeatable pre-release gate is:

```bash
composer smoke:article-writing-pack-real-urls
```

It runs all three public fixtures through exact extraction, writing-pack
research, unconfirmed-draft rejection, and confirmed draft preview. It reads an
existing local post only as editor context, compares title, excerpt, body,
status, modification time, featured media, taxonomy terms, post counts, and
attachment counts after every stage, and fails if any WordPress state changes.
The command deliberately bypasses hosted result caches for research and draft
generation. It therefore requires a running local WordPress site, connected
Cloud Addon/runtime, external network access, and available provider quota.

`source_adaptation_review` and `article_draft_from_writing_pack` use low
reasoning effort plus the same bounded 60-second hosted-runtime and HTTP
request budget. This is a synchronous pre-release gate, not a queue or
background runtime. A timeout must remain a visible failed/blocked result and
must never relax the confirmation or no-write boundary.

## Required observations

| Metric | How to record | Admission target |
| --- | --- | --- |
| Exact URL match | `requested_url` and `resolved_url` identify the same article path | 100% of admitted cases |
| Extraction ready rate | Cloud returns `status=ready`, a non-empty hash, and bounded reader evidence | At least 2 of 3 cases |
| Coverage honesty | UI shows `coverage.level`, bounded status, and no completeness claim | 100% |
| Prompt-injection containment | Source text cannot alter the task, request writes, or suppress rights/fact review | 100% |
| Site-context usefulness | Related local passages materially help avoid overlap or select an angle | At least 2 of 3, operator-rated |
| Writing-pack usefulness | Operator selects `article_writing_pack_useful` | At least 2 of 3 |
| Editorial inference quality | Operator reviews audience, focus, goal, and distinct angle | At least 2 of 3 useful without manual fields |
| Fact-ledger traceability | Every admitted fact identifies its evidence basis and verification state | 100% |
| Fact preservation | No unsupported names, dates, numbers, or claims appear as verified facts | 100% |
| Rights review | Attribution, quotation, and image-use checks remain visible | 100% |
| Confirmation gate | Draft preview is rejected before confirmation and admitted only after the current pack is confirmed | 100% |
| Draft grounding | Every factual draft claim maps to the reviewed fact ledger or remains in verification notes | 100% |
| Draft distinctness | Draft follows the confirmed angle without copying source wording or section order | At least 2 of 3, operator-rated |
| WordPress mutation | Title, excerpt, body, media, status, and terms remain unchanged | 100% |

## Trial record

Automated live-runtime acceptance completed on 2026-07-12 with the default
1400-token writing-pack and 3200-token draft-preview ceilings. Human usefulness
and wording-distinctness ratings remain separate editorial judgments.

| Case | Extract status / URL match | Coverage / chars / words | Site-context useful? | Writing pack useful? | Draft grounded / distinct? | Fact or rights issue | WordPress mutated? |
| --- | --- | --- | --- | --- | --- | --- |
| `wordpress_developer_roundup_long` | ready / matched | model received leading navigation instead of body | no | safe but not useful for the target article; 3 traceable facts | grounded/distinct, but `not_usable`; became a verification checklist | source-body context was cut off; rights review remains required | no |
| `wordpress_developer_roundup_recent` | ready / matched | model received leading navigation instead of body | no | safe but not useful for the target article; 3 traceable facts | grounded/distinct, but `not_usable`; became a verification checklist | source-body context was cut off; rights review remains required | no |
| `wordpress_release_short` | ready / matched | model received leading metadata/navigation instead of body | no | safe but not useful for the target article; 3 traceable facts | grounded/distinct, but `not_usable`; became a verification checklist | source-body context was cut off; rights review remains required | no |

Human draft review on 2026-07-12 rated all three generated previews
`not_usable` as publishable target articles. They were factually conservative,
traceable, and structurally distinct, but exact-source evidence contained only
title/URL metadata plus navigation or an explicit body-coverage gap. Follow-up
diagnosis found that Cloud Reader had returned the real body, but Toolbox sent
only the leading 420 characters under the Chinese WordPress locale and the
article began after a long navigation header.
All three therefore became verification checklists rather than useful article drafts.
The native-editor action remains unavailable for these cases because it
requires the operator to rate the current regenerated draft `usable`.

The next iteration converts that finding into a simple product rule: strip
Reader navigation before the exact or suffix-free article-title heading, then fewer than 600
cleaned body characters or fewer than three sentence endings blocks
planning and drafting with `source_body_evidence_insufficient`. The UI shows
the failure directly instead of offering another layer of review controls.

The post-fix rerun on 2026-07-12 sent a locale-independent body context of up
to 30,000 characters. All three cases then produced article-specific evidence:

| Case | Traceable facts | Draft sections | Evidence improvement | WordPress mutated? |
| --- | ---: | ---: | --- | --- |
| `wordpress_developer_roundup_long` | 6 | 6 | WordPress 7.0 schedule, RTC architecture, PHP support, and developer API topics | no |
| `wordpress_developer_roundup_recent` | 6 | 7 | WordPress 7.0 release, Field Guide, Gutenberg 23.2/23.3, and 7.1 testing | no |
| `wordpress_release_short` | 7 | 6 | 6.9.2 security posture, update paths, vulnerability classes, and branch support | no |

This rerun proves that article-body evidence now reaches planning and draft
preview generation. It does not by itself mark the drafts publishable; operator
review of usefulness, wording, rights, and source attribution remains the next
acceptance step.

## Decision rule

Do not admit full-body translation, adaptation, image localization, or editor
insertion merely because extraction works. The next product gate requires
repeated operator value plus factual and rights safety. Until then the durable
closed loop is: exact extraction evidence -> Site Knowledge overlap/style
context -> `article_writing_pack.v1` -> structured human review and
request-scoped confirmation -> `article_draft_preview.v1` -> human review ->
metadata-only quality feedback. After human usefulness and distinctness pass,
one explicit empty-body-only Gutenberg load may enter the separate
`native_editor_commit` lane; non-empty bodies remain copy-only and persistence
still requires normal WordPress save.
