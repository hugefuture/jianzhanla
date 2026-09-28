# 建站啦后台管理系统

[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php)](https://www.php.net/)
[![Vue](https://img.shields.io/badge/Vue-3-4FC08D?logo=vue.js)](https://vuejs.org/)
[![ThinkPHP](https://img.shields.io/badge/ThinkPHP-8-blue)](https://www.thinkphp.cn/)

> 仓库地址：<https://github.com/hugefuture/jianzhanla> · 协议：[MIT](./LICENSE) · 产品主页：[homepage/index.html](./homepage/index.html)

ThinkPHP8 + MySQL8 多应用后端 + Vue3 / Element Plus 前端，controller / service / dao / model 四层结构。

**核心能力**：RBAC + 数据权限 · 代码生成器 · 插件系统（钩子驱动）· 远程升级（OTA，带签名校验与回滚）·
Excel 流式导入导出 · 登录防护 · 日志审计 · 系统监控 · 定时任务 · 数据备份。

> **命名约定（2026-09-28 起）**：对外产品名统一为 **建站啦后台管理系统**（简称「建站啦」），
> 工程/部署标识统一为 `jianzhanla-admin`（包名 `jianzhanla-admin-web`、JWT 签发者 `jianzhanla-admin`、部署路径 `/www/wwwroot/jianzhanla-admin`）。
> **数据库层沿用历史标识**：库名 `sdccc_admin`、表前缀 `sdccc_`（纯技术前缀，已在运行中，改名需整库迁移，对外不可见；
> 需要一并迁移时执行 `sql/migrate_prefix_jzl.sql`（待生成）或联系维护者）。

## 目录结构

```
admin-framework/
├── backend/                 # ThinkPHP8 后端（多应用）
│   ├── app/
│   │   ├── admin/           # 管理后台应用（/admin/*）
│   │   │   ├── controller/  #   控制器层（thin，只做入参/出参）
│   │   │   ├── service/     #   业务逻辑层（事务、校验、钩子）
│   │   │   ├── dao/         #   数据访问层（通用查询/分页封装）
│   │   │   ├── model/       #   模型层（ORM、自动时间戳、加密钩子）
│   │   │   └── route/       #   应用路由
│   │   ├── api/             # API 应用（/api/*，示例）
│   │   ├── common/          # 跨应用公共层
│   │   │   ├── controller/  #   AbstractController
│   │   │   ├── service/     #   AbstractService / TokenService(JWT)
│   │   │   ├── dao/         #   BaseDao（通用搜索/分页/CRUD）
│   │   │   ├── middleware/  #   Cors / Token鉴权 / OperLog操作日志
│   │   │   └── exception/   #   ApiException
│   │   └── command/         # 控制台命令（admin:password）
│   ├── config/              # 全局配置（含 token.php JWT 配置）
│   └── public/              # 入口（index.php / router.php）
├── frontend/                # Vue3 + Vite + Element Plus 前端
│   └── src/
│       ├── api/             # 接口封装
│       ├── layout/          # 布局（侧边栏/顶栏）
│       ├── router/          # 固定路由（动态路由由后端菜单生成）
│       ├── store/           # Pinia（user / app）
│       └── views/           # 页面
└── sql/init.sql             # MySQL8 初始化脚本（sdccc_ 前缀）
```

## 功能清单

- **登录认证**：JWT（7 天有效期，支持续期 / 强制下线），bcrypt 密码，登录日志
- **登录防护**：图形验证码（GD 生成）、连续失败锁定、密码强度策略（均可配置）
- **数据权限**：角色级 `data_scope`（全部 / 自定义部门 / 本部门 / 本部门及以下 / 仅本人），Dao 一行开关即可生效
- **在线用户**：会话列表、按会话强制下线（token 版本号机制）
- **RBAC 权限**：用户 / 角色 / 菜单（目录·菜单·按钮三级）/ 部门 / 岗位，前端动态路由 + 按钮级权限
- **字典管理**：类型 + 数据，带缓存
- **参数配置**：键值参数，带缓存
- **日志**：操作日志（中间件自动记录写操作）/ 登录日志，支持清空
- **个人中心**：资料修改、改密、头像上传
- **上传**：图片 / 文件上传（public/uploads）
- **业务模块样例**：通知公告（公告发布 / 下架 / 置顶），完整示范四层结构写法 —— 见 `docs/新增业务模块指南.md`
- **接口规范**：统一 `{code, msg, data}`、全量 CORS、分页 `{list, total}`

## 快速开始

### 1. 数据库

```bash
mysql -uroot -p < sql/init.sql

# 增量迁移（init.sql 之后按序执行，全部幂等，可重复运行）
php tools/db-import.php sql/migrate_p0.sql sql/migrate_notice.sql sql/migrate_file.sql \
    sql/seed_gen_menu.sql sql/seed_online_menu.sql sql/seed_monitor_menu.sql \
    sql/migrate_task_log.sql sql/migrate_plugin.sql sql/migrate_upgrade.sql
```

> **重要**：`init.sql` 只包含基础 36 条菜单。文件管理、通知公告、系统监控、任务日志、
> 在线用户、代码生成、插件管理、系统升级等模块由上述增量迁移补入。
> 若因「远程升级 - 含数据库回滚」等原因导致库被早期快照覆盖、菜单变少，
> 重跑上面那一条命令即可恢复；自检用 `tools/verify-admin-completeness.js`（见验证脚本一节）。

### 2. 后端

```bash
cd backend
cp .env.example .env       # 修改数据库密码、JWT KEY
composer install           # 国内建议 --registry https://mirrors.aliyun.com/composer/
php think run -p 8000      # 或用 phpStudy/Nginx 指向 backend/public
```

若用 PHP 内置服务器手动启动，**必须带 `-t public`**（否则 `/uploads/...` 静态文件会全部 404，
导出的 Excel 下不下来）：

```bash
cd backend
php -S 127.0.0.1:8000 -t public public/router.php
```

`start-server.bat` 已按此方式启动，双击即可。

忘记密码时可重置：

```bash
php think admin:password                # admin 密码重置为 123456
php think admin:password someuser 123456 --create
```

### 3. 前端

```bash
cd frontend
npm install
npm run dev        # http://localhost:5183（已配置代理到 127.0.0.1:8000）
```

默认账号：`admin / 123456`（超级管理员，放行全部接口）、`test / 123456`（仅查看）。

## 二次开发约定

1. **新增业务表**：建表（`sdccc_` 前缀）→ Model → Dao（继承 BaseDao，定义 `$search` 搜索映射）→ Service（继承 AbstractService，定义字段白名单/唯一性）→ Controller（继承 CrudController）→ 路由一行注册。
2. **权限点**：路由路径即权限标识（`system/user/save` → `system:user:save`），在 sys_menu 中为按钮（F）登记 perms 即可生效，前端用 `hasPerm()` 控制按钮显隐。
3. **多应用**：`app/` 下新建目录即新应用（需在 `config/app.php` 的 auto_multi_app 模式下），每个应用有独立 route/middleware。
4. **生产环境**：Nginx + php-fpm 指向 `backend/public`；`.env` 中关闭 `APP_DEBUG`、更换 `JWT.KEY`；数据库账号最小权限。

## 接口一览（admin 应用）

| 模块 | 主要接口 |
|---|---|
| 认证 | POST /admin/login · GET /admin/getInfo · POST /admin/logout |
| 用户 | /admin/system/user/{list,read,save,delete,status,resetPwd,assignRoles,roleOptions,postOptions} |
| 角色 | /admin/system/role/{list,read,all,save,delete,status,menuIds,assignMenus} |
| 菜单 | /admin/system/menu/{list,read,tree,save,delete} |
| 部门 | /admin/system/dept/{list,read,tree,save,delete} |
| 岗位 | /admin/system/post/{list,read,save,delete,status} |
| 字典 | /admin/system/dict/{list,read,save,delete,dataList,dataByType,saveData,deleteData} |
| 参数 | /admin/system/config/{list,read,getKey,allValues,save,delete,status} |
| 日志 | /admin/system/log/{oper,clearOper,login,clearLogin} |
| 在线用户 | GET /admin/system/online/list · POST /admin/system/online/kick |
| 系统监控 | GET /admin/system/monitor/index（服务器 + MySQL 状态） |
| 任务日志 | GET /admin/system/monitor/tasklog · GET /admin/system/monitor/taskOverview · POST /admin/system/monitor/clearTasklog |
| 上传下载 | POST /admin/upload/{image,file} · GET /admin/system/file/download?id=（**鉴权下载**，需登录） |
| 通知公告 | /admin/biz/notice/{list,read,save,delete,publish,top} |
| 插件管理 | /admin/system/plugin/{list,install,uninstall,enable,disable,config,saveConfig,hooks} |
| 插件自有接口 | /admin/plugin/\<插件标识\>/\<rule\>（由插件清单声明，仅已启用插件可访问） |
| 远程升级 | GET /admin/system/upgrade/{info,history} · POST /admin/system/upgrade/{check,apply,rollback,clearCache}（apply/rollback 仅超管） |
| 其他 | GET /admin/index/stats · POST /admin/upload/{image,file} · /admin/profile/* · POST /admin/refresh |

## 插件机制

插件用来**在不改动主干代码的前提下扩展业务模块**：自带菜单、后端接口、前端页面，并可监听系统埋点事件。

```
backend/plugin/<标识>/
  plugin.json            清单（必填）：名称/版本/菜单/接口/配置项/监听钩子
  Plugin.php             主类（可选）：继承 AbstractPlugin，覆写 install/uninstall/register
  controller/Index.php   插件接口（可选）：命名空间 plugin\<标识>\controller
  ui/index.vue           前端页面（可选）：安装时拷贝到 frontend/src/views/plugin/<标识>/
```

清单示例（`plugin.json`）：

```json
{
  "name": "hello", "title": "Hello 示例插件", "version": "1.0.0", "author": "建站啦",
  "routes": [{ "method": "get", "rule": "index", "action": "Index/index" }],
  "menus": [{ "name": "Hello 插件", "path": "hello", "component": "plugin/hello/index",
              "perms": "plugin:hello:index", "icon": "MagicStick" }],
  "config": [{ "key": "greeting", "label": "问候语", "type": "text", "default": "你好", "required": true }],
  "hooks": ["notice.publish.after"]
}
```

- **生命周期**：安装（写库 + 建菜单 + 拷前端页面）→ 启用/禁用（同步菜单可见性 + 路由开关）→ 卸载（删菜单 + 删页面 + 删记录）
- **路由**：已启用插件的 `routes` 自动注册为 `/admin/plugin/<标识>/<rule>`，与后台接口共用 Token + 操作日志中间件
- **钩子**：系统埋点 `user.login.success` / `user.login.fail` / `notice.publish.after`，插件在 `register()` 里 `listen()` 即可；监听器异常只记日志，不影响主流程
- **配置**：清单声明 schema（text/textarea/number/switch/select），管理端渲染表单，保存时按类型转换并校验必填，插件内用 `$this->config('key')` 读取
- **内置示例**：`hello`（菜单 + 接口 + 页面 + 钩子）、`login-notify`（纯钩子：沉淀登录动态并提供统计页面）
- **注意**：带 `ui/` 的插件安装后需重新构建前端（或重启 dev 服务）才能看到新页面；目录名允许连字符，命名空间自动映射为下划线（`login-notify` → `plugin\login_notify`）
- **数据表**：`sdccc_sys_plugin`（安装状态与配置），安装入口在「系统管理 > 插件管理」（`sql/migrate_plugin.sql`）

## 远程升级（OTA）

后台「系统管理 > 系统升级」可检查官方升级源的新版本并一键升级落地：

```
manifest.json（远端） → 下载 zip → sha256/HMAC 校验 → 备份文件+整库 → 落盘 → 执行 SQL
                     → 回写 config/version.php → 清缓存 → 写 sdccc_sys_upgrade_log
```

- **安全**：包内路径必须在白名单（`backend/app`、`frontend/src`、`docs`…）内，`..`/绝对盘符/`runtime`/`.env`/`dist` 一律拒绝；SQL 命中 `drop database`、`grant` 等关键词拒绝；sha256/体积不符拒绝；缺 `upgrade.json` 拒绝
- **兜底**：任一步失败自动回滚到备份点；支持手工回滚（「回滚文件」/「含数据库」，后者默认关闭），数据库回滚会跳过升级日志表以保留审计记录
- **鉴权**：apply / rollback / clearCache 仅限 super 角色；同刻只允许一个任务（文件锁）
- **本地演示**：`tools/make-upgrade-package.php` 造包 + `tools/upgrade-repo` 起本地源（`php -S 127.0.0.1:8899 -t .`），完整说明见 `docs/upgrade.md`

新增埋点：业务代码里调用 `\app\common\plugin\PluginManager::getInstance()->trigger('事件名', $payload)` 即可。

## 定时任务

```bash
cd backend
php think task:run          # 统一入口（内置到期判断，重复触发安全）
php think task:run --force  # 忽略间隔强制执行
php think task:clean-logs --days=90   # 清理日志（默认保留 90 天）
php think task:clean-cache            # 清理 runtime 缓存
```
调度：Linux crontab `*/5 * * * * php think task:run`；Windows 用 schtasks /Sc MINUTE。
每次执行写入 `sdccc_sys_task_log`（任务、状态、耗时、错误信息），可在「系统监控 > 任务日志」查看。
详见 `docs/deploy.md`。

## 开放接口（api 应用）

面向门户 / 第三方，**不使用后台 JWT**，走独立的轻量 HMAC 签名。

| 接口 | 说明 |
|---|---|
| `GET /api/notice/list?page&limit&type&title` | 已发布公告分页（只读 `status=1`，草稿不会外泄） |
| `GET /api/notice/read?id=` | 单条已发布公告详情（剔除 remark / create_by 内部字段） |

```bash
curl "http://127.0.0.1:8000/api/notice/list?page=1&limit=10"
```

签名开关：`config/api.php` 的 `sign_secret`
- 留空 → 不校验，匿名可读（本地开发 / 内网可信环境）
- 非空 → 必须带 `app_key + timestamp + nonce + sign`，超时 ±5 分钟拒绝，nonce 不可重放

签名算法：除 sign 外参数按 key 升序 → `k1=v1&k2=v2` → `md5(secret + str + secret)`。
nonce 记录在 `runtime/api-nonce/`（刻意不用 Cache，避免被 cache 清理任务抹掉后被重放）。

## 验证脚本

```bash
cd tools
php dev-captcha.php 12 > .captcha-pool.json   # 登录需验证码，先生成验证码池（TTL 300 秒，跑前现生成）
node smoke-test.js          # 全量回归 35 项
node verify-data-scope.js   # 数据权限 14 项
node verify-login-guard.js  # 登录防护 15 项
node verify-token.js        # Token 续期与强制下线 11 项
node verify-notice.js       # 业务模块样例 21 项
node verify-monitor.js      # 系统监控 18 项
node verify-download.js     # 静态文件下载链路 12 项（导出/模板/中文文件名）
php verify-task.php         # 定时任务 9 项（本机 node child_process 被 EBUSY 拦截，CLI 类验证用 PHP 实现）
php verify-backup.php       # 数据库备份/轮转 12 项
php db-import.php sql/migrate_task_log.sql   # PDO 导入 SQL（本机 mysql.exe 会被安全策略拦截）

node verify-upload-guard.js # 上传白名单 + 鉴权下载 12 项
node verify-tasklog.js      # 定时任务执行日志 21 项（跑前先 `php think task:run --force` 造记录）
node verify-excel-stream.js # Excel 流式导出 13 项
node verify-api-open.js     # 开放接口（草稿不外泄 / 只读 / 参数校验）17 项
node verify-admin-completeness.js  # 后台功能完整性 38 项（菜单齐全 + 各模块接口可用）
node verify-plugin.js       # 插件管理 44 项（发现/安装/接口/配置/钩子/启停/卸载）
node verify-upgrade.js      # 远程升级 45 项（需先起本地升级源，见 docs/upgrade.md）
node verify-upgrade.js --only=badsha   # sha256 篡改被拒（配合 --reindex --badsha=1.1.0）
```

> 每个脚本跑前都要**重新生成验证码池**（服务端 TTL 300 秒，旧池会失效）：
> `php tools/dev-captcha.php N > tools/.captcha-pool.json`（`verify-excel.js` 读 `tools/.captcha.json`，
> 且需在 `tools/` 目录下运行）。`verify-api-open.js` 不需要验证码池，但它会用 `.captcha-pool.json` 登录后台造负例数据。

### 单元测试（PHPUnit）

```bash
cd backend
php ../tools/phpunit.phar -c phpunit.xml
# 单跑一组：
php ../tools/phpunit.phar -c phpunit.xml --filter UploadGuardTest
```

| 测试类 | 覆盖内容 | 用例数 |
|---|---|---|
| `tests/Unit/TokenServiceTest.php` | JWT 签发/解析/过期/篡改/踢下线 | 7 |
| `tests/Unit/ExcelServiceTest.php` | 导入侧：往返、空行跳过、表头校验、缺文件/非法扩展名 | 6 |
| `tests/Unit/ExcelStreamTest.php` | 导出侧：流式写入、2500 行自动分 3 个 sheet、生成器数据源、特殊字符/emoji、超限拒绝 | 10 |
| `tests/Unit/UploadGuardTest.php` | 后缀白名单交集、大小上限钳制、真实 MIME 校验 | 10 |
| `tests/Unit/SysTaskLogTest.php` | 任务日志写入、失败留痕、错误截断、按任务聚合 | 5 |
| `tests/Unit/ApiSignTest.php` | 开放签名：默认关闭、篡改被拒、过期被拒、nonce 防重放、与参数顺序无关 | 9 |
| `tests/Unit/PluginManagerTest.php` | 插件清单解析、连字符命名空间映射、钩子优先级/链式改写/异常隔离 | 16 |
| `tests/Unit/UpgradeServiceTest.php` | 版本比较、requires 表达式、路径白名单/黑名单/穿越拒绝、危险 SQL 拦截 | 11 |

> 本机没有 composer，PHPUnit 以 `tools/phpunit.phar`（11.5）形式引入；测试不连数据库做写入，
> 运行时写到 `backend/runtime/test`。`SysTaskLogTest` 例外——它读写真实库的任务日志表，
> 但只造/只删自己 `phpunit_` 前缀的数据。

## Excel 导出（流式）

导出走 **OpenSpout 真流式写入**，内存恒定不随行数增长，超过 10 万行自动分 sheet（`数据` / `数据2` …）。
数据源可以是数组、生成器，或返回生成器的闭包——通知公告导出即用游标，边查边写。

> 依赖说明：OpenSpout 源码放在 `backend/extend/OpenSpout/`（`composer.json` 的 PSR-0 fallback 已映射到 `extend/`，
> 无需 composer 即可自动加载）。将来机器上有了 composer，建议改为
> `composer require openspout/openspout` 并删除 `extend/OpenSpout`，二者只能留其一，否则类重复。

## 生产部署

见 `docs/deploy.md`，配置模板在 `deploy/nginx/jianzhanla-admin.conf`、`deploy/php-fpm/jianzhanla-admin.conf`，
环境变量模板 `backend/.example.env`（上线前 `APP_DEBUG=false`、换 JWT KEY）。
