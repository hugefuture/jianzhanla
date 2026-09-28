<?php
declare(strict_types=1);

namespace app\admin\model;

use think\Model;

/**
 * 部门表 sdccc_sys_dept
 */
class SysDept extends Model
{
    protected $name = 'SysDept';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
}
