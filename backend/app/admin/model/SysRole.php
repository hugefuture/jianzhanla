<?php
declare(strict_types=1);

namespace app\admin\model;

use think\Model;

/**
 * 角色表 sdccc_sys_role
 */
class SysRole extends Model
{
    protected $name = 'SysRole';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';

    /** 超级管理员角色标识 */
    public const SUPER_CODE = 'super';

    public function menus()
    {
        return $this->belongsToMany(SysMenu::class, SysRoleMenu::class, 'menu_id', 'role_id');
    }
}
