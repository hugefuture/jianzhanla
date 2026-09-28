<?php
declare(strict_types=1);

namespace app\admin\service;

use app\common\service\AbstractService;
use app\admin\model\SysDept;
use app\admin\model\SysUser;
use app\admin\dao\SysDeptDao;
use app\common\exception\ApiException;

/**
 * 部门服务
 */
class SysDeptService extends AbstractService
{
    protected array $allowFields = ['parent_id', 'name', 'order_num', 'leader', 'phone', 'email', 'status'];

    protected array $required = ['name'];

    public function __construct()
    {
        $this->dao = SysDeptDao::getInstance();
    }

    /** 树形列表 */
    public function page(array $params = []): array
    {
        $list = $this->dao->getList($params);
        $tree = $this->buildTree($list);
        return ['list' => $tree, 'total' => count($list)];
    }

    public function buildTree(array $list, int $parentId = 0): array
    {
        $tree = [];
        foreach ($list as $item) {
            if ((int) $item['parent_id'] === $parentId) {
                $item['children'] = $this->buildTree($list, (int) $item['id']);
                if (empty($item['children'])) {
                    unset($item['children']);
                }
                $tree[] = $item;
            }
        }
        return $tree;
    }

    /** 取部门及所有子孙部门 ID */
    public function childrenIds(int $deptId): array
    {
        $all = SysDept::column('id, parent_id');
        $ids = [$deptId];
        $queue = [$deptId];
        while ($queue) {
            $current = array_shift($queue);
            foreach ($all as $item) {
                if ((int) $item['parent_id'] === (int) $current && !in_array((int) $item['id'], $ids, true)) {
                    $ids[] = (int) $item['id'];
                    $queue[] = (int) $item['id'];
                }
            }
        }
        return $ids;
    }

    protected function beforeCreate(array &$data): void
    {
        $data['parent_id'] = (int) ($data['parent_id'] ?? 0);
    }

    protected function beforeUpdate(array &$data, int $id): void
    {
        $data['parent_id'] = (int) ($data['parent_id'] ?? 0);
        if ($data['parent_id'] === $id) {
            throw ApiException::badRequest('上级部门不能是自己');
        }
        // 不能移动到自己的子部门下
        if (in_array($data['parent_id'], $this->childrenIds($id), true)) {
            throw ApiException::badRequest('上级部门不能是自己的下级部门');
        }
    }

    protected function beforeRemove(array $ids): void
    {
        if (SysDept::whereIn('parent_id', $ids)->count() > 0) {
            throw ApiException::badRequest('存在下级部门，不允许删除');
        }
        if (SysUser::whereIn('dept_id', $ids)->count() > 0) {
            throw ApiException::badRequest('部门下存在用户，不允许删除');
        }
    }
}
