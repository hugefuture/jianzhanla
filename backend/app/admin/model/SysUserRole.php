<?php
declare(strict_types=1);

namespace app\admin\model;

use think\Model;

/**
 * 用户-角色关联表 sdccc_sys_user_role
 */
class SysUserRole extends Model
{
    protected $name = 'SysUserRole';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;
}
