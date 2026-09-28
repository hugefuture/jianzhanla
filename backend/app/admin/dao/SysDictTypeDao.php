<?php
declare(strict_types=1);

namespace app\admin\dao;

use app\common\dao\BaseDao;

/**
 * 字典类型 Dao
 */
class SysDictTypeDao extends BaseDao
{
    protected array $search = [
        'name' => 'like',
        'type' => 'like',
        'status' => '=',
    ];

    protected function setModel(): string
    {
        return \app\admin\model\SysDictType::class;
    }
}
