<?php
declare(strict_types=1);

namespace app\admin\controller\system;

use app\admin\controller\CrudController;
use app\admin\service\DataScopeService;
use app\admin\service\SysRoleService;
use think\Response;

/**
 * 角色管理
 */
class Role extends CrudController
{
    public function __construct(\think\App $app)
    {
        parent::__construct($app);
        $this->service = SysRoleService::getInstance();
    }

    /** 数据范围选项 GET system/role/scopeOptions */
    public function scopeOptions(): Response
    {
        return $this->success(SysRoleService::getInstance()->scopeOptions());
    }

    /** 全量角色（下拉用） GET system/role/all */
    public function all(): Response
    {
        return $this->success($this->service->list(['status' => 1]));
    }

    /** 角色已授权菜单 ID GET system/role/menuIds/:id */
    public function menuIds(int $id): Response
    {
        return $this->success(SysRoleService::getInstance()->menuIds($id));
    }

    /** 保存角色数据范围部门 POST system/role/saveDepts */
    public function saveDepts(): Response
    {
        $id = (int) $this->request->param('id');
        $deptIds = (array) $this->request->param('dept_ids/a', []);
        DataScopeService::getInstance()->saveRoleDepts($id, $deptIds);
        return $this->success(null, '数据范围已保存');
    }

    /** 分配菜单 POST system/role/assignMenus */
    public function assignMenus(): Response
    {
        $id = (int) $this->request->param('id');
        $menuIds = (array) $this->request->param('menu_ids/a', []);
        SysRoleService::getInstance()->assignMenus($id, $menuIds);
        return $this->success(null, '授权成功');
    }
}
