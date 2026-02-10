# VenlanChat Docker 部署指南

> 一键启动完整的聊天应用环境（Web + MySQL + phpMyAdmin）

---

## 包含的服务

| 服务 | 端口 | 说明 |
|------|------|------|
| **Web 应用** | 8080 | VenlanChat 主应用（PHP 8.2 + Apache） |
| **MySQL 数据库** | 3306 | MySQL 8.0 数据库服务 |
| **phpMyAdmin** | 8081 | 数据库管理界面（可选） |

---

## 快速开始

### Windows 用户

1. **双击运行**：`start.bat`
2. 等待启动完成
3. 访问 http://localhost:8080

### macOS / Linux 用户

```bash
# 方法 1：使用脚本
chmod +x start.sh
./start.sh

# 方法 2：直接使用 docker-compose
docker-compose up -d
```

---

## 前置要求

### 1. 安装 Docker

**Windows / macOS**：
- 下载并安装 [Docker Desktop](https://www.docker.com/products/docker-desktop)
- 启动 Docker Desktop

**Linux**：
```bash
# Ubuntu / Debian
sudo apt-get update
sudo apt-get install docker.io docker-compose

# CentOS / RHEL
sudo yum install docker docker-compose

# 启动 Docker
sudo systemctl start docker
sudo systemctl enable docker
```

### 2. 验证安装

```bash
docker --version
docker-compose --version
```

---

## 配置说明

### 默认配置

所有配置已预设好，开箱即用：

| 配置项 | 值 |
|--------|---|
| Web 端口 | 8080 |
| 数据库端口 | 3306 |
| phpMyAdmin 端口 | 8081 |
| 数据库名 | venlanchat |
| 数据库用户 | venlanchat_user |
| 数据库密码 | venlanchat_pass_2026 |
| Root 密码 | venlanchat_root_2026 |
| 管理员密码 | admin123 |

### 修改端口

编辑 `docker-compose.yml`：

```yaml
services:
  web:
    ports:
      - "8080:80"  # 改为 "你的端口:80"

  db:
    ports:
      - "3306:3306"  # 改为 "你的端口:3306"

  phpmyadmin:
    ports:
      - "8081:80"  # 改为 "你的端口:80"
```

### 修改数据库密码

编辑 `docker-compose.yml` 和 `config.php` 中的对应配置。

---

## 数据持久化

以下目录会挂载到容器外，数据不会丢失：

```
./data       → 配置文件和 JSON 数据
./uploads    → 用户上传的文件
./avatars    → 用户头像
./logs       → 应用日志
mysql-data   → MySQL 数据库文件（Docker Volume）
```

---

## 常用命令

### 启动服务

```bash
# 启动所有服务
docker-compose up -d

# 启动并查看日志
docker-compose up
```

### 停止服务

```bash
# 停止所有服务
docker-compose down

# 停止并删除数据卷（注意：会删除数据库数据）
docker-compose down -v
```

### 查看日志

```bash
# 查看所有服务日志
docker-compose logs

# 实时查看日志
docker-compose logs -f

# 查看特定服务日志
docker-compose logs web
docker-compose logs db
```

### 重启服务

```bash
# 重启所有服务
docker-compose restart

# 重启特定服务
docker-compose restart web
```

### 进入容器

```bash
# 进入 Web 容器
docker-compose exec web bash

# 进入数据库容器
docker-compose exec db bash

# 在数据库容器中执行 MySQL 命令
docker-compose exec db mysql -u venlanchat_user -p venlanchat
```

### 重新构建

```bash
# 代码修改后重新构建并启动
docker-compose up -d --build
```

---

## 数据库管理

### 方法 1：使用 phpMyAdmin（推荐）

访问 http://localhost:8081

- **服务器**：db
- **用户名**：venlanchat_user
- **密码**：venlanchat_pass_2026

### 方法 2：命令行

```bash
# 连接数据库
docker-compose exec db mysql -u venlanchat_user -pvenlanchat_pass_2026 venlanchat

# 备份数据库
docker-compose exec db mysqldump -u venlanchat_user -pvenlanchat_pass_2026 venlanchat > backup.sql

# 恢复数据库
docker-compose exec -T db mysql -u venlanchat_user -pvenlanchat_pass_2026 venlanchat < backup.sql
```

---

## 故障排查

### 1. 端口被占用

**错误信息**：
```
Error: bind: address already in use
```

**解决方法**：
- 修改 `docker-compose.yml` 中的端口映射
- 或关闭占用端口的程序

### 2. 数据库连接失败

**检查步骤**：
1. 确认数据库容器是否运行：`docker-compose ps`
2. 查看数据库日志：`docker-compose logs db`
3. 验证配置：`config.php` 中的数据库配置是否正确

### 3. 权限问题（Linux）

```bash
# 修复文件权限
sudo chown -R $USER:$USER data uploads avatars logs
chmod -R 755 data uploads avatars logs
```

### 4. 清理并重新开始

```bash
# 停止并删除所有容器和数据
docker-compose down -v

# 删除镜像
docker rmi venlanchat-web

# 重新启动
docker-compose up -d --build
```

---

## 安全建议

在生产环境部署时：

1. **修改默认密码**
   - 数据库密码
   - 管理员密码（admin123）

2. **使用环境变量**
   创建 `.env` 文件：
   ```env
   DB_ROOT_PASSWORD=your_secure_root_password
   DB_PASSWORD=your_secure_db_password
   ADMIN_PASSWORD=your_secure_admin_password
   ```

3. **关闭 phpMyAdmin**
   编辑 `docker-compose.yml`，注释掉 phpMyAdmin 服务

4. **配置防火墙**
   仅开放必要端口（如 8080）

5. **启用 HTTPS**
   配置反向代理（Nginx / Traefik）

---

## 性能优化

### 1. 调整 PHP 配置

编辑 `Dockerfile`，添加：

```dockerfile
RUN echo "upload_max_filesize = 10M" >> /usr/local/etc/php/php.ini \
    && echo "post_max_size = 10M" >> /usr/local/etc/php/php.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/php.ini
```

### 2. 优化 MySQL

编辑 `docker-compose.yml`，在 db 服务的 command 中添加：

```yaml
command: >
  --default-authentication-plugin=mysql_native_password
  --character-set-server=utf8mb4
  --collation-server=utf8mb4_unicode_ci
  --innodb_buffer_pool_size=256M
  --max_connections=200
```

---

## 反向代理配置

### Nginx 示例

```nginx
server {
    listen 80;
    server_name chat.example.com;

    location / {
        proxy_pass http://localhost:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

---

## 开发模式

### 实时代码同步

编辑 `docker-compose.yml`，添加代码目录挂载：

```yaml
services:
  web:
    volumes:
      - ./:/var/www/html
      - ./data:/var/www/html/data
      - ./uploads:/var/www/html/uploads
      - ./avatars:/var/www/html/avatars
      - ./logs:/var/www/html/logs
```

代码修改后会立即生效，无需重启容器。

---

## 学习资源

- [Docker 官方文档](https://docs.docker.com/)
- [Docker Compose 文档](https://docs.docker.com/compose/)
- [PHP 官方镜像](https://hub.docker.com/_/php)
- [MySQL 官方镜像](https://hub.docker.com/_/mysql)

---

## 支持

遇到问题？

1. 查看日志：`docker-compose logs -f`
2. 检查容器状态：`docker-compose ps`
3. 重启服务：`docker-compose restart`

---

*最后更新：2026-02-10*
