# Site Check Decision Entry Closeout - 2026-06-30

Status: local milestone committed in `fc9700a`.

## 背景

本轮讨论从 `operations-insights` 页面开始。最初的问题是：
`全站洞察 / Full-site Insights` 到底做什么、解决什么问题、是否值得保留，以及它是否和
`npcink-workflow-toolbox` 的定位冲突。

结论是：这个能力值得保留，但不能继续像一个独立分析产品或运营仪表盘。它应该改名并收敛为
**Site Check / 站点检查**：固定按钮工具箱里的站点级决策入口。它的任务不是替代 Cloud、
Core 或 workflow runtime，而是把当前站点的只读证据整理成下一步操作判断。

## 产品判断

Site Check 的价值不在于提供更多指标，而在于回答普通站点操作者的三个问题：

1. 现在最应该先看什么？
2. 这个问题应该人工处理、进入既有审核流程，还是暂时观察？
3. 什么时候才需要显式调用 Cloud detail？

因此它不应该是新的分析中心、任务系统、队列系统、审批系统或历史趋势系统。它也不应该把
Cloud 的语义总结、排序和解释能力做成本地控制面。Cloud 在这里只负责可选的 runtime/detail；
Toolbox 只展示建议和分流，不创建 Core proposal，不写 WordPress。

## 已落实的整改

本轮已把可见能力从 `Full-site Insights` 收敛为 `Site Check / 站点检查`，并调整为固定工作流
入口：

- Start/Overview 页面把站点检查作为主要建议动作，而不是把它暴露成一个大的分析 tab。
- `operations-insights` 深链接保留，用于兼容已有入口和报告 URL。
- 报告默认视图改成行动优先：先显示 site action brief、优先处理项、第一步安全操作和处理路径。
- 覆盖范围、图表、证据、JSON、Cloud detail 放到 disclosure 或二级 tab 中，避免默认页面像报表。
- 每个主要 finding 增加只读 first-action links，指向受影响文章、媒体、评论、分类、站点资料、
  内容库使用页或 Cloud detail。
- Review-workflow candidate 增加折叠预览，只展示候选对象、证据和建议备注，不创建 proposal。
- 优先级显示从裸分数改为可读标签。
- 修复 `toolbox_tab=operations-insights` 服务端深链接渲染，避免登录后默认回到 Overview。
- 补齐 zh_CN 翻译并重新生成 `.mo`。
- 更新相关文档、边界说明、Cloud detail wording、静态合同和浏览器 smoke。

## 边界确认

这次整改没有引入以下能力：

- 本地 runtime、队列、调度、重试或 run table；
- 第二 workflow registry、第二 ability registry、第二 approval store；
- Core proposal 创建；
- 直接发布、媒体写入、SEO 写入、taxonomy 写入或其他 WordPress final write；
- `confirm_token` 或 `write_confirmed`；
- Cloud 自动执行、自动重试或自动写回。

Site Check 当前只做 bounded read-only scan、operator decision routing、optional Cloud detail request
和 review-only display。任何写入型后续动作都必须留在正常编辑路径、既有固定 workflow，或未来
Core-governed handoff 中。

## 验证结果

本轮收尾前通过了以下检查：

- `php -l includes/Admin_Page.php`
- `php tests/run.php --quiet`
- `NODE_PATH=... composer smoke:site-ops-insights-browser`
- `composer test:all`
- `msgfmt --check --output-file=languages/npcink-workflow-toolbox-zh_CN.mo languages/npcink-workflow-toolbox-zh_CN.po`
- `git diff --check`

浏览器 smoke 覆盖了真实 WordPress admin 路径，确认：

- Overview 从 action brief 开始；
- treatment path 面板可见；
- decision queue 解释原因、影响对象、第一步安全操作和处理方式；
- first-action buttons 可见；
- Cloud detail 不会自动运行；
- 没有调用 Core proposal、execute 或 Cloud detail routes。

## 本轮发现的问题

页面“不好用”的根因不是缺少分析能力，而是默认信息架构错误：

- 操作者第一屏看到的是报告感和指标感，不是下一步决策。
- 维度 tab 和覆盖数据太靠前，导致用户需要自己翻译“这些指标意味着我要做什么”。
- Cloud detail 曾经看起来像并列分析入口，而不是一个可选解释层。
- finding 曾经缺少直接的第一步入口，用户看完建议后还要自己找受影响对象。
- 技术边界文案过多时会挤占操作焦点；边界需要存在，但应折叠在规则和限制里。

这些问题已经通过 action brief、decision queue、treatment path、first-action links 和 folded detail
做了第一轮修正。

## 后续停止线

短期不要继续扩展 Site Check 的能力范围。下一步如果要继续做，只应围绕真实操作者试用反馈优化：

- 哪些 first-action links 最常用；
- 哪些 finding 需要更好的文案或排序；
- 哪些 item 应该明确导向已有固定 workflow；
- 哪些 Cloud detail 输出确实帮助决策，哪些只是噪声。

在没有新的 workflow-level contract 前，不应把历史趋势、结果保留、自动复扫、任务创建、批量执行或
Cloud run recovery 做进这个本地插件页面。
