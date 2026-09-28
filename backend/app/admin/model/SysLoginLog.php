<?php
declare(strict_types=1);

namespace app\admin\model;

use think\Model;

/**
 * 登录日志表 sdccc_sys_login_log
 */
class SysLoginLog extends Model
{
    protected $name = 'SysLoginLog';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'login_time';
    protected $updateTime = false;
}
