# 全球商家采集管理后台开发文档

## 方案 A：独立 Web 管理后台

-   文档版本：1.0
-   日期：2026-11-22
-   项目代号：Global Business Scraper Admin
-   目标运行环境：Mac mini 本地开发，后续可部署到 Linux 服务器
-   开发方式：分阶段交付，建议由 Codex 按本文档逐阶段实施

------------------------------------------------------------------------

# 1. 项目目标

建设一个独立的全球商家采集管理后台。现有 Google Maps Scraper
继续作为外部采集引擎运行，本项目通过 HTTP 接口调用它、读取任务状态、下载
CSV，并将数据清洗后导入自己的数据库。

**基本原则：不修改、不覆盖、不重构原 Scraper 的核心程序。**
新系统通过适配器与原服务通信。即使未来更换采集引擎，也只需替换适配器，不影响商家数据库和管理后台。

## 1.1 第一版目标

1.  管理国家、州/省、城市。
2.  管理行业分类及分类层级。
3.  管理多语言搜索关键词。
4.  创建单个和批量采集任务。
5.  调用现有 Scraper 创建任务。
6.  查看任务状态、结果数量、失败信息。
7.  自动下载并导入 CSV。
8.  保存完整原始数据，同时生成标准化商家资料。
9.  依据多个标识进行商家去重和合并。
10. 提供商家搜索、筛选、详情和导出。
11. 对官网公开商务联系方式进行可控补全，作为独立后续模块。

## 1.2 第一版暂不做

-   不开发自己的 Google Maps 抓取引擎。
-   不修改现有 Scraper 的代码和数据库。
-   不做自动群发邮件、短信或电话营销。
-   不绕过登录、验证码、付费墙或访问限制。
-   不把推测出的邮箱当作已验证邮箱。
-   不在第一版引入复杂的多租户、计费和权限体系。

------------------------------------------------------------------------

# 2. 已知现有 Scraper 信息

当前已在 Mac mini 本机验证服务运行正常：

`http://127.0.0.1:8080`

已观察到的接口：

  --------------------------------------------------------------------------------------------------------
  方法              路径                           当前已知用途      说明
  ----------------- ------------------------------ ----------------- -------------------------------------
  POST              `/scrape`                      创建采集任务      `application/x-www-form-urlencoded`

  GET               `/jobs`                        返回 HTML/HTMX    不是 JSON API
                                                   任务列表          

  GET               `/api/v1/jobs`                 返回 JSON         已确认 HTTP 200，结构待检查

  GET               `/download?id=<job_id>`        下载任务 CSV      已成功下载

  GET               `/view?id=<job_id>`            查看任务结果      页面/响应结构待检查

  DELETE            `/delete?id=<job_id>&page=1`   删除任务          已在 HTML
                                                                     按钮中观察到，调用前需验证行为
  --------------------------------------------------------------------------------------------------------

创建任务表单已观察到的字段：

  字段          示例/默认值            用途
  ------------- ---------------------- ------------------------------
  `name`        London Plumbers Test   任务名称
  `keywords`    plumbers in London     搜索关键词
  `lang`        en                     搜索语言
  `zoom`        15                     地图缩放
  `latitude`    51.5072                纬度
  `longitude`   -0.1276                经度
  `fastmode`    on 或不传              快速模式，实际布尔约定待验证
  `radius`      10000                  搜索半径
  `depth`       10                     搜索深度
  `email`       on 或不传              邮箱提取选项
  `maxtime`     10m                    最大任务时间
  `proxies`     空                     代理列表

**重要：**
上述参数来自当前网页表单及一次成功请求。程序实现时应将字段名集中配置，不要散落在各处。`fastmode`、`email`
的关闭方式、数值边界、代理格式以及 `maxtime`
的合法格式必须通过当前服务实测，不能自行假设。

## 2.1 已实测 CSV 列

当前下载文件识别为 CSV，表头如下：

``` text
input_id,link,title,category,address,open_hours,popular_times,website,phone,plus_code,review_count,review_rating,reviews_per_rating,latitude,longitude,cid,status,descriptions,reviews_link,thumbnail,timezone,price_range,data_id,street_view_url,place_id,images,reservations,order_online,menu,owner,complete_address,credit_cards_accepted,about,user_reviews,user_reviews_extended,emails,review_collection
```

一次伦敦水管工测试共 15 条记录，观察到：

  字段                     非空数量
  ---------------------- ----------
  title                       15/15
  phone                       15/15
  website                     15/15
  latitude / longitude        15/15
  data_id                     15/15
  complete_address            15/15
  address                     12/15
  emails                       0/15
  place_id                     0/15
  cid                          0/15
  category                     0/15

数据解释注意事项：

-   `input_id` 在同一任务的 15
    行中相同，属于输入/任务相关标识，不得作为商家唯一键。
-   `data_id` 样例为 `0x...:0x...`，是候选 Google Maps
    外部标识。可作为高优先级去重依据，但必须先检查是否存在重复、跨任务是否稳定。
-   `cid` 和 `place_id` 当前样本为空，不得设为必填。
-   `complete_address` 看起来是 JSON 字符串，导入时解析并保留原文。
-   终端展示的 `website` 看起来像 Markdown 链接，但可能是聊天界面对 URL
    的格式化。必须检查原始 CSV 文件字节和 CSV
    解析结果，再决定是否需要去除 Markdown 包装。
-   `emails` 本次 15
    条均为空，只能说明该批数据没有返回邮箱，不能据此判断所有任务都无法提取邮箱。
-   `category`
    本次为空，行业分类应由任务配置和后续人工/规则补全，不得将任务关键词直接当成已确认商家类别。

------------------------------------------------------------------------

# 3. 总体架构

``` text
浏览器
  |
  v
独立 Web 管理后台
  |
  +-- FastAPI 路由/API
  +-- Jinja2 页面 + HTMX 交互
  +-- 任务编排服务
  +-- Scraper 适配器
  +-- CSV 下载/解析/清洗
  +-- 商家去重与合并
  +-- 官网公开联系方式补全模块（后续阶段）
  |
  v
SQLite 数据库
  |
  +-- 国家/地区/行业/关键词
  +-- 采集任务与任务明细
  +-- 原始 CSV 行
  +-- 标准化商家
  +-- 联系方式与来源
  +-- 导入日志/审计日志

独立后台 --HTTP--> 现有 Google Maps Scraper
                         |
                         +-- POST /scrape
                         +-- GET /api/v1/jobs
                         +-- GET /download?id=...
```

## 3.1 技术选型

  层            技术                                  原因
  ------------- ------------------------------------- --------------------------
  后端          Python 3.11+、FastAPI                 接口清晰、开发快
  页面          Jinja2 + HTMX + 少量原生 JavaScript   第一版无需复杂前端构建
  UI 样式       Tailwind CSS（可先用本地构建产物）    响应式、组件统一
  数据库        SQLite                                单机开发和第一版足够
  ORM           SQLAlchemy 2.x                        后续迁移 PostgreSQL
  数据校验      Pydantic                              表单/API 参数校验
  HTTP 客户端   httpx                                 调用 Scraper
  CSV           Python csv 模块                       可靠处理引号、逗号和换行
  后台任务      第一版数据库任务队列 + 独立 worker    控制并发和可恢复性
  导出          CSV；Excel 使用 openpyxl              可交付给业务人员
  测试          pytest                                自动化验收

第一版不建议直接引入 Redis、Celery、Kubernetes
等基础设施。任务量上升后再评估。

## 3.2 运行端口

-   原 Scraper：`127.0.0.1:8080`
-   新管理后台：`127.0.0.1:9000`
-   SQLite：项目 `data/` 目录
-   日志：项目 `logs/` 目录

后台服务应默认只监听本机
`127.0.0.1`。如需局域网或公网访问，必须另行配置身份验证、HTTPS、防火墙和访问控制。

------------------------------------------------------------------------

# 4. 推荐项目目录

``` text
global-business-admin/
├── app/
│   ├── main.py
│   ├── config.py
│   ├── database.py
│   ├── models/
│   │   ├── country.py
│   │   ├── region.py
│   │   ├── industry.py
│   │   ├── keyword.py
│   │   ├── scrape_job.py
│   │   ├── business.py
│   │   ├── contact.py
│   │   └── raw_record.py
│   ├── routers/
│   │   ├── dashboard.py
│   │   ├── countries.py
│   │   ├── industries.py
│   │   ├── keywords.py
│   │   ├── jobs.py
│   │   ├── businesses.py
│   │   ├── exports.py
│   │   └── settings.py
│   ├── services/
│   │   ├── scraper_client.py
│   │   ├── job_service.py
│   │   ├── csv_importer.py
│   │   ├── data_cleaner.py
│   │   ├── deduplicator.py
│   │   ├── business_service.py
│   │   └── website_contact_finder.py
│   ├── workers/
│   │   └── job_worker.py
│   ├── schemas/
│   ├── templates/
│   │   ├── base.html
│   │   ├── dashboard.html
│   │   ├── jobs/
│   │   ├── businesses/
│   │   ├── countries/
│   │   ├── industries/
│   │   └── keywords/
│   └── static/
│       ├── css/
│       ├── js/
│       └── images/
├── data/
│   ├── app.db
│   ├── raw/
│   └── exports/
├── logs/
├── tests/
│   ├── fixtures/
│   ├── test_scraper_client.py
│   ├── test_csv_importer.py
│   ├── test_deduplicator.py
│   └── test_jobs.py
├── scripts/
│   ├── inspect_scraper_api.py
│   └── import_csv.py
├── .env.example
├── .gitignore
├── requirements.txt
├── README.md
└── alembic/
```

------------------------------------------------------------------------

# 5. 数据库设计

采用 SQLAlchemy 管理模型，Alembic 管理迁移。SQLite
开启外键约束。时间统一保存 UTC，界面按用户设置显示。

## 5.1 核心表

### countries 国家

  字段               类型                说明
  ------------------ ------------------- --------------------
  id                 INTEGER PK          主键
  iso2               VARCHAR(2) UNIQUE   ISO 3166-1 alpha-2
  iso3               VARCHAR(3)          ISO 3166-1 alpha-3
  name_en            VARCHAR(120)        英文名称
  name_local         VARCHAR(120)        本地名称
  default_language   VARCHAR(10)         默认搜索语言
  phone_code         VARCHAR(10)         国际电话区号
  is_active          BOOLEAN             是否启用

### regions 地区

  字段          类型           说明
  ------------- -------------- -------------------------------------
  id            INTEGER PK     主键
  country_id    FK             所属国家
  parent_id     FK nullable    上级地区
  region_type   VARCHAR(30)    state/province/county/city/district
  name          VARCHAR(160)   地区名称
  name_local    VARCHAR(160)   本地名称
  code          VARCHAR(40)    行政区代码，可空

国家、州/省、城市采用树形结构，避免为每个国家写死相同的行政级别。

### industries 行业

  字段          类型                 说明
  ------------- -------------------- ----------
  id            INTEGER PK           主键
  parent_id     FK nullable          父级行业
  code          VARCHAR(60) UNIQUE   内部编码
  name          VARCHAR(160)         默认名称
  description   TEXT                 描述
  is_active     BOOLEAN              是否启用

另建
`industry_translations(industry_id, language_code, name, description)`
保存多语言名称。

### keywords 搜索关键词

  字段            类型           说明
  --------------- -------------- ----------------
  id              INTEGER PK     主键
  industry_id     FK             对应行业
  country_id      FK nullable    指定国家，可空
  language_code   VARCHAR(10)    关键词语言
  keyword         VARCHAR(300)   实际搜索词
  is_active       BOOLEAN        是否启用
  notes           TEXT           备注

关键词可按行业、国家和语言组合管理。批量生成任务时，必须展示预计任务数并要求用户确认，防止意外创建大量任务。

### scrape_batches 批次

  ---------------------------------------------------------------------------------------------------------
  字段                    类型                    说明
  ----------------------- ----------------------- ---------------------------------------------------------
  id                      UUID/字符串 PK          批次 ID

  name                    VARCHAR(200)            批次名称

  created_at              DATETIME                创建时间

  status                  VARCHAR(30)             draft/queued/running/completed/partial/failed/cancelled

  total_jobs              INTEGER                 子任务数

  notes                   TEXT                    备注
  ---------------------------------------------------------------------------------------------------------

### scrape_jobs 采集任务

  字段             类型           说明
  ---------------- -------------- ----------------------
  id               UUID PK        本系统任务 ID
  batch_id         FK nullable    所属批次
  country_id       FK             国家
  region_id        FK nullable    城市/地区
  industry_id      FK nullable    行业
  keyword_id       FK nullable    关键词记录
  task_name        VARCHAR(200)   任务名称
  keyword_text     VARCHAR(300)   实际提交的关键词快照
  language_code    VARCHAR(10)    语言
  latitude         FLOAT          纬度
  longitude        FLOAT          经度
  zoom             INTEGER        地图缩放
  radius           INTEGER        半径
  depth            INTEGER        深度
  fastmode         BOOLEAN        快速模式
  extract_email    BOOLEAN        Scraper 邮箱选项
  max_time         VARCHAR(30)    最大任务时间
  scraper_job_id   VARCHAR(100)   外部 Scraper 任务 ID
  status           VARCHAR(30)    本系统任务状态
  result_count     INTEGER        导入记录数
  error_message    TEXT           错误信息
  submitted_at     DATETIME       提交时间
  started_at       DATETIME       开始时间（若能确认）
  completed_at     DATETIME       完成时间
  imported_at      DATETIME       导入时间
  created_at       DATETIME       创建时间

### businesses 商家主表

  字段                 类型           说明
  -------------------- -------------- -------------------------------------
  id                   INTEGER PK     内部商家 ID
  business_name        VARCHAR(300)   商家名称
  normalized_name      VARCHAR(300)   标准化名称
  industry_id          FK nullable    归一行业
  country_id           FK nullable    国家
  region_id            FK nullable    地区/城市
  country_code         VARCHAR(2)     国家代码快照
  city                 VARCHAR(160)   城市
  state_region         VARCHAR(160)   州/省
  postal_code          VARCHAR(40)    邮编
  address              TEXT           展示地址
  phone                VARCHAR(80)    原始电话
  normalized_phone     VARCHAR(40)    标准化电话
  website              TEXT           官网 URL
  website_domain       VARCHAR(255)   规范化域名
  email_status         VARCHAR(30)    not_checked/found/none_found/failed
  google_data_id       VARCHAR(160)   data_id
  google_cid           VARCHAR(100)   CID，若可提取
  google_place_id      VARCHAR(200)   Place ID
  plus_code            VARCHAR(80)    Plus Code
  latitude             FLOAT          纬度
  longitude            FLOAT          经度
  rating               FLOAT          评分
  review_count         INTEGER        评论数
  business_status      VARCHAR(80)    营业状态原值
  timezone             VARCHAR(80)    时区
  source_first_seen    VARCHAR(60)    首次来源
  completeness_score   INTEGER        完整度 0-100
  created_at           DATETIME       创建时间
  updated_at           DATETIME       更新时间

不要把 `google_place_id`、`google_cid`
设为必填。一个商家可以暂时没有这些标识。

### business_contacts 联系方式

  字段                  类型           说明
  --------------------- -------------- ----------------------------------
  id                    INTEGER PK     主键
  business_id           FK             商家
  contact_type          VARCHAR(20)    email/phone/contact_form
  contact_value         VARCHAR(500)   联系方式
  normalized_value      VARCHAR(500)   标准化值
  source_url            TEXT           发现来源 URL
  source_type           VARCHAR(40)    scraper/official_website/manual
  verification_status   VARCHAR(30)    unverified/valid/invalid/unknown
  is_primary            BOOLEAN        是否主联系方式
  discovered_at         DATETIME       发现时间

同一家商家允许多个邮箱和电话；唯一约束建议为
`(business_id, contact_type, normalized_value)`。

### raw_records 原始记录

  字段               类型           说明
  ------------------ -------------- --------------------
  id                 INTEGER PK     主键
  job_id             FK             本系统任务
  row_number         INTEGER        CSV 行号
  external_data_id   VARCHAR(160)   原始 data_id
  raw_json           TEXT           原始 CSV 行转 JSON
  content_hash       VARCHAR(64)    行内容哈希
  imported_at        DATETIME       导入时间

原始记录只追加、不覆盖。重复导入同一任务时通过内容哈希避免重复插入。

### business_sources 商家来源关联

  字段            类型         说明
  --------------- ------------ ----------
  id              INTEGER PK   主键
  business_id     FK           商家
  raw_record_id   FK           原始记录
  job_id          FK           采集任务
  first_seen_at   DATETIME     首次发现
  last_seen_at    DATETIME     最近发现

一个商家可以出现在多个关键词、城市和批次中，必须保留全部来源关系。

## 5.2 去重策略

按可信度从高到低执行：

1.  `google_place_id` 完全相同：强匹配。
2.  `google_data_id` 完全相同：强匹配，但需先做格式验证。
3.  已解析且可信的 CID 相同：强匹配。
4.  标准化电话相同，且国家相同：候选匹配。
5.  规范化官网域名相同，且名称/地址相近：候选匹配。
6.  标准化名称 + 邮编/地址相近：候选匹配。

规则：

-   不得只因名称相同就自动合并。
-   同一地址可能有多家企业，不能仅凭地址合并。
-   电话可能被集团、分店共用，电话相同应结合其他字段判断。
-   自动强匹配可以合并；模糊匹配进入"疑似重复"列表供人工确认。
-   每次合并必须保留操作日志、原始记录和来源关系。
-   保存 `data_id` 原值，不要在没有验证的情况下拆解并丢弃原始值。

------------------------------------------------------------------------

# 6. CSV 数据导入与清洗

## 6.1 导入流程

``` text
任务状态确认完成
    |
    v
下载 CSV 到 data/raw/
    |
    v
校验 HTTP 状态、文件大小、CSV 表头
    |
    v
按 CSV 标准规则解析
    |
    v
保存原始行 raw_records
    |
    v
清洗 website / phone / address / JSON 字段
    |
    v
解析 complete_address
    |
    v
计算标准化标识和内容哈希
    |
    v
查找已有商家 / 创建新商家
    |
    v
建立 business_sources 关联
    |
    v
更新任务导入统计
```

## 6.2 清洗规则

-   使用 `csv.DictReader`，不能简单用逗号分割。
-   文件编码先尝试 UTF-8 with BOM，再回退 UTF-8；必要时记录编码错误。
-   保留每个字段的原始值，清洗后的值存入标准化字段。
-   URL 清洗：去空格、解析 URL、移除显示层 Markdown
    包装（仅当原始文件确实存在此包装时），保留原始 URL。
-   域名规范化：小写、去 `www.`、去默认端口；保留原始网站地址。
-   电话：保留原始电话；结合国家区号生成标准化版本。无法确定国家时不要猜测。
-   `complete_address`：尝试 JSON 解析，失败时保存原始字符串并记录
    warning。
-   `latitude` / `longitude`：转换为浮点数并验证范围。
-   `review_count`：转换整数；`review_rating`：转换浮点数并检查合理范围。
-   `open_hours`、`images`、`about` 等复杂字段原样保存；能解析 JSON
    时额外保存解析结果。
-   空字符串统一视为 NULL，但不得把字符串 `"0"` 当作空值。
-   不要因 `category` 为空而丢弃商家。

## 6.3 Website 解析注意

用户终端输出出现过：

`[https://www.pimlicoplumbers.com/](https://www.pimlicoplumbers.com/)`

这可能是聊天界面自动格式化，也可能是文件中的实际字符串。开发时必须读取原始
CSV 文件并打印 Python `repr(row["website"])`。只有确认原文件中存在
Markdown 包装，才执行相应清洗。清洗逻辑应有单元测试。

## 6.4 完整度评分

第一版使用透明、可配置的简单评分：

-   有名称：20 分
-   有国家/城市或完整地址：15 分
-   有电话：15 分
-   有官网：15 分
-   有邮箱：20 分
-   有经纬度：5 分
-   有评分/评论数：5 分
-   有行业分类：5 分

总分 100。分数只表示资料字段完整程度，不代表商家真实性或客户质量。

------------------------------------------------------------------------

# 7. 任务管理和 Scraper 适配器

## 7.1 适配器职责

创建 `ScraperClient` 类，统一负责：

-   检查 Scraper
    健康状态（只有找到可靠健康接口后才启用；初期可用已确认的接口做连接检查）。
-   提交任务。
-   查询任务。
-   下载 CSV。
-   解析外部任务 ID。
-   返回统一的内部数据结构。
-   记录 HTTP 错误、超时和原始响应摘要。

所有 Scraper URL 放在环境变量中：

``` env
SCRAPER_BASE_URL=http://127.0.0.1:8080
SCRAPER_CONNECT_TIMEOUT=10
SCRAPER_READ_TIMEOUT=60
SCRAPER_MAX_CONCURRENT_JOBS=1
```

## 7.2 创建任务请求映射

内部任务配置映射到当前已知表单字段：

``` python
payload = {
    "name": task.name,
    "keywords": task.keyword_text,
    "lang": task.language_code,
    "zoom": str(task.zoom),
    "latitude": str(task.latitude),
    "longitude": str(task.longitude),
    "radius": str(task.radius),
    "depth": str(task.depth),
    "maxtime": task.max_time,
}
```

`fastmode` 和 `email` 是否传 `on`、`true`
或其他值，必须通过实际表单行为验证后实现。关闭时是否省略字段也需验证。不要把未验证的值写死。

使用 httpx：

-   `POST /scrape`
-   设置 `Content-Type: application/x-www-form-urlencoded`
-   解析响应中的任务 ID。当前已知 HTML 响应中包含
    UUID，但不能假设所有版本都返回同样格式。
-   若响应不能可靠提取任务 ID，标记为
    `submission_unknown`，保存响应摘要，不要自动重复提交，以免产生重复任务。

## 7.3 外部任务状态同步

当前 `/jobs` 是 HTML/HTMX 页面，`/api/v1/jobs` 返回 JSON，但其 JSON
schema 尚未检查。

必须先执行接口侦察：

``` bash
curl -i http://127.0.0.1:8080/api/v1/jobs
```

将完整响应保存为测试样本，确认：

-   JSON 顶层类型是数组还是对象。
-   每个任务的 ID 字段名称。
-   状态字段名称及可能状态值。
-   是否包含结果数量、时间、错误信息。
-   是否分页。
-   是否需要 query 参数。

在 schema 未确认前，不得编写假设字段名的解析器。可先将原始 JSON 保存到
`tests/fixtures/`，再写适配解析函数。

## 7.4 任务状态

本系统内部状态建议：

-   `draft`：草稿
-   `queued`：等待提交
-   `submitting`：正在向 Scraper 提交
-   `running`：Scraper 正在运行
-   `completed`：Scraper 成功，CSV 已下载并导入
-   `partial`：任务结束但数据导入部分成功
-   `failed`：提交、采集或导入失败
-   `cancelled`：本系统取消
-   `submission_unknown`：请求超时，无法确认 Scraper 是否已创建任务

必须区分 Scraper 的外部状态和本系统的导入状态。Scraper 返回 `ok`
不等于本系统已经完成 CSV 导入。

## 7.5 轮询与并发

-   第一版默认最多同时运行 1 个外部采集任务。
-   轮询间隔建议 10 秒，可配置。
-   任务完成后下载 CSV；下载成功并导入后，内部状态才变为 `completed`。
-   HTTP 429、5xx、网络错误采用指数退避；不要无限重试。
-   创建任务的 POST 请求不应在超时后盲目重发。
-   第一版不实现强制取消，除非确认 Scraper 有安全、可用的取消接口。
-   删除 Scraper 任务与删除本地商家数据是不同操作，后台不得混淆。

------------------------------------------------------------------------

# 8. 批量任务生成

批量任务可由以下维度组合：

-   国家
-   州/省/城市
-   行业
-   关键词语言
-   关键词
-   地图中心点
-   半径、缩放和深度
-   是否启用 Scraper 邮箱选项
-   最大任务时间

示例：选择英国 + London + Plumbers +
English，可生成一个或多个关键词任务。

创建前显示预览：

  项目         示例
  ------------ --------------------
  国家         United Kingdom
  城市         London
  行业         Plumbing
  语言         English
  关键词       plumbers in London
  预计任务数   1
  并发数       1

用户确认后才加入队列。

批量任务必须支持：

-   预览任务清单
-   去重关键词
-   暂停后续提交
-   失败任务重试
-   查看每个子任务
-   导出批次结果

------------------------------------------------------------------------

# 9. 后台页面规划

## 9.1 仪表盘 `/`

展示：

-   商家总数
-   本日新增商家
-   采集任务总数
-   正在运行任务
-   成功/失败任务
-   有邮箱商家数
-   有官网商家数
-   国家覆盖数
-   最近任务
-   最近导入记录

所有统计从数据库实时查询，不使用静态假数据。

## 9.2 国家与地区 `/countries`

功能：

-   国家搜索、添加、编辑、启用/停用
-   国家默认语言、电话区号
-   地区树形管理
-   导入国家基础数据
-   城市坐标配置

## 9.3 行业分类 `/industries`

功能：

-   多级行业树
-   添加、编辑、排序
-   多语言名称
-   关联关键词
-   查看各行业商家数量

## 9.4 关键词库 `/keywords`

筛选条件：

-   国家
-   行业
-   语言
-   启用状态

功能：

-   新增/编辑/批量导入关键词
-   一个行业配置多种语言
-   同一关键词可关联不同国家
-   防止完全重复关键词
-   预览由关键词生成的任务

## 9.5 采集任务 `/jobs`

展示：

-   本地任务 ID
-   外部 Scraper ID
-   批次
-   国家/城市
-   行业/关键词
-   状态
-   创建时间
-   结果数量
-   错误信息

操作：

-   新建任务
-   批量创建
-   查看详情
-   手动同步状态
-   重试失败任务
-   下载原始 CSV
-   查看导入日志

## 9.6 商家资料库 `/businesses`

筛选：

-   国家、州/省、城市
-   行业
-   是否有电话
-   是否有官网
-   是否有邮箱
-   评分区间
-   评论数量
-   完整度区间
-   首次采集时间
-   来源批次/任务

列表字段：

-   商家名称
-   行业
-   国家/城市
-   电话
-   邮箱数量
-   官网
-   评分
-   评论数
-   完整度
-   最近采集时间

详情页展示：

-   基本资料
-   联系方式及来源
-   地址和地图坐标
-   官网
-   Google Maps 标识
-   原始数据
-   采集历史
-   重复记录/合并历史
-   邮箱补全状态

## 9.7 数据质量 `/data-quality`

展示：

-   缺少电话
-   缺少官网
-   缺少邮箱
-   缺少行业
-   地址不完整
-   坐标异常
-   疑似重复
-   导入失败记录

## 9.8 导出 `/exports`

支持：

-   CSV
-   XLSX
-   按当前筛选条件导出
-   选择导出字段
-   大数据量分批生成文件
-   导出任务历史

导出时默认排除原始评论正文等非必要大字段，用户可在高级选项中选择。

------------------------------------------------------------------------

# 10. 官网公开邮箱补全模块

该模块与 Google Maps
采集分开，第一版可先完成接口和状态字段，第二阶段再启用抓取。

## 10.1 处理流程

``` text
商家有官网且无邮箱
       |
       v
规范化并检查官网域名
       |
       v
读取首页及明确的联系页面链接
       |
       v
提取页面公开展示的商务邮箱
       |
       v
记录邮箱、来源 URL、发现时间
       |
       v
标记 unverified
       |
       v
可选进行格式/MX 检查
```

## 10.2 合规和安全要求

-   只访问公开网页和公开展示的商务联系方式。
-   尊重网站服务条款、robots.txt、访问频率限制和明确的禁止抓取要求。
-   设置低并发、请求间隔、超时和总页面上限。
-   不绕过登录、验证码、付费墙、IP 封锁或其他技术访问限制。
-   不猜测邮箱地址；推测地址必须单独标注为推测，默认不进入已发现邮箱。
-   不将邮箱格式正确等同于邮箱真实可投递。
-   保存来源 URL 和发现时间，方便核查。
-   提供域名黑名单、任务暂停和删除补全数据功能。
-   不自动群发营销邮件。后续如需营销功能，必须单独评估当地隐私、反垃圾邮件和营销法规。

## 10.3 邮箱状态

-   `not_checked`：未检查
-   `found`：发现公开邮箱
-   `none_found`：检查过但未发现
-   `failed`：访问失败
-   `blocked`：网站禁止或拒绝访问

每个邮箱的验证状态另存：

-   `unverified`
-   `syntax_valid`
-   `mx_found`
-   `invalid`
-   `unknown`

MX 存在不代表邮箱账户存在。

------------------------------------------------------------------------

# 11. 内部 API 设计

内部 API 以 `/api` 开头，前端页面可通过 HTMX 调用。

  方法               路径                             用途
  ------------------ -------------------------------- ----------------------------
  GET                `/api/dashboard/stats`           仪表盘统计
  GET/POST           `/api/countries`                 国家列表/新增
  GET/PATCH/DELETE   `/api/countries/{id}`            国家详情/编辑/停用
  GET/POST           `/api/regions`                   地区列表/新增
  GET/POST           `/api/industries`                行业列表/新增
  GET/POST           `/api/keywords`                  关键词列表/新增
  POST               `/api/jobs`                      创建采集任务
  POST               `/api/batches`                   创建批次
  GET                `/api/jobs`                      本地任务列表
  GET                `/api/jobs/{id}`                 任务详情
  POST               `/api/jobs/{id}/sync`            手动同步外部状态
  POST               `/api/jobs/{id}/retry`           重试失败任务
  GET                `/api/businesses`                商家搜索
  GET                `/api/businesses/{id}`           商家详情
  PATCH              `/api/businesses/{id}`           编辑商家
  GET                `/api/exports/businesses.csv`    导出 CSV
  POST               `/api/exports/businesses.xlsx`   生成 XLSX
  GET                `/api/settings/scraper`          查看连接配置（隐藏敏感值）
  POST               `/api/settings/scraper/test`     测试 Scraper 连接

API 返回统一结构：

``` json
{
  "success": true,
  "data": {},
  "error": null
}
```

错误时：

``` json
{
  "success": false,
  "data": null,
  "error": {
    "code": "SCRAPER_UNAVAILABLE",
    "message": "无法连接采集服务"
  }
}
```

列表接口统一支持 `page`、`page_size`、`sort`。对排序字段使用白名单，避免
SQL 注入。

------------------------------------------------------------------------

# 12. 配置与启动

## 12.1 `.env.example`

``` env
APP_NAME=Global Business Scraper Admin
APP_ENV=development
APP_HOST=127.0.0.1
APP_PORT=9000
SECRET_KEY=replace-this-with-a-random-secret

DATABASE_URL=sqlite:///./data/app.db

SCRAPER_BASE_URL=http://127.0.0.1:8080
SCRAPER_CONNECT_TIMEOUT=10
SCRAPER_READ_TIMEOUT=60
SCRAPER_MAX_CONCURRENT_JOBS=1
SCRAPER_POLL_INTERVAL=10

LOG_LEVEL=INFO
```

真实 `.env` 不得提交到 Git。`.gitignore` 必须排除
`.env`、数据库文件、原始采集数据、导出文件和日志。

## 12.2 开发环境启动示例

``` bash
cd global-business-admin

python3 -m venv .venv
source .venv/bin/activate

pip install -r requirements.txt

cp .env.example .env

mkdir -p data/raw data/exports logs

uvicorn app.main:app --host 127.0.0.1 --port 9000 --reload
```

浏览器打开：

`http://127.0.0.1:9000`

生产运行不要使用 `--reload`。建议使用 systemd、launchd
或其他进程管理方式，并配置数据库备份。

------------------------------------------------------------------------

# 13. 安全、稳定性和数据保护

1.  默认仅监听本机地址。
2.  所有外部 HTTP 请求设置连接和读取超时。
3.  Scraper URL 通过环境变量配置。
4.  不在页面或日志中暴露代理密码、密钥和完整敏感配置。
5.  限制 CSV 文件大小和可接受的列数。
6.  对 CSV 导出执行公式注入防护：以 `=`, `+`, `-`, `@`
    开头的单元格按文本安全处理。
7.  对官网邮箱补全模块防止 SSRF：拒绝 localhost、私有
    IP、链路本地地址、云元数据地址及非 HTTP(S) 协议；解析 DNS
    后再次校验目标 IP，防止 DNS rebinding。
8.  下载 CSV 时验证响应状态、内容类型、文件大小和文件签名/表头。
9.  原始 CSV 保存到本地受控目录，不允许用户通过任意路径下载文件。
10. 数据库定期备份；合并商家时保留审计记录。
11. 对任务提交、重试、合并、删除等操作记录操作日志。
12. 若开放局域网/公网访问，必须增加登录、CSRF 防护、HTTPS 和权限控制。

------------------------------------------------------------------------

# 14. 必须先完成的接口侦察

这是正式开发前的第一项工作。不得猜测现有 Scraper 的 JSON 结构。

## 14.1 检查 JSON 任务接口

``` bash
curl -sS -i http://127.0.0.1:8080/api/v1/jobs
```

保存响应头和响应体，确认 schema。

## 14.2 检查下载 CSV 原始字段

``` bash
python3 - <<'PY'
import csv
from pathlib import Path

p = Path.home() / "Desktop/london-plumbers-test"
with p.open("r", encoding="utf-8-sig", newline="") as f:
    reader = csv.DictReader(f)
    print("headers:", reader.fieldnames)
    for i, row in enumerate(reader):
        if i == 0:
            print("website repr:", repr(row.get("website")))
            print("data_id repr:", repr(row.get("data_id")))
            print("complete_address repr:", repr(row.get("complete_address")))
            break
PY
```

## 14.3 检查任务状态刷新

观察同一任务从 pending 到 ok 的状态变化，确认 `/api/v1/jobs`
是否包含同样的状态以及状态字段名称。

## 14.4 检查并发限制

先用少量测试任务验证：

-   同时提交两个任务是否允许。
-   第二个任务是否排队、并行或被拒绝。
-   Scraper 服务重启后任务状态如何表现。
-   下载中的文件是否可能尚未完成。
-   任务失败时 `/api/v1/jobs` 返回什么。
-   `/download` 在任务未完成时返回什么。

## 14.5 检查表单布尔值

用测试任务分别验证：

-   `fastmode` 省略、`on`、`true` 的差异。
-   `email` 省略、`on`、`true` 的差异。
-   `maxtime` 支持的格式。
-   `proxies` 的格式。

每次测试使用清晰任务名，避免混淆。确认后将结果写入
`docs/scraper-api-notes.md` 和自动化测试 fixture。

------------------------------------------------------------------------

# 15. 分阶段开发计划（交给 Codex）

## 阶段 0：项目检查和接口侦察

交付：

-   创建项目目录和 README。
-   创建 Scraper API 侦察脚本。
-   保存 `/api/v1/jobs` 的真实响应样本。
-   验证 CSV 原始值。
-   记录状态值、表单布尔参数、并发行为。
-   不修改原 Scraper。

验收：有可重复执行的侦察脚本和接口说明；未知结构明确标记，未编造字段。

## 阶段 1：后台骨架

交付：

-   FastAPI 应用。
-   SQLite 数据库连接。
-   SQLAlchemy 模型和 Alembic。
-   Jinja2 + HTMX 基础布局。
-   仪表盘空状态页面。
-   健康检查 `/health`。
-   `.env.example`、README、启动脚本。

验收：本地 `127.0.0.1:9000` 能打开；数据库自动初始化；页面无静态假统计。

## 阶段 2：Scraper 接入与任务管理

交付：

-   `ScraperClient`。
-   创建任务表单。
-   本地任务记录。
-   外部任务 ID 解析。
-   状态同步 worker。
-   CSV 下载。
-   失败记录和重试策略。

验收：从新后台提交一个伦敦测试任务，能在任务详情中看到外部 ID
和状态；不会因提交超时盲目重复创建任务。

## 阶段 3：CSV 导入与商家库

交付：

-   CSV 表头校验。
-   原始行保存。
-   字段清洗。
-   `complete_address` JSON 解析。
-   商家主表写入。
-   来源关联。
-   基础去重。
-   导入统计和错误日志。

验收：导入现有 15 条测试 CSV，原始行数量与文件一致；15
条都可追溯到原始记录；空字段不导致整行丢失；重复导入不会重复创建商家或原始记录。

## 阶段 4：国家、地区、行业和关键词

交付：

-   国家管理。
-   地区树。
-   行业树及翻译。
-   多语言关键词库。
-   批量任务预览与创建。

验收：可以配置英国/London/Plumbing/English，并生成任务预览；未确认前不提交
Scraper。

## 阶段 5：商家管理和导出

交付：

-   商家列表、详情、筛选。
-   数据质量页面。
-   疑似重复审核。
-   CSV/XLSX 导出。
-   导出字段选择。

验收：筛选条件有效；导出行数和当前查询结果一致；Excel
中电话、邮编等文本不会被错误转换。

## 阶段 6：官网公开邮箱补全

交付：

-   补全队列。
-   公开联系页面发现。
-   邮箱提取与来源记录。
-   请求限速、域名黑名单、SSRF 防护。
-   补全状态和错误日志。

验收：仅处理允许访问的公开网页；邮箱都有来源
URL；无法确认的邮箱不标记为已验证；禁止访问的目标会被跳过。

## 阶段 7：稳定性与部署

交付：

-   自动化测试。
-   数据库备份脚本。
-   日志轮转。
-   错误提示。
-   配置检查。
-   macOS launchd 示例（可选）。
-   Linux 部署说明。

验收：服务重启后任务和商家数据仍存在；失败任务不会静默丢失；备份可以恢复。

------------------------------------------------------------------------

# 16. 第一版验收清单

-   [ ] 原 Scraper 不做任何核心代码修改。
-   [ ] 新后台独立运行在 9000 端口。
-   [ ] 能读取并记录 Scraper 任务 ID。
-   [ ] 能同步真实任务状态。
-   [ ] 能下载 CSV 并验证表头。
-   [ ] 能导入用户提供的 15 条测试记录。
-   [ ] 保存全部原始字段。
-   [ ] 正确解析 `complete_address`。
-   [ ] 不将 `input_id` 当成商家唯一标识。
-   [ ] `data_id` 保留原值并参与候选去重。
-   [ ] `place_id`、`cid`、`emails`、`category` 允许为空。
-   [ ] 同一商家跨任务重复出现时可关联到同一商家记录。
-   [ ] 模糊重复不会自动误合并。
-   [ ] 商家可按国家、城市、行业和联系方式筛选。
-   [ ] 可导出 CSV 和 XLSX。
-   [ ] 任务失败有明确错误信息。
-   [ ] 数据库可备份和恢复。
-   [ ] 所有未确认的 Scraper 行为都有实测记录。

------------------------------------------------------------------------

# 17. 给 Codex 的总执行指令

请在 Mac mini 上新建一个独立目录
`global-business-admin`，严格按照本文档开发。

执行规则：

1.  先阅读本文档并检查当前开发环境。
2.  第一阶段只做接口侦察，不要修改原 Scraper。
3.  所有未确认的 Scraper API 字段和状态必须先实测，不得猜测。
4.  采用 FastAPI + SQLAlchemy + SQLite + Jinja2 + HTMX。
5.  先完成可运行的纵向功能，再逐步扩展页面，不要只生成静态 UI。
6.  数据库模型、CSV 清洗、去重、Scraper 通信都必须有自动化测试。
7.  原始数据必须保留，清洗数据不能覆盖原始值。
8.  每个阶段完成后运行测试并汇报文件变更、启动方式和验收结果。
9.  不得用模拟数据冒充真实采集结果。
10. 不得在未获授权时自动扩大采集范围或并发量。
11. 遇到接口不明确时，先输出侦察结果和需要验证的具体问题，不得编造接口。
12. 第一版先在本机运行，不擅自部署到公网。

**第一条 Codex 任务：执行阶段 0------检查当前 Scraper 接口、保存真实响应
fixture、验证 CSV 原始字段，并生成
`docs/scraper-api-notes.md`。完成后再进入阶段 1。**

------------------------------------------------------------------------

# 18. 当前已知限制与待确认事项

  项目                            状态
  ------------------------------- -------------------------------
  Scraper 服务运行                已验证
  `/scrape` 表单提交              已验证
  `/jobs` HTML 页面               已验证
  `/api/v1/jobs` HTTP 200         已验证
  `/api/v1/jobs` JSON schema      待确认
  `/download` 下载 CSV            已验证
  CSV 字段列表                    已取得
  CSV website 原始值              待检查原文件
  `data_id` 唯一性                待跨任务验证
  `place_id` / `cid`              当前样本为空
  邮箱提取效果                    当前样本 0/15，待其他样本验证
  `fastmode` / `email` 布尔约定   待实测
  Scraper 并发能力                待实测
  任务取消接口                    未确认，不应假设存在
  CSV 下载鉴权/大小限制           待确认

文档结论：先完成接口侦察和可运行的后台骨架，再接入任务队列与数据导入。以真实接口和真实
CSV 为依据，避免围绕未经验证的假设开发。
