# VenlanChat

VenlanChat 是一个基于 PHP + MySQL 的轻量实时聊天室，包含公共聊天与私聊两种模式，支持 Markdown 渲染、代码高亮、数学公式与文件上传预览等能力。

## 界面展示

![image-20260210182342502](C:\Users\zh301\AppData\Roaming\Typora\typora-user-images\image-20260210182342502.png)

![image-20260210182411801](C:\Users\zh301\AppData\Roaming\Typora\typora-user-images\image-20260210182411801.png)

![image-20260210182457273](C:\Users\zh301\AppData\Roaming\Typora\typora-user-images\image-20260210182457273.png)

## 功能概览

- 用户注册、登录、头像
- 公共聊天（`public_messages`）
- 私聊（`private_messages`）
- 消息回复、撤回
- Markdown 渲染（后端 Parsedown + 前端安全过滤）
- 文件上传（图片直显；`pdf/txt/md` 支持预览）
- 用户外观设置（保存在 `data/settings_{user_id}.json`）
- iframe链接嵌入

## 运行方式

推荐使用 Docker 一键启动（包含 Web + MySQL + phpMyAdmin）。

### 方式 A：Docker（推荐）

1. 启动服务

```bash
docker compose up -d --build
```

2. 访问

- 应用：http://localhost:8080
- phpMyAdmin（可选）：http://localhost:8081

3. 首次使用

- 打开应用后先注册账号，再登录进入聊天页面

说明：

- MySQL 容器首次启动会执行 `docker/init.sql` 初始化表结构
- 应用侧也带有「缺表自愈」逻辑：运行中发现缺少关键表会尝试自动建表（需要数据库账号具备建表权限）

### 方式 B：传统 PHP 环境（不使用 Docker）

前置依赖：

- PHP 8.2+
- MySQL 8.0+
- PHP 扩展：`mysqli`、`pdo_mysql`

步骤：

1. 配置数据库连接（推荐使用环境变量，见下文）
2. 部署到 Apache/Nginx+PHP-FPM
3. 访问 `install.php` 进行初始化（可选，但推荐）

## 配置说明

项目优先从环境变量读取数据库配置；未提供时使用 `config.php` 内的默认值。

常用环境变量：

- `DB_HOST`
- `DB_USER`
- `DB_PASS`
- `DB_NAME`

提示：

- `docker-compose.yml` 与 `config.php` 中的默认账号密码仅用于本地演示，生产环境务必替换

## 数据库表结构

核心表：

- `users`：用户信息
- `public_messages`：公共聊天消息（本次报错涉及的缺失表）
- `private_messages`：私聊消息

当你看到类似报错：

```
Table 'venlanchat.public_messages' doesn't exist
```

通常意味着数据库初始化脚本未执行或数据卷复用导致缺表。当前版本已做了两层兜底：

1. `docker/init.sql` 会创建 `public_messages`
2. 运行时 `get_db_connection()` 会在缺表时尝试自动建表

## 从旧版本 JSON 迁移公聊消息（可选）

如果你从旧版本升级，且存在 `data/messages.json`（历史公聊消息以 JSON 文件存储），可以访问以下入口将其迁移到数据库：

- `migrate_public_messages.php`

迁移脚本会读取 `data/messages.json` 并写入 `public_messages` 表。

## 目录结构

入口脚本已按职责归类到 `app/entrypoints/`，根目录同名文件仍保留为兼容入口（仅做转发），现有 URL 不需要调整。

- `app/entrypoints/public/`：对外页面与接口入口
- `app/entrypoints/maintenance/`：管理、安装、迁移等入口
- `app/api/actions/`：API 动作处理（AJAX）
- `app/services/`：业务服务（消息、用户、设置等）
- `app/helpers/`：通用工具（安全、日志、限流等）
- `assets/css/`：样式（页面内联加载）
- `assets/js/`：前端逻辑（页面内联加载）
- `docker/`：Docker 初始化与部署相关文件
- `data/`：运行时数据与错误日志（会挂载持久化）
- `uploads/`：上传文件（会挂载持久化）
- `avatars/`：头像文件（会挂载持久化）
- `logs/`：应用日志（会挂载持久化）

## 日志与排错

- PHP 错误日志：`data/php_errors.log`
- Docker 日志：

```bash
docker compose logs -f
```

常见问题排查顺序：

1. `docker compose ps` 确认容器都在运行
2. `docker compose logs web` / `docker compose logs db` 查看错误
3. 进入 DB 容器确认表是否存在：

```bash
docker compose exec -T db mysql -u <user> -p<pass> -D <db> -e "SHOW TABLES;"
```

## 安全建议（生产环境必须做）

- 修改默认密码（数据库账号、站点管理密码等）
- 限制数据库账号权限（至少不要给 root）
- 通过反向代理启用 HTTPS
- 保护安装/迁移入口（部署完成后移除或限制访问 `install.php`、`migrate_public_messages.php` 等维护入口）
