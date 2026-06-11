# AI Summary Evaluation

Status: first hard-gate scaffold.

The editor summary feature should be evaluated in layers instead of relying on
manual spot checks. The first layer is an offline hard gate that catches
obvious failures before a model response reaches an editor or a batch workflow.

## Sample Sources

Use three sample pools:

1. Universal fixtures for errors no site should accept, such as meta phrasing,
   JSON leaks, unsupported claims, and title repetition.
2. Real published content from the product owner's site, such as npc.ink, to
   cover Chinese WordPress resource, tutorial, and product-introduction pages.
3. Per-site calibration samples exported from each customer's own published
   posts and existing excerpts.

Development filler content is useful only as stress input. It should not define
the desired writing style.

## Hard Gate

Run:

```bash
composer eval:summary
```

The default fixture lives at `tests/summary-eval/samples.json`. The script checks
candidate excerpts for:

- length limits;
- forbidden meta phrasing such as "本文说明" or "这篇草稿主张";
- JSON or Markdown leakage;
- full title repetition;
- risky unsupported claims such as "保证", "最佳", "完全自动", or "无需人工".

Universal fixtures can use tighter per-sample limits. Exported site samples use
a practical default of 50-160 Chinese characters so the hard gate catches obvious
failures without rejecting concise but usable preview copy.

To evaluate generated candidates, create a JSON file with the same shape and
pass it to the runner:

```bash
php tests/summary-eval/run.php /path/to/generated-summary-candidates.json
```

## Export Local WordPress Samples

After importing representative content into the local WordPress site, export
source samples by author:

```bash
composer eval:summary:export
```

The default author is `Muze`, and the default output path is
`tests/summary-eval/generated/muze-source-samples.json`. Override when needed:

```bash
SUMMARY_EVAL_AUTHOR="Muze" SUMMARY_EVAL_LIMIT=100 composer eval:summary:export
```

The exported file is source material, not a pass/fail eval file yet. It includes
post title, body text, existing excerpt, categories, tags, and forbidden summary
phrases. Add generated candidates before running it through
`tests/summary-eval/run.php`.

## Generate Candidates

Generate AI summary candidates from the exported source samples:

```bash
composer eval:summary:generate
```

The default input is `tests/summary-eval/generated/muze-source-samples.json`,
the default output is `tests/summary-eval/generated/muze-candidates.json`, and
the default generation limit is 10 samples. The command also retries transient
Cloud active-run errors and sleeps between samples because a local site may allow
only one active hosted run at a time. Keep the default small while tuning
prompts. Override when needed:

```bash
SUMMARY_EVAL_GENERATE_LIMIT=20 composer eval:summary:generate
```

This command calls the existing editor Content Support REST route with
`intent=summary_suggestions`. It writes only the local eval JSON file and does
not update WordPress excerpts, terms, metadata, posts, or Core proposals.

Evaluate the generated candidates:

```bash
php tests/summary-eval/run.php tests/summary-eval/generated/muze-candidates.json
```

## Human Review Worksheet

After candidates pass the hard gate, export a lightweight worksheet for manual
adoption review:

```bash
composer eval:summary:review
```

The default input is `tests/summary-eval/generated/muze-candidates.json`. The
command writes ignored local files with Chinese review columns:

- `tests/summary-eval/generated/summary-human-review.md`
- `tests/summary-eval/generated/summary-human-review.json`
- `tests/summary-eval/generated/summary-human-review.csv`

Override the input and output paths when reviewing a specific batch:

```bash
SUMMARY_REVIEW_INPUT=tests/summary-eval/generated/muze-candidates-offset25-limit20.json \
SUMMARY_REVIEW_MD=tests/summary-eval/generated/muze-candidates-offset25-review.md \
SUMMARY_REVIEW_JSON=tests/summary-eval/generated/muze-candidates-offset25-review.json \
SUMMARY_REVIEW_CSV=tests/summary-eval/generated/muze-candidates-offset25-review.csv \
composer eval:summary:review
```

Use `direct_use`, `minor_edit`, or `reject` for the `评价` field. Keep failure
reasons coarse in `问题类型`: `generation_error`, `too_generic`,
`missing_core_value`, `wrong_tone`, `instruction_like`,
`insufficient_coverage`, `too_marketing`, `too_short`, `too_long`,
`unsupported_claim`, or `other`.

## Opening Diversity

If generated excerpts start too often with formulaic audience labels such as
`面向` or `适合`, run the offline opening analysis:

```bash
SUMMARY_OPENINGS_INPUT=tests/summary-eval/generated/muze-candidates-offset25-limit20.json \
composer eval:summary:openings
```

The report counts opening prefixes, audience-label rate, and cases where both
candidates for the same sample use the same opening bucket. It does not call
Cloud or WordPress.

## Next Layer

After the hard gate is stable, add a model-judged layer for faithfulness,
coverage, reader usefulness, and regeneration diversity. Keep that layer outside
`composer test:all` unless it can run deterministically without external
network or provider credentials.
