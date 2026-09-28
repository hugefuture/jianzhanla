<?php
declare(strict_types=1);

namespace app\admin\service;

use app\common\service\AbstractService;
use app\admin\model\SysMenu;
use app\admin\dao\SysMenuDao;
use app\common\exception\ApiException;

/**
 * 菜单服务
 */
class SysMenuService extends AbstractService
{
    protected array $allowFields = ['parent_id', 'name', 'type', 'perms', 'path', 'component', 'icon', 'order_num', 'visible', 'status', 'remark'];

    protected array $uniques = [];
    protected array $required = ['name', 'type'];

    public function __construct()
    {
        $this->dao = SysMenuDao::getInstance();
    }

    /** 重写分页：菜单管理返回全量树 */
    public function page(array $params = []): array
    {
        $list = $this->dao->getList($params);
        $list = $this->toTree($list);
        return ['list' => $list, 'total' => count($list)];
    }

    /** 扁平列表转树 */
    public function toTree(array $list, int $parentId = 0): array
    {
        $map = [];
        foreach ($list as $item) {
            $map[(int) $item['id']] = $item;
        }
        $tree = [];
        foreach ($map as $id => &$node) {
            $pid = (int) $node['parent_id'];
            if (isset($map[$pid])) {
                $map[$pid]['children'][] = &$node;
            } else {
                $tree[] = &$node;
            }
        }
        unset($node);
        return $this->normalizeTree($tree);
    }

    protected function normalizeTree(array $nodes): array
    {
        foreach ($nodes as &$node) {
            if (!empty($node['children']) && is_array($node['children'])) {
                $node['children'] = $this->normalizeTree($node['children']);
            } else {
                unset($node['children']);
            }
        }
        return $nodes;
    }

    /** 角色授权用的菜单树（id/name/children） */
    public function treeSelect(): array
    {
        $list = $this->dao->getList(['status' => 1]);
        return $this->buildSelectTree($list, 0);
    }

    protected function buildSelectTree(array $list, int $parentId): array
    {
        $tree = [];
        foreach ($list as $item) {
            if ((int) $item['parent_id'] !== $parentId) {
                continue;
            }
            $node = ['id' => (int) $item['id'], 'label' => $item['name'], 'children' => $this->buildSelectTree($list, (int) $item['id'])];
            if (empty($node['children'])) {
                unset($node['children']);
            }
            $tree[] = $node;
        }
        return $tree;
    }

    /** 前端路由树：仅目录与可见菜单 */
    public function getUserMenus(array $user): array
    {
        $perms = AuthService::getInstance()->getUserPerms($user);
        if (in_array('*', $perms, true)) {
            $list = $this->dao->getList(['status' => 1]);
        } else {
            $roleIds = \app\admin\model\SysRole::alias('r')
                ->join('SysUserRole ur', 'ur.role_id = r.id')
                ->where('ur.user_id', (int) $user['id'])
                ->column('r.id');
            $list = [];
            if ($roleIds) {
                $list = SysMenu::alias('m')
                    ->join('SysRoleMenu rm', 'rm.menu_id = m.id')
                    ->whereIn('rm.role_id', $roleIds)
                    ->where('m.status', 1)
                    ->whereIn('m.type', [SysMenu::TYPE_DIR, SysMenu::TYPE_MENU])
                    ->field('m.*')
                    ->select()
                    ->toArray();
            }
            // 去重
            $seen = [];
            $list = array_values(array_filter($list, function ($item) use (&$seen) {
                if (isset($seen[$item['id']])) {
                    return false;
                }
                $seen[$item['id']] = true;
                return true;
            }));
        }
        return $this->toTree(array_values(array_filter($list, fn ($m) => (int) $m['visible'] === 1)));
    }

    /** 删除前校验 */
    protected function beforeRemove(array $ids): void
    {
        if (SysMenu::whereIn('parent_id', $ids)->count() > 0) {
            throw ApiException::badRequest('存在子菜单，不允许删除');
        }
        if (\app\admin\model\SysRoleMenu::whereIn('menu_id', $ids)->count() > 0) {
            throw ApiException::badRequest('菜单已分配给角色，请先解除授权');
        }
        \app\admin\model\SysRoleMenu::whereIn('menu_id', $ids)->delete();
    }
}
