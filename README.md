# DokePHP（PHP 5.6 - 8.3）

目标：不依赖任何第三方库，不用 Composer，仅用纯 PHP 实现“内核 / 业务”分离的最小接口框架骨架。

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
│   └── index.php
└── storage
    └── logs
```

## 启动

把 `Public/` 配置为站点根目录。

本地快速运行（PHP 内置 Server）：

```bash
php -S 127.0.0.1:8000 -t Public
```

测试接口：

- `GET /`
- `GET /ping`
- `GET /hello/{name}`
- `POST /echo`（演示参数校验）
- `GET /health`
- `GET /account/me`（演示路由组 + 路由级中间件：AuthMiddleware）
- `GET /secure/me`（演示路由组 + 路由级强制鉴权：AuthRequiredMiddleware）

## 说明

- 自动加载：`Framework/Foundation/Autoloader.php`
- 启动顺序：`Public/index.php` → `Framework/Bootstrap.php` → 加载配置/注册自动加载/加载路由 → `Application::run()`
- JSON 输出：`Framework/Support/Api.php`
- 跨域处理：`Framework/Http/Cors.php`（含 OPTIONS 预检）
- 路由：`Framework/Routing/Router.php`
- 异常：`Framework/Exception/Handler.php`（记录到 `storage/logs/app.log`）
- 中间件：`Framework/Support/Pipeline.php`（从 `App/Config/app.php` 的 `middleware` 数组加载，全局生效）
- 路由分组/路由级中间件：`Framework/Routing/Router.php`

## 上线建议（必须做）

- `App/Config/app.php` 中将 `app.debug` 设为 `false`
- 配置真实数据库连接信息（`db.dsn/username/password`）
- 按业务收紧 CORS（不要长期 `allow_origin = *`）
- WebServer 层禁止直接访问 `storage/`（避免日志外泄）
- 生产环境建议配置 `body_limit.max_bytes` 防御超大请求
- 如需接口鉴权：
  - 全局鉴权：启用 `auth.enabled` 并配置 `auth.tokens` 或 `auth.token_file`（Bearer Token）
  - 路由级强制鉴权：对路由/路由组使用 `Framework\\Http\\AuthRequiredMiddleware`
- 生产环境建议启用访问日志与基础限流（本项目已提供中间件）
- 接口参数建议统一走 `BaseController::validate()`（字段级错误会以 422 返回）
