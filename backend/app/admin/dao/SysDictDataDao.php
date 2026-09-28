<?php
declare(strict_types=1);

namespace app\admin\dao;

use app\common\dao\BaseDao;

/**
 * 字典数据 Dao
 */
class SysDictDataDao extends BaseDao
{
    protected array $search = [
        'dict_type' => '=',
        'label' => 'like',
        'status' => '=',
    ];

    protected string $order = 'dict_sort asc, id asc';

    protected function setModel(): string
    {
        return \app\admin\model\SysDictData::class;
    }
}
