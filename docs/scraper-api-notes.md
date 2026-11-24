# Scraper API 侦察记录

日期：2026-11-22（Asia/Shanghai）
目标：`http://127.0.0.1:8080`
原则：只做只读侦察，不修改、不重启、不覆盖原 Scraper。

## 1. `GET /api/v1/jobs`

已执行：

```bash
curl -sS -i http://127.0.0.1:8080/api/v1/jobs
```

- HTTP：`200 OK`
- `Content-Type`：`application/json`
- 顶层结构：JSON **数组**（不是对象、不是分页对象）
- 原始响应已保存为 `tests/fixtures/scraper_jobs.json`
- 当前实测样例：

```json
[
  {
    "ID": "b5a6e8a7-60ca-4117-8a82-3c2fe0f73057",
    "Name": "London Plumbers Test",
    "Date": "2026-11-22T05:45:02Z",
    "Status": "ok",
    "Data": {
      "keywords": ["plumbers in London"],
      "lang": "en",
      "zoom": 15,
      "lat": "51.5072",
      "lon": "-0.1276",
      "fast_mode": true,
      "radius": 10000,
      "depth": 10,
      "email": true,
      "extra_reviews": false,
      "max_time": 600000000000,
      "proxies": null
    }
  }
]
```

### 已验证字段

| 字段 | 类型 | 用途 |
|---|---|---|
| `ID` | string UUID | 外部任务 ID |
| `Name` | string | 任务名称 |
| `Date` | ISO 8601 UTC | 创建时间 |
| `Status` | string | 外部状态；当前样本为 `ok` |
| `Data.keywords` | string[] | 关键词数组 |
| `Data.lang` | string | 语言 |
| `Data.zoom` | number | 缩放 |
| `Data.lat` / `Data.lon` | string | 中心坐标 |
| `Data.fast_mode` | boolean | 快速模式 |
| `Data.radius` | number | 半径（米） |
| `Data.depth` | number | 深度 |
| `Data.email` | boolean | 邮箱选项 |
| `Data.max_time` | number | 当前样本为 `600000000000`，单位未确认 |

### 未确认

- `Status` 的全量枚举。当前只实测到 `ok`。
- 外部任务失败时、排队时、运行中的状态值。
- `Data.max_time` 的单位与合法格式；页面表单示例为 `10m`，与 JSON 中数值不一致。
- API 是否分页、是否有隐藏 query 参数。
- JSON 中完全没有 CSV 行数据，因此仍必须通过 `/download` 获取结果。

## 2. `POST /scrape`

文档给出的表单字段已实现为集中映射：

```
name, keywords, lang, zoom, latitude, longitude, radius, depth, maxtime
```

布尔字段 `fastmode` / `email` 的实现约定：

- 开启时发送 `on`，关闭时省略。
- **尚未实测确认**。在验证完成前，页面明确提示该限制。
- 如提交超时或无可靠任务 ID，系统标记为 `submission_unknown`，禁止自动重试。

任务 ID 解析：

1. 从响应正文中查找 UUID。
2. 若响应是 JSON，读取 `id` / `ID` / `job_id` / `jobId` / `data.id`。
3. 都找不到时返回 `null`，由调用方标记未知，不编造 ID。

## 3. `GET /download?id=<job_id>`

- 已验证可从现有任务下载 CSV。
- 导入器验证：HTTP 200、文件非空、大小 ≤ 50 MB、表头包含 `input_id`。
- 原始文件保存到 `storage/raw/`，文件名加入内容哈希，避免覆盖。
- **实测空结果**：任务 `ec40b9b6-37c5-468b-a700-0567f97f198a` 的外部状态为 `ok`，但 `/download` 返回 `HTTP 200`、`Content-Type: text/csv`、`Content-Length: 0`。
  - 这不是网络下载失败，而是合法的 0 行结果。
  - 本地现在将其标记为 `completed`、`result_count = 0`，并记录一条 `empty` 导入日志；页面显示“空结果”提示。
  - 该任务坐标约为 `48.284573, 31.376953`（乌克兰附近）、zoom 3，关键词为 `plumbers in London`，可解释为什么没有搜索结果。

## 4. 真实 CSV 样本

样本：`~/Desktop/london-plumbers-test`（15 行）
复制到：`tests/fixtures/london-plumbers-test.csv`

实际读取结果（PHP `fgetcsv` 原始值）：

- `website`：`https://www.pimlicoplumbers.com/`，**没有 Markdown 包装**。
- `data_id`：`0x487604bfe981a7d5:0x5c52ea845400c21e`
- `complete_address`：合法 JSON 字符串。
- `emails` 在该样本中为空；不能据此推断所有任务都无法提取邮箱。
- `place_id`、`cid`、`category` 在该样本中为空；不得设为必填。

## 5. 实现结论

- 只使用已验证字段构造适配器；未知字段保存在原始 JSON 中。
- 外部 `ok` 不等于本地已完成：必须下载并导入 CSV 后，本地状态才变为 `completed`。
- 原始 CSV 行按任务保存到 `raw_records`，以内容哈希与行号去重。
- 任务同步支持把外部任务元数据映射到本地；CSV 导入可由详情页手动触发，或在同步时自动下载。
- 并发、取消接口、429/5xx 的正式行为仍待后续实测。
