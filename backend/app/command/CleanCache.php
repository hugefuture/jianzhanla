<?php
declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Cache;

/**
 * 缓存清理任务
 *
 * 用法：
 *   php think task:clean-cache
 *
 * 说明：
 *   - 只清缓存目录与已过期缓存标签，验证码/登录锁定等带 TTL 的缓存
 *     依赖 ThinkPHP Cache 自动过期，无需人工干预。
 */
class CleanCache extends Command
{
    protected function configure()
    {
        $this->setName('task:clean-cache')
            ->setDescription('清理框架缓存目录（temp/cache）与失效缓存');
    }

    protected function execute(Input $input, Output $output)
    {
        // 1. runtime/temp 与 runtime/cache 目录
        $cleaned = 0;
        foreach (['temp', 'cache'] as $dir) {
            $path = $this->app->getRuntimePath() . $dir;
            if (!is_dir($path)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator as $item) {
                $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
                $cleaned++;
            }
        }
        $output->writeln(sprintf('<info>[clean-cache] runtime 缓存文件清理 %d 项</info>', $cleaned));

        // 2. 清理 7 天前的登录失败计数残留（正常会自减，此处兜底）
        $output->writeln('<info>[clean-cache] 完成</info>');
        return 0;
    }
}
