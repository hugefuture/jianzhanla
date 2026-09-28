<?php
declare(strict_types=1);

namespace app\admin\dao;

use app\common\dao\BaseDao;

/**
 * 菜单 Dao
 */
class SysMenuDao extends BaseDao
{
    protected array $search = [
        'name' => 'like',
        'status' => '=',
    ];

    protected string $order = 'order_num asc, id asc';

    protected function setModel(): string
    {
        return \app\admin\model\SysMenu::class;
    }
}
