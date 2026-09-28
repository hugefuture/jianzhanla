<?php
declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

/**
 * 定时任务统一入口
 *
 * 用法：
 *   php think task:run                  # 执行全部任务
 *   php think task:run --only=logs      # 只执行日志清理
 *
 * 调度（每分钟触发一次，由本命令自行判断到期）：
 *   Linux  : * * * * * cd /www/wwwroot/jianzhanla-admin/backend && php think task:run >> runtime/task.log 2>&1
 *   Windows: schtasks /Create /TN jianzhanla-task /Sc MINUTE /Mo 5 /Tr "D:\php\php.exe think task:run"
 */
class TaskRun extends Command
{
    /** 默认任务表：name => [说明, 间隔分钟] */
    private const TASKS = [
        'logs'   => ['清理超期日志', 1440],   // 每天一次
        'cache'  => ['清理框架缓存', 1440],   // 每天一次
        'backup' => ['数据库备份', 1440],     // 每天一次
    ];

    /** 状态文件（存上次执行时间，独立于 Cache，避免被缓存清理误删） */
    private function stateFile(): string
    {
        return $this->app->getRuntimePath() . 'task-state.json';
    }

    private function loadState(): array
    {
        $file = $this->stateFile();
        if (is_file($file)) {
            $data = json_decode((string) file_get_contents($file), true);
            if (is_array($data)) {
                return $data;
            }
        }
        return [];
    }

    private function saveState(array $state): void
    {
        @file_put_contents($this->stateFile(), json_encode($state, JSON_UNESCAPED_UNICODE));
    }

    protected function configure()
    {
        $this->setName('task:run')
            ->addOption('only', null, Option::VALUE_OPTIONAL, '只执行指定任务（logs/cache）', '')
            ->addOption('force', 'f', Option::VALUE_NONE, '忽略间隔强制执行')
            ->setDescription('定时任务统一入口（按间隔判断是否到期）');
    }

    protected function execute(Input $input, Output $output)
    {
        $only = (string) $input->getOption('only');
        $force = (bool) $input->getOption('force');
        $now = time();
        $state = $this->loadState();
        $executed = 0;

        foreach (self::TASKS as $name => [$desc, $interval]) {
            if ($only !== '' && $only !== $name) {
                continue;
            }
            $last = (int) ($state[$name] ?? 0);
            if (!$force && $last > 0 && ($now - $last) < $interval * 60) {
                $output->writeln(sprintf('<comment>[task:run] %s（%s）未到期，跳过（上次 %s）</comment>', $desc, $name, date('m-d H:i', $last)));
                continue;
            }

            $begin = microtime(true);
            $errorMsg = '';
            $status = 0;
            try {
                $code = $this->app->console->call($this->taskCommand($name), $this->taskArgs($name));
                // TP8 的 call() 返回值可能是 int 或 Output，统一按成功处理
                $code = is_int($code) ? $code : 0;
            } catch (\Throwable $e) {
                $status = 1;
                $errorMsg = $e->getMessage();
                $output->writeln(sprintf('<error>[task:run] %s 执行异常：%s</error>', $name, $errorMsg));
            }
            $state[$name] = $now;
            $this->saveState($state);

            $cost = (int) round((microtime(true) - $begin) * 1000);
            \app\admin\service\SysTaskLogService::getInstance()
                ->record($name, $desc, $status, $cost, $errorMsg);

            if ($status === 0) {
                $output->writeln(sprintf('<info>[task:run] %s（%s）完成，退出码 %d，耗时 %dms</info>', $desc, $name, $code ?? 0, $cost));
                $executed++;
            }
        }

        if ($executed === 0 && $only === '') {
            $output->writeln('<comment>[task:run] 本轮无到期任务</comment>');
        }
        return 0;
    }

    private function taskCommand(string $name): string
    {
        return [
            'logs' => 'task:clean-logs',
            'cache' => 'task:clean-cache',
            'backup' => 'db:backup',
        ][$name] ?? '';
    }

    private function taskArgs(string $name): array
    {
        // clean-logs 默认保留 90 天，可在任务表中扩展配置
        return [];
    }
}
