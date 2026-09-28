<?php
declare(strict_types=1);

namespace app\admin\model;

use think\Model;

/**
 * 岗位表 sdccc_sys_post
 */
class SysPost extends Model
{
    protected $name = 'SysPost';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
}
