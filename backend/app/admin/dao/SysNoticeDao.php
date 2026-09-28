<?php
declare(strict_types=1);

namespace app\admin\dao;

use app\common\dao\BaseDao;

/**
 * 通知公告 Dao（业务模块样例）
 *
 * 要点：
 *  - $search      通用查询映射，控制器无需再写查询逻辑
 *  - $dataScope   开启数据权限，列表自动按部门 / 创建人过滤
 *  - $order       置顶优先 + 最新在前
 */
class SysNoticeDao extends BaseDao
{
    protected array $search = [
        'title' => 'like',
        'type' => '=',
        'status' => '=',
        'is_top' => '=',
        'create_time' => 'time',
    ];

    protected string $order = 'is_top desc, id desc';

    /** 开启数据权限过滤 */
    protected bool $dataScope = true;
    protected string $scopeDeptField = 'dept_id';
    protected string $scopeUserField = 'create_by';

    protected function setModel(): string
    {
        return \app\admin\model\SysNotice::class;
    }
}
