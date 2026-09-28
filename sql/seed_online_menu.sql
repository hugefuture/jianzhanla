-- ============================================================
-- P0-3 菜单种子：在线用户（挂在「系统管理」下）
-- 可重复执行（用 INSERT ... SELECT WHERE NOT EXISTS 思路，MySQL 用 NOT EXISTS 子查询）
-- ============================================================

-- 目录：系统监控（顶级 M）
INSERT INTO `sdccc_sys_menu` (`parent_id`, `name`, `type`, `perms`, `path`, `component`, `icon`, `order_num`, `visible`, `status`, `remark`)
SELECT 0, '系统监控', 'M', '', 'monitor', '', 'Monitor', 2, 1, 1, ''
WHERE NOT EXISTS (SELECT 1 FROM (SELECT 1) t WHERE EXISTS (SELECT 1 FROM `sdccc_sys_menu` m WHERE m.`name` = '系统监控' AND m.`type` = 'M'));

-- 菜单：在线用户（C）
INSERT INTO `sdccc_sys_menu` (`parent_id`, `name`, `type`, `perms`, `path`, `component`, `icon`, `order_num`, `visible`, `status`, `remark`)
SELECT p.id, '在线用户', 'C', 'system:online:list', 'online', 'system/online/index', 'Connection', 1, 1, 1, ''
FROM `sdccc_sys_menu` p
WHERE p.`name` = '系统监控' AND p.`type` = 'M'
  AND NOT EXISTS (SELECT 1 FROM `sdccc_sys_menu` c WHERE c.`perms` = 'system:online:list');

-- 按钮：强制下线（F）
INSERT INTO `sdccc_sys_menu` (`parent_id`, `name`, `type`, `perms`, `path`, `component`, `icon`, `order_num`, `visible`, `status`, `remark`)
SELECT c.id, '在线用户强制下线', 'F', 'system:online:kick', '', '', '', 1, 1, 1, ''
FROM `sdccc_sys_menu` c
WHERE c.`perms` = 'system:online:list'
  AND NOT EXISTS (SELECT 1 FROM `sdccc_sys_menu` f WHERE f.`perms` = 'system:online:kick');

-- 超管角色授权以上菜单
INSERT INTO `sdccc_sys_role_menu` (`role_id`, `menu_id`)
SELECT 1, m.id FROM `sdccc_sys_menu` m
WHERE m.`perms` IN ('system:online:list', 'system:online:kick')
  AND NOT EXISTS (
    SELECT 1 FROM `sdccc_sys_role_menu` rm WHERE rm.`role_id` = 1 AND rm.`menu_id` = m.id
  );
