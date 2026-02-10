# assets/ 模块文档

[根目录](../CLAUDE.md) > **assets**

## 变更记录 (Changelog)

| 时间 | 操作 | 说明 |
|------|------|------|
| 2026-02-10 14:41:35 | 初始化 | 首次生成模块文档 |

---

## 模块职责

`assets/` 包含 VenlanChat 的所有前端资源，分为 CSS 样式模块和 JavaScript 逻辑模块。采用原生 ES6 开发，无构建工具（Webpack/Vite 等），所有文件通过 PHP 视图直接内联注入到 HTML 中。

---

## 入口与启动

### 加载机制

前端资源不是通过 `<link>` 或 `<script src>` 引用，而是由 PHP 视图通过 `file_get_contents()` 和 `readfile()` **内联注入**：

- **CSS**：在 `app/views/chat.php` 中的 `$cssFiles` 数组定义加载顺序，内联到 `<style>` 标签中
- **JS**：在 `app/views/chat_js.php` 中的 `$jsFiles` 数组定义加载顺序，内联到 `<script>` 标签中

### CSS 加载顺序

```php
$cssFiles = [
    'assets/css/chat/base.css',        // 基础样式和布局
    'assets/css/chat/responsive.css',   // 响应式适配
    'assets/css/chat/markdown.css',     // Markdown 渲染样式
    'assets/css/chat/settings.css',     // 设置面板样式
    'assets/css/chat/files.css',        // 文件上传/预览样式
];
```

### JS 加载顺序

```php
$jsFiles = [
    'assets/js/chat/bootstrap.js',     // 状态变量、缓存管理
    'assets/js/chat/utils.js',         // 通知、弹窗等通用工具
    'assets/js/chat/ui.js',            // UI 交互（焦点、滚动、回车发送）
    'assets/js/chat/attachments.js',   // 文件附件上传
    'assets/js/chat/messages.js',      // 消息发送/接收核心逻辑
    'assets/js/chat/settings.js',      // 主题设置功能
    'assets/js/chat/init.js',          // DOMContentLoaded 初始化
];
```

**注意**：`virtual-scroll.js` 和 `messages-integration.js` 通过独立 `<script>` 标签加载，不在 `$jsFiles` 中。

---

## 对外接口

### 全局配置对象

PHP 通过 `window.VENCHAT_CONFIG` 向前端注入配置：

```javascript
window.VENCHAT_CONFIG = {
    userId: 123,              // 当前用户 ID
    csrfToken: "hex_string",  // CSRF token
    initialSettings: {         // 用户主题设置
        hue: 217,
        mode: "light",
        radius: 20
    }
};
```

### 外部依赖（CDN）

| 库 | 用途 | CDN |
|---|------|-----|
| Font Awesome 6.0 | 图标库 | cdnjs |
| MathJax 3.2.2 | 数学公式渲染 | cdnjs |
| Highlight.js 11.7 | 代码高亮 | cdnjs |

---

## 关键依赖与配置

### 全局状态变量（`bootstrap.js`）

```javascript
let latestPublicTimestamp = 0;    // 最新公共消息时间戳
let latestPrivateTimestamp = 0;   // 最新私聊消息时间戳
let pollingInterval = null;       // 轮询定时器
let isPublicChat = true;          // 当前是否在公共聊天
let selectedReceiverId = null;    // 私聊对象 ID
let replyToId = null;             // 回复目标消息 ID
let currentTab = 'public';        // 当前标签页
```

### 缓存策略（`bootstrap.js`）

- **缓存键格式**：`venchat_v1_user{uid}_public` / `venchat_v1_user{uid}_private_{peerId}`
- **有效期**：7 天
- **存储位置**：`localStorage`
- **上传限制**：4MB（`MAX_UPLOAD_BYTES`）

---

## JavaScript 模块说明

| 文件 | 职责 | 核心函数/类 |
|------|------|------------|
| `bootstrap.js` | 全局状态、缓存管理 | `getCacheKey()`, `saveMessagesToCache()`, `loadMessagesFromCache()` |
| `utils.js` | 通知与弹窗 | `showToast()`, `showConfirm()`, `closeModal()` |
| `ui.js` | UI 交互 | `deactivateInputFocus()`, `resizeInput()`, `handleEnterKey()`, `updateScrollBtnPosition()` |
| `attachments.js` | 文件附件 | `showFileUploadModal()`, `handleFileDrop()`, `closeFileUploadModal()` |
| `messages.js` | 消息收发 | `sendMessage()`, `sendPublicMessage()`, `sendPrivateMessage()`, `loadInitialMessages()` |
| `settings.js` | 主题设置 | `handleSettingsUpdate()`, `revertSettings()`, `updateVisualSettingsControls()` |
| `init.js` | 初始化启动 | DOM 事件绑定、轮询启动、标签页切换、用户列表交互 |
| `virtual-scroll.js` | 虚拟滚动 | `VirtualScrollManager` 类（仅渲染可见区域） |
| `messages-integration.js` | 虚拟滚动集成 | 将虚拟滚动桥接到消息系统（当前**默认禁用**） |

### 虚拟滚动状态

`messages-integration.js` 中 `ENABLE_VIRTUAL_SCROLL = false`，虚拟滚动功能当前被禁用。启用方法：将该常量改为 `true`。

---

## CSS 模块说明

| 文件 | 职责 |
|------|------|
| `base.css` | 全局重置、布局骨架、侧边栏、消息气泡、输入区域、底部导航 |
| `responsive.css` | 桌面/移动端响应式适配、断点样式 |
| `markdown.css` | Markdown 渲染后的排版样式（代码块、表格、引用等） |
| `settings.css` | 设置面板布局、滑块控件样式 |
| `files.css` | 文件上传弹窗、文件卡片、拖拽上传区域样式 |

### 主题系统

采用 HSL 色彩体系，通过 `hue`（色相 0-360）和 `mode`（light/dark）动态生成主题色。前端通过 CSS 变量控制，用户可自定义圆角半径（`radius`）。

---

## 测试与质量

当前无前端自动化测试。

建议覆盖方向：
- 消息发送/接收流程的 E2E 测试
- 虚拟滚动性能测试
- 缓存管理的边界条件测试

---

## 常见问题 (FAQ)

**Q: 为什么前端资源是内联注入而不是外链引用？**
A: 减少 HTTP 请求数，适合小型部署。缺点是每次页面加载都传输全部资源，无法利用浏览器缓存。

**Q: 如何添加新的 JS 模块？**
A: 在 `assets/js/chat/` 创建文件，然后在 `app/views/chat_js.php` 的 `$jsFiles` 数组中按依赖顺序添加路径。

**Q: 如何添加新的 CSS 模块？**
A: 在 `assets/css/chat/` 创建文件，然后在 `app/views/chat.php` 的 `$cssFiles` 数组中添加路径。

---

## 相关文件清单

```
assets/
├── css/
│   ├── chat.css              # 旧版合并样式（可能已废弃）
│   └── chat/
│       ├── base.css
│       ├── responsive.css
│       ├── markdown.css
│       ├── settings.css
│       └── files.css
└── js/
    └── chat/
        ├── bootstrap.js
        ├── utils.js
        ├── ui.js
        ├── attachments.js
        ├── messages.js
        ├── settings.js
        ├── init.js
        ├── virtual-scroll.js
        └── messages-integration.js
```
