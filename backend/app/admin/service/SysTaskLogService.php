<?php
declare(strict_types=1);

namespace app\admin\service;

use app\admin\dao\SysTaskLogDao;
use app\admin\model\SysTaskLog;
use app\common\service\AbstractService;

/**
 * 定时任务执行日志服务
 */
class SysTaskLogService extends AbstractService
{
    public function __construct()
    {
        $this->dao = SysTaskLogDao::getInstance();
    }

    /**
     * 记录一次任务执行结果
     * 说明：写日志失败绝不能影响任务本身，所有异常在这里吞掉
     */
    public function record(string $name, string $desc, int $status, int $costMs, string $errorMsg = ''): void
    {
        try {
            SysTaskLog::create([
                'task_name' => $name,
                'task_desc' => $desc,
                'status' => $status,
                'cost_ms' => max(0, $costMs),
                'error_msg' => mb_substr($errorMsg, 0, 500),
            ]);
        } catch (\Throwable $e) {
            // 日志表未建/连不上库时静默降级，任务照常执行
        }
    }

    /** 清空日志 */
    public function clear(): void
    {
        SysTaskLog::query()->delete(true);
    }

    /**
     * 每个任务的最近一次执行情况概览
     * @return array{list: array, lastRunAt: string|null, failCount: int}
     */
    public function overview(int $recentDays = 7): array
    {
        try {
            $since = date('Y-m-d H:i:s', time() - $recentDays * 86400);
            $rows = SysTaskLog::where('create_time', '>=', $since)->select()->toArray();
        } catch (\Throwable $e) {
            return ['list' => [], 'lastRunAt' => null, 'failCount' => 0];
        }

        $grouped = [];
        foreach ($rows as $r) {
            $name = $r['task_name'];
            if (!isset($grouped[$name])) {
                $grouped[$name] = [
                    'task_name' => $name,
                    'task_desc' => $r['task_desc'],
                    'runs' => 0,
                    'fails' => 0,
                    'total_ms' => 0,
                    'last_time' => '',
                    'last_status' => SysTaskLog::ST_OK,
                ];
            }
            $g = &$grouped[$name];
            $g['runs']++;
            $g['fails'] += ((int) $r['status'] === SysTaskLog::ST_FAIL) ? 1 : 0;
            $g['total_ms'] += (int) $r['cost_ms'];
            if ($r['create_time'] >= $g['last_time']) {
                $g['last_time'] = (string) $r['create_time'];
                $g['last_status'] = (int) $r['status'];
            }
            unset($g);
        }

        $list = array_values($grouped);
        foreach ($list as &$item) {
            $item['avg_ms'] = $item['runs'] > 0 ? (int) round($item['total_ms'] / $item['runs']) : 0;
            unset($item['total_ms']);
        }

        $lastRunAt = $rows ? max(array_column($rows, 'create_time')) : null;
        $failCount = count(array_filter($rows, fn ($r) => (int) $r['status'] === SysTaskLog::ST_FAIL));

        return ['list' => $list, 'lastRunAt' => $lastRunAt, 'failCount' => $failCount];
    }
}
