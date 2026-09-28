-- ============================================================
-- 远程升级：升级日志表 + 「系统管理 > 系统升级」菜单种子
-- 可重复执行
-- ============================================================

CREATE TABLE IF NOT EXISTS `sdccc_sys_upgrade_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `from_version` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '升级前版本',
  `to_version` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '目标版本',
  `channel` VARCHAR(16) NOT NULL DEFAULT 'stable' COMMENT '发布通道',
  `package` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '包来源URL/文件名',
  `status` TINYINT NOT NULL DEFAULT 0 COMMENT '状态 0成功 1失败 2已回滚 3回滚失败',
  `file_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '落盘文件数',
  `sql_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '执行SQL脚本数',
  `backup_dir` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '文件备份目录',
  `backup_db` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '数据库备份文件',
  `error_msg` VARCHAR(1000) NOT NULL DEFAULT '' COMMENT '失败原因',
  `cost_ms` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '耗时(毫秒)',
  `create_by` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作人',
  `create_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_version` (`to_version`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='远程升级日志表';

-- 菜单去重（幂等自愈）：同名兵器 - F 按钮与重复的 C 菜单只保留 id 最小的一条
DELETE m FROM `sdccc_sys_menu` m
JOIN (
    SELECT `perms`, MIN(`id`) AS keep_id FROM `sdccc_sys_menu`
    WHERE `perms` LIKE 'system:upgrade:%' GROUP BY `perms`
) k ON k.`perms` = m.`perms`
WHERE m.`perms` LIKE 'system:upgrade:%' AND m.`id` > k.keep_id;

-- 角色授权表里的孤儿引用一并清掉
DELETE rm FROM `sdccc_sys_role_menu` rm
WHERE NOT EXISTS (SELECT 1 FROM `sdccc_sys_menu` mm WHERE mm.`id` = rm.`menu_id`);

-- 目录兜底（正常安装时「系统管理」已存在）
INSERT INTO `sdccc_sys_menu` (`parent_id`, `name`, `type`, `perms`, `path`, `component`, `icon`, `order_num`, `visible`, `status`, `remark`)
SELECT 0, '系统管理', 'M', '', 'system', '', 'Setting', 1, 1, 1, ''
WHERE NOT EXISTS (SELECT 1 FROM `sdccc_sys_menu` m WHERE m.`name` = '系统管理' AND m.`type` = 'M');

-- 菜单：系统升级（C）
-- 历史数据修正（曾用名 upgrade:check）：必须先改，否则下一句会再插一条重复菜单
UPDATE `sdccc_sys_menu` SET `perms` = 'system:upgrade:check' WHERE `perms` = 'upgrade:check';

INSERT INTO `sdccc_sys_menu` (`parent_id`, `name`, `type`, `perms`, `path`, `component`, `icon`, `order_num`, `visible`, `status`, `remark`)
SELECT p.id, '系统升级', 'C', 'system:upgrade:check', 'upgrade', 'system/upgrade/index', 'Upload', 9, 1, 1, ''
FROM `sdccc_sys_menu` p
WHERE p.`name` = '系统管理' AND p.`type` = 'M'
  AND NOT EXISTS (SELECT 1 FROM `sdccc_sys_menu` c WHERE c.`perms` = 'system:upgrade:check');

-- 按钮级权限（F）：'列表/历史/执行/回滚/清理'
INSERT INTO `sdccc_sys_menu` (`parent_id`, `name`, `type`, `perms`, `order_num`, `visible`, `status`, `remark`)
SELECT p.id, s.name, 'F', s.perms, s.ord, 0, 1, ''
FROM (
    SELECT '升级历史' AS name, 'system:upgrade:history' AS perms, 1 AS ord
    UNION ALL SELECT '执行升级', 'system:upgrade:apply', 2
    UNION ALL SELECT '版本回滚', 'system:upgrade:rollback', 3
    UNION ALL SELECT '清理缓存', 'system:upgrade:clearCache', 4
) s
JOIN (SELECT MIN(`id`) AS id FROM `sdccc_sys_menu` WHERE `perms` = 'system:upgrade:check') p
WHERE NOT EXISTS (SELECT 1 FROM `sdccc_sys_menu` c WHERE c.`perms` = s.perms);

-- 超管角色授权：上述 C 菜单与 F 按钮
INSERT INTO `sdccc_sys_role_menu` (`role_id`, `menu_id`)
SELECT 1, m.id FROM `sdccc_sys_menu` m
WHERE m.`perms` IN ('system:upgrade:check', 'system:upgrade:history', 'system:upgrade:apply', 'system:upgrade:rollback', 'system:upgrade:clearCache')
  AND NOT EXISTS (
    SELECT 1 FROM `sdccc_sys_role_menu` rm WHERE rm.`role_id` = 1 AND rm.`menu_id` = m.id
  );
