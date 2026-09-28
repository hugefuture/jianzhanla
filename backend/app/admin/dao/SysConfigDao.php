<?php
declare(strict_types=1);

namespace app\admin\dao;

use app\common\dao\BaseDao;

/**
 * 参数配置 Dao
 */
class SysConfigDao extends BaseDao
{
    protected array $search = [
        'name' => 'like',
        'key' => 'like',
        'status' => '=',
    ];

    protected function setModel(): string
    {
        return \app\admin\model\SysConfig::class;
    }
}
