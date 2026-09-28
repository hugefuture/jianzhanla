-- P1-1 代码生成菜单种子（系统工具 > 代码生成）

INSERT INTO `sdccc_sys_menu` (`parent_id`, `name`, `type`, `perms`, `path`, `component`, `icon`, `order_num`, `visible`, `status`, `remark`)
SELECT 0, '系统工具', 'M', '', 'tool', '', 'Tools', 4, 1, 1, ''
WHERE NOT EXISTS (SELECT 1 FROM `sdccc_sys_menu` m WHERE m.`name` = '系统工具' AND m.`type` = 'M');

INSERT INTO `sdccc_sys_menu` (`parent_id`, `name`, `type`, `perms`, `path`, `component`, `icon`, `order_num`, `visible`, `status`, `remark`)
SELECT p.id, '代码生成', 'C', 'tool:gen:list', 'gen', 'tool/gen/index', 'MagicStick', 1, 1, 1, ''
FROM `sdccc_sys_menu` p
WHERE p.`name` = '系统工具' AND p.`type` = 'M'
  AND NOT EXISTS (SELECT 1 FROM `sdccc_sys_menu` c WHERE c.`perms` = 'tool:gen:list');

INSERT INTO `sdccc_sys_menu` (`parent_id`, `name`, `type`, `perms`, `order_num`, `visible`, `status`)
SELECT c.id, '代码生成-执行', 'F', 'tool:gen:generate', 1, 1, 1
FROM `sdccc_sys_menu` c
WHERE c.`perms` = 'tool:gen:list'
  AND NOT EXISTS (SELECT 1 FROM `sdccc_sys_menu` f WHERE f.`perms` = 'tool:gen:generate');

INSERT INTO `sdccc_sys_role_menu` (`role_id`, `menu_id`)
SELECT 1, m.id FROM `sdccc_sys_menu` m
WHERE m.`perms` IN ('tool:gen:list', 'tool:gen:generate')
  AND NOT EXISTS (
    SELECT 1 FROM `sdccc_sys_role_menu` rm WHERE rm.`role_id` = 1 AND rm.`menu_id` = m.id
  );
