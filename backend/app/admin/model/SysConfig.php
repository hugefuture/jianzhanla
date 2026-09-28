<?php
declare(strict_types=1);

namespace app\admin\model;

use think\Model;

/**
 * 参数配置表 sdccc_sys_config
 */
class SysConfig extends Model
{
    protected $name = 'SysConfig';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
}
