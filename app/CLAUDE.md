# app/ 模块文档

[根目录](../CLAUDE.md) > **app**

## 变更记录 (Changelog)

| 时间 | 操作 | 说明 |
|------|------|------|
| 2026-02-10 14:41:35 | 初始化 | 首次生成模块文档 |

---

## 模块职责

`app/` 是 VenlanChat 的后端核心目录，包含所有 PHP 服务端逻辑。采用分层架构：引导层、入口层、业务服务层、辅助工具层、API 分发层和视图渲染层。

---

## 入口与启动

**引导文件**：`bootstrap.php`

执行以下初始化工作（按顺序）：
1. 配置 PHP 错误日志路径（`data/php_errors.log`）
2. 设置会话 Cookie 参数（30 天有效期、HttpOnly、SameSite=Strict）
3. 启动会话（`session_start()`）
4. 生成 CSRF token（`bin2hex(random_bytes(32))`）
5. Polyfill `str_starts_with`（兼容 PHP < 8）
6. 加载配置（`config.php`）
7. 加载数据库连接函数（`db_connect.php`）
8. 加载并初始化 Parsedown（安全模式）

---

## 对外接口

### 公共端点 (`entrypoints/public/`)

| 文件 | 路由 | 方法 | 说明 |
|------|------|------|------|
| `index.php` | `/` | GET/POST | 主页面（GET 渲染聊天，POST 转发到 API） |
| `api.php` | `/api.php` | POST | API 统一入口，分发 11 个 action |
| `login.php` | `/login.php` | GET/POST | 用户登录（含登录表单和认证逻辑） |
| `register.php` | `/register.php` | GET/POST | 用户注册 |
| `logout.php` | `/logout.php` | GET | 销毁会话并重定向 |
| `file_proxy.php` | `/file_proxy.php` | GET | 文件代理访问（限制在 uploads/avatars 目录） |

### API Action 列表 (`api/actions/`)

| Action 名称 | 文件 | 说明 |
|-------------|------|------|
| `send_message` | `send_message.php` | 发送公共消息（含回复引用） |
| `get_messages` | `get_messages.php` | 获取公共消息（支持分页） |
| `send_private_message` | `send_private_message.php` | 发送私聊消息 |
| `get_private_messages` | `get_private_messages.php` | 获取私聊消息（支持分页） |
| `check_new_messages` | `check_new_messages.php` | 轮询新消息（公聊+私聊+撤回） |
| `recall_message` | `recall_message.php` | 撤回消息（公聊/私聊） |
| `upload_file` | `upload_file.php` | 上传文件（本地存储） |
| `update_profile` | `update_profile.php` | 更新头像和用户名 |
| `get_settings` | `get_settings.php` | 获取用户主题设置 |
| `save_settings` | `save_settings.php` | 保存用户主题设置 |
| `get_file_preview` | `get_file_preview.php` | 获取 txt/md 文件预览 |

### 维护端点 (`entrypoints/maintenance/`)

| 文件 | 说明 | 认证方式 |
|------|------|---------|
| `admin.php` | 管理面板（仪表盘、用户管理、消息管理、日志、备份、设置） | 独立管理员密码 |
| `db_api.php` | 数据库 CRUD REST API（show_tables/select/insert/update/delete） | Bearer Token |
| `notification_api.php` | 消息通知 API（获取私聊消息，支持用户密码认证） | 用户密码 |
| `install.php` | 安装向导（创建数据库表、目录、下载 Parsedown） | 无（安装后删除） |

---

## 关键依赖与配置

- **配置文件**：`config.php`（数据库连接、消息限制、安全设置、文件上传、主题配置）
- **数据库连接**：`db_connect.php` 中的 `get_db_connection()` 函数返回 `mysqli` 实例
- **第三方库**：`lib/Parsedown.php`（Markdown 解析）

---

## 数据模型

### users 表

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    avatar VARCHAR(255) DEFAULT 'default_avatar.png',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_username (username),
    INDEX idx_email (email)
);
```

### private_messages 表

```sql
CREATE TABLE private_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    reply_to_id INT DEFAULT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    recalled BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reply_to_id) REFERENCES private_messages(id) ON DELETE SET NULL,
    INDEX idx_sender (sender_id),
    INDEX idx_receiver (receiver_id),
    INDEX idx_timestamp (timestamp)
);
```

### 公共消息 JSON 结构

```json
{
    "id": "uniqid()",
    "user_id": 1,
    "username": "example",
    "avatar": "url_or_data_uri",
    "message": "消息文本或文件JSON",
    "reply_to": null,
    "timestamp": 1700000000,
    "ip": "127.0.0.1",
    "recalled": false
}
```

### 文件消息格式

```json
{
    "type": "file",
    "file": {
        "filename": "原始文件名.pdf",
        "safe_filename": "随机hex.pdf",
        "path": "uploads/随机hex.pdf",
        "size": 1234567,
        "type": "pdf"
    },
    "text": "附带的文字消息"
}
```

---

## 服务层 (`services/`)

| 文件 | 核心函数 | 说明 |
|------|---------|------|
| `messages.php` | `getPublicMessages()`, `savePublicMessages()`, `markPublicMessagesAsRead()` | 公共消息 JSON 文件读写（含全局缓存） |
| `user.php` | `getCurrentUser()`, `ensureAvatar()`, `updateLastSeen()`, `getUserList()` | 用户查询与管理 |
| `settings.php` | `getUserSettings()`, `saveUserSettings()`, `migrateSettings()` | 用户主题设置（JSON 文件存储，含旧格式迁移） |

## 辅助层 (`helpers/`)

| 文件 | 核心函数 | 说明 |
|------|---------|------|
| `security.php` | `customParse()`, `customParseAllowHtml()`, `verify_csrf()` | Markdown 安全解析与 CSRF 验证 |
| `logger.php` | `logAccess()` | 访问日志记录 |
| `ip.php` | `getUserIP()` | IP 获取（支持代理头） |
| `rate_limiter.php` | `checkRateLimit()`, `cleanOldRateFiles()` | 基于 JSON 文件的每用户速率限制 |

---

## 测试与质量

当前无自动化测试。建议覆盖方向：
- `services/messages.php`：公共消息读写逻辑
- `services/settings.php`：设置迁移逻辑
- `helpers/security.php`：Markdown 解析安全性
- `helpers/rate_limiter.php`：速率限制准确性

---

## 常见问题 (FAQ)

**Q: 为什么根目录还有 `index.php`、`api.php` 等文件？**
A: 它们是薄代理，仅包含一行 `require` 指向 `app/entrypoints/` 下的真正入口。这样保持了 URL 路径简洁（如 `/login.php`）同时将逻辑集中在 `app/` 内。

**Q: 公共消息和私聊消息为什么用不同存储？**
A: 公共消息使用 JSON 文件以获得快速读取性能（避免数据库查询），代价是不支持复杂查询。私聊消息需要按用户对筛选，适合用数据库。

**Q: notification_api.php 与 api.php 有什么区别？**
A: `api.php` 是前端使用的内部 API（基于 session 认证）。`notification_api.php` 是外部 API（基于用户密码认证），用于第三方集成。

---

## 相关文件清单

```
app/
├── bootstrap.php
├── entrypoints/
│   ├── public/
│   │   ├── index.php
│   │   ├── api.php
│   │   ├── login.php
│   │   ├── register.php
│   │   ├── logout.php
│   │   └── file_proxy.php
│   └── maintenance/
│       ├── admin.php
│       ├── db_api.php
│       ├── notification_api.php
│       └── install.php
├── helpers/
│   ├── security.php
│   ├── logger.php
│   ├── ip.php
│   └── rate_limiter.php
├── services/
│   ├── messages.php
│   ├── user.php
│   └── settings.php
├── api/actions/
│   ├── send_message.php
│   ├── get_messages.php
│   ├── send_private_message.php
│   ├── get_private_messages.php
│   ├── check_new_messages.php
│   ├── recall_message.php
│   ├── upload_file.php
│   ├── update_profile.php
│   ├── get_settings.php
│   ├── save_settings.php
│   └── get_file_preview.php
└── views/
    ├── chat.php
    └── chat_js.php
```
