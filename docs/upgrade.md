# 远程升级指南

系统内置 OTA 升级能力：**管理后台可一键检查更新、下载官方升级包、自动完成文件落盘与数据库迁移，失败自动回滚，支持版本回滚（可选连同数据库）。**

## 1. 组成

| 组件 | 位置 |
|---|---|
| 版本基线 | `backend/config/version.php`（当前版本 / 构建号 / 发布日 / 通道） |
| 升级配置 | `backend/config/upgrade.php` + `.env [UPGRADE]` |
| 服务 | `backend/app/admin/service/UpgradeService.php` |
| 接口 | `system/upgrade/{info,check,history,apply,rollback,clearCache}` |
| 数据表 | `sdccc_sys_upgrade_log`（每次升级/回滚一条记录，含备份路径、耗时、错误） |
| 页面 | 系统管理 → 系统升级 |
| 演示升级源 | `tools/upgrade-repo/`（含 manifest.json 与多种场景包） |

## 2. 配置项（`.env`）

```ini
[UPGRADE]
ENABLED = true
REMOTE = https://upgrade.example.com/jianzhanla   # 升级源根目录，末尾不带 /
APPID = jianzhanla-admin                          # 需与 manifest.json 的 appid 一致
SECRET =                                          # HMAC 验签密钥，生产必配
CHANNEL = stable                                  # 只拉同通道的包
BACKUP_DB = true                                  # 升级前整库 mysqldump
TIMEOUT = 20
```

`config/upgrade.php` 里维护安全边界：`allowed_roots`（允许写入的目录白名单）、`deny_paths`（黑名单，如 `.env`、`runtime`、`dist`）、`deny_sql`（危险 SQL 关键字）、`sql_max_kb`。

## 3. 升级源协议

`GET {REMOTE}/manifest.json`：

```json
{
  "appid": "jianzhanla-admin",
  "latest": "1.1.0",
  "packages": [{
    "version": "1.1.0",
    "channel": "stable",
    "requires": ">=1.0.0",
    "size": 895,
    "sha256": "4558...c09b",
    "url": "http://host/jianzhanla-admin-1.1.0.zip",
    "released_at": "2026-09-28",
    "changelog": ["…"],
    "signature": "hmac_sha256(appid|version|sha256, SECRET)"
  }]
}
```

- `signature` 在配置了 `SECRET` 时**必填**，校验不通过前端直接禁用升级按钮、后端拒绝执行。
- `requires` 支持 `>=1.0.0`、`<2.0.0` 等写法，多个条件逗号分隔。
- 清单本地缓存 5 分钟，「检查更新」按钮传 `force=1` 直连远端。

## 4. 升级包格式

```
<包名>.zip
├── upgrade.json      # {"version":"1.1.0","build":"20260928.01","release":"…","sql":["sql/probe.sql"]}
├── payload/          # 按项目根目录相对路径放置的待更新文件（backend/app、frontend/src、docs…）
└── sql/*.sql         # 迁移脚本，按 update.json 声明顺序执行
```

安全规则（违反即整包拒绝，绝不部分落盘）：

1. 包内文件路径必须在 `allowed_roots` 白名单内，且不在 `deny_paths` 黑名单内；
2. 出现 `..`、绝对盘符、Windows 反斜杠路径一律拒绝（防 Zip Slip）；
3. SQL 逐条拆分执行，命中 `drop database / create database / drop user / grant` 等关键字拒绝；
4. sha256 与清单不符拒绝；体积与清单不符拒绝；缺少 `upgrade.json` 拒绝。

## 5. 执行流程

```
检查 → 下载 → sha256 校验 → 备份文件（含 version.php）→ 整库 mysqldump
     → 落盘文件 → 执行 SQL → 回写 config/version.php → 清缓存 → 写升级日志
```

- 任一步失败：**自动回滚**到备份点，并写入失败日志。
- 同一时刻只允许一个升级/回滚任务（文件锁，15 分钟兜底过期）。
- 只有 **super 角色**可执行 upgrade / rollback / clearCache。
- 数据库备份会 `--ignore-table` 掉升级日志表，保证「回滚后依然看得到这次升级记录」。

## 6. 回滚

「系统升级」页每条成功记录提供两个按钮：

- **回滚文件**：按备份清单还原被覆盖文件、删除新增文件，版本号回到 `from_version`（不动数据）；
- **含数据库**：额外用 `db.sql` 还原整库（默认关闭，二次确认，避免误灌库）。

## 7. 本地演示（无需公网）

```bash
# 1) 造演示包：正常包 + 5 种恶意包
php tools/make-upgrade-package.php --version=1.1.0 --sql=1
php tools/make-upgrade-package.php --scenario=traversal  --version=9.8.0
php tools/make-upgrade-package.php --scenario=outside    --version=9.7.0
php tools/make-upgrade-package.php --scenario=denypath   --version=9.6.0
php tools/make-upgrade-package.php --scenario=badsql     --version=9.5.0
php tools/make-upgrade-package.php --scenario=nomanifest --version=9.4.0

# 2) 起本地升级源（另一个终端）
cd tools/upgrade-repo && php -S 127.0.0.1:8899 -t .

# 3) 端到端验证
php tools/dev-captcha.php 40 > tools/.captcha-pool.json
node tools/verify-upgrade.js            # 45 项

# 4) sha256 篡改场景
php tools/make-upgrade-package.php --reindex --badsha=1.1.0
node tools/verify-upgrade.js --only=badsha
php tools/make-upgrade-package.php --reindex   # 复原
```

## 8. 生产注意事项

1. 升级包若含 `frontend/src` 改动，**包落地后仍需重新构建前端**（`npm run build` 并把 `dist` 上线）；`frontend/dist` 已在黑名单中，不允许包直接覆盖构建产物。
2. 生产务必配置 `SECRET` 并使用 HTTPS 升级源，否则存在中间人投毒风险。
3. 建议把 `backend/runtime/admin/upgrade/backup` 纳入异地备份。
4. 大规模升级（大 SQL）耗时可能超过 Web 请求超时，建议反向代理放行该接口（已在代码内 `set_time_limit(0)`）。
