<?php
declare(strict_types=1);

namespace app\admin\service;

use app\common\service\AbstractService;
use app\admin\dao\SysOperLogDao;
use app\admin\model\SysOperLog;

/**
 * 操作日志服务
 */
class SysOperLogService extends AbstractService
{
    public function __construct()
    {
        $this->dao = SysOperLogDao::getInstance();
    }

    /** 清空日志 */
    public function clear(): void
    {
        SysOperLog::query()->delete(true);
    }
}
