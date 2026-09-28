<?php
declare(strict_types=1);

namespace app\admin\service;

use app\common\service\AbstractService;
use app\admin\dao\SysLoginLogDao;
use app\admin\model\SysLoginLog;

/**
 * 登录日志服务
 */
class SysLoginLogService extends AbstractService
{
    public function __construct()
    {
        $this->dao = SysLoginLogDao::getInstance();
    }

    /** 清空日志 */
    public function clear(): void
    {
        SysLoginLog::query()->delete(true);
    }
}
