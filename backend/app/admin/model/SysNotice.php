<?php
declare(strict_types=1);

namespace app\admin\model;

use think\Model;

/**
 * 通知公告表 sdccc_sys_notice
 *
 * 业务模块样例：展示 Model 层的典型用法
 *  - $name        表名（SysNotice -> sdccc_sys_notice）
 *  - 自动时间戳   create_time / update_time
 *  - 获取器       type_text / status_text 供列表直接展示，无需前端翻译
 */
class SysNotice extends Model
{
    protected $name = 'SysNotice';
    protected $pk = 'id';
    protected $autoWriteTimestamp = 'datetime';

    /** 追加的派生字段（只输出，不参与写入） */
    protected $append = ['type_text', 'status_text'];

    public const TYPE_NOTICE = 1;
    public const TYPE_ANNOUNCEMENT = 2;

    public function getTypeTextAttr($value, array $data = []): string
    {
        return (int) ($data['type'] ?? self::TYPE_NOTICE) === self::TYPE_ANNOUNCEMENT ? '公告' : '通知';
    }

    public function getStatusTextAttr($value, array $data = []): string
    {
        return (int) ($data['status'] ?? 0) === 1 ? '已发布' : '草稿';
    }
}
