@echo off
chcp 65001 >nul
echo.
echo ========================================
echo   VenlanChat Docker 一键启动脚本
echo ========================================
echo.

REM 检查 Docker 是否安装
docker --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [错误] 未检测到 Docker，请先安装 Docker Desktop
    echo 下载地址: https://www.docker.com/products/docker-desktop
    pause
    exit /b 1
)

REM 检查 Docker 是否运行
docker info >nul 2>&1
if %errorlevel% neq 0 (
    echo [错误] Docker 未运行，请启动 Docker Desktop
    pause
    exit /b 1
)

echo [1/4] 停止旧容器...
docker-compose down

echo.
echo [2/4] 构建镜像...
docker-compose build

echo.
echo [3/4] 启动服务...
docker-compose up -d

echo.
echo [4/4] 等待服务启动...
timeout /t 5 >nul

echo.
echo ========================================
echo   启动完成！
echo ========================================
echo.
echo 访问地址:
echo   - 聊天应用: http://localhost:8080
echo   - phpMyAdmin: http://localhost:8081
echo.
echo 数据库信息:
echo   - 主机: localhost:3306
echo   - 数据库: venlanchat
echo   - 用户名: venlanchat_user
echo   - 密码: venlanchat_pass_2026
echo.
echo 管理员密码: admin123
echo.
echo 查看日志: docker-compose logs -f
echo 停止服务: docker-compose down
echo ========================================
echo.
pause
