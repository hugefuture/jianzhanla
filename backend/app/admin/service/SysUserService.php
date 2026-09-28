<?php
declare(strict_types=1);

namespace app\admin\service;

use app\common\service\AbstractService;
use app\admin\model\SysDept;
use app\admin\model\SysUser;
use app\admin\model\SysUserPost;
use app\admin\model\SysUserRole;
use app\admin\dao\SysUserDao;
use app\admin\service\LoginGuardService;
use app\common\exception\ApiException;

/**
 * 用户服务
 */
class SysUserService extends AbstractService
{
    protected array $allowFields = ['dept_id', 'username', 'nickname', 'password', 'phone', 'email', 'avatar', 'status', 'remark'];

    protected array $uniques = ['username' => '账号'];
    protected array $required = ['username', 'nickname'];

    /** 非表字段：角色/岗位 ID 列表（走关联表同步） */
    protected array $extraFields = ['role_ids', 'post_ids'];

    public function __construct()
    {
        $this->dao = SysUserDao::getInstance();
    }

    /** 重写分页：支持按部门及其子部门过滤 */
    public function page(array $params = []): array
    {
        if (!empty($params['dept_id'])) {
            $deptIds = SysDeptService::getInstance()->childrenIds((int) $params['dept_id']);
            $params['dept_id'] = ['in', $deptIds];
            // in 条件走 BaseDao 的 applyWhere（支持数组值）
        }
        $result = parent::page($params);
        // 附带角色
        $userIds = array_column($result['list'], 'id');
        if ($userIds) {
            $roles = \app\admin\model\SysRole::alias('r')
                ->join('SysUserRole ur', 'ur.role_id = r.id')
                ->whereIn('ur.user_id', $userIds)
                ->field('ur.user_id, r.id, r.name')
                ->select()
                ->toArray();
            $roleMap = [];
            foreach ($roles as $r) {
                $roleMap[$r['user_id']][] = ['id' => (int) $r['id'], 'name' => $r['name']];
            }
            foreach ($result['list'] as &$item) {
                $item['roles'] = $roleMap[$item['id']] ?? [];
            }
        }
        return $result;
    }

    /** BaseDao 支持 ['in', [...]] 形式 */
    protected function patchSearchParams(): void {}

    protected function beforeCreate(array &$data): void
    {
        if (empty($data['password'])) {
            $data['password'] = '123456';
        }
    }

    protected function afterCreate(array $data, $row): void
    {
        $this->syncRoles((int) $row['id'], $data['role_ids'] ?? []);
        $this->syncPosts((int) $row['id'], $data['post_ids'] ?? []);
    }

    protected function beforeUpdate(array &$data, int $id): void
    {
        if ($id === 1) {
            $this->assertSuper();
        }
        $this->assertInScope($id);
        if (isset($data['password']) && $data['password'] === '') {
            unset($data['password']);
        }
    }

    protected function afterUpdate(array $data, int $id): void
    {
        if (array_key_exists('role_ids', $data)) {
            $this->syncRoles($id, $data['role_ids']);
        }
        if (array_key_exists('post_ids', $data)) {
            $this->syncPosts($id, $data['post_ids']);
        }
    }

    protected function beforeRemove(array $ids): void
    {
        $this->assertSuper();
        foreach ($ids as $id) {
            $this->assertInScope((int) $id);
        }
        if (in_array(1, $ids, true)) {
            throw ApiException::forbidden('超级管理员不允许删除');
        }
        if (in_array((int) (request()->user['id'] ?? 0), $ids, true)) {
            throw ApiException::badRequest('不能删除当前登录账号');
        }
    }

    /** 删除后清理关联表，避免留下孤儿数据 */
    protected function afterRemove(array $ids): void
    {
        SysUserRole::whereIn('user_id', $ids)->delete();
        SysUserPost::whereIn('user_id', $ids)->delete();
    }

    /** 重置密码 */
    public function resetPwd(int $id, string $password): void
    {
        LoginGuardService::getInstance()->assertPassword($password);
        if ($id === 1) {
            $this->assertSuper();
        }
        // 走模型实例触发密码加密修改器
        $user = SysUser::find($id);
        $user->password = $password;
        $user->save();
    }

    /** 分配角色 */
    public function assignRoles(int $id, array $roleIds): void
    {
        if ($id === 1) {
            $this->assertSuper();
        }
        $this->syncRoles($id, $roleIds);
    }

    /** 修改状态 */
    public function changeStatus(int $id, int $status): void
    {
        if ($id === 1) {
            $this->assertSuper();
        }
        parent::changeStatus($id, $status);
    }

    /** 角色选项（新增/编辑用） */
    public function roleOptions(): array
    {
        return \app\admin\model\SysRole::where('status', 1)->field('id, name')->select()->toArray();
    }

    /** 岗位选项 */
    public function postOptions(): array
    {
        return \app\admin\model\SysPost::where('status', 1)->field('id, name')->select()->toArray();
    }

    /** 用户详情（含角色/岗位 ID） */
    public function read(int $id): array
    {
        $row = parent::read($id);
        $row['role_ids'] = SysUserRole::where('user_id', $id)->column('role_id');
        $row['post_ids'] = SysUserPost::where('user_id', $id)->column('post_id');
        unset($row['password']);
        return $row;
    }

    /** 修改个人资料 */
    public function updateProfile(int $id, array $data): void
    {
        $this->dao->updateByPk($id, array_intersect_key($data, array_flip(['nickname', 'phone', 'email', 'avatar', 'sex'])));
    }

    /** 修改自己的密码 */
    public function changePassword(int $id, string $old, string $new): void
    {
        $raw = SysUser::where('id', $id)->value('password');
        if (!$raw || !password_verify($old, (string) $raw)) {
            throw ApiException::badRequest('原密码不正确');
        }
        LoginGuardService::getInstance()->assertPassword($new);
        $user = SysUser::find($id);
        $user->password = $new;
        $user->save();
    }

    /** 校验目标数据是否在当前用户的数据权限范围内 */
    protected function assertInScope(int $id): void
    {
        $user = request()->user ?? null;
        if (!is_array($user) || empty($user['id'])) {
            return;
        }
        $scope = DataScopeService::getInstance()->resolve($user);
        if (!empty($scope['all'])) {
            return;
        }
        $row = $this->dao->findByPk($id);
        if (!$row) {
            throw ApiException::notFound();
        }
        if (!empty($scope['self_only'])) {
            if ((int) $row['id'] !== (int) $user['id']) {
                throw ApiException::forbidden('无权操作该数据');
            }
            return;
        }
        if (!in_array((int) $row['dept_id'], $scope['dept_ids'], true)) {
            throw ApiException::forbidden('无权操作该数据');
        }
    }

    /** 非超管操作超管账号需要超管权限 */
    protected function assertSuper(): void
    {
        $codes = AuthService::getInstance()->getUserRoleCodes((int) (request()->user['id'] ?? 0));
        if (!in_array(\app\admin\model\SysRole::SUPER_CODE, $codes, true)) {
            throw ApiException::forbidden('该操作需要超级管理员权限');
        }
    }

    protected function syncRoles(int $userId, array $roleIds): void
    {
        SysUserRole::where('user_id', $userId)->delete();
        $rows = [];
        foreach (array_unique(array_map('intval', $roleIds)) as $roleId) {
            if ($roleId > 0) {
                $rows[] = ['user_id' => $userId, 'role_id' => $roleId];
            }
        }
        if ($rows) {
            (new SysUserRole())->saveAll($rows);
        }
    }

    protected function syncPosts(int $userId, array $postIds): void
    {
        SysUserPost::where('user_id', $userId)->delete();
        $rows = [];
        foreach (array_unique(array_map('intval', $postIds)) as $postId) {
            if ($postId > 0) {
                $rows[] = ['user_id' => $userId, 'post_id' => $postId];
            }
        }
        if ($rows) {
            (new SysUserPost())->saveAll($rows);
        }
    }
}
