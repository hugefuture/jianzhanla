<?php
declare(strict_types=1);

namespace app\admin\model;

use think\Model;

/**
 * 角色-部门关联表 sdccc_sys_role_dept（数据范围=自定义部门时生效）
 */
class SysRoleDept extends Model
{
    protected $name = 'SysRoleDept';
    protected $pk = 'role_id';
    public $timestamps = false;
}
