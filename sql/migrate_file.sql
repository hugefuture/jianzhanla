-- P1-3 文件管理：上传记录表 + 菜单种子

CREATE TABLE IF NOT EXISTS `sdccc_sys_file` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '文件ID',
  `name` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '原始文件名',
  `path` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '存储相对路径',
  `url` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '访问URL',
  `ext` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '后缀',
  `size` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '大小(字节)',
  `mime` VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'MIME类型',
  `module` VARCHAR(50) NOT NULL DEFAULT 'common' COMMENT '来源模块',
  `dept_id` INT NOT NULL DEFAULT 0 COMMENT '归属部门',
  `create_by` INT NOT NULL DEFAULT 0 COMMENT '上传人',
  `create_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '上传时间',
  `update_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_module` (`module`),
  KEY `idx_create_by` (`create_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='文件记录表';

-- 菜单：系统管理 > 文件管理
INSERT INTO `sdccc_sys_menu` (`parent_id`, `name`, `type`, `perms`, `path`, `component`, `icon`, `order_num`, `visible`, `status`, `remark`)
SELECT p.id, '文件管理', 'C', 'system:file:list', 'file', 'system/file/index', 'FolderOpened', 8, 1, 1, ''
FROM `sdccc_sys_menu` p
WHERE p.`name` = '系统管理' AND p.`type` = 'M'
  AND NOT EXISTS (SELECT 1 FROM `sdccc_sys_menu` c WHERE c.`perms` = 'system:file:list');

INSERT INTO `sdccc_sys_menu` (`parent_id`, `name`, `type`, `perms`, `order_num`, `visible`, `status`)
SELECT c.id, '文件删除', 'F', 'system:file:delete', 1, 1, 1
FROM `sdccc_sys_menu` c
WHERE c.`perms` = 'system:file:list'
  AND NOT EXISTS (SELECT 1 FROM `sdccc_sys_menu` f WHERE f.`perms` = 'system:file:delete');

INSERT INTO `sdccc_sys_menu` (`parent_id`, `name`, `type`, `perms`, `order_num`, `visible`, `status`)
SELECT c.id, '孤儿文件清理', 'F', 'system:file:clear', 2, 1, 1
FROM `sdccc_sys_menu` c
WHERE c.`perms` = 'system:file:list'
  AND NOT EXISTS (SELECT 1 FROM `sdccc_sys_menu` f WHERE f.`perms` = 'system:file:clear');

INSERT INTO `sdccc_sys_role_menu` (`role_id`, `menu_id`)
SELECT 1, m.id FROM `sdccc_sys_menu` m
WHERE m.`perms` IN ('system:file:list', 'system:file:delete', 'system:file:clear')
  AND NOT EXISTS (
    SELECT 1 FROM `sdccc_sys_role_menu` rm WHERE rm.`role_id` = 1 AND rm.`menu_id` = m.id
  );
