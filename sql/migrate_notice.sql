-- ============================================================
-- P0-4 业务模块样例：通知公告
-- 包含：建表 + 字典 + 菜单种子（可重复执行）
-- 本文件同时是「如何新增一个业务模块」的参考模板：
--   1) 建表          2) 字典（下拉选项）
--   3) 菜单 + 按钮权限 4) 超管授权
--   5) Model / Dao / Service / Controller 四层（见 backend/app/admin/*）
--   6) 前端页面（见 frontend/src/views/business/notice/index.vue）
-- ============================================================

-- ---------- 1. 业务表 ----------
CREATE TABLE IF NOT EXISTS `sdccc_sys_notice` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '公告ID',
  `title` VARCHAR(100) NOT NULL COMMENT '公告标题',
  `type` TINYINT NOT NULL DEFAULT 1 COMMENT '类型 1通知 2公告',
  `content` TEXT COMMENT '公告内容',
  `status` TINYINT NOT NULL DEFAULT 0 COMMENT '状态 0草稿 1发布',
  `is_top` TINYINT NOT NULL DEFAULT 0 COMMENT '是否置顶 1是 0否',
  `dept_id` INT NOT NULL DEFAULT 0 COMMENT '归属部门（数据权限维度）',
  `create_by` INT NOT NULL DEFAULT 0 COMMENT '创建人',
  `remark` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '备注',
  `create_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_dept` (`dept_id`),
  KEY `idx_create_by` (`create_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='通知公告表';

-- ---------- 2. 字典 ----------
INSERT INTO `sdccc_sys_dict_type` (`name`, `type`, `remark`)
SELECT '公告类型', 'biz_notice_type', '通知公告类型'
WHERE NOT EXISTS (SELECT 1 FROM `sdccc_sys_dict_type` t WHERE t.`type` = 'biz_notice_type');

INSERT INTO `sdccc_sys_dict_data` (`dict_type`, `label`, `value`, `dict_sort`, `status`, `remark`)
SELECT 'biz_notice_type', '通知', '1', 1, 1, ''
WHERE NOT EXISTS (SELECT 1 FROM `sdccc_sys_dict_data` d WHERE d.`dict_type` = 'biz_notice_type' AND d.`value` = '1');

INSERT INTO `sdccc_sys_dict_data` (`dict_type`, `label`, `value`, `dict_sort`, `status`, `remark`)
SELECT 'biz_notice_type', '公告', '2', 2, 1, ''
WHERE NOT EXISTS (SELECT 1 FROM `sdccc_sys_dict_data` d WHERE d.`dict_type` = 'biz_notice_type' AND d.`value` = '2');

INSERT INTO `sdccc_sys_dict_type` (`name`, `type`, `remark`)
SELECT '公告状态', 'biz_notice_status', '草稿/发布'
WHERE NOT EXISTS (SELECT 1 FROM `sdccc_sys_dict_type` t WHERE t.`type` = 'biz_notice_status');

INSERT INTO `sdccc_sys_dict_data` (`dict_type`, `label`, `value`, `dict_sort`, `status`, `remark`)
SELECT 'biz_notice_status', '草稿', '0', 1, 1, ''
WHERE NOT EXISTS (SELECT 1 FROM `sdccc_sys_dict_data` d WHERE d.`dict_type` = 'biz_notice_status' AND d.`value` = '0');

INSERT INTO `sdccc_sys_dict_data` (`dict_type`, `label`, `value`, `dict_sort`, `status`, `remark`)
SELECT 'biz_notice_status', '已发布', '1', 2, 1, ''
WHERE NOT EXISTS (SELECT 1 FROM `sdccc_sys_dict_data` d WHERE d.`dict_type` = 'biz_notice_status' AND d.`value` = '1');

-- ---------- 3. 菜单（顶级目录「业务管理」+ 页面 + 按钮） ----------
INSERT INTO `sdccc_sys_menu` (`parent_id`, `name`, `type`, `perms`, `path`, `component`, `icon`, `order_num`, `visible`, `status`, `remark`)
SELECT 0, '业务管理', 'M', '', 'business', '', 'TrendCharts', 3, 1, 1, '业务模块样例目录'
WHERE NOT EXISTS (SELECT 1 FROM `sdccc_sys_menu` m WHERE m.`name` = '业务管理' AND m.`type` = 'M');

INSERT INTO `sdccc_sys_menu` (`parent_id`, `name`, `type`, `perms`, `path`, `component`, `icon`, `order_num`, `visible`, `status`, `remark`)
SELECT p.id, '通知公告', 'C', 'biz:notice:list', 'notice', 'business/notice/index', 'Bell', 1, 1, 1, ''
FROM `sdccc_sys_menu` p
WHERE p.`name` = '业务管理' AND p.`type` = 'M'
  AND NOT EXISTS (SELECT 1 FROM `sdccc_sys_menu` c WHERE c.`perms` = 'biz:notice:list');

INSERT INTO `sdccc_sys_menu` (`parent_id`, `name`, `type`, `perms`, `path`, `component`, `icon`, `order_num`, `visible`, `status`, `remark`)
SELECT c.id, '公告新增/编辑', 'F', 'biz:notice:save', '', '', '', 1, 1, 1, ''
FROM `sdccc_sys_menu` c
WHERE c.`perms` = 'biz:notice:list'
  AND NOT EXISTS (SELECT 1 FROM `sdccc_sys_menu` f WHERE f.`perms` = 'biz:notice:save');

INSERT INTO `sdccc_sys_menu` (`parent_id`, `name`, `type`, `perms`, `path`, `component`, `icon`, `order_num`, `visible`, `status`, `remark`)
SELECT c.id, '公告删除', 'F', 'biz:notice:delete', '', '', '', 2, 1, 1, ''
FROM `sdccc_sys_menu` c
WHERE c.`perms` = 'biz:notice:list'
  AND NOT EXISTS (SELECT 1 FROM `sdccc_sys_menu` f WHERE f.`perms` = 'biz:notice:delete');

INSERT INTO `sdccc_sys_menu` (`parent_id`, `name`, `type`, `perms`, `path`, `component`, `icon`, `order_num`, `visible`, `status`, `remark`)
SELECT c.id, '公告发布/下架', 'F', 'biz:notice:publish', '', '', '', 3, 1, 1, ''
FROM `sdccc_sys_menu` c
WHERE c.`perms` = 'biz:notice:list'
  AND NOT EXISTS (SELECT 1 FROM `sdccc_sys_menu` f WHERE f.`perms` = 'biz:notice:publish');

-- ---------- 4. 超管角色授权 ----------
INSERT INTO `sdccc_sys_role_menu` (`role_id`, `menu_id`)
SELECT 1, m.id FROM `sdccc_sys_menu` m
WHERE m.`perms` IN ('biz:notice:list', 'biz:notice:save', 'biz:notice:delete', 'biz:notice:publish')
  AND NOT EXISTS (
    SELECT 1 FROM `sdccc_sys_role_menu` rm WHERE rm.`role_id` = 1 AND rm.`menu_id` = m.id
  );

-- ---------- 5. 演示数据 ----------
INSERT INTO `sdccc_sys_notice` (`title`, `type`, `content`, `status`, `is_top`, `dept_id`, `create_by`, `remark`)
SELECT '系统上线通知', 1, '后台管理框架已上线，默认账号 admin / 123456，请及时修改密码。', 1, 1, 1, 1, '示例数据'
WHERE NOT EXISTS (SELECT 1 FROM `sdccc_sys_notice` n WHERE n.`title` = '系统上线通知');

INSERT INTO `sdccc_sys_notice` (`title`, `type`, `content`, `status`, `is_top`, `dept_id`, `create_by`, `remark`)
SELECT '国庆放假安排', 2, '10 月 1 日至 10 月 7 日放假调休，共 7 天。', 0, 0, 1, 1, '示例数据'
WHERE NOT EXISTS (SELECT 1 FROM `sdccc_sys_notice` n WHERE n.`title` = '国庆放假安排');
