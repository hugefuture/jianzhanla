<?php
declare(strict_types=1);

namespace app\admin\controller\system;

use app\admin\controller\CrudController;
use app\admin\service\SysDeptService;
use think\Response;

/**
 * 部门管理
 */
class Dept extends CrudController
{
    public function __construct(\think\App $app)
    {
        parent::__construct($app);
        $this->service = SysDeptService::getInstance();
    }

    /** 部门树（下拉用，不含停用） GET system/dept/tree */
    public function tree(): Response
    {
        $list = SysDeptService::getInstance()->list(['status' => 1]);
        return $this->success(SysDeptService::getInstance()->buildTree($list));
    }
}
