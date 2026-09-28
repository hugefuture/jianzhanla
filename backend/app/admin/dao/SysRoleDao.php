<?php
declare(strict_types=1);

namespace app\admin\dao;

use app\common\dao\BaseDao;

/**
 * 角色 Dao
 */
class SysRoleDao extends BaseDao
{
    protected array $search = [
        'name' => 'like',
        'code' => 'like',
        'status' => '=',
    ];

    protected function setModel(): string
    {
        return \app\admin\model\SysRole::class;
    }
}
