<?php
declare(strict_types=1);

namespace app\admin\dao;

use app\common\dao\BaseDao;

/**
 * 登录日志 Dao
 */
class SysLoginLogDao extends BaseDao
{
    protected array $search = [
        'username' => 'like',
        'ip' => 'like',
        'status' => '=',
        'login_time' => 'time',
    ];

    protected function setModel(): string
    {
        return \app\admin\model\SysLoginLog::class;
    }
}
