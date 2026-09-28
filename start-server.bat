@echo off
rem jianzhanla-admin 一键启动：临时 MySQL(3306) + 后端(8000) + 前端 Vite(5183)
title jianzhanla-admin
set ROOT=%~dp0
set PHP=D:\phpstudy_pro\Extensions\php\php8.2.9nts\php.exe
set MYSQL=D:\phpstudy_pro\Extensions\MySQL5.7.26\bin

echo [1/3] 启动 MySQL (3306)...
start "jianzhanla-mysql" /min "%MYSQL%\mysqld.exe" --no-defaults --datadir="%ROOT%.smoke\mysql-data" --port=3306 --console
timeout /t 3 /nobreak >nul

echo [2/3] 启动后端 (8000)...
cd /d "%ROOT%backend"
start "jianzhanla-backend" /min "%PHP%" -S 127.0.0.1:8000 -t public public/router.php
timeout /t 2 /nobreak >nul

echo [3/3] 启动前端 (5183)...
cd /d "%ROOT%frontend"
start "jianzhanla-frontend" cmd /c "npm run dev"
timeout /t 3 /nobreak >nul
start http://localhost:5183
echo 全部已启动：前端 http://localhost:5183 | 账号 admin / 123456
echo 说明：Vite 只监听 IPv6，请用 localhost 访问（不要用 127.0.0.1）
pause
