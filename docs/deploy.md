# jianzhanla-admin 生产部署指南（宝塔 / Nginx + php-fpm）

## 一、环境要求

| 组件 | 版本要求 | 说明 |
|---|---|---|
| Nginx | 1.20+ | 反代 + 静态资源 |
| PHP | 8.1 / 8.2（NTS） | php-fpm 方式运行 |
| MySQL | 5.7+ / 8.0 | 字符集 utf8mb4 |
| PHP 扩展 | pdo_mysql、fileinfo、gd、mbstring、openssl、zip | gd 用于验证码、zip 用于导出 |

---

## 二、目录规划

```
/www/wwwroot/jianzhanla-admin/
├── backend/          # 后端（ThinkPHP8，仅 public 可被 Web 访问）
│   ├── app/  config/  runtime/  vendor/
│   └── public/       # 唯一 Web 入口
├── frontend/dist/    # 前端构建产物（纯静态）
└── sql/              # 初始化 SQL（不放在 Web 可访问目录）
```

> 建议仓库里 `sql/`、`deploy/` 不随代码上传到站点目录，单独保管。

---

## 三、部署步骤

### 1. 数据库
```bash
mysql -u root -p sdccc_admin < sql/init.sql
# 按 P0/P1 迭代顺序执行增量脚本：
mysql -u root -p sdccc_admin < sql/migrate_p0.sql
mysql -u root -p sdccc_admin < sql/migrate_notice.sql
mysql -u root -p sdccc_admin < sql/migrate_file.sql
# 菜单种子（按需）
mysql -u root -p sdccc_admin < sql/seed_online_menu.sql
mysql -u root -p sdccc_admin < sql/seed_gen_menu.sql
```

创建专用数据库账号（不要用 root）：
```sql
CREATE USER 'sdccc_admin'@'localhost' IDENTIFIED BY '强密码';
GRANT SELECT,INSERT,UPDATE,DELETE,CREATE,ALTER,INDEX,REFERENCES ON sdccc_admin.* TO 'sdccc_admin'@'localhost';
FLUSH PRIVILEGES;
```

### 2. 后端
```bash
cd /www/wwwroot/jianzhanla-admin/backend
# 上传代码后（vendor 不上传则执行）：
php composer.phar install --no-dev --optimize-autoloader
# 复制环境配置并修改（数据库账号、JWT KEY 用 openssl rand -hex 32 生成）
cp .example.env .env
vi .env   # APP_DEBUG=false、改库账号密码、换 JWT KEY
# runtime 目录权限
mkdir -p runtime && chown -R www:www runtime public/uploads
```

### 3. 前端
本地构建后将 `dist/` 上传到 `/www/wwwroot/jianzhanla-admin/frontend/dist`。
> dev 代理只在开发模式生效，生产环境由 Nginx 承担 `/api` 反代，前端无需重新构建。

### 4. Nginx
```bash
cp deploy/nginx/jianzhanla-admin.conf /www/server/panel/vhost/nginx/jianzhanla-admin.conf
# 修改 server_name 为实际域名后：
nginx -t && nginx -s reload
```

### 5. php-fpm
```bash
cp deploy/php-fpm/jianzhanla-admin.conf /www/server/php/82/etc/php-fpm.d/jianzhanla-admin.conf
# 重启 php-fpm（宝塔面板「PHP 8.2 -> 服务 -> 重启」）
```

---

## 四、上线检查清单

- [ ] `.env` 中 `APP_DEBUG = false`（暴露调试堆栈是最高频事故源）
- [ ] JWT KEY 已替换为随机 32+ 位串（沿用默认值 = 任何人可伪造 token）
- [ ] 数据库不用 root 账号、密码非弱密码
- [ ] MySQL 执行过全部增量 SQL（`sql/*.sql`，含最新的 `migrate_task_log.sql`）
- [ ] 默认账号 admin/test 已改强密码或禁用 test
- [ ] `runtime/`、`public/uploads/` 属主为 www
- [ ] `config/api.php` 的 `sign_secret` 已配置（开放接口不再裸奔）
- [ ] OpenSpout 二选一：`backend/extend/OpenSpout/` 与 composer 安装版本**只能留其一**
- [ ] Nginx `client_max_body_size` 与 php-fpm `upload_max_filesize` 一致（20M）
- [ ] 配置 HTTPS（宝塔 SSL 一键申请）后放开 301 跳转
- [ ] 验证 `https://域名/api/admin/auth/captcha` 返回 JSON
- [ ] 登录 → 各菜单页面逐一点开无 404 → 文件上传 → Excel 导出各验一遍

---

## 五、定时任务

统一入口 `php think task:run`（内置到期判断，重复触发安全），内置任务：

| 任务 | 命令 | 默认频率 | 说明 |
|---|---|---|---|
| 日志清理 | `task:clean-logs --days=90` | 每天一次 | 清理操作/登录日志表与 runtime 日志文件 |
| 缓存清理 | `task:clean-cache` | 每天一次 | 清理 runtime/temp 与 runtime/cache |
| 数据库备份 | `db:backup --keep=20` | 每天一次 | mysqldump 整库导出到 backend/runtime/backup，按份数轮转 |

```bash
# 手动备份（保留最近 20 份）
cd backend && php think db:backup
# 自定义目录与份数
php think db:backup --dir=/data/backup --keep=30
# 找不到 mysqldump 时显式指定
php think db:backup --mysqldump=/usr/local/mysql/bin/mysqldump
```

备份为纯 SQL，**不做自动恢复**（灌库不可撤销，需人工确认）：

```bash
mysql -u sdccc_admin -p sdccc_admin < backend/runtime/backup/sdccc_admin_20260927_213511.sql
```

### 执行日志

每次执行（含失败）写入表 `sdccc_sys_task_log`：`task_name / task_desc / status(0成功 1失败) / cost_ms / error_msg / create_time`。
在管理端「系统监控 > 任务日志」可按任务筛选、查看错误、查看每个任务最近 7 天的执行次数/失败数/平均耗时，也可一键清空。

> task:run 的到期判断存在 `runtime/task-state.json`（**刻意不用 Cache**——`task:clean-cache` 会把 cache 目录整块删掉，锁会丢失导致每分钟重复执行）。
> 同理，开放接口的 nonce 也存在 `runtime/api-nonce/`。

> 生产环境建议把备份目录挂到独立于站点数据盘的存储，并做异地拷贝。

调度注册（选其一）：

```bash
# Linux crontab（每 5 分钟）：
*/5 * * * * cd /www/wwwroot/jianzhanla-admin/backend && /usr/bin/php think task:run >> runtime/task.log 2>&1

# 宝塔面板：计划任务 → Shell 脚本，周期 N 分钟，内容同上
# Windows 计划任务（管理员 CMD）：
schtasks /Create /TN jianzhanla-task /Sc MINUTE /Mo 5 /TR "D:\phpstudy_pro\Extensions\php\php8.2.9nts\php.exe /www/wwwroot/jianzhanla-admin/backend/think task:run"
```

扩展新任务：在 `app/command/` 写 Command 类，`config/console.php` 注册，并在 `TaskRun::TASKS` 表加一行（name => [说明, 间隔分钟]）。

## 六、常用运维命令

```bash
# 查看后端错误日志
tail -f backend/runtime/log/$(date +%Y%m)/$(date +%d).log

# 清理运行时缓存
cd backend && php think clear

# 代码生成器生成的 demo 表清理
mysql -u root -p sdccc_admin -e "DROP TABLE IF EXISTS sdccc_demo_book;"
```

---

## 七、与本地开发的差异说明

| 项 | 本地开发 | 生产 |
|---|---|---|
| PHP 运行 | `php -S 127.0.0.1:8000 router.php` | php-fpm + Unix socket |
| 前端 | Vite dev（5183，代理到 8000） | Nginx 托管 dist 静态文件 |
| API 入口 | `/api/admin/...` 同域代理 | Nginx `/api` alias 到 backend/public |
| 调试 | APP_DEBUG=true | **必须 false** |
| 上传大小 | php.ini 默认 | php-fpm 池里显式 20M |
