<?php
declare(strict_types=1);

namespace app\admin\model;

use think\Model;

/**
 * 用户-岗位关联表 sdccc_sys_user_post
 */
class SysUserPost extends Model
{
    protected $name = 'SysUserPost';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;
}
