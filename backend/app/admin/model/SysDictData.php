<?php
declare(strict_types=1);

namespace app\admin\model;

use think\Model;

/**
 * 字典数据表 sdccc_sys_dict_data
 */
class SysDictData extends Model
{
    protected $name = 'SysDictData';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
}
