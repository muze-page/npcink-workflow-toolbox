# Editor Summary Generation Performance

Status: active implementation note.

Date: 2026-06-18.

This note records the editor summary-generation latency investigation, the
current optimization decision, and the remaining trade-offs. It applies to the
post editor `summary_suggestions` intent in Npcink Content Support.

## Scope

This is a performance and product-behavior note for the editor summary shortcut.
It does not change the Toolbox product boundary:

- Toolbox returns excerpt candidates for operator review.
- Toolbox does not write excerpts, SEO metadata, taxonomy, or post content.
- Accepted writes still require the normal editor save path, Core proposal
  handoff, or a future explicitly classified local confirmation contract.
- Cloud hosted AI may generate wording, but it does not receive write authority.

## User Problem

The editor needs high-quality summary suggestions that feel fast enough for
daily author use. The target experience is roughly 3 to 5 seconds for ordinary
regeneration when possible, while keeping a slower high-quality fallback for
hard cases.

The observed behavior before optimization was:

- clicking the ordinary rerun button could return almost instantly but with the
  same result;
- a true Cloud hosted AI regeneration took about 12 seconds;
- the result quality was acceptable, but the wait felt too long for a focused
  editor sidebar tool.

## Root Cause

The "instant but unchanged" behavior was cache-related. A repeated ordinary
request with the same payload hit the short hosted-AI cache, so it returned the
same candidates quickly.

True regeneration was slow because the default hosted AI request was too heavy
for an interactive summary shortcut. The measured default payload was roughly:

| Metric | Before optimization |
| --- | ---: |
| User message size | about 26 KB |
| Rough token estimate | about 6.5k tokens |
| Hosted AI timeout | 30 seconds |
| Hosted AI elapsed time | about 12 seconds |
| Site Knowledge blocking time | 0 ms |

The bottleneck was therefore the Cloud hosted AI model call, not WordPress,
REST routing, local caching, or Site Knowledge vector lookup. In `fast_brief`
mode, Site Knowledge only uses an existing short cache hit and does not block
first results.

## Product Tension

The main tension is:

```text
high-quality structured review
vs
3-5 second interactive regeneration
```

The original request combined too many jobs in one AI call:

- generate three public-facing excerpt candidates;
- perform coverage analysis;
- return reasons and quality fields;
- follow a large quality contract;
- process full site context and related context;
- support regeneration variety.

For the focused editor shortcut, this is too much work for the default path.
The richer review still has value, but it belongs in the advanced rerun path.

## Decision

Use two summary-generation paths:

| Path | Intended use | Prompt shape | Quality handling |
| --- | --- | --- | --- |
| `fast_summary_v2` | Default summary generation and ordinary regeneration | Short JSON-only prompt with three excerpt fields | PHP post-processing handles length, meta wording, coverage, and reranking |
| `full_context` | Advanced rerun fallback | Rich quality contract and fuller context | Hosted AI performs more reasoning before local parsing and review |

The default `summary_suggestions + fast_brief` path now asks Cloud hosted AI for
only this shape:

```json
{
  "recommended_excerpt": "...",
  "alternate_excerpt": "...",
  "third_excerpt": "..."
}
```

The default prompt intentionally omits:

- full content context;
- related-content context;
- post context wrappers;
- large quality contracts;
- editor-facing explanation fields;
- coverage-check objects.

The richer full-context behavior remains available through the advanced rerun.

## Implementation Summary

The implementation keeps the same public editor intent and REST route:

- route: `/wp-json/npcink-toolbox/v1/editor/content-support`
- intent: `summary_suggestions`
- default summary mode: `fast_brief`
- advanced fallback mode: `full_context`

Key implementation points:

- ordinary summary regeneration sends `force_regenerate=true`;
- forced regeneration bypasses the hosted AI transient cache;
- the response timing exposes `hosted_ai_cache_status=bypass` for forced runs;
- the default hosted payload carries `summary_prompt_mode=fast_summary_v2`;
- `max_tokens` for fast summary is reduced to 260;
- fast summary timeout is reduced to 12 seconds;
- full-context summary fallback keeps the richer prompt and 60 second timeout;
- local PHP still strips meta phrasing, enforces the 50-160 Chinese character
  review band, scores candidates, and reranks them.

## Measured Result

Local smoke testing against post `279876` showed:

| Metric | Before | After |
| --- | ---: | ---: |
| User message size | about 26 KB | about 5.8 KB |
| Rough token estimate | about 6.5k tokens | about 1.4k tokens |
| Hosted AI elapsed time | about 12 seconds | about 6.2 seconds |
| Candidate count | 3 | 3 |
| First candidate quality score | acceptable | 100 in the sampled run |

Example output after optimization:

```text
Somnia Pro 是一款售价598元的 Linear 风格博客社区主题，采用灰白黑简洁设计，支持用户中心、积分充值、VIP会员、付费阅读与社区互动。
```

This is close to the desired 3 to 5 second target, but not yet reliably inside
that target. The remaining latency is still mostly Cloud model execution time.

## Why Not Pure Cache

Cache is useful for accidental duplicate clicks, but it does not satisfy the
editor expectation for "regenerate". A visible regenerate action should produce
a fresh wording attempt. Therefore:

- cache hits are acceptable for repeated non-forced checks;
- ordinary summary rerun should force regeneration;
- forced regeneration should not write the new result into the short cache as a
  replacement for normal deterministic cache behavior.

## Why Not Make Everything Full Quality

The full quality contract is valuable for hard articles, but it is not the
right default for the sidebar. It increases prompt size, model reasoning time,
and output complexity. The editor default should optimize for quick reviewable
candidates, then rely on local deterministic checks for common quality issues.

Advanced rerun exists for cases where:

- the fast result misses important late-section content;
- the subject has multiple tools, workflows, or product relationships;
- the editor wants a more careful full-draft pass;
- speed is less important than coverage.

## Remaining Options To Improve Speed

Further speed work should focus on the Cloud/runtime side or progressive
response behavior:

1. Use a faster Cloud model or faster model route for `fast_summary_v2`.
2. Return the recommended excerpt first and generate alternates later.
3. Reduce the default output to one candidate plus one optional alternate.
4. Add Cloud-side structured output support so the model does less formatting
   work.
5. Keep a site-level style profile precomputed in Cloud instead of sending
   repeated style/context hints.
6. Track latency percentiles per model and article length, not only one-off
   local smoke timing.

## Acceptance Criteria

The current implementation is acceptable for the next local trial when:

- ordinary summary rerun sends `force_regenerate=true`;
- forced rerun reports `hosted_ai_cache_status=bypass`;
- default hosted payload reports `summary_prompt_mode=fast_summary_v2`;
- default prompt stays small enough for interactive use;
- full-context rerun remains available as the quality fallback;
- summary candidates remain suggestion-only and never write the post excerpt
  without the editor choosing and saving.

## Verification Used

The implementation was verified with:

```bash
php -l includes/Provider_Client.php
php tests/run.php --filter="summary"
node --check assets/editor-content-support.js
composer test:all
git diff --check
```

The full static contract gate passed with `1175 passed` in the recorded run.
