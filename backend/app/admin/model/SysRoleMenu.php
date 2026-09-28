<?php
declare(strict_types=1);

namespace app\admin\model;

use think\Model;

/**
 * 角色-菜单关联表 sdccc_sys_role_menu
 */
class SysRoleMenu extends Model
{
    protected $name = 'SysRoleMenu';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;
}
