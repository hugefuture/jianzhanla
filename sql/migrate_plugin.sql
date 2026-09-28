-- =============================================================
-- 插件管理模块
--   1) 建表 sdccc_sys_plugin（插件实例：标识/版本/状态/配置）
--   2) 菜单：插件管理（目录 M）+ 插件列表（菜单 C）
--   3) 超管角色授权
-- 可重复执行
-- =============================================================

CREATE TABLE IF NOT EXISTS `sdccc_sys_plugin` (
  `id`           int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `name`         varchar(50)  NOT NULL DEFAULT '' COMMENT '插件标识（目录名，唯一）',
  `title`        varchar(100) NOT NULL DEFAULT '' COMMENT '插件名称',
  `version`      varchar(20)  NOT NULL DEFAULT '' COMMENT '版本',
  `author`       varchar(50)  NOT NULL DEFAULT '' COMMENT '作者',
  `description`  varchar(255) NOT NULL DEFAULT '' COMMENT '描述',
  `status`       tinyint(1)   NOT NULL DEFAULT 0 COMMENT '状态 1启用 2禁用（未安装不落库）',
  `config`       text COMMENT '插件配置 JSON',
  `install_time` datetime     DEFAULT NULL COMMENT '安装时间',
  `create_time`  datetime     DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件实例';

-- 目录：插件管理（M）
INSERT INTO `sdccc_sys_menu` (`parent_id`, `name`, `type`, `perms`, `path`, `component`, `icon`, `order_num`, `visible`, `status`, `remark`)
SELECT 0, '插件管理', 'M', 'plugin:manage', 'plugin', '', 'MagicStick', 8, 1, 1, '插件市场与已装插件'
WHERE NOT EXISTS (SELECT 1 FROM `sdccc_sys_menu` m WHERE m.`perms` = 'plugin:manage');

-- 菜单：插件列表（C）
INSERT INTO `sdccc_sys_menu` (`parent_id`, `name`, `type`, `perms`, `path`, `component`, `icon`, `order_num`, `visible`, `status`, `remark`)
SELECT p.id, '插件列表', 'C', 'system:plugin:list', 'list', 'system/plugin/index', 'Grid', 1, 1, 1, ''
FROM `sdccc_sys_menu` p
WHERE p.`perms` = 'plugin:manage'
  AND NOT EXISTS (SELECT 1 FROM `sdccc_sys_menu` c WHERE c.`perms` = 'system:plugin:list');

-- 超管角色授权（role_id = 1）
INSERT INTO `sdccc_sys_role_menu` (`role_id`, `menu_id`)
SELECT 1, m.id FROM `sdccc_sys_menu` m
WHERE m.`perms` IN ('plugin:manage', 'system:plugin:list')
  AND NOT EXISTS (
    SELECT 1 FROM `sdccc_sys_role_menu` rm WHERE rm.`role_id` = 1 AND rm.`menu_id` = m.id
  );
