# Recommendation Evaluation Loop

Status: local test workflow.

This development workflow now lives in the sibling
`/Users/muze/gitee/npcink-eval-lab` repository. Toolbox keeps Composer
proxy commands for convenience, but the scripts, generated files, model
profiles, and local provider-key handling are owned by the eval lab.

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

Run a three-model rotating review batch and export only differences:

```bash
REC_EVAL_TRIAD_LIMIT=10 composer eval:recommendation:triad
```

The expanded form is:

```bash
REC_EVAL_CYCLE_LIMIT=10 REC_EVAL_OUTPUT=recommendation/generated/ai-cycle-gpt55.json \
REC_EVAL_GENERATOR_PROFILE=gpt55 REC_EVAL_REVIEWER_PROFILE=deepseek REC_EVAL_REPAIR_PROFILE=gpt55 \
composer eval:recommendation:cycle

REC_EVAL_CYCLE_LIMIT=10 REC_EVAL_OUTPUT=recommendation/generated/ai-cycle-grok43.json \
REC_EVAL_GENERATOR_PROFILE=grok43 REC_EVAL_REVIEWER_PROFILE=gpt55 REC_EVAL_REPAIR_PROFILE=grok43 \
composer eval:recommendation:cycle

REC_EVAL_CYCLE_LIMIT=10 REC_EVAL_OUTPUT=recommendation/generated/ai-cycle-deepseek.json \
REC_EVAL_GENERATOR_PROFILE=deepseek REC_EVAL_REVIEWER_PROFILE=grok43 REC_EVAL_REPAIR_PROFILE=deepseek \
composer eval:recommendation:cycle

composer eval:recommendation:diff
```

The difference report contains only rows where at least one model disagrees,
scores a tool at 3 or below, reports issues, or fails the cycle. The default
outputs are:

- `recommendation/generated/differences.md`
- `recommendation/generated/differences.json`
- `recommendation/generated/differences.csv`

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
