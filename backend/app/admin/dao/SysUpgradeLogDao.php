<?php
declare(strict_types=1);

namespace app\admin\dao;

use app\common\dao\BaseDao;

/**
 * 远程升级日志 Dao
 */
class SysUpgradeLogDao extends BaseDao
{
    protected array $search = [
        'to_version' => '=',
        'status' => '=',
        'create_time' => 'time',
    ];

    protected function setModel(): string
    {
        return \app\admin\model\SysUpgradeLog::class;
    }
}
