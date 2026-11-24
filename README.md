# ATLAS · Global Business Scraper Admin

![Dashboard](docs/screenshots/dashboard.png)

![Global targeting](docs/screenshots/targeting.png)

全球商家采集管理后台，按照 `Global-Business-Scraper-Admin-Development-Spec.md` 的业务要求实现。
**技术栈：PHP 8.2 + MySQL 9.7（InnoDB / utf8mb4）+ Tailwind CSS**，不使用原文档中的 FastAPI/Jinja2。业务库已从 SQLite 迁移到 MySQL；全球城市字典仍是独立的只读 SQLite 库。

## 已实现

- 中文优先界面：导航、统计标题、操作按钮、状态、表头、筛选项均使用中文；国家、城市、行业与语言名称也做了中文显示。
- 原始业务数据（商家名称、官网、关键词、外部任务名称）保留原文，不做错误翻译。

- 深色科技感后台：指挥中心、全球覆盖地图、运行脉冲、任务流、情报流。
- **全球地址选点**：Leaflet 地图、全球地址搜索（Nominatim）、点击/拖拽选点、缩放、扫描半径、逆地理编码、国家/城市联动、卫星图层、全屏。
- **全市网格覆盖**：自动读取城市行政区边界和面积（Nominatim， 本地缓存），按边界最远半径拆分重叠子任务，绕过 Google 单次结果上限；单次扫描半径上限 10 km；覆盖模式下滚动条切换为“城市覆盖半径”，按行政区边界自动取值。单格半径可调（快捷 5/8/10 km），单批最多 200 个子任务；10 km 更少任务，5 km 更细、更适合触发 Google 结果上限时补采。
- **任务列表无刷新刷新**：平铺列表每 10 秒在后台同步活跃任务并局部替换表格与统计，不整页刷新、不闪屏。默认任务页按批次合并：未展开批次只占一条，展开后任务进入同一个列表并统一分页；未分组任务支持单个删除和整组删除。
- **覆盖队列**：覆盖任务生成后进入任务列表；手动提交第一个后，任务列表或详情页会轮询同步，完成当前任务后自动提交下一个。任务列表顶部会显示最新自动覆盖队列的暂停/继续和全取消控制，任务详情页也保留同样操作；可取消当前任务或取消整批未完成任务；外部 Scraper 任务会尝试同步删除。自动推进期间请保持任务页打开。
- **关键词与位置分离**：任务关键词默认留空，提示“填写关键词，或从下拉选择预设”；只填写采购意图，位置由国家、城市和地图坐标决定，不再默认拼接 `in Berlin`；现有 B2B 关键词已统一为“importer / distributor / wholesaler / supplier”等采购商高频词。任务页关键词下拉会在每项前显示对应国家；没有国家时显示「全局」，并保留操作员当前选择的国家。
- 采集任务：单点创建、草稿、显式提交 Scraper、批量任务预览与确认、状态同步、失败重试、CSV 下载/上传/导入、原始文件下载；CSV 导入成功后自动删除对应的外部 Scraper 任务，删除失败会保留「外部任务删除失败」状态；所有任务均可删除；删除时同步清理该任务专属的原始记录和商家结果，已与其他任务共用的商家会保留。
- 国家/地区、行业树、多语言名称、关键词库、筛选与批量导入。
- 商家资料库：筛选、详情、商家介绍、联系人、采集历史、疑似重复、编辑、邮箱状态。
- 数据质量：缺失字段、坐标异常、重复候选、失败导入。
- 导出：CSV（UTF-8 BOM + 公式注入防护）、XLSX（电话/邮编按文本），导出历史；国家/行业筛选只列出当前账号已采集到的数据；导出包含商家介绍。
- Scraper 适配器：真实 `/api/v1/jobs` 侦察、任务 ID 解析、超时不明状态保护、50 MB CSV 校验。
- CSV 导入：原始行完整保留、`complete_address` 解析、URL 清洗、电话标准化、完整度评分、强标识去重、跨任务来源关联、幂等重导。
- CSRF、本地路径穿越防护、敏感配置仅服务端读取、SQL 排序白名单。

## 快速启动

```bash
cd "/Users/thomas/Sites/Global Business Scraper"
cp .env.example .env
npm install
npm run build:css
php -S 127.0.0.1:9010 -t public public/router.php
```

通过 Apache 打开：**http://google.test/**

独立运行 PHP 内置服务器：`http://127.0.0.1:9010/`

> 文档建议端口 9000，但本机 9000/9001 已被其他进程占用，因此内置服务器默认使用 9010。
> 可在 `.env` 中修改 `APP_PORT` / `APP_URL`。

首次初始化时使用 `.env` 中的初始管理员：

```text
用户名：admin
密码：  见 .env 的 INITIAL_ADMIN_PASSWORD（默认示例为 Atlas!2026）
```

如果已经修改过管理员密码，请使用修改后的密码。初始密码只在数据库没有用户时创建一次。登录后请立即进入 **账号设置** 修改密码。

## 多用户与数据隔离

![Login](docs/screenshots/login.png)

![Users](docs/screenshots/users.png)

- **登录与账号**：每个账号独立登录，密码使用 PHP `password_hash`；支持管理员创建、停用、重置账号。
- **数据归属**：采集任务、商家、原始记录、联系人、关键词、批次、导入日志、导出历史和账号设置都带 `user_id` 归属。
- **B2B 关键词独立词库**：每个租户在创建账号时获得一份自己的 25 条 B2B 启动关键词；之后的增删改只影响该账号，不会共享或回写公共模板。
- **严格隔离**：成员只能读取和操作自己名下的数据；直接猜 URL、任务 ID、商家 ID 或导出文件名也会返回 404/403。所有任务仅允许所有者删除；有结果时删除前会弹出确认，并说明将清理的结果范围。
- **共享基础字典**：国家、城市、B2B 行业和多语言行业名称是全局字典，所有账号可读；只有管理员可以修改。
- **外部 Scraper**：共享采集节点上的任务按本地 `scraper_job_id + user_id` 归属过滤；同步时不会把其他账号的外部任务导入当前账号。
- **导出文件**：文件名带账号前缀，下载前校验 `export_history.user_id`。
- **设置隔离**：个人偏好写入 `user_settings`；全局 `app_settings` 仅作为默认值。
- **个人设置仅管理员**：`/settings`、采集引擎测试/同步和个人偏好仅管理员可见；普通成员没有该入口。管理员可在「系统设置 → 采集默认值」设置「新建任务默认快速模式」，该设置对所有账号的新建任务统一生效，任务创建页不再单独显示快速模式开关。
- **普通成员权限**：成员只能修改自己的登录密码；显示名称、邮箱和角色由管理员维护。

数据库已统一迁移到 MySQL。旧 SQLite 数据库升级/迁移：

```bash
# 1) 创建目标库和表结构（应用首次启动也会自动建表）
mysql -h127.0.0.1 -uroot -p -e "CREATE DATABASE IF NOT EXISTS atlas_global CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -h127.0.0.1 -uroot -p atlas_global < database/schema_mysql.sql

# 2) 迁移旧 SQLite 数据（会先清空目标库业务表，先做备份）
php scripts/migrate_sqlite_to_mysql.php storage/backups/app-before-mysql-20261122-215329.sqlite
```

商家介绍读取采集 CSV 的 `descriptions` / `about` 字段，保存后显示在商家详情并进入导出。若原始数据没有返回这两个字段，详情页会显示暂无介绍。

> **快速模式不会返回完整字段。** 快速模式由管理员在系统设置中统一控制；关闭快速模式（即完整采集模式）才会请求 Google 商家详情，从而有机会取得 `descriptions` / `about`。快速模式最多返回 21 条基础结果。已有快速模式任务需要重新采集才能补全介绍。

新数据库初始化时会自动创建管理员并写入默认设置。

## 宝塔面板部署

宝塔常见问题、Nginx 伪静态、MySQL 建库、`.env`、目录权限和排错步骤见：

[宝塔部署说明](<docs/baota-deploy.md>)

上传后可在项目目录执行部署检查：

```bash
php scripts/check_deployment.php
```

## Apache 部署

本机 Homebrew Apache 已配置 `google.test` 虚拟主机：

```apache
<VirtualHost *:80>
    DocumentRoot "/Users/thomas/Sites/Global Business Scraper/public"
    ServerName google.test
    <Directory "/Users/thomas/Sites/Global Business Scraper/public">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

访问地址：**http://google.test/**

已完成的适配：

- `public/.htaccess` 将所有非静态请求重写到 `index.php`，使 `/jobs`、`/businesses` 等路由在 Apache 下生效。
- Apache 的 mod_php 以 `_www` 用户运行，因此给 `storage/` 添加了 `_www` 的读写 ACL；导出、原始 CSV 和缓存文件可写，业务数据通过 MySQL 连接，不再依赖 SQLite 文件。
- PHP 8.5 下移除了已废弃的 `curl_close()`，并关闭 web 端错误直接输出，避免 Deprecation 破坏 JSON API。
- MySQL 连接由 `.env` 的 `DB_*` 配置提供；建议生产环境使用独立数据库账号，只授予 `atlas_global` 的 SELECT/INSERT/UPDATE/DELETE 权限。

`127.0.0.1` 根路径已经绑定到 Panel 站点，所以本项目使用 `google.test`。

如需在新机器或权限被重置后重新授权：

```bash
./scripts/fix-apache-permissions.sh
```

Apache 不需要重启，`.htaccess` 会自动生效（虚拟主机已设置 `AllowOverride All`）。


首次请求会在 MySQL 中自动创建表结构（如果目标库和账号有权限），并写入国家、行业与管理员基础数据。不会写入虚构商家或采集结果。

## 导入真实 London 测试数据

```bash
# 先同步现有 Scraper 任务（也可在 /settings 点击「同步外部任务」）
php scripts/recon_scraper.php

# 取得本地任务 ID
mysql -h127.0.0.1 -uroot -p atlas_global -e "select id,task_name from scrape_jobs order by created_at desc limit 3;"

# 导入真实 CSV
php scripts/import_csv.php <local-job-id> ~/Desktop/london-plumbers-test
```

也可以在任务详情页直接上传 CSV。

## B2B 外贸行业分类

行业库已切换为外贸 B2B 分类，覆盖 13 个大类与 36 个子类，包括：

- 工业机械与设备、工程机械、工业设备、五金工具、模具与零部件
- 汽车与配件、汽车零部件、商用车、摩托车与电动车
- 电子与电器、消费电子、电子元器件、家用电器、照明与 LED
- 建材与家居、建筑材料、卫浴厨房、家具、门窗与装饰
- 包装与印刷、化工与塑料、纺织与服装、美妆与个护
- 食品与农业、医疗与健康、新能源与环保、日用消费品
- 跨境贸易服务、进出口贸易、批发分销、物流供应链、电商服务

旧本地服务分类（水管、餐厅、牙医等）已停用并默认隐藏，历史商家记录仍保留。关键词库已预置 25 个 B2B 采购商搜索词（如 `industrial machinery importers`），位置由国家/城市/地图坐标决定，关键词不重复写城市。

已有数据库升级：

```bash
php scripts/apply_b2b_taxonomy.php
```

重新初始化空数据库时会直接使用 [b2b_taxonomy.php](<database/b2b_taxonomy.php>)。

## 官网邮箱补全

Google Maps CSV 的 `emails` 字段经常为空（当前真实样本 15 条中为 0），所以系统将「官网公开邮箱补全」作为独立功能：

1. 在「数据质量」点击 **补全缺失邮箱**，每轮最多处理 3 家；或在商家详情点击 **从官网补全邮箱**。
2. 只读取该商家的官网首页，以及最多 2 个联系/关于页面。
3. 从页面可见文本和 `mailto:` 链接中提取公开邮箱，并保存来源 URL。
4. 邮箱统一标记为 `unverified`（未验证），表示格式合法，不代表真实可投递。
5. 没有官网、robots.txt 禁止、访问失败会分别记录为 `failed`、`blocked` 等状态。

安全限制：仅允许公网 HTTP(S)、禁止 localhost/私有 IP/云元数据地址、DNS 解析后校验并使用固定 IP 连接、限制重定向、2 MB 响应上限、8 秒超时、每页间隔请求；不绕过登录、验证码或访问限制，不猜测邮箱，也不做 SMTP 探测。

可在「系统设置 → 邮箱补全模块」中开关，并配置域名黑名单与最大检查页数。

## 测试

```bash
php tests/run.php
```

PHP 测试：54 个断言，运行在隔离的 `atlas_global_test` MySQL 数据库中，覆盖适配器 ID 解析、真实 15 行 CSV 导入、原始字段保留、地址解析、商家介绍提取、About 选项解析、租户关键词隔离、跨任务结果去重、电话标准化、幂等导入、跨任务去重、来源关联、XLSX 结构、CSV 公式注入防护、邮箱提取与 SSRF 防护、全球城市库搜索。

浏览器测试：51 个断言，覆盖登录、页面渲染、地图点击选点、全球地址搜索、国家联动、半径联动、命令面板、用户管理、移动端导航与全站 200 检查。

```bash
ATLAS_URL=http://google.test ATLAS_USER=admin ATLAS_PASS='当前管理员密码' node tests/browser.mjs
```

## 目录

```
app/
  Core/                 Auth、Router、Database、View、Controller、Session
  Models/               User、Setting 等领域模型
  Services/             Scraper、Geocoder、CSV Importer、XLSX、统计
  Support/              helpers.php、icons.php
  Modules/
    Admin/
      Dashboard/        Controller + View
      Jobs/             Controller + View（选点、列表、详情、批量）
      Businesses/       Controller + View
      Countries/        Controller + View
      Industries/       Controller + View
      Keywords/         Controller + View
      Quality/          Controller + View
      Exports/          Controller + View
      Settings/         Controller + View
      Users/            Controller + View（用户管理、账号设置）
      Api/              JSON API Controller
      Layouts/          app.php（后台统一布局）
    Auth/Login/         Controller + View（登录）
    Common/View/        error.php
config/app.php
database/               schema_mysql.sql、seed.php、全球国家/城市中文名数据
public/                 唯一入口 + 本地静态资源
routes/
  Auth.php              登录/退出
  Admin.php             后台页面与 API
  Web.php               路由装配入口
scripts/                SQLite→MySQL 迁移、性能/维护、侦察、导入、城市库
storage/                geo.sqlite / raw / exports / logs / cache / backups
tests/run.php
tests/browser.mjs
docs/scraper-api-notes.md
```

> 所有页面仍复用原有 Tailwind 样式与布局，只移动了 MVC 文件和视图位置，界面外观和业务操作保持不变。


## 安全边界

- 默认仅本机访问；现已启用登录、角色与按账号数据隔离。对外网开放前仍建议加 HTTPS、反向代理限速与备份策略。
- 密码只保存 `password_hash`，不在页面输出密码、代理凭据或密钥。
- 邮箱补全模块只保留状态与设置入口，不自动抓取、不群发。
- 未验证的 Scraper 布尔字段与 `maxtime` 格式已在界面和文档中明确标注。
- `submission_unknown` 任务禁止直接重试，避免重复外部任务。

## MySQL 运行与排错

- 业务库：MySQL 9.7，InnoDB，`utf8mb4_unicode_ci`；所有业务表带 `user_id` 归属和多租户查询索引。
- 连接配置：`.env` 的 `DB_HOST`、`DB_PORT`、`DB_DATABASE`、`DB_USERNAME`、`DB_PASSWORD`。
- 首次启动会自动创建数据库表并写入基础字典；已有库不会重复建表。
- 生产环境请使用独立 MySQL 账号，不要直接使用 root；只授予当前数据库的必要权限。
- 建议为 MySQL 设置足够的 `innodb_buffer_pool_size`，并开启慢查询日志观察大数据量下的 `LIKE`/聚合查询。

健康检查页面 **系统设置 → 节点健康** 会显示 MySQL 版本和数据库大小。

常用备份/恢复：

```bash
mysqldump -h127.0.0.1 -uroot -p --single-transaction atlas_global > storage/backups/atlas_global.sql
mysql -h127.0.0.1 -uroot -p atlas_global < storage/backups/atlas_global.sql
```

连接失败时检查：

```bash
mysql -h127.0.0.1 -u<user> -p -e "select 1" atlas_global
```

确认访问的是 **http://google.test/**（Apache）或 **http://127.0.0.1:9010/**（PHP 内置服务器）。`127.0.0.1` 根路径当前属于另一个 Panel 站点，不是本项目。

## MySQL 大数据量优化

- 所有业务表使用 InnoDB；连接使用原生预处理语句（`ATTR_EMULATE_PREPARES=false`）。
- `scrape_jobs`：`(user_id,status,created_at)`、`(user_id,created_at)`、`(batch_id,queue_order)`、`(user_id,scraper_job_id)`。
- `businesses`：按用户的时间、国家、行业、电话、域名、完整度建立复合索引，并为名称/地址/官网/电话建立 FULLTEXT 索引；英文搜索走 `MATCH ... AGAINST`，中文仍使用 `LIKE` 兜底。
- `raw_records` / `business_sources`：任务、商家关联索引 + 唯一约束，保证幂等导入。
- `keywords`：按用户、启用状态、国家、行业、语言建立 picker 索引。
- `audit_logs` / `export_history`：按用户和创建时间索引，避免大数据量审计拖慢页面。
- 数据字典（国家/行业）与城市库读取量小，继续缓存/独立存储，不参与业务大表扫描。
- 大数据量分页统一使用 `LIMIT/OFFSET` 且带用户过滤；排序字段均在索引或可控范围。
- 备份脚本：`storage/backups/` 下保留迁移前 SQLite 和迁移后 MySQL dump。

## 全球国家与城市数据

- 国家使用完整 ISO 3166-1 全球注册表，共 250 个国家/地区，字段包括 ISO2、ISO3、中英文名称、本地名称、国际区号、默认语言、中心坐标和默认缩放。
- 数据文件：`database/countries_global.php`；已有数据库升级命令：

```bash
php scripts/apply_global_countries.php
```

- 全球城市使用**独立数据库** `storage/geo.sqlite`，基于 GeoNames `cities15000`（数据许可 CC BY 4.0），当前包含 **34,249 个城市**，不写入业务数据库。
- 城市中文名只采用 GeoNames `alternateNamesV2` 中标记为中文（`zh` / `zh-Hans` 等）的名称（`database/city_names_zh.php`），不会再把日文假名或日文汉字别名当成中文。
- 城市表包含英文名、中文别名、国家代码、一级行政区、经纬度、人口、时区；支持中文/英文/别名模糊搜索。
- 导入或重建城市库：

```bash
php scripts/import_geonames_cities.php
# 也可指定已下载的 cities15000.zip
php scripts/import_geonames_cities.php /path/to/cities15000.zip
```

- 搜索接口：`GET /api/cities?q=慕尼黑&country=DE`

![全球城市库](<docs/screenshots/city-dictionary.png>)
- “全球选点”的「城市节点」下拉会根据所选国家自动加载该国全部城市（按人口排序）；选择城市后自动写入国家、坐标、缩放和地址。原生下拉支持键盘输入城市名快速跳转。
- 城市库之外的具体街道/地址仍可通过 OpenStreetMap Nominatim 地址搜索实时定位。
