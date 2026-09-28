<?php
declare(strict_types=1);

namespace app\admin\dao;

use app\common\dao\BaseDao;

/**
 * 定时任务执行日志 Dao
 */
class SysTaskLogDao extends BaseDao
{
    protected array $search = [
        'task_name' => '=',
        'status' => '=',
        'create_time' => 'time',
    ];

    protected function setModel(): string
    {
        return \app\admin\model\SysTaskLog::class;
    }
}
