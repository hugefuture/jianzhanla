<?php
declare(strict_types=1);

namespace app\admin\dao;

use app\common\dao\BaseDao;

/**
 * 插件实例 Dao
 */
class SysPluginDao extends BaseDao
{
    protected array $search = [
        'name' => 'like',
        'title' => 'like',
        'status' => '=',
    ];

    protected function setModel(): string
    {
        return \app\admin\model\SysPlugin::class;
    }
}
