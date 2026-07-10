# DokePHP 道客PHP框架（PHP 5.6 - 8.3）

目标：不依赖任何第三方库，不用 Composer，仅用纯 PHP 实现“内核 / 业务”分离的最小接口PHP框架。

## 文档

- [docs/介绍.md](docs/介绍.md)
- [docs/使用手册.md](docs/使用手册.md)

## 目录结构

```
.
├── App
│   ├── Config
│   ├── Controllers
│   ├── Models
│   └── Routes
├── Framework
│   ├── Bootstrap.php
│   ├── Database
│   ├── Exception
│   ├── Foundation
│   ├── Http
│   ├── Routing
│   └── Support
├── Public
│   ├── .htaccess
│   ├── index.php
│   └── router.php
└── storage
    └── logs
```

## 启动

把 `Public/` 配置为站点根目录。

本地快速运行（PHP 内置 Server）：

```bash
php -S 127.0.0.1:8000 -t Public Public/router.php
```

注意：必须指定最后的 router script（`Public/router.php` 或 `Public/index.php`），仅使用 `-t Public` 会导致类似 `/admin/stats` 这种“非真实文件路径”直接返回 404。

测试接口：

- `GET /`
- `GET /ping`
- `GET /hello/{name}`
- `POST /echo`（演示参数校验）
- `GET /health`
- `GET /account/me`（演示路由组 + 路由级中间件：AuthMiddleware）
- `GET /secure/me`（演示路由组 + 路由级强制鉴权：AuthRequiredMiddleware）
- `GET /admin/stats`（演示 RBAC：auth_required + permission:admin）
- `GET /admin/rbac`（演示 RBAC 权限来源汇总：config/db/hybrid）
- RBAC 管理接口（需要数据库 + 管理权限）：
  - `GET /admin/rbac/snapshot`（导出 RBAC 快照：roles/permissions/绑定关系）
  - `GET /admin/roles` / `POST /admin/roles` / `PUT /admin/roles/{id}` / `DELETE /admin/roles/{id}`
  - `GET /admin/roles/{id}/permissions` / `POST /admin/roles/{id}/permissions`（支持 mode=replace/add/remove）
  - `GET /admin/permissions` / `POST /admin/permissions` / `PUT /admin/permissions/{id}` / `DELETE /admin/permissions/{id}`
  - `GET /admin/identities` / `POST /admin/identities` / `PUT /admin/identities/{id}`
  - `GET /admin/identities/{id}/roles` / `POST /admin/identities/{id}/roles`（支持 mode=replace/add/remove）
  - `GET /admin/tokens` / `POST /admin/tokens` / `POST /admin/tokens/{id}/revoke`
  - `POST /admin/tokens/{id}/rotate`（吊销旧 token 并生成新 token）
  - `DELETE /admin/tokens/{id}`（仅允许删除已 revoked 的 token）
  - `POST /admin/identities/{id}/tokens/revoke_all`（吊销该 identity 下所有未吊销 token）
  - `GET /admin/audit`（审计日志查询，需要 `audit.enabled=true` 且数据库可用）

## 说明

- 自动加载：`Framework/Foundation/Autoloader.php`
- 启动顺序：`Public/index.php` → `Framework/Bootstrap.php` → 加载配置/注册自动加载/加载路由 → `Application::run()`
- JSON 输出：`Framework/Support/Api.php`
- 跨域处理：`Framework/Http/Cors.php`（含 OPTIONS 预检）
- 路由：`Framework/Routing/Router.php`
- 异常：`Framework/Exception/Handler.php`（记录到 `storage/logs/app.jsonl`）
- 中间件：`Framework/Support/Pipeline.php`（从 `App/Config/app.php` 的 `middleware` 数组加载，全局生效）
- 路由分组/路由级中间件：`Framework/Routing/Router.php`

## 常用脚本

```bash
php bin/cache-config.php
php bin/migrate.php migrate
php bin/migrate.php status
php bin/migrate.php rollback 1
php bin/hash-token.php your_token
php bin/seed-rbac.php admin 0
php bin/make-local.php --force --db.dsn="mysql:host=127.0.0.1;dbname=test;charset=utf8mb4" --db.username=root --db.password=pass --rbac.enabled=true --rbac.driver=db --auth.driver=db --auth.enabled=true --audit.enabled=true
php bin/export-rbac.php storage/cache/rbac-export.json
php bin/import-rbac.php storage/cache/rbac-export.json --mode=merge --purge=0
php bin/import-rbac.php storage/cache/rbac-export.json --mode=merge --purge=0 --dry_run=1
```

## 上线建议（必须做）

- `App/Config/app.php` 中将 `app.debug` 设为 `false`
- 配置真实数据库连接信息（`db.dsn/username/password`）
- 按业务收紧 CORS（不要长期 `allow_origin = *`）
- WebServer 层禁止直接访问 `storage/`（避免日志外泄）
- 生产环境建议配置 `body_limit.max_bytes` 防御超大请求
- 如需接口鉴权：
  - 全局鉴权：启用 `auth.enabled` 并配置 `auth.tokens` 或 `auth.token_file`（Bearer Token）
  - 路由级强制鉴权：对路由/路由组使用 `Framework\\Http\\AuthRequiredMiddleware`
- RBAC 权限（403）：
  - 启用 `rbac.enabled`
  - `rbac.driver` 支持 `config/db/hybrid`
  - 路由使用 `permission:xxx`（例如 `permission:admin` 或 `permission:order.read`）
  - token_file 行格式支持 `token|expTimestamp|uid|role1,role2`
- DBAuth（可选）：
  - `auth.driver` 支持 `file/db/hybrid`（默认 `file`，不改现有行为）
  - `php bin/migrate.php migrate` 执行 `database/migrations/20260710_000001_auth_rbac.*.sql`
  - `auth_tokens` 使用 `sha256(token)` 存 `token_hash`，并可选更新 `last_used_at`（`auth.db.touch_last_used`）
  - 一键初始化管理员（可选）：`php bin/seed-rbac.php admin 0`（会创建 admin 角色与 * 权限，并生成一枚 token）
- 审计日志（可选）：
  - 配置 `audit.enabled=true` 后，`/admin/*` 的写操作与 401/403 会写入 `audit_logs`（数据库已配置时）或 `storage/logs/audit.jsonl`
  - 后台接口的“DB 管理能力”默认加了防误用保护：若 `rbac.driver` 仍为 `config`，访问 `/admin/roles` 等会返回 409（需要先切到 `db/hybrid`）
  - 迁移 `20260710_000003_audit_logs_action.*.sql` 会增加 `action` 字段，方便按后台动作检索（并支持 `GET /admin/audit?action=...`）
- 如需开放 API / Webhook 验签：启用 `signature.enabled` 并配置 `signature.secret`，客户端按约定传 `X-Signature/X-Timestamp/X-Nonce`
- 生产环境建议启用访问日志与基础限流（本项目已提供中间件）
- 接口参数建议统一走 `BaseController::validate()`（字段级错误会以 422 返回）

## 迁移文件约定

- Up：`xxxx_name.sql` 或 `xxxx_name.up.sql`
- Down（可选）：对应 `xxxx_name.down.sql`
