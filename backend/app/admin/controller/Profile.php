<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\admin\service\SysUserService;
use app\common\controller\AbstractController;
use think\Response;

/**
 * 个人中心
 */
class Profile extends AbstractController
{
    /** 当前用户资料 GET profile/info */
    public function info(): Response
    {
        $user = $this->request->user;
        // 最近登录记录
        $logs = \app\admin\model\SysLoginLog::where('username', $user['username'])
            ->order('id desc')
            ->limit(5)
            ->select()
            ->toArray();
        return $this->success(['user' => $user, 'login_logs' => $logs]);
    }

    /** 更新资料 POST profile/update */
    public function update(): Response
    {
        SysUserService::getInstance()->updateProfile(
            (int) $this->request->user['id'],
            $this->request->only(['nickname', 'phone', 'email', 'avatar', 'sex'])
        );
        return $this->success(null, '资料已更新');
    }

    /** 修改密码 POST profile/password */
    public function password(): Response
    {
        $old = (string) $this->request->param('oldPassword', '');
        $new = (string) $this->request->param('newPassword', '');
        SysUserService::getInstance()->changePassword((int) $this->request->user['id'], $old, $new);
        return $this->success(null, '密码已修改，请重新登录');
    }
}
