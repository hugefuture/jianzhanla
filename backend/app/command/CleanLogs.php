<?php
declare(strict_types=1);

namespace app\command;

use app\admin\model\SysLoginLog;
use app\admin\model\SysOperLog;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

/**
 * 日志清理任务
 *
 * 用法：
 *   php think task:clean-logs                # 默认保留 90 天
 *   php think task:clean-logs --days=30      # 自定义保留天数
 */
class CleanLogs extends Command
{
    protected function configure()
    {
        $this->setName('task:clean-logs')
            ->addOption('days', null, Option::VALUE_OPTIONAL, '日志保留天数', 90)
            ->setDescription('清理超期的操作日志/登录日志与 runtime 日志文件');
    }

    protected function execute(Input $input, Output $output)
    {
        $days = max(1, (int) $input->getOption('days'));
        $before = date('Y-m-d H:i:s', time() - $days * 86400);

        // 1. 数据库日志表
        $oper = SysOperLog::where('create_time', '<', $before)->delete();
        $login = SysLoginLog::where('login_time', '<', $before)->delete();
        $output->writeln(sprintf('<info>[clean-logs] 保留 %d 天：操作日志删除 %d 行，登录日志删除 %d 行</info>', $days, $oper, $login));

        // 2. runtime/log 目录下超期的日志文件（按文件修改时间）
        $logDir = $this->app->getRuntimePath() . 'log';
        $files = 0;
        if (is_dir($logDir)) {
            $expire = time() - $days * 86400;
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($logDir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getMTime() < $expire) {
                    @unlink($file->getPathname());
                    $files++;
                }
            }
        }
        $output->writeln(sprintf('<info>[clean-logs] runtime 日志文件清理 %d 个</info>', $files));
        return 0;
    }
}
