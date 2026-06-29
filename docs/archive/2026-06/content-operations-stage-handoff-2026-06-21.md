# Content Operations 阶段交接总结 - 2026-06-21

本文归纳本轮围绕云端能力、Toolbox 产品入口、Content Operations 覆盖面板、评论回复建议和反馈质量映射的讨论、实现、验证与后续建议。

## 背景判断

本轮起点是评估“基于当前云端能力，还可以开发哪些实用 AI 功能”。在复查现有能力后，结论不是继续扩建 Cloud，而是在现有 Cloud runtime 能力之上，把 Toolbox 做成更清晰的 operator-facing 产品入口。

核心边界保持不变：

- Cloud 只做 runtime、服务增强、状态和质量信号。
- Cloud 不成为第二个 ability registry、第二个 workflow registry、第二个 WordPress 控制面。
- Toolbox 负责固定按钮、操作员界面、只读状态展示和建议流入口。
- Core、Adapter、Abilities Toolkit 继续承担审查、审批、能力执行和最终写入治理。
- 所有新输出保持 `suggestion_only`，最终 WordPress 写入仍走 `core_proposal_required` 或本地治理路径。

## 阶段建议

当时建议按三步推进：

1. P0: 做一个 Content Operations 状态面板，让当前内容运营能力覆盖面可见。
2. P1: 补一个评论回复建议能力，先做 review-only 候选，不发布评论、不改变评论状态。
3. P2: 把反馈质量信号按 source runtime 拆开，为后续运营质量看板做准备。

用户确认后，本轮按 P0/P1/P2 依次落实。

## 已完成实现

### P0: Content Operations 状态面板

在 Toolbox 后台 Cloud Checks 增加了 `Content Operations` 子面板。

该面板展示：

- 内容运营 surface 覆盖情况。
- 当前 suggestion contracts。
- feedback coverage。
- source runtimes。
- 原始状态 payload。

关键性质：

- 只读状态投影。
- 不新增 Cloud 控制面。
- 不转移 approval truth 或 final write truth。

### P1: 评论回复建议

在 `/editor/content-support` 增加固定 intent：

- `comment_reply_suggestion`

返回 artifact：

- `comment_reply_suggestion.v1`

边界字段：

- `write_posture=suggestion_only`
- `final_write_path=core_proposal_required`
- `direct_wordpress_write=false`
- `comment_publication_policy=operator_review_only_no_comment_publish`
- `comment_status_unchanged=true`

候选生成委托给 Toolkit ability：

- `npcink-abilities-toolkit/build-comment-mention-reply-suggest`

Toolbox 只负责把能力接入编辑器 fixed-flow，并保持审查边界。

### P2: 反馈质量 source runtime 映射

编辑器反馈从单一 `content_support` 细分为：

- `editor_content_support`
- `image_candidates`
- `nightly_site_inspection`
- `site_knowledge`
- `seo_metadata`
- `media_alt_caption`
- `comment_reply`

后台 `Agent Feedback Quality` 面板展示 tracked source runtimes，并强调：

- quality signal only。
- local proposal and write truth stay unchanged。

## 验证记录

### 静态与测试验证

已通过：

- `php -l includes/Rest_Controller.php`
- `php -l includes/Admin_Page.php`
- `php -l tests/run.php`
- `php -l tests/progressive-recommendations-behavior.php`
- `php tests/progressive-recommendations-behavior.php`
- `composer test:all`
- `git diff --check`

### REST live check

本地 WordPress 环境确认：

- `npcink-toolbox` 插件为 active。
- `/npcink-toolbox/v1/status` 返回 200。
- `content_operations` 存在。
- `write_posture=suggestion_only`。
- `direct_wordpress_write=false`。
- source runtimes 包含 `comment_reply`。

`/agent-feedback/summary` 返回 200：

- `events_total=0`
- 返回 quality summary 结构。
- 包含 `source_runtimes` 和 `quality_trend`。

`/editor/content-support` with `intent=comment_reply_suggestion` 返回：

- `status=ready`
- `artifact_type=comment_reply_suggestion.v1`
- 3 条 review-only reply items。
- 3 条 recommendation candidates。
- 不发布评论，不改变评论状态。

### UI 点击级 smoke

浏览器后台点击级 smoke 已完成，记录在：

- `docs/archive/2026-06/content-operations-ui-smoke-2026-06-21.md`

结果：

- Cloud Checks 后台页可进入。
- `content-operations` 子 tab 可点击。
- `agent-quality` 子 tab 可点击。
- Content Operations refresh 成功。
- Agent Feedback Quality refresh 成功。
- 前端 console 无 error/warning。
- smoke 未提交真实 Agent feedback。
- smoke 未发布评论。
- smoke 未产生 WordPress 内容写入。

## 本轮提交

本轮实现与 smoke 收尾完成后，`npcink-toolbox` 本地 `master` 相对 `origin/master` ahead 5。本文件作为额外 handoff 总结提交后，ahead 数会变为 6。

最近相关提交：

- `ba8027b Migrate content support plans to toolkit`
- `a06d452 Delegate taxonomy suggestions to toolkit`
- `bea7898 Add content operations coverage surface`
- `4d591b3 Delegate comment reply suggestions to toolkit`
- `abbc7d6 Document content operations UI smoke`

本文件用于补充会话级 handoff 总结。

## 当前仓库状态

`npcink-toolbox`：

- 本文件写入前，已完成的实现与 smoke 收尾提交处于干净状态。
- 本文件提交后，`master` 预计相对 `origin/master` ahead 6。

`npcink-ai-cloud`：

- 本轮未改 Cloud 代码。
- 仍有一个既有未跟踪文件：
  - `docs/nightly-intelligence-history-summary-2026-06-21.md`

## 后续建议

短期不建议继续扩大功能面。下一阶段应等真实使用反馈后再推进。

优先级建议：

1. 推送或整理当前 Toolbox 的 5 个本地提交。
2. 让实际编辑场景试用 `comment_reply_suggestion`，观察候选是否足够可用。
3. 如果反馈量起来，再把 `Agent Feedback Quality` 从状态面板升级为正式运营质量看板。
4. 若要继续扩展，也应优先扩展 Toolbox 的 fixed-flow 操作入口，而不是增加 Cloud 控制逻辑。

## 交接结论

本阶段可以关闭。当前闭环已经包括：

- 边界复查。
- 三阶段实现。
- 自动测试。
- REST live check。
- 后台点击级 UI smoke。
- smoke 文档记录。

继续工作的最小入口是：基于真实使用反馈，决定是优化评论回复候选质量，还是把反馈质量面板产品化。
