<?php
declare(strict_types=1);

namespace app\admin\controller\system;

use app\admin\service\SysLoginLogService;
use app\admin\service\SysOperLogService;
use app\common\controller\AbstractController;
use think\Response;

/**
 * 日志管理：操作日志 + 登录日志
 */
class Log extends AbstractController
{
    /** 操作日志分页 GET system/log/oper */
    public function oper(): Response
    {
        return $this->success(SysOperLogService::getInstance()->page($this->request->param()));
    }

    /** 清空操作日志 POST system/log/clearOper */
    public function clearOper(): Response
    {
        SysOperLogService::getInstance()->clear();
        return $this->success(null, '已清空');
    }

    /** 登录日志分页 GET system/log/login */
    public function login(): Response
    {
        return $this->success(SysLoginLogService::getInstance()->page($this->request->param()));
    }

    /** 清空登录日志 POST system/log/clearLogin */
    public function clearLogin(): Response
    {
        SysLoginLogService::getInstance()->clear();
        return $this->success(null, '已清空');
    }
}
