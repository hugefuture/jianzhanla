-- =====================================================================
-- sdccc-admin 系统初始化脚本（MySQL 8.0+，表前缀：sdccc_）
-- 默认账号：admin / 123456（超级管理员）
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `sdccc_admin` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `sdccc_admin`;

-- ----------------------------
-- 部门表
-- ----------------------------
DROP TABLE IF EXISTS `sdccc_sys_dept`;
CREATE TABLE `sdccc_sys_dept` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '部门ID',
  `parent_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '父部门ID',
  `name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '部门名称',
  `order_num` INT NOT NULL DEFAULT 0 COMMENT '排序',
  `leader` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '负责人',
  `phone` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '联系电话',
  `email` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '邮箱',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态 1正常 0停用',
  `create_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='部门表';

-- ----------------------------
-- 用户表
-- ----------------------------
DROP TABLE IF EXISTS `sdccc_sys_user`;
CREATE TABLE `sdccc_sys_user` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '用户ID',
  `dept_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '部门ID',
  `username` VARCHAR(30) NOT NULL COMMENT '账号',
  `nickname` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '昵称',
  `password` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '密码(bcrypt)',
  `phone` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '手机号',
  `email` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '邮箱',
  `avatar` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '头像',
  `sex` TINYINT NOT NULL DEFAULT 0 COMMENT '性别 0未知 1男 2女',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态 1正常 0停用',
  `remark` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '备注',
  `last_login_time` DATETIME DEFAULT NULL COMMENT '最后登录时间',
  `last_login_ip` VARCHAR(45) NOT NULL DEFAULT '' COMMENT '最后登录IP',
  `create_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`),
  KEY `idx_dept_id` (`dept_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户表';

-- ----------------------------
-- 角色表
-- ----------------------------
DROP TABLE IF EXISTS `sdccc_sys_role`;
CREATE TABLE `sdccc_sys_role` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '角色ID',
  `name` VARCHAR(30) NOT NULL COMMENT '角色名称',
  `code` VARCHAR(30) NOT NULL COMMENT '角色编码',
  `sort` INT NOT NULL DEFAULT 0 COMMENT '排序',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态 1正常 0停用',
  `remark` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '备注',
  `create_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='角色表';

-- ----------------------------
-- 菜单/权限表
-- ----------------------------
DROP TABLE IF EXISTS `sdccc_sys_menu`;
CREATE TABLE `sdccc_sys_menu` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '菜单ID',
  `parent_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '父菜单ID',
  `name` VARCHAR(50) NOT NULL COMMENT '菜单名称',
  `type` CHAR(1) NOT NULL DEFAULT 'C' COMMENT '类型 M目录 C菜单 F按钮',
  `perms` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '权限标识 如 system:user:list',
  `path` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '路由地址',
  `component` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '前端组件路径',
  `icon` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '图标',
  `order_num` INT NOT NULL DEFAULT 0 COMMENT '排序',
  `visible` TINYINT NOT NULL DEFAULT 1 COMMENT '是否显示 1显示 0隐藏',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态 1正常 0停用',
  `remark` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '备注',
  `create_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='菜单权限表';

-- ----------------------------
-- 用户-角色 / 用户-岗位 / 角色-菜单 关联表
-- ----------------------------
DROP TABLE IF EXISTS `sdccc_sys_user_role`;
CREATE TABLE `sdccc_sys_user_role` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL COMMENT '用户ID',
  `role_id` INT UNSIGNED NOT NULL COMMENT '角色ID',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_role` (`user_id`, `role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户角色关联表';

DROP TABLE IF EXISTS `sdccc_sys_user_post`;
CREATE TABLE `sdccc_sys_user_post` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL COMMENT '用户ID',
  `post_id` INT UNSIGNED NOT NULL COMMENT '岗位ID',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_post` (`user_id`, `post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户岗位关联表';

DROP TABLE IF EXISTS `sdccc_sys_role_menu`;
CREATE TABLE `sdccc_sys_role_menu` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id` INT UNSIGNED NOT NULL COMMENT '角色ID',
  `menu_id` INT UNSIGNED NOT NULL COMMENT '菜单ID',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_role_menu` (`role_id`, `menu_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='角色菜单关联表';

-- ----------------------------
-- 岗位表
-- ----------------------------
DROP TABLE IF EXISTS `sdccc_sys_post`;
CREATE TABLE `sdccc_sys_post` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '岗位ID',
  `name` VARCHAR(30) NOT NULL COMMENT '岗位名称',
  `code` VARCHAR(30) NOT NULL COMMENT '岗位编码',
  `order_num` INT NOT NULL DEFAULT 0 COMMENT '排序',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态 1正常 0停用',
  `remark` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '备注',
  `create_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='岗位表';

-- ----------------------------
-- 字典类型表 / 字典数据表
-- ----------------------------
DROP TABLE IF EXISTS `sdccc_sys_dict_type`;
CREATE TABLE `sdccc_sys_dict_type` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '字典类型ID',
  `name` VARCHAR(50) NOT NULL COMMENT '字典名称',
  `type` VARCHAR(50) NOT NULL COMMENT '字典类型标识',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态 1正常 0停用',
  `remark` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '备注',
  `create_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='字典类型表';

DROP TABLE IF EXISTS `sdccc_sys_dict_data`;
CREATE TABLE `sdccc_sys_dict_data` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '字典数据ID',
  `dict_type` VARCHAR(50) NOT NULL COMMENT '字典类型标识',
  `label` VARCHAR(50) NOT NULL COMMENT '标签',
  `value` VARCHAR(50) NOT NULL COMMENT '值',
  `dict_sort` INT NOT NULL DEFAULT 0 COMMENT '排序',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态 1正常 0停用',
  `remark` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '备注',
  `create_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_dict_type` (`dict_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='字典数据表';

-- ----------------------------
-- 参数配置表
-- ----------------------------
DROP TABLE IF EXISTS `sdccc_sys_config`;
CREATE TABLE `sdccc_sys_config` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '参数ID',
  `name` VARCHAR(50) NOT NULL COMMENT '参数名称',
  `key` VARCHAR(50) NOT NULL COMMENT '参数键名',
  `value` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '参数值',
  `type` VARCHAR(20) NOT NULL DEFAULT 'text' COMMENT '类型 text/textarea/number/switch',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态 1正常 0停用',
  `remark` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '备注',
  `create_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='参数配置表';

-- ----------------------------
-- 操作日志表 / 登录日志表
-- ----------------------------
DROP TABLE IF EXISTS `sdccc_sys_oper_log`;
CREATE TABLE `sdccc_sys_oper_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '日志ID',
  `user_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户ID',
  `username` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '账号',
  `module` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '模块',
  `action` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '操作(控制器@方法)',
  `method` VARCHAR(10) NOT NULL DEFAULT '' COMMENT '请求方式',
  `url` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '请求地址',
  `params` VARCHAR(2000) NOT NULL DEFAULT '' COMMENT '请求参数',
  `ip` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'IP',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态 1成功 0失败',
  `error_msg` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '错误信息',
  `cost_time` DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '耗时(ms)',
  `create_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='操作日志表';

DROP TABLE IF EXISTS `sdccc_sys_login_log`;
CREATE TABLE `sdccc_sys_login_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '日志ID',
  `username` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '账号',
  `ip` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'IP',
  `browser` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '浏览器',
  `os` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '操作系统',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态 1成功 0失败',
  `msg` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '提示信息',
  `login_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '登录时间',
  PRIMARY KEY (`id`),
  KEY `idx_username` (`username`),
  KEY `idx_login_time` (`login_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='登录日志表';

-- =====================================================================
-- 种子数据
-- =====================================================================

-- 部门
INSERT INTO `sdccc_sys_dept` (`id`, `parent_id`, `name`, `order_num`) VALUES
(1, 0, '总公司', 1),
(2, 1, '研发部', 1),
(3, 1, '市场部', 2),
(4, 1, '财务部', 3);

-- 用户（密码均为 123456）
INSERT INTO `sdccc_sys_user` (`id`, `dept_id`, `username`, `nickname`, `password`, `status`, `remark`) VALUES
(1, 1, 'admin', '超级管理员', '$2y$10$NOFCU/EEsifAAQ02X/rYEuvY2F3udG6zWipHyGmcZ0vbObASCzNoC', 1, '内置超级管理员'),
(2, 2, 'test', '测试用户', '$2y$10$NOFCU/EEsifAAQ02X/rYEuvY2F3udG6zWipHyGmcZ0vbObASCzNoC', 1, '普通演示账号');

-- 角色
INSERT INTO `sdccc_sys_role` (`id`, `name`, `code`, `sort`, `remark`) VALUES
(1, '超级管理员', 'super', 1, '拥有全部权限（代码内置放行）'),
(2, '普通用户', 'common', 2, '仅查看权限');

-- 用户角色
INSERT INTO `sdccc_sys_user_role` (`user_id`, `role_id`) VALUES (1, 1), (2, 2);

-- 岗位
INSERT INTO `sdccc_sys_post` (`id`, `name`, `code`, `order_num`) VALUES
(1, '董事长', 'ceo', 1),
(2, '项目经理', 'pm', 2),
(3, '工程师', 'engineer', 3),
(4, '普通员工', 'staff', 4);

-- 菜单
INSERT INTO `sdccc_sys_menu` (`id`, `parent_id`, `name`, `type`, `perms`, `path`, `component`, `icon`, `order_num`) VALUES
(1,   0, '系统管理', 'M', '', 'system', '', 'Setting', 1),
-- 用户管理
(100, 1, '用户管理', 'C', 'system:user:list', 'user', 'system/user/index', 'User', 1),
(1001, 100, '用户保存', 'F', 'system:user:save', '', '', '', 1),
(1002, 100, '用户删除', 'F', 'system:user:delete', '', '', '', 2),
(1003, 100, '用户状态', 'F', 'system:user:status', '', '', '', 3),
(1004, 100, '重置密码', 'F', 'system:user:resetPwd', '', '', '', 4),
(1005, 100, '分配角色', 'F', 'system:user:assignRoles', '', '', '', 5),
-- 角色管理
(101, 1, '角色管理', 'C', 'system:role:list', 'role', 'system/role/index', 'UserFilled', 2),
(1011, 101, '角色保存', 'F', 'system:role:save', '', '', '', 1),
(1012, 101, '角色删除', 'F', 'system:role:delete', '', '', '', 2),
(1013, 101, '角色状态', 'F', 'system:role:status', '', '', '', 3),
(1014, 101, '角色授权', 'F', 'system:role:assignMenus', '', '', '', 4),
-- 菜单管理
(102, 1, '菜单管理', 'C', 'system:menu:list', 'menu', 'system/menu/index', 'Menu', 3),
(1021, 102, '菜单保存', 'F', 'system:menu:save', '', '', '', 1),
(1022, 102, '菜单删除', 'F', 'system:menu:delete', '', '', '', 2),
-- 部门管理
(103, 1, '部门管理', 'C', 'system:dept:list', 'dept', 'system/dept/index', 'OfficeBuilding', 4),
(1031, 103, '部门保存', 'F', 'system:dept:save', '', '', '', 1),
(1032, 103, '部门删除', 'F', 'system:dept:delete', '', '', '', 2),
-- 岗位管理
(104, 1, '岗位管理', 'C', 'system:post:list', 'post', 'system/post/index', 'Postcard', 5),
(1041, 104, '岗位保存', 'F', 'system:post:save', '', '', '', 1),
(1042, 104, '岗位删除', 'F', 'system:post:delete', '', '', '', 2),
(1043, 104, '岗位状态', 'F', 'system:post:status', '', '', '', 3),
-- 字典管理
(105, 1, '字典管理', 'C', 'system:dict:list', 'dict', 'system/dict/index', 'Collection', 6),
(1051, 105, '字典保存', 'F', 'system:dict:save', '', '', '', 1),
(1052, 105, '字典删除', 'F', 'system:dict:delete', '', '', '', 2),
(1053, 105, '字典数据保存', 'F', 'system:dict:saveData', '', '', '', 3),
(1054, 105, '字典数据删除', 'F', 'system:dict:deleteData', '', '', '', 4),
-- 参数设置
(106, 1, '参数设置', 'C', 'system:config:list', 'config', 'system/config/index', 'Tools', 7),
(1061, 106, '参数保存', 'F', 'system:config:save', '', '', '', 1),
(1062, 106, '参数删除', 'F', 'system:config:delete', '', '', '', 2),
(1063, 106, '参数状态', 'F', 'system:config:status', '', '', '', 3),
-- 日志管理
(107, 1, '日志管理', 'M', '', 'log', '', 'Document', 8),
(108, 107, '操作日志', 'C', 'system:log:oper', 'oper', 'system/log/oper', '', 1),
(1081, 108, '清空操作日志', 'F', 'system:log:clearOper', '', '', '', 1),
(109, 107, '登录日志', 'C', 'system:log:login', 'loginlog', 'system/log/login', '', 2),
(1091, 109, '清空登录日志', 'F', 'system:log:clearLogin', '', '', '', 1);

-- 超管角色拥有全部菜单
INSERT INTO `sdccc_sys_role_menu` (`role_id`, `menu_id`)
SELECT 1, `id` FROM `sdccc_sys_menu`;

-- 普通用户角色：仅目录和菜单（查看权限）
INSERT INTO `sdccc_sys_role_menu` (`role_id`, `menu_id`)
SELECT 2, `id` FROM `sdccc_sys_menu` WHERE `type` IN ('M', 'C');

-- 参数配置
INSERT INTO `sdccc_sys_config` (`name`, `key`, `value`, `type`, `remark`) VALUES
('系统名称', 'site_name', '建站啦后台管理系统', 'text', '后台显示的系统名称'),
('默认分页大小', 'page_size', '10', 'number', '列表默认每页条数'),
('版权信息', 'copyright', '© 2026 建站啦', 'text', '页脚版权信息');

-- 字典类型
INSERT INTO `sdccc_sys_dict_type` (`name`, `type`, `remark`) VALUES
('系统状态', 'sys_normal_status', '通用启用/停用状态'),
('用户性别', 'sys_user_sex', '用户性别字典'),
('菜单类型', 'sys_menu_type', '菜单类型字典');

-- 字典数据
INSERT INTO `sdccc_sys_dict_data` (`dict_type`, `label`, `value`, `dict_sort`) VALUES
('sys_normal_status', '正常', '1', 1),
('sys_normal_status', '停用', '0', 2),
('sys_user_sex', '未知', '0', 1),
('sys_user_sex', '男', '1', 2),
('sys_user_sex', '女', '2', 3),
('sys_menu_type', '目录', 'M', 1),
('sys_menu_type', '菜单', 'C', 2),
('sys_menu_type', '按钮', 'F', 3);
