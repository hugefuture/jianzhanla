<?php
declare(strict_types=1);

namespace app\admin\controller\system;

use app\admin\controller\CrudController;
use app\admin\service\SysUserService;
use app\common\exception\ApiException;
use think\Response;

/**
 * 用户管理
 */
class User extends CrudController
{
    public function __construct(\think\App $app)
    {
        parent::__construct($app);
        $this->service = SysUserService::getInstance();
    }

    /** 角色下拉选项 GET system/user/roleOptions */
    public function roleOptions(): Response
    {
        return $this->success(SysUserService::getInstance()->roleOptions());
    }

    /** 岗位下拉选项 GET system/user/postOptions */
    public function postOptions(): Response
    {
        return $this->success(SysUserService::getInstance()->postOptions());
    }

    /** 重置密码 POST system/user/resetPwd */
    public function resetPwd(): Response
    {
        $id = (int) $this->request->param('id');
        $password = (string) $this->request->param('password', '');
        SysUserService::getInstance()->resetPwd($id, $password);
        return $this->success(null, '密码已重置');
    }

    /** 分配角色 POST system/user/assignRoles */
    public function assignRoles(): Response
    {
        $id = (int) $this->request->param('id');
        $roleIds = (array) $this->request->param('role_ids/a', []);
        SysUserService::getInstance()->assignRoles($id, $roleIds);
        return $this->success(null, '分配成功');
    }
}
