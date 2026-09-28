<?php
declare(strict_types=1);

namespace app\admin\controller\system;

use app\admin\controller\CrudController;
use app\common\service\TokenService;
use think\Response;

/**
 * 在线用户管理：查看在线会话、强制下线
 */
class Online extends CrudController
{
    /** 在线会话列表 GET system/online/list */
    public function index(): Response
    {
        $list = TokenService::getInstance()->onlineList();
        return $this->success(['list' => $list, 'total' => count($list)]);
    }

    /** 强制下线（按会话）POST system/online/kick */
    public function kick(): Response
    {
        $jti = (string) $this->request->param('jti', '');
        if ($jti === '') {
            return $this->fail('缺少会话标识');
        }
        TokenService::getInstance()->kickToken($jti);
        return $this->success(null, '已强制下线');
    }

    /** 强制下线（按用户，踢掉其所有会话）POST system/online/kickUser */
    public function kickUser(): Response
    {
        $userId = (int) $this->request->param('user_id', 0);
        if ($userId <= 0) {
            return $this->fail('缺少用户ID');
        }
        TokenService::getInstance()->kickUser($userId);
        return $this->success(null, '已强制下线该用户的所有会话');
    }
}
