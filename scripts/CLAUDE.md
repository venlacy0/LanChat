# scripts/ 模块文档

[根目录](../CLAUDE.md) > **scripts**

## 变更记录 (Changelog)

| 时间 | 操作 | 说明 |
|------|------|------|
| 2026-02-10 14:41:35 | 初始化 | 首次生成模块文档 |

---

## 模块职责

`scripts/` 包含项目的部署与运维工具脚本。目前仅有 FTP 增量同步脚本，用于将代码从本地部署到生产服务器。

---

## 入口与启动

### ftp_sync.py - FTP 增量部署

```bash
# 标准用法
cd scripts
python3 ftp_sync.py

# 强制上传所有文件
FTP_SYNC_FORCE=1 python3 ftp_sync.py
```

### generate_ftp_state.py - 生成同步状态

```bash
python3 scripts/generate_ftp_state.py
```

---

## 对外接口

### 环境变量

| 变量 | 默认值 | 说明 |
|------|-------|------|
| `FTP_SYNC_FORCE` | `0` | 设为 `1` 强制上传所有文件 |
| `FTP_SYNC_DIR_MODE` | `755` | FTP 目录权限 |
| `FTP_SYNC_FILE_MODE` | `644` | FTP 文件权限 |
| `FTP_SYNC_MAX_RETRIES` | `0` | 最大重试次数（0 = 无限重试） |

---

## 关键依赖与配置

- **语言**：Python 3
- **依赖**：仅使用标准库（`ftplib`、`hashlib`、`json`、`pathlib`）
- **状态文件**：`.ftp_sync_state.json`（记录已上传文件的 SHA-256 哈希，用于增量同步）

### 排除规则

以下路径在同步时被跳过：

**排除文件**：
- `data/messages.json`（避免覆盖线上消息）
- `.user.ini`
- `.ftp_sync_state.json`

**排除目录**：
- `.git`
- `node_modules`
- `__pycache__`
- `data/`
- `uploads/`
- `logs/`

---

## 测试与质量

无自动化测试。建议通过 `FTP_SYNC_FORCE=1` 做冒烟测试。

---

## 相关文件清单

```
scripts/
├── ftp_sync.py              # FTP 增量同步部署脚本
└── generate_ftp_state.py    # 生成 FTP 同步状态文件
```
