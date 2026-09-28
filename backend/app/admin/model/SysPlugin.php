<?php
declare(strict_types=1);

namespace app\admin\model;

use think\Model;

/**
 * 插件实例表 sdccc_sys_plugin
 * status: 1启用 2禁用（未安装不落库）
 */
class SysPlugin extends Model
{
    protected $name = 'SysPlugin';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'create_time';
    protected $updateTime = false;

    /** 启用 */
    public const ST_ENABLED = 1;
    /** 禁用 */
    public const ST_DISABLED = 2;

    public function getStatusTextAttr($value, $data): string
    {
        return ((int) ($data['status'] ?? 0)) === self::ST_ENABLED ? '已启用' : '已禁用';
    }
}
