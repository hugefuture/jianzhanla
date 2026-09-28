<?php
declare(strict_types=1);

namespace app\admin\dao;

use app\common\dao\BaseDao;

/**
 * 操作日志 Dao
 */
class SysOperLogDao extends BaseDao
{
    protected array $search = [
        'username' => 'like',
        'module' => '=',
        'method' => '=',
        'status' => '=',
        'create_time' => 'time',
    ];

    protected function setModel(): string
    {
        return \app\admin\model\SysOperLog::class;
    }
}
