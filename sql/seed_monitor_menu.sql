-- ============================================================
-- P2-4 菜单种子：系统监控页（挂在「系统监控」目录下）
-- 可重复执行
-- ============================================================

-- 目录不存在时先建（与 seed_online_menu.sql 兼容）
INSERT INTO `sdccc_sys_menu` (`parent_id`, `name`, `type`, `perms`, `path`, `component`, `icon`, `order_num`, `visible`, `status`, `remark`)
SELECT 0, '系统监控', 'M', '', 'monitor', '', 'Monitor', 2, 1, 1, ''
WHERE NOT EXISTS (SELECT 1 FROM `sdccc_sys_menu` m WHERE m.`name` = '系统监控' AND m.`type` = 'M');

-- 菜单：服务器监控（C）
INSERT INTO `sdccc_sys_menu` (`parent_id`, `name`, `type`, `perms`, `path`, `component`, `icon`, `order_num`, `visible`, `status`, `remark`)
SELECT p.id, '服务器监控', 'C', 'system:monitor:index', 'server', 'system/monitor/index', 'Cpu', 2, 1, 1, ''
FROM `sdccc_sys_menu` p
WHERE p.`name` = '系统监控' AND p.`type` = 'M'
  AND NOT EXISTS (SELECT 1 FROM `sdccc_sys_menu` c WHERE c.`perms` = 'system:monitor:index');

-- 超管角色授权
INSERT INTO `sdccc_sys_role_menu` (`role_id`, `menu_id`)
SELECT 1, m.id FROM `sdccc_sys_menu` m
WHERE m.`perms` = 'system:monitor:index'
  AND NOT EXISTS (
    SELECT 1 FROM `sdccc_sys_role_menu` rm WHERE rm.`role_id` = 1 AND rm.`menu_id` = m.id
  );
