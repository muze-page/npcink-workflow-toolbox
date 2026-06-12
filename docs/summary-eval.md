# AI Summary Evaluation

Status: first hard-gate scaffold.

This development workflow now lives in the sibling
`/Users/muze/gitee/magick-ai-eval-lab` repository. Toolbox keeps Composer
proxy commands for convenience, but the scripts, fixtures, generated files, and
provider-key environment handling are owned by the eval lab.

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

The default fixture lives at `summary/samples.json`. The script checks
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
php summary/run.php /path/to/generated-summary-candidates.json
```

## Export Local WordPress Samples

After importing representative content into the local WordPress site, export
source samples by author:

```bash
composer eval:summary:export
```

The default author is `Muze`, and the default output path is
`summary/generated/muze-source-samples.json`. Override when needed:

```bash
SUMMARY_EVAL_AUTHOR="Muze" SUMMARY_EVAL_LIMIT=100 composer eval:summary:export
```

The exported file is source material, not a pass/fail eval file yet. It includes
post title, body text, existing excerpt, categories, tags, and forbidden summary
phrases. Add generated candidates before running it through
`summary/run.php`.

## Generate Candidates

Generate AI summary candidates from the exported source samples:

```bash
composer eval:summary:generate
```

The default input is `summary/generated/muze-source-samples.json`,
the default output is `summary/generated/muze-candidates.json`, and
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
php summary/run.php summary/generated/muze-candidates.json
```

## Coverage Quality

Summary generation must cover the article's core subject before polishing the
wording. The hosted prompt asks the model to identify the core subject, content
type, title positioning, primary reader value, must-cover points, and
relationship rules before writing the excerpt. This is especially important for:

- product introductions, where the excerpt must cover product type or
  positioning plus the highest-value capability groups instead of only
  secondary details such as license, UI framework, or implementation notes;
- tutorials, where the excerpt must cover the main workflow, scenarios, or
  decision path instead of only the first step or one local section;
- tool/process articles, where the excerpt must not confuse which tool,
  method, or step applies to which scenario.

When the article has more detail than the excerpt length allows, compress
details into capability or scenario groups. Title-level differentiators are
must-cover when the supplied draft supports them. Treat `核心对象缺失`,
`只覆盖局部小节`, unrepresented must-cover point groups, dropped title-level
positioning, and object/tool relationship confusion as quality failures even
when the excerpt is fluent and within the length band.

The editor runtime also applies a lightweight candidate gate after hosted AI
returns. The hosted prompt now sends the full normalized draft body for summary
generation, with a high runtime-safety character cap only for extreme outliers.
It also sends segment-level coverage hints for the lead, middle, and end of
the draft so the model can notice later-section tools, scenarios, or workflow
branches instead of overfitting to the opening section.
The runtime quality gate also checks whether named tools or methods appear in
multiple draft segments; candidates that only cover one segment are downgraded
for review instead of being treated as clean passes.
Because full-draft summaries can take longer than title, outline, or polish
support, `summary_suggestions` uses a longer hosted-runtime timeout while the
other lightweight draft-support intents keep the shorter synchronous budget.
The heading map, extracted key terms, and lead/middle/end hints remain as
coverage aids, not as a replacement for the draft. The runtime gate does not
call a second model. It cleans meta lead-ins, checks the 50-160 character review band, reads
`coverage_check`, scores each candidate for core-subject, title-positioning,
and must-cover-point coverage, then sorts the candidates by quality score
before showing the top three. Weak coverage candidates are flagged for review
instead of being hidden, so the editor does not see an empty result when the
model response is usable but imperfect. Detailed quality notes stay in the
on-demand evidence modal so the narrow editor panel remains focused on the
recommended excerpt, apply action, and regeneration.

Offline model judges remain a batch and release-quality tool, not the default
editor-click path. Use GPT/DeepSeek cross review when calibrating prompts,
reviewing regressions, or generating summaries for many posts.

## Human Review Worksheet

After candidates pass the hard gate, export a lightweight worksheet for manual
adoption review:

```bash
composer eval:summary:review
```

The default input is `summary/generated/muze-candidates.json`. The
command writes ignored local files with Chinese review columns:

- `summary/generated/summary-human-review.md`
- `summary/generated/summary-human-review.json`
- `summary/generated/summary-human-review.csv`
- `summary/generated/summary-human-review.xlsx`

Override the input and output paths when reviewing a specific batch:

```bash
SUMMARY_REVIEW_INPUT=summary/generated/muze-candidates-offset25-limit20.json \
SUMMARY_REVIEW_MD=summary/generated/muze-candidates-offset25-review.md \
SUMMARY_REVIEW_JSON=summary/generated/muze-candidates-offset25-review.json \
SUMMARY_REVIEW_CSV=summary/generated/muze-candidates-offset25-review.csv \
SUMMARY_REVIEW_XLSX=summary/generated/muze-candidates-offset25-review.xlsx \
composer eval:summary:review
```

Use the XLSX file when you want dropdowns. It includes dropdowns for
`评分(1-5)`, `采用决策`, and `问题类型`. Use `评分(1-5)` as the primary
quality signal:

- `1`: unusable; generation failed, factually wrong, generic, or unsuitable.
- `2`: weak; related to the topic but needs major rewriting.
- `3`: usable direction; the editor would rewrite it before publishing.
- `4`: good; only minor edits are needed.
- `5`: excellent; can be used as the current post excerpt.

Use `direct_use`, `minor_edit`, or `reject` for the `采用决策` field as the
adoption decision. Keep failure reasons coarse in `问题类型`:
`generation_error`, `too_generic`, `missing_core_value`, `logic_confusing`,
`misleading`, `wrong_tone`, `instruction_like`, `insufficient_coverage`,
`too_marketing`, `too_short`, `too_long`, `unsupported_claim`, or `other`.

## Opening Diversity

If generated excerpts start too often with formulaic audience labels such as
`面向` or `适合`, run the offline opening analysis:

```bash
SUMMARY_OPENINGS_INPUT=summary/generated/muze-candidates-offset25-limit20.json \
composer eval:summary:openings
```

The report counts opening prefixes, audience-label rate, and cases where both
candidates for the same sample use the same opening bucket. It does not call
Cloud or WordPress.

## Promptfoo Offline Metrics

Use Promptfoo when comparing batches or prompt variants without hand-counting
basic failures:

```bash
npm install -g promptfoo
SUMMARY_PROMPTFOO_INPUT=summary/generated/muze-candidates-offset25-limit20.json \
composer eval:summary:promptfoo
```

The Composer command first exports `summary/generated/promptfoo-cases.csv`
and then runs the local `promptfoo eval -c summary/promptfoo.yaml`.
The Promptfoo config treats generated candidates as offline outputs and scores
them with local JavaScript assertions:

- generation success: no `ERROR:` placeholder.
- excerpt length: 50-160 Chinese characters, with 70-140 preferred.
- meta framing: no `本文说明`, `这篇文章`, `这篇草稿主张`, or similar wording.
- opening quality: formulaic `面向` / `适合` / `需要` openings receive a lower score.

This layer is for repeatable prompt/batch comparison. It does not call Cloud,
write WordPress data, or replace human 1-5 review scores.

## AI Judge Pre-Scoring

After a prompt version passes hard gates, run an AI-judge pass to pre-score
candidates before manual review:

```bash
export OPENAI_API_KEY=...
SUMMARY_JUDGE_INPUT=summary/generated/muze-candidates-offset25-limit30-newprompt.json \
SUMMARY_JUDGE_LIMIT=20 \
composer eval:summary:judge
```

The command exports `summary/generated/promptfoo-judge-cases.csv` and
then runs `summary/promptfoo-judge.yaml` with Promptfoo
`llm-rubric`. It compares the candidate summary against the supplied title and
article content, using a 1-5 equivalent rubric:

- 1: unusable, generated error, factual error, or misleading.
- 2: related but needs major rewriting.
- 3: direction is usable but requires clear editing.
- 4: good; minor edits only.
- 5: can be used directly.

By default the Composer script uses `openai:gpt-4.1-mini` as the grader. Override
with `SUMMARY_JUDGE_GRADER` if needed. OpenAI graders require
`OPENAI_API_KEY`. Keep this as pre-scoring only: calibrate it against
human-filled review sheets before using it to reduce manual review.

### Cross-Model Judge

For stronger pre-scoring, run GPT-5.5 and DeepSeek independently and compare
their decisions:

```bash
export GPT55_API_KEY=...
export DEEPSEEK_API_KEY=...
SUMMARY_JUDGE_INPUT=summary/generated/muze-candidates-offset25-limit30-newprompt.json \
SUMMARY_JUDGE_LIMIT=20 \
composer eval:summary:judge:cross
```

Defaults:

- GPT-5.5: `openai:chat:gpt-5.5` with
  `GPT55_BASE_URL=https://api.mqzj.top/v1`.
- DeepSeek: `deepseek:deepseek-v4-pro`, using Promptfoo's DeepSeek provider.

The cross-judge command writes:

- `summary/generated/promptfoo-judge-gpt55.json`
- `summary/generated/promptfoo-judge-deepseek.json`
- `summary/generated/promptfoo-judge-cross.json`
- `summary/generated/promptfoo-judge-cross.csv`

Override model-specific outputs with `SUMMARY_JUDGE_GPT55_OUTPUT` and
`SUMMARY_JUDGE_DEEPSEEK_OUTPUT`. Override comparison inputs with
`SUMMARY_JUDGE_PRIMARY` and `SUMMARY_JUDGE_SECONDARY`. Do not use the generic
`SUMMARY_JUDGE_OUTPUT` for cross-model runs, because each model needs its own
result file before comparison.

Promptfoo may return a non-zero status when a grader marks candidates as weak.
The Composer scripts still continue if the model result JSON is written,
because low scores are review data rather than execution failures.

Treat cross-judge results as triage, not final truth. Prioritize manual review
when either model scores `1-2`, either model flags misleading or confusing
logic, or the two model scores differ by 2 or more.

## Next Layer

After the hard gate is stable, add a model-judged layer for faithfulness,
coverage, reader usefulness, and regeneration diversity. Keep that layer outside
`composer test:all` unless it can run deterministically without external
network or provider credentials.
