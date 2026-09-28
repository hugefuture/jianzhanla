<?php
/**
 * 远程升级配置
 *
 * 协议：GET {remote}/manifest.json 返回
 *  {
 *    "appid": "jianzhanla-admin",
 *    "latest": "1.1.0",
 *    "packages": [
 *      {
 *        "version": "1.1.0", "channel": "stable", "requires": ">=1.0.0",
 *        "size": 12345, "sha256": "…", "url": "http://host/xx-1.1.0.zip",
 *        "changelog": ["…"], "released_at": "2026-09-28",
 *        "signature": "hmac-sha256(appid|version|sha256, secret)"   // 配置了密钥时必填
 *      }
 *    ]
 *  }
 */

return [
    // 是否开放远程升级
    'enabled' => (bool) env('UPGRADE.ENABLED', true),

    // 升级源根地址（末尾不带 /）
    'remote' => (string) env('UPGRADE.REMOTE', ''),

    // 应用标识，必须与远端 manifest 的 appid 一致
    'appid' => (string) env('UPGRADE.APPID', 'jianzhanla-admin'),

    // 验签密钥：为空则只校 sha256，不校签名（生产必须配置）
    'secret' => (string) env('UPGRADE.SECRET', ''),

    // 发布通道：stable | beta | dev，留空自动跟随 config/version.php 的 channel
    'channel' => (string) env('UPGRADE.CHANNEL', ''),

    // 网络超时（秒）
    'timeout' => (int) env('UPGRADE.TIMEOUT', 20),

    // 升级前是否自动做整库 SQL 备份（用于回滚）
    'backup_db' => (bool) env('UPGRADE.BACKUP_DB', true),

    // mysqldump 路径，留空自动探测
    'mysqldump' => (string) env('UPGRADE.MYSQLDUMP', ''),

    // 工作目录（缓存包、临时解压、备份），留空用 runtime/upgrade
    'work_dir' => (string) env('UPGRADE.WORK_DIR', ''),

    // ---------- 安全边界 ----------
    // 允许写入的根目录（相对项目根目录 admin-framework/），包内任何 payload 路径都必须落在其中之一
    'allowed_roots' => [
        'backend/app',
        'backend/config',
        'backend/plugin',
        'backend/public',
        'frontend/src',
        'frontend/index.html',
        'docs',
        'deploy',
        'sql',
        'tools',
    ],

    // 黑名单：即使落在允许根内也禁止覆盖（环境配置、密钥、数据、日志、缓存）
    'deny_paths' => [
        'backend/.env',
        'backend/config/database.php',
        'backend/config/token.php',
        'backend/runtime',
        'frontend/dist',
        'frontend/node_modules',
    ],

    // SQL 迁移脚本禁止出现的语句（大小写不敏感）
    'deny_sql' => [
        'drop database',
        'create database',
        'drop user',
        'grant ',
        'flush privileges',
    ],

    // 单笔 SQL 文件大小上限（KB）
    'sql_max_kb' => 512,
];
