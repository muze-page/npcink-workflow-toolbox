# Site Check Scheduled Review Cloud Addon Closeout - 2026-07-01

Status: local milestone committed through `31a893f`.

## 背景

本轮讨论从两个困惑开始：

1. `定期站点检查 / Morning Brief` 到底做什么、解决什么问题，是否值得保留。
2. 它和 `站点检查 / operations-insights` 的功能定位是否冲突。

后续又围绕 Cloud Addon 拆分继续收敛：哪些运行状态、恢复、Cloud
详情、备用预览、设置项和历史入口应该迁到 `npcink-cloud-addon`，哪些必须留在
Toolbox 作为本地只读预览或兼容入口。

最终结论是：能力可以保留，但默认产品入口必须更少、更清楚。普通运营人员只需要理解
一个主入口：**Site Check / 站点检查**。其中 **Current Check / 当前检查** 是日常人工站点报告，
**Scheduled Review / 定期巡检** 是低频 dry-run 预览和本地备用设置，不是第二个站点检查、
不是 Cloud 运行恢复台，也不是 Core 提案入口。

## 用户反馈归纳

这一轮暴露的真实问题不是缺少功能，而是入口和文案制造了误解：

- `Morning Brief`、`定期站点检查`、`站点检查`、`高级`、`复核` 等名称混用，用户看不出主次。
- `站点检查详情` 和 `查看定期巡检` 看起来像两个并列能力，但实际一个是当前人工报告，一个是低频预览。
- `高级` tab 里只有跳转卡片，形成了一个没有业务价值的中间入口。
- 定期巡检预览默认展示 raw item、score、reason code，普通运营人员无法理解。
- dry-run JSON textarea 即使折叠，也让页面显得像开发调试台。
- Cloud run recovery 和 Core handoff 曾经出现在 Toolbox 兼容 UI 中，容易让 Toolbox 看起来像第二运行面或第二提案面。
- 本地备用 WP-Cron 设置如果直接暴露，会被误解为 Cloud scheduled inspection。

这些反馈说明：默认页面必须从“解释系统能力”改成“告诉操作者下一步该去哪”。

## 当前产品决策

### Site Check 是唯一默认站点维护入口

`Site Check / 站点检查` 是固定按钮工具箱里的站点级只读决策入口。它回答：

1. 当前站点最应该先看什么。
2. 这个问题应该人工处理、进入既有审核流程，还是暂时观察。
3. 是否需要显式使用 Cloud detail 获得更强解释或排序。

它不负责历史趋势、任务保留、自动复扫、运行恢复、队列、审批或写入。

### Current Check 和 Scheduled Review 必须分隔

Site Check 下保留两个子区：

- **Current Check**：日常人工站点检查。它是普通运营人员的默认入口。
- **Scheduled Review**：低频定期巡检 dry-run 预览。它只确认本地内容读取和预览信号，适合支持、试用、恢复前检查或排查。

两者在同一顶层 tab 下，是为了降低入口数量；两者用子 tab 分隔，是为了避免把“当前报告”和“低频预览”混成一个能力。

### Cloud Addon 拥有运行状态和恢复

Cloud 近期运行、状态读取、结果读取、retry/recovery、权益、配额、运行保留和 runtime detail
应放在 Cloud Addon Runtime Runs 或 Cloud service-plane surface。Toolbox 只保留兼容 REST/JS
读取路径和跳转，不再渲染本地 Cloud run recovery 工作台。

原因：

- Cloud run state 属于 Cloud，不属于本地 Toolbox。
- 恢复和 retry 会天然走向运行控制面，不能放在固定按钮工具箱里。
- Toolbox 如果展示 run history、retry、result retention，就会被误认为第二 Cloud Addon。

### Core proposal follow-up 不从 Scheduled Review 默认 UI 发起

Nightly Inspection review-plan REST/Ability 兼容能力仍保留为 read-only planning artifact，
但默认 Scheduled Review 兼容详情不再提供：

- 选择 Morning Brief review items；
- `Submit selected to Core review`；
- completed draft title/content 输入；
- `Submit completed draft to Core`。

提案、审批、preflight、执行和最终 WordPress 写入仍由 Core/Adapter/Abilities 负责。若未来需要从 Cloud
结果发起 proposal follow-up，应先在 Cloud Addon/Core 明确产品路径和 payload contract，不能在
Toolbox Scheduled Review 中恢复一个本地提案入口。

## 已落实的提交序列

本轮完成的关键提交包括：

- `b706d93`：把 Nightly runtime detail 收敛到 Cloud Addon，Toolbox 不再承担运行恢复控制面。
- `e5bee17`：合并 scheduled review 入口，减少并列入口。
- `ad3f6e2`：折叠 advanced scheduled review entry，避免默认页出现无意义中间目录。
- `d886aa6`：提升 Site Check tab，使普通站点维护有明确入口。
- `aef1f40`：在 Site Check 下拆出 Current Check / Scheduled Review 两个子 tab。
- `57cf13c`：隐藏 scheduled review 默认 raw items，只显示数量摘要。
- `31a893f`：进一步收敛 Scheduled Review：
  - dry-run JSON 改为 nonce-protected download，不再嵌入页面 textarea 或 data URL；
  - `Review items` 改为 `Preview signals`，强调只读线索不是任务；
  - 移除 Nightly Cloud 兼容详情中的 Core review item selection 和 completed draft proposal UI；
  - 更新 docs、tests、zh_CN 翻译和 `.mo`。

## 最终界面规则

后续改动应遵守以下规则：

- 默认入口只叫 **Site Check / 站点检查**，不要恢复单独的 `Advanced` 目录或 `Morning Brief` 产品入口。
- 日常站点维护文案指向 **Current Check**，不要让 Scheduled Review 看起来像日常检查。
- Scheduled Review 默认只显示状态、预览信号数量、执行禁用边界、刷新预览和 Cloud Addon 跳转。
- Local Fallback Preview 必须保留在 advanced disclosure 中，并明确是本地 WP-Cron dry-run fallback，不是 Cloud scheduled inspection。
- raw JSON 只能作为高级下载或支持排查材料，不应作为页面内默认可读内容。
- Cloud result detail 可以作为兼容只读展示，但 recovery、retry、run history 和 proposal follow-up 不应在 Toolbox 默认页面操作。
- 技术边界要存在，但放在说明、disclosure、测试和文档里；默认 UI 应优先让运营人员知道下一步。

## 明确不做

本轮没有引入，也不应在同一产品面补回：

- 本地 runtime、queue、run table、lease、retry processor、dead letter；
- Action Scheduler 作为 Basic 或 Pro Nightly path；
- Cloud scheduler truth 或 Cloud-owned WordPress write；
- 第二 workflow registry、ability registry、approval store；
- Scheduled Review 自动创建 Core proposal；
- direct publish、direct media mutation、direct SEO/meta/taxonomy write；
- `confirm_token` 或 `write_confirmed`；
- 本地长期结果保留或历史趋势。

## 当前功能边界表

| Surface | 当前职责 | 不拥有 |
| --- | --- | --- |
| Site Check / Current Check | 当前站点只读报告、优先处理项、手动处理路径、可选 Cloud detail request | 调度、历史、恢复、写入、提案创建 |
| Site Check / Scheduled Review | 低频 dry-run 预览、本地 WP-Cron fallback 设置、Cloud Addon recovery 链接 | Cloud run recovery、Core proposal follow-up、任务系统 |
| Cloud Addon Runtime Runs | Cloud run history、status/result reads、retry/recovery、entitlement/quota/retention detail | WordPress final write、Core governance truth |
| Core/Adapter/Abilities | proposal、approval、preflight、audit、approved execution、final WordPress callbacks | Cloud runtime execution、Toolbox UI state |

## 验证结果

本轮最后一次代码收敛通过了：

- `php -l includes/Admin_Page.php`
- `php -l includes/Plugin.php`
- `node --check assets/admin.js`
- `composer test:translations`
- `php tests/smoke-nightly-inspection-cloud-ui-contract.php`
- `composer test:all`

并完成推送：

- Branch: `codex/nightly-runtime-addon-boundary`
- Commit: `31a893f Streamline scheduled review preview`

## 后续建议

到当前阶段可以暂停继续拆入口。下一步如果要做，应基于真实后台截图或运营试用反馈，只改可理解性：

- 检查 Current Check / Scheduled Review 两个子 tab 的文案是否仍像“两个产品”。
- 检查 Scheduled Review 默认页是否仍暴露过多技术名词。
- 检查 Cloud Addon 的 Runtime Runs 是否能承接 recovery 和 result detail，不让用户回头找 Toolbox。
- 检查 zh_CN 文案是否使用运营人员能理解的词，而不是内部合同名。

除非出现新的 workflow-level contract，不应继续把 Cloud recovery、Core handoff、历史保留或自动执行能力加回
Toolbox Scheduled Review。
