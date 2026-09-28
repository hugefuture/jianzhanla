<?php
declare(strict_types=1);

namespace app\admin\service;

use app\common\service\AbstractService;
use app\admin\model\SysRole;
use app\admin\model\SysRoleMenu;
use app\admin\model\SysUser;
use app\admin\model\SysUserRole;
use app\admin\dao\SysRoleDao;
use app\common\exception\ApiException;

/**
 * 角色服务
 */
class SysRoleService extends AbstractService
{
    protected array $allowFields = ['name', 'code', 'sort', 'status', 'remark', 'data_scope'];

    protected array $uniques = ['code' => '角色编码'];
    protected array $required = ['name', 'code'];

    /** 非表字段：自定义部门 ID 列表 */
    protected array $extraFields = ['dept_ids'];

    public function __construct()
    {
        $this->dao = SysRoleDao::getInstance();
    }

    protected function beforeCreate(array &$data): void
    {
        if ($data['code'] === SysRole::SUPER_CODE) {
            throw ApiException::forbidden('超级管理员角色不允许重复创建');
        }
    }

    protected function beforeUpdate(array &$data, int $id): void
    {
        if ($id === 1) {
            $this->assertSuper();
            // 超管角色不允许修改编码
            unset($data['code']);
        }
    }

    protected function beforeRemove(array $ids): void
    {
        $this->assertSuper();
        if (in_array(1, $ids, true)) {
            throw ApiException::forbidden('超级管理员角色不允许删除');
        }
        $used = SysUserRole::whereIn('role_id', $ids)->count();
        if ($used > 0) {
            throw ApiException::badRequest('角色已分配给用户，请先解除分配');
        }
        SysRoleMenu::whereIn('role_id', $ids)->delete();
        \app\admin\model\SysRoleDept::whereIn('role_id', $ids)->delete();
    }

    /** 角色详情（含自定义部门 ID） */
    public function read(int $id): array
    {
        $row = parent::read($id);
        $row['dept_ids'] = DataScopeService::getInstance()->roleDeptIds($id);
        return $row;
    }

    protected function afterCreate(array $data, $row): void
    {
        if (array_key_exists('dept_ids', $data)) {
            DataScopeService::getInstance()->saveRoleDepts((int) $row['id'], (array) $data['dept_ids']);
        }
    }

    protected function afterUpdate(array $data, int $id): void
    {
        if (array_key_exists('dept_ids', $data)) {
            DataScopeService::getInstance()->saveRoleDepts($id, (array) $data['dept_ids']);
        }
    }

    /** 数据范围选项（前端下拉） */
    public function scopeOptions(): array
    {
        return DataScopeService::LABELS;
    }

    /** 分配菜单权限 */
    public function assignMenus(int $roleId, array $menuIds): void
    {
        if ($roleId === 1) {
            $this->assertSuper();
        }
        SysRoleMenu::where('role_id', $roleId)->delete();
        $rows = [];
        foreach (array_unique(array_map('intval', $menuIds)) as $menuId) {
            if ($menuId > 0) {
                $rows[] = ['role_id' => $roleId, 'menu_id' => $menuId];
            }
        }
        if ($rows) {
            (new SysRoleMenu())->saveAll($rows);
        }
    }

    /** 角色已授权的菜单 ID */
    public function menuIds(int $roleId): array
    {
        return SysRoleMenu::where('role_id', $roleId)->column('menu_id');
    }

    protected function assertSuper(): void
    {
        $codes = AuthService::getInstance()->getUserRoleCodes((int) (request()->user['id'] ?? 0));
        if (!in_array(SysRole::SUPER_CODE, $codes, true)) {
            throw ApiException::forbidden('该操作需要超级管理员权限');
        }
    }
}
