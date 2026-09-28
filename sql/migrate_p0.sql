-- ============================================================
-- P0 增强迁移脚本（在 init.sql 之后执行，可重复执行）
-- 1) 数据权限：角色数据范围 + 角色部门关联
-- 2) 登录防护：验证码 / 重试锁定 / 密码策略 参数
-- ============================================================

-- ---------- 1. 数据权限 ----------
-- 说明：重复执行时若列已存在会报 1060，属正常，可忽略或先注释本段
ALTER TABLE `sdccc_sys_role`
  ADD COLUMN `data_scope` tinyint NOT NULL DEFAULT 1
  COMMENT '数据范围：1全部 2自定义部门 3本部门 4本部门及以下 5仅本人' AFTER `sort`;

CREATE TABLE IF NOT EXISTS `sdccc_sys_role_dept` (
  `role_id` int unsigned NOT NULL COMMENT '角色ID',
  `dept_id` int unsigned NOT NULL COMMENT '部门ID',
  PRIMARY KEY (`role_id`, `dept_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='角色与部门关联（数据范围=自定义时生效）';

-- ---------- 2. 登录防护参数 ----------
INSERT INTO `sdccc_sys_config` (`name`, `key`, `value`, `type`, `remark`)
VALUES
  ('登录验证码开关', 'sys.login.captcha', '1', 1, '1开启 0关闭，开启后登录必须带验证码'),
  ('登录失败次数上限', 'sys.login.maxRetry', '5', 1, '连续失败达到该次数后锁定账号'),
  ('登录锁定时长(分钟)', 'sys.login.lockMinutes', '10', 1, '锁定后需等待的分钟数'),
  ('密码最小长度', 'sys.password.minLength', '6', 1, '新建/重置密码时的最小长度'),
  ('密码复杂度要求', 'sys.password.complex', '0', 1, '0不要求 1必须同时含字母和数字')
ON DUPLICATE KEY UPDATE `update_time` = NOW();
