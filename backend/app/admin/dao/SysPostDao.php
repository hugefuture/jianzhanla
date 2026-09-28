<?php
declare(strict_types=1);

namespace app\admin\dao;

use app\common\dao\BaseDao;

/**
 * 岗位 Dao
 */
class SysPostDao extends BaseDao
{
    protected array $search = [
        'name' => 'like',
        'code' => 'like',
        'status' => '=',
    ];

    protected string $order = 'order_num asc, id asc';

    protected function setModel(): string
    {
        return \app\admin\model\SysPost::class;
    }
}
