<?php
declare(strict_types=1);

namespace app\admin\model;

use think\Model;

/**
 * 字典类型表 sdccc_sys_dict_type
 */
class SysDictType extends Model
{
    protected $name = 'SysDictType';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
}
