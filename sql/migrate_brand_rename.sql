-- 品牌改名：sdccc 管理系统 -> 建站啦后台管理系统
-- 只改数据表里的“显示内容”，不涉及表名/表前缀。

UPDATE `sdccc_sys_config` SET `value` = '建站啦后台管理系统'
WHERE `key` = 'site_name';

UPDATE `sdccc_sys_config` SET `value` = '© 2026 建站啦'
WHERE `key` = 'copyright';

SELECT `id`, `key`, `value` FROM `sdccc_sys_config`
WHERE `key` IN ('site_name', 'copyright');
