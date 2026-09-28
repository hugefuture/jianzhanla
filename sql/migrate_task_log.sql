-- =============================================================
-- P1-D 定时任务执行日志
--   1) 建表 sdccc_sys_task_log
--   2) 菜单：系统监控 > 任务日志
--   3) 超管角色授权
-- 可重复执行
-- =============================================================

CREATE TABLE IF NOT EXISTS `sdccc_sys_task_log` (
  `id`          int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `task_name`   varchar(50)  NOT NULL DEFAULT '' COMMENT '任务标识 logs/cache/backup',
  `task_desc`   varchar(100) NOT NULL DEFAULT '' COMMENT '任务名称',
  `status`      tinyint(1)   NOT NULL DEFAULT 0 COMMENT '状态 0成功 1失败',
  `cost_ms`     int unsigned NOT NULL DEFAULT 0 COMMENT '耗时毫秒',
  `error_msg`   varchar(500) NOT NULL DEFAULT '' COMMENT '错误信息',
  `create_time` datetime     DEFAULT NULL COMMENT '执行时间',
  PRIMARY KEY (`id`),
  KEY `idx_task` (`task_name`),
  KEY `idx_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='定时任务执行日志';

-- 菜单：任务日志（挂到「系统监控」目录下，order_num=3 排在服务器监控之后）
INSERT INTO `sdccc_sys_menu` (`parent_id`, `name`, `type`, `perms`, `path`, `component`, `icon`, `order_num`, `visible`, `status`, `remark`)
SELECT p.id, '任务日志', 'C', 'system:monitor:tasklog', 'tasklog', 'system/monitor/tasklog', 'Clock', 3, 1, 1, ''
FROM `sdccc_sys_menu` p
WHERE p.`name` = '系统监控' AND p.`type` = 'M'
  AND NOT EXISTS (SELECT 1 FROM `sdccc_sys_menu` c WHERE c.`perms` = 'system:monitor:tasklog');

-- 超管角色授权
INSERT INTO `sdccc_sys_role_menu` (`role_id`, `menu_id`)
SELECT 1, m.id FROM `sdccc_sys_menu` m
WHERE m.`perms` = 'system:monitor:tasklog'
  AND NOT EXISTS (
    SELECT 1 FROM `sdccc_sys_role_menu` rm WHERE rm.`role_id` = 1 AND rm.`menu_id` = m.id
  );
