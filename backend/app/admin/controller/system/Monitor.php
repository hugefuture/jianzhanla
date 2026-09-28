<?php
declare(strict_types=1);

namespace app\admin\controller\system;

use app\admin\controller\CrudController;
use app\admin\service\MonitorService;
use app\admin\service\SysTaskLogService;
use think\Response;

/**
 * 系统监控：服务器与 MySQL 状态 + 定时任务执行日志
 */
class Monitor extends CrudController
{
    /** 监控总览 GET system/monitor/index */
    public function index(): Response
    {
        $service = new MonitorService();
        return $this->success([
            'server' => $service->server(),
            'mysql'  => $service->mysql(),
        ]);
    }

    /** 定时任务执行日志分页 GET system/monitor/tasklog */
    public function tasklog(): Response
    {
        return $this->success(SysTaskLogService::getInstance()->page($this->request->param()));
    }

    /** 任务执行概况（按任务聚合最近记录）GET system/monitor/taskOverview */
    public function taskOverview(): Response
    {
        $days = (int) $this->request->param('days', 7);
        return $this->success(SysTaskLogService::getInstance()->overview($days));
    }

    /** 清空任务日志 POST system/monitor/clearTasklog */
    public function clearTasklog(): Response
    {
        SysTaskLogService::getInstance()->clear();
        return $this->success(null, '已清空');
    }
}
