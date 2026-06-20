# Content Support Writing Checkup History

Status: historical summary
Date: 2026-06-20

## Purpose

This document records the product decisions and implementation direction behind
the recent Npcink Content Support writing-checkup work.

The goal is to keep future sessions aligned: Toolbox helps operators improve
human-written articles through diagnostics, review artifacts, and governed
handoffs. It must not become an autonomous writer, article generator, rewrite
engine, approval store, or WordPress write surface.

## Boundary

The current writing-checkup feature stays inside the Toolbox product boundary:

- It supports human editors by pointing out clarity, fact-gap, tone, structure,
  semantic-consistency, and format issues.
- It does not generate replacement paragraphs or article body copy.
- It does not directly modify post content, media, SEO fields, taxonomy, or
  publishing state.
- It keeps generated output suggestion-only and operator-review-only.
- It does not introduce a new REST write path, workflow runtime, queue,
  provider orchestration layer, or Core approval substitute.

The relevant output contracts should continue to preserve:

- `operator_review_only_no_insert=true`
- `direct_wordpress_write=false`
- no automatic body replacement
- no one-click formatting mutation

## Decision History

1. The initial "polish selected text" idea was too close to rewriting and text
   generation. It was reframed as a selected-paragraph check that gives
   diagnostics without replacement copy.
2. The entry point moved from the article-level sidebar action list to the
   selected-block toolbar. The action depends on a selected paragraph, so it
   belongs near the paragraph image suggestion icon instead of as a broad
   sidebar button.
3. A hosted AI no-result state showed that the runtime request could reach the
   provider while no useful text result returned. The UI now avoids a blank
   experience by surfacing diagnostics and local fallback output.
4. A local paragraph overlay was added so selected-paragraph checks can still
   produce bounded clarity, fact-gap, and tone diagnostics when hosted output is
   omitted or incomplete.
5. Selected text handling was tightened so checks use the bounded selected
   paragraph text, not unrelated surrounding article content.
6. Local heuristics were narrowed to reduce false positives. Numeric attachment
   IDs are no longer treated as speed/performance claims, and performance claims
   are separated from generic numeric or identifier-like text.
7. Full-article checkup was kept as a diagnostic surface, not a rewrite surface.
   It checks sentence density, fact gaps, tone, structure, semantic consistency,
   and format issues.
8. Semantic-consistency checks were added for article-level tensions such as
   answer-first AEO structure, unclear term boundaries, or unsupported scope
   shifts.
9. Format-consistency checks were added for layout guidance, such as inline
   numbered or option lists that may be easier to scan as list blocks. This is
   guidance only, not one-click formatting.
10. Article checkup results were grouped by issue family so operators can scan
    the output without adding heavier AI behavior.

## Current State

The current implementation has two bounded writing-support surfaces:

- Selected paragraph check: available from the selected-block toolbar. It
  returns paragraph-level diagnostics and does not insert replacement text.
- Article checkup: available from the Npcink Content Support sidebar. It returns
  full-draft diagnostics grouped by issue type.

Both surfaces are intentionally lightweight. They are useful enough for
operator review while avoiding product weight, hidden writes, or a second
governance system.

## Verification History

The following gates covered the recent implementation stages:

- `composer smoke:editor-hosted-ai-no-result` verifies hosted no-result
  handling, selected-paragraph fallback behavior, semantic and format artifacts,
  and no-write boundaries.
- `composer test:all` remains the default repository gate.
- `composer eval:editor-followup:trial` passed after the current diagnostic
  grouping stage with `85 passed`, `0 warnings`, and `0 failures`.
- Three-model review through the evaluation lab improved from mixed review
  feedback during earlier selected-text diagnostics to pass-through results
  after the article format and grouping stages.

## Related Commits

Recent commits that shaped this feature:

- `e712f8a` - Improve editor paragraph diagnostics
- `598e622` - Add local overlay for paragraph checks
- `cdb90d0` - Add lightweight article semantic check
- `38ad0e8` - Improve paragraph check selected text diagnostics
- `1b88b46` - Add lightweight article format checkup
- `d5dc043` - Group article checkup diagnostics

## What Not To Do Next

Avoid expanding this feature in ways that make Toolbox heavier than the current
product needs:

- Do not restore "polish selected text" as a rewrite or generation feature.
- Do not add one-click article body formatting or automatic block mutation.
- Do not add automatic full-article rewriting.
- Do not create a new provider orchestration, queue, or workflow runtime inside
  Toolbox for this feature.
- Do not persist approval, audit, or learning truth in Toolbox.
- Do not bypass WordPress abilities and Core governance for any final write.

## Recommended Next Phase

Stop feature expansion here and move to operator trial.

The next useful work is not more UI or AI weight. It is to collect real article
checkup examples, identify false positives and missed issues, and tune the
lightweight diagnostics only where operator feedback proves value.

If a release is needed, this feature can be packaged as:

- selected-paragraph check for local diagnostics;
- full-article checkup for grouped draft review;
- no rewrite, no automatic formatting, and no direct WordPress write.
