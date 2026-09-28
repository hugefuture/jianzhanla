<?php
declare(strict_types=1);

namespace app\admin\dao;

use app\common\dao\BaseDao;

/**
 * 用户 Dao
 */
class SysUserDao extends BaseDao
{
    /** 通用搜索映射 */
    protected array $search = [
        'username' => 'like',
        'nickname' => 'like',
        'phone' => 'like',
        'status' => '=',
        'dept_id' => '=',
        'create_time' => 'time',
    ];

    /** 启用数据权限；用户表的「本人」维度用 id 字段 */
    protected bool $dataScope = true;
    protected string $scopeUserField = 'id';

    protected function setModel(): string
    {
        return \app\admin\model\SysUser::class;
    }

    /** 重写分页：附带部门名称 */
    public function getPage(array $params, int $page, int $limit): array
    {
        $result = parent::getPage($params, $page, $limit);
        $deptIds = array_filter(array_unique(array_column($result['list'], 'dept_id')));
        $depts = [];
        if ($deptIds) {
            $depts = \app\admin\model\SysDept::whereIn('id', $deptIds)->column('name', 'id');
        }
        foreach ($result['list'] as &$item) {
            $item['dept_name'] = $depts[$item['dept_id']] ?? '';
        }
        return $result;
    }

    /** 按用户名取用户（含密码字段） */
    public function findWithPassword(string $username): ?array
    {
        $row = \app\admin\model\SysUser::where('username', $username)->find();
        return $row ? $row->getData() : null;
    }
}
