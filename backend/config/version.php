<?php
/**
 * 版本基线（远程升级的最小真值）
 *
 * 说明：
 *  - 系统当前版本以此文件为准，升级成功后由 UpgradeService 回写 version / build / release
 *  - 该文件本身允许被升级包覆盖（包内若为 backend/config/version.php 则以包内容为准）
 *  - 不要在此文件写任何动态逻辑，保持纯数组常量，便于被程序安全地重写
 */

return [
    // 语义化版本号（升级比较用此字段）
    'version' => '1.0.0',
    // 构建号：建议 日期.序号，用于区分同版本的不同补丁
    'build'   => '20260928.01',
    // 发布日期
    'release' => '2026-09-28',
    // 当前发布通道：stable | beta | dev —— 只会拉取同通道的包
    'channel' => 'stable',
];
