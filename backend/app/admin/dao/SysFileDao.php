<?php
declare(strict_types=1);

namespace app\admin\dao;

use app\common\dao\BaseDao;

/**
 * 文件记录 Dao
 */
class SysFileDao extends BaseDao
{
    protected array $search = [
        'name' => 'like',
        'ext' => '=',
        'module' => '=',
        'create_time' => 'time',
    ];

    protected string $order = 'id desc';

    protected bool $dataScope = true;
    protected string $scopeDeptField = 'dept_id';
    protected string $scopeUserField = 'create_by';

    protected function setModel(): string
    {
        return \app\admin\model\SysFile::class;
    }
}
