<?php
declare(strict_types=1);

namespace app\admin\model;

use think\Model;

/**
 * 定时任务执行日志表 sdccc_sys_task_log
 */
class SysTaskLog extends Model
{
    protected $name = 'SysTaskLog';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'create_time';
    protected $updateTime = false;

    /** 状态：成功 */
    public const ST_OK = 0;
    /** 状态：失败 */
    public const ST_FAIL = 1;

    public function getStatusTextAttr($value, $data): string
    {
        return ((int) ($data['status'] ?? 0)) === self::ST_OK ? '成功' : '失败';
    }
}
