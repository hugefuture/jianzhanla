<?php
declare(strict_types=1);

namespace app\admin\model;

use think\Model;

/**
 * 操作日志表 sdccc_sys_oper_log
 */
class SysOperLog extends Model
{
    protected $name = 'SysOperLog';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'create_time';
    protected $updateTime = false;
}
