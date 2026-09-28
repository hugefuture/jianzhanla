<?php
declare(strict_types=1);

namespace app\admin\model;

use think\Model;

/**
 * 用户表 sdccc_sys_user
 */
class SysUser extends Model
{
    protected $name = 'SysUser';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    /** 隐藏密码字段 */
    protected $hidden = ['password'];

    /** 密码加密：入库时自动 bcrypt */
    public function setPasswordAttr($value)
    {
        if ($value !== '' && !str_starts_with($value, '$2y$')) {
            return password_hash((string) $value, PASSWORD_BCRYPT);
        }
        return $value;
    }

    public function dept()
    {
        return $this->belongsTo(SysDept::class, 'dept_id', 'id');
    }
}
