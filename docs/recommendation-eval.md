# Recommendation Evaluation Loop

Status: local test workflow.

This workflow evaluates the first-stage recommendation tools with real local
WordPress articles:

- title candidates;
- excerpt/summary candidates;
- existing category candidates;
- existing tag candidates;
- vocabulary-gap new tag candidates for human review only.

It is intentionally outside the production runtime. It does not write
WordPress data, does not create Core proposals, and does not store provider
keys in the repository.

## Boundary

The evaluation scripts are local quality tools. Their output is evidence for
prompt, ranking, and UI iteration, not acceptance or audit truth.

Allowed:

- export sampled local post context through WP-CLI;
- call temporary provider profiles from environment variables;
- let one model generate candidates, another model review them, and the first
  model repair once;
- export Markdown, JSON, and CSV worksheets for human sampling.

Not allowed:

- writing excerpts, terms, links, images, SEO metadata, or post content;
- creating or approving Core proposals;
- storing provider keys, request logs, quotas, or billing state;
- treating AI review as final quality truth without human sampling.

## Provider Environment Variables

Use evaluation-only environment variables so regular provider settings are not
accidentally consumed:

```bash
export REC_EVAL_GPT55_API_KEY="..."
export REC_EVAL_GPT55_BASE_URL="https://api.example.test"
export REC_EVAL_GPT55_MODEL="gpt-5.5"

export REC_EVAL_GROK43_API_KEY="..."
export REC_EVAL_GROK43_BASE_URL="https://api.example.test"
export REC_EVAL_GROK43_MODEL="grok-4.3"

export REC_EVAL_DEEPSEEK_API_KEY="..."
export REC_EVAL_DEEPSEEK_BASE_URL="https://api.deepseek.com"
export REC_EVAL_DEEPSEEK_MODEL="deepseek-v4-pro"
```

Do not commit these values. Remove them after the test window.

## Run

Export stratified article samples:

```bash
composer eval:recommendation:export
```

Run a no-provider dry run to verify the local pipeline:

```bash
composer eval:recommendation:cycle -- dry_run=1 limit=2
composer eval:recommendation:review
```

Run a real two-model cycle after exporting provider environment variables:

```bash
composer eval:recommendation:cycle -- \
  generator_profile=gpt55 \
  reviewer_profile=deepseek \
  repair_profile=gpt55 \
  limit=30 \
  operator_note="标题更口语化，摘要偏 SEO，优先复用已有标签"

composer eval:recommendation:review
```

To use the third model as generator or reviewer, switch the profile name to
`grok43`.

## Sampling Plan

Start with 30-50 posts and keep the buckets mixed:

- recent posts;
- older posts;
- random posts;
- posts missing excerpt, category, or tag signals;
- long-form posts.

After each batch, inspect the worksheet and record:

- direct-use rate;
- minor-edit rate;
- reject rate;
- factual or unsupported-claim failures;
- wrong category/tag reuse;
- cases where a proposed new tag should have reused an existing tag.

The next iteration should adjust prompts, term ranking, or UI copy only after
human review confirms a repeated issue.
