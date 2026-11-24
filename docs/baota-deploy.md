# 宝塔面板部署

## 1. 运行环境

- PHP 8.2 或更高（推荐 8.2/8.3）
- MySQL 5.7+ / 8.x（当前开发库为 MySQL 9.7）
- Nginx 或 Apache
- PHP 扩展：`pdo_mysql`、`pdo_sqlite`、`sqlite3`、`mbstring`、`curl`、`openssl`、`zip`、`fileinfo`、`session`

Node.js 不是运行依赖；只有重新构建 Tailwind CSS 或跑 Playwright 测试时才需要。

## 2. 网站根目录

必须指向项目的：

```text
/项目目录/public
```

不要指向项目根目录。否则会 403/404，或把 `.env`、`storage` 暴露出去。

本站必须部署在域名根路径，不能放在 `/subdir/` 下，因为路由和静态资源使用根路径。

## 3. 创建 MySQL 数据库

在宝塔数据库页面创建：

```text
数据库名：atlas_global
字符集：utf8mb4
```

然后导入：

```text
database/schema_mysql.sql
```

也可以命令行：

```bash
mysql -u数据库用户 -p atlas_global < database/schema_mysql.sql
```

如果数据库用户没有建库权限，必须先手工建库；应用不会强行创建。首次访问时会自动写入国家、行业、管理员基础数据。

## 4. 配置 .env

宝塔上传的压缩包通常不会包含隐藏文件 `.env`。项目根目录提供了非隐藏模板 `env.baota.example`，复制后修改：

```bash
cp env.baota.example .env
```

至少修改：

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://你的域名

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=atlas_global
DB_USERNAME=数据库用户
DB_PASSWORD=数据库密码
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci

GEO_DB_PATH=storage/geo.sqlite
SCRAPER_BASE_URL=http://127.0.0.1:8080
```

排错时先临时设置：

```env
APP_ENV=development
APP_DEBUG=true
```

修复后再改回 production。

如果宝塔和采集引擎不在同一台服务器，`SCRAPER_BASE_URL` 不能写 `127.0.0.1`，要改成采集引擎实际可访问地址。

## 5. 目录权限

宝塔的 PHP-FPM 用户通常是 `www`：

```bash
chown -R www:www storage
chmod -R 775 storage
```

至少需要可写：

```text
storage/cache
storage/logs
storage/raw
storage/exports
```

`storage/geo.sqlite` 必须上传，不能只上传代码。

## 6. Nginx 伪静态

宝塔站点设置 → 伪静态，加入：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ ^/(app|config|database|routes|scripts|storage)/ {
    deny all;
}

location ~ /\.(?!well-known) {
    deny all;
}
```

宝塔的 PHP 配置由面板自动生成，不要覆盖 `location ~ \.php$` 段。

Apache 站点直接使用项目自带的 `public/.htaccess`，并确认 `AllowOverride All`。

如果上传 CSV 失败，在宝塔站点配置增加：

```nginx
client_max_body_size 64m;
```

## 7. 首次访问

打开：

```text
https://你的域名/login
```

首次访问会初始化数据库并生成管理员：

```text
用户名：.env 的 INITIAL_ADMIN_USERNAME
密码：  .env 的 INITIAL_ADMIN_PASSWORD
```

如果已经有用户，初始密码不会覆盖现有密码。

## 8. 常见错误

### 404

- 网站根目录不是 `public`
- Nginx 没有加 `try_files ... /index.php?$query_string`
- 路由被宝塔默认站点截获

### 403

- 根目录指向了项目根，目录禁止访问
- `public` 权限不对
- Nginx 拒绝了 `.env` 或 storage（这是正确的，不要访问它们）

### 500 / 白屏

临时把 `.env` 改为：

```env
APP_ENV=development
APP_DEBUG=true
```

然后查看：

```text
宝塔网站日志
PHP 错误日志
storage/logs
```

最常见原因：

- PHP 版本低于 8.2
- 缺少 `pdo_mysql` / `pdo_sqlite`
- MySQL 用户名、密码或库名错误
- `storage/` 不可写
- `storage/geo.sqlite` 没有上传

### 数据库连接失败

确认：

```bash
mysql -h127.0.0.1 -u数据库用户 -p atlas_global -e "select 1"
```

宝塔数据库权限要包含 `atlas_global`。

### 城市下拉为空

检查：

```text
storage/geo.sqlite 是否存在
PHP 是否启用 pdo_sqlite / sqlite3
```

### 采集任务提交失败

检查：

```bash
curl http://127.0.0.1:8080/api/v1/jobs
```

如果宝塔和 Scraper 不是同一台机器，必须修改 `SCRAPER_BASE_URL`。

## 9. 部署检查脚本

在项目目录执行：

```bash
php scripts/check_deployment.php
```

它会检查 PHP 版本、扩展、目录权限、`.env`、MySQL 和城市库。
