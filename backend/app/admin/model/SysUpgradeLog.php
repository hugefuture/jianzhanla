<?php
declare(strict_types=1);

namespace app\admin\model;

use think\Model;

/**
 * 远程升级日志表 sdccc_sys_upgrade_log
 * status: 0成功 1失败 2已回滚 3回滚失败
 */
class SysUpgradeLog extends Model
{
    protected $name = 'SysUpgradeLog';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'create_time';
    protected $updateTime = false;

    /** 输出时自动附加状态文案 */
    protected $append = ['status_text'];

    /** 状态：升级成功 */
    public const ST_OK = 0;
    /** 状态：升级失败 */
    public const ST_FAIL = 1;
    /** 状态：已回滚 */
    public const ST_ROLLBACK = 2;
    /** 状态：回滚失败 */
    public const ST_ROLLBACK_FAIL = 3;

    public function getStatusTextAttr($value, $data): string
    {
        return match ((int) ($data['status'] ?? 0)) {
            self::ST_OK => '成功',
            self::ST_FAIL => '失败',
            self::ST_ROLLBACK => '已回滚',
            default => '回滚失败',
        };
    }
}
