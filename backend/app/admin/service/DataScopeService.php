<?php
declare(strict_types=1);

namespace app\admin\service;

use app\admin\model\SysRole;
use app\admin\model\SysRoleDept;
use app\admin\model\SysUserRole;
use app\common\traits\Instance;
use think\db\Query;

/**
 * 数据权限服务
 *
 * 数据范围（sys_role.data_scope）：
 *  1 ALL            全部数据
 *  2 CUSTOM         自定义部门（sdccc_sys_role_dept 中指定的部门及其子部门）
 *  3 DEPT           仅本部门
 *  4 DEPT_AND_BELOW 本部门及以下
 *  5 SELF           仅本人数据
 *
 * 多个角色取「最宽」的范围：只要有一个角色是 ALL，即为 ALL；
 * 部门集合取并集；只有当所有角色都是 SELF 时才是 SELF。
 *
 * 用法：
 *   $scope = DataScopeService::getInstance()->resolve($user);
 *   $query = DataScopeService::getInstance()->apply($query, $scope, 'dept_id', 'create_by');
 */
class DataScopeService
{
    use Instance;

    public const ALL = 1;
    public const CUSTOM = 2;
    public const DEPT = 3;
    public const DEPT_AND_BELOW = 4;
    public const SELF = 5;

    /** 人类可读的范围名（前端下拉用） */
    public const LABELS = [
        self::ALL => '全部数据',
        self::CUSTOM => '自定义部门',
        self::DEPT => '仅本部门',
        self::DEPT_AND_BELOW => '本部门及以下',
        self::SELF => '仅本人',
    ];

    /**
     * 解析当前用户的数据范围
     * @return array{all:bool, self_only:bool, dept_ids:int[], scope:int}
     */
    public function resolve(array $user): array
    {
        $userId = (int) ($user['id'] ?? 0);
        $deptId = (int) ($user['dept_id'] ?? 0);

        // 超级管理员直接放行全部
        $roleCodes = AuthService::getInstance()->getUserRoleCodes($userId);
        if (in_array(SysRole::SUPER_CODE, $roleCodes, true)) {
            return ['all' => true, 'self_only' => false, 'dept_ids' => [], 'scope' => self::ALL];
        }

        $roleIds = SysUserRole::where('user_id', $userId)->column('role_id');
        if (!$roleIds) {
            // 没有任何角色：只能看自己的数据
            return ['all' => false, 'self_only' => true, 'dept_ids' => [], 'scope' => self::SELF];
        }

        $roles = SysRole::whereIn('id', $roleIds)->field('id, data_scope')->select()->toArray();
        $deptIds = [];
        $scopes = [];
        foreach ($roles as $role) {
            $scope = (int) ($role['data_scope'] ?? self::ALL);
            $scopes[] = $scope;
            switch ($scope) {
                case self::ALL:
                    return ['all' => true, 'self_only' => false, 'dept_ids' => [], 'scope' => self::ALL];
                case self::CUSTOM:
                    $custom = SysRoleDept::where('role_id', (int) $role['id'])->column('dept_id');
                    foreach ($custom as $did) {
                        // 自定义部门含其子孙
                        $deptIds = array_merge($deptIds, SysDeptService::getInstance()->childrenIds((int) $did));
                    }
                    break;
                case self::DEPT:
                    if ($deptId > 0) {
                        $deptIds[] = $deptId;
                    }
                    break;
                case self::DEPT_AND_BELOW:
                    if ($deptId > 0) {
                        $deptIds = array_merge($deptIds, SysDeptService::getInstance()->childrenIds($deptId));
                    }
                    break;
            }
        }

        // 全部角色都是「仅本人」
        $allSelf = count(array_unique($scopes)) === 1 && ($scopes[0] ?? 0) === self::SELF;
        if ($allSelf) {
            return ['all' => false, 'self_only' => true, 'dept_ids' => [], 'scope' => self::SELF];
        }

        $deptIds = array_values(array_unique(array_filter(array_map('intval', $deptIds))));
        // 没有解析出任何部门，退化为仅本人，避免「越权看到全部」
        if (!$deptIds) {
            return ['all' => false, 'self_only' => true, 'dept_ids' => [], 'scope' => self::SELF];
        }

        return [
            'all' => false,
            'self_only' => false,
            'dept_ids' => $deptIds,
            'scope' => in_array(self::CUSTOM, $scopes, true) ? self::CUSTOM : (in_array(self::DEPT_AND_BELOW, $scopes, true) ? self::DEPT_AND_BELOW : self::DEPT),
        ];
    }

    /**
     * 把数据范围附加到查询构造器
     * @param array $scope resolve() 的返回值
     * @param string $deptField 部门字段名（表无该字段时传空字符串表示不支持部门维度）
     * @param string $userField 创建人字段名
     */
    public function apply(Query $query, array $scope, string $deptField = 'dept_id', string $userField = 'create_by'): Query
    {
        if (!empty($scope['all'])) {
            return $query;
        }
        if (!empty($scope['self_only'])) {
            if ($userField !== '') {
                return $query->where($userField, (int) (request()->user['id'] ?? 0));
            }
            return $query->where('1=0');
        }
        if ($deptField !== '' && !empty($scope['dept_ids'])) {
            return $query->whereIn($deptField, $scope['dept_ids']);
        }
        return $query;
    }

    /** 角色自定义部门 ID 列表 */
    public function roleDeptIds(int $roleId): array
    {
        return array_map('intval', SysRoleDept::where('role_id', $roleId)->column('dept_id'));
    }

    /** 保存角色自定义部门 */
    public function saveRoleDepts(int $roleId, array $deptIds): void
    {
        SysRoleDept::where('role_id', $roleId)->delete();
        $rows = [];
        foreach (array_unique(array_map('intval', $deptIds)) as $deptId) {
            if ($deptId > 0) {
                $rows[] = ['role_id' => $roleId, 'dept_id' => $deptId];
            }
        }
        if ($rows) {
            // 复合主键表用 insertAll，避免 saveAll 被识别为更新
            SysRoleDept::insertAll($rows);
        }
    }
}
