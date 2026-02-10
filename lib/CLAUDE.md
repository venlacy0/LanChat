# lib/ 模块文档

[根目录](../CLAUDE.md) > **lib**

## 变更记录 (Changelog)

| 时间 | 操作 | 说明 |
|------|------|------|
| 2026-02-10 14:41:35 | 初始化 | 首次生成模块文档 |

---

## 模块职责

`lib/` 包含第三方库和外部依赖。当前仅有 Parsedown 库，用于将 Markdown 文本安全地转换为 HTML。

---

## 入口与启动

**自动加载**：在 `app/bootstrap.php` 中通过 `require_once` 加载 Parsedown 类：

```php
require_once __DIR__ . '/../lib/Parsedown.php';
$parsedown = new Parsedown();
$parsedown->setSafeMode(true); // 启用安全模式，防止 XSS
```

---

## 对外接口

### Parsedown 类

| 方法 | 参数 | 返回值 | 说明 |
|------|------|--------|------|
| `text($markdown)` | `string` | `string` | 将 Markdown 转换为 HTML |
| `setSafeMode($enabled)` | `boolean` | `void` | 启用/禁用安全模式（禁用 HTML 标签） |

**使用示例**：

```php
$html = $parsedown->text("# Hello\n\nThis is **bold** text.");
```

---

## 关键依赖与配置

- **版本**：Parsedown 1.8.0
- **来源**：官方发布（https://parsedown.org）
- **许可证**：MIT License
- **安全模式**：已强制启用（`setSafeMode(true)`），用户输入的 HTML 标签会被转义

---

## 测试与质量

无自动化测试。Parsedown 本身有官方测试套件，但项目中未集成。

---

## 常见问题 (FAQ)

**Q: 为什么要本地托管 Parsedown 而不使用 Composer？**
A: 项目追求零构建工具部署，避免依赖 Composer 和 Node.js。Parsedown 是单文件库，适合直接下载。

**Q: 安全模式的作用？**
A: 防止用户在消息中注入恶意 HTML/JavaScript 代码（XSS 攻击）。安全模式下，所有 HTML 标签会被转义。

---

## 相关文件清单

```
lib/
└── Parsedown.php    # Markdown 解析库（v1.8.0）
```
