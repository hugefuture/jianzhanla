<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\admin\model\SysLoginLog;
use app\admin\model\SysOperLog;
use app\admin\model\SysUser;
use app\common\controller\AbstractController;
use think\Response;

/**
 * 仪表盘
 */
class Index extends AbstractController
{
    public function stats(): Response
    {
        $today = date('Y-m-d');
        return $this->success([
            'user_total' => SysUser::count(),
            'user_active' => SysUser::where('status', 1)->count(),
            'login_today' => SysLoginLog::where('login_time', '>=', $today . ' 00:00:00')->count(),
            'oper_today' => SysOperLog::where('create_time', '>=', $today . ' 00:00:00')->count(),
            'recent_logins' => SysLoginLog::order('id desc')->limit(8)->select()->toArray(),
        ]);
    }
}
