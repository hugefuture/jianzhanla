<?php
declare(strict_types=1);

namespace app\admin\controller\system;

use app\admin\controller\CrudController;
use app\admin\service\SysMenuService;
use think\Response;

/**
 * 菜单管理
 */
class Menu extends CrudController
{
    public function __construct(\think\App $app)
    {
        parent::__construct($app);
        $this->service = SysMenuService::getInstance();
    }

    /** 菜单树（角色授权用） GET system/menu/tree */
    public function tree(): Response
    {
        return $this->success(SysMenuService::getInstance()->treeSelect());
    }
}
