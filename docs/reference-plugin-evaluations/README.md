# Reference Plugin Evaluation Records

Status: active evaluation log.

This directory stores small, reviewable records created from the
[Reference Plugin Evaluation Checklist](../reference-plugin-evaluation-checklist.md).
Each record should answer one capability question and stop before product code
unless the repository owner, boundary posture, and verification gate are clear.

Use the [Reference Plugin Evaluation Record Template](../reference-plugin-evaluation-record-template.md)
for new records.

## Records

- [Connector status diagnostics readiness evaluation - 2026-07-08](connector-status-diagnostics-readiness-2026-07-08.md)
- [Site Kit connection readiness evaluation - 2026-07-08](site-kit-connection-readiness-2026-07-08.md)
- [SEO/checklist media metadata recommendation evaluation - 2026-07-08](seo-media-metadata-checklist-2026-07-08.md)

## Rules

- One record should evaluate one capability pattern.
- Records may cite mature plugin behavior as inspiration, but they must decide
  against the current Npcink repository boundaries.
- Records must not add a runtime, queue, registry, approval store, provider
  billing/log owner, or WordPress write path.
- A record that recommends implementation must name the minimum gate and the
  broader gate required before closeout.
