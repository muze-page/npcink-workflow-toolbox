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

## Next Layer

After the hard gate is stable, add a model-judged layer for faithfulness,
coverage, reader usefulness, and regeneration diversity. Keep that layer outside
`composer test:all` unless it can run deterministically without external
network or provider credentials.
