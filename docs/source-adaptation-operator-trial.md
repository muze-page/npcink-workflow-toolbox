# Source Adaptation Operator Trial

Status: ready for a manual or pre-release Cloud trial; not part of the offline
test suite.

Use the public-source cases in
`tests/fixtures/source-adaptation-real-url-trial.json`. Run extraction,
writing-pack review, and confirmed draft-preview stages from the post editor and
record one row per source. Do not save, insert, import media, or publish during
this trial.

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

| Case | Extract status / URL match | Coverage / chars / words | Site-context useful? | Writing pack useful? | Draft grounded / distinct? | Fact or rights issue | WordPress mutated? |
| --- | --- | --- | --- | --- | --- | --- |
| `wordpress_developer_roundup_long` | pending | pending | pending | pending | pending | pending | must be no |
| `wordpress_developer_roundup_recent` | pending | pending | pending | pending | pending | pending | must be no |
| `wordpress_release_short` | pending | pending | pending | pending | pending | pending | must be no |

## Decision rule

Do not admit full-body translation, adaptation, image localization, or editor
insertion merely because extraction works. The next product gate requires
repeated operator value plus factual and rights safety. Until then the durable
closed loop is: exact extraction evidence -> Site Knowledge overlap/style
context -> `article_writing_pack.v1` -> structured human review and
request-scoped confirmation -> `article_draft_preview.v1` -> human review ->
metadata-only quality feedback. Native insertion remains outside this gate.
