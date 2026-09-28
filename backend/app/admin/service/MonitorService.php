<?php
declare(strict_types=1);

namespace app\admin\service;

use think\facade\Db;

/**
 * 系统监控服务
 *
 * 说明：
 *  - 服务器信息以 PHP 可获得的跨平台数据为准（CPU 实时占用需 OS 级探针，不在范围内）
 *  - Linux 下额外提供负载均值（sys_getloadavg）
 */
class MonitorService
{
    /** 服务器概况 */
    public function server(): array
    {
        $root = dirname(__DIR__, 3); // backend/

        return [
            'os'         => PHP_OS_FAMILY . ' / ' . php_uname('s') . ' ' . php_uname('r'),
            'php'        => PHP_VERSION . ' (' . php_sapi_name() . ')',
            'thinkphp'   => \think\App::VERSION,
            'timezone'   => date_default_timezone_get(),
            'time'       => date('Y-m-d H:i:s'),
            'load'       => $this->loadAvg(),
            'disk'       => $this->disk(),
            'ini'        => [
                'memory_limit'      => ini_get('memory_limit') ?: 'unknown',
                'upload_max_filesize' => ini_get('upload_max_filesize') ?: 'unknown',
                'post_max_size'     => ini_get('post_max_size') ?: 'unknown',
                'max_execution_time' => ini_get('max_execution_time') . 's',
            ],
            'extensions' => count(get_loaded_extensions()),
            'uploads'    => $this->dirStat($root . '/public/uploads'),
            'runtime'    => $this->dirStat($root . '/runtime'),
        ];
    }

    /** MySQL 状态 */
    public function mysql(): array
    {
        $version = Db::query('SELECT VERSION() AS v')[0]['v'] ?? '';

        $status = [];
        foreach (Db::query('SHOW GLOBAL STATUS') as $row) {
            $status[$row['Variable_name']] = $row['Value'];
        }

        $uptime = (int) ($status['Uptime'] ?? 0);
        $questions = (int) ($status['Questions'] ?? 0);

        $db = config('database.connections.' . config('database.default', 'mysql') . '.database');
        $tableStat = Db::query(
            "SELECT COUNT(*) AS tables, IFNULL(SUM(data_length + index_length), 0) AS size
             FROM information_schema.tables WHERE table_schema = ?",
            [$db]
        )[0];

        return [
            'version'      => $version,
            'database'     => $db,
            'uptime'       => $uptime,
            'uptime_text'  => $this->humanDuration($uptime),
            'connections'  => (int) ($status['Threads_connected'] ?? 0),
            'max_connections' => (int) (Db::query("SHOW VARIABLES LIKE 'max_connections'")[0]['Value'] ?? 0),
            'threads_running' => (int) ($status['Threads_running'] ?? 0),
            'qps'          => $uptime > 0 ? round($questions / $uptime, 2) : 0,
            'total_queries'=> $questions,
            'slow_queries' => (int) ($status['Slow_queries'] ?? 0),
            'table_count'  => (int) ($tableStat['tables'] ?? 0),
            'size'         => (int) ($tableStat['size'] ?? 0),
            'size_text'    => $this->humanSize((int) ($tableStat['size'] ?? 0)),
        ];
    }

    /** 磁盘（项目所在盘） */
    private function disk(): array
    {
        $total = @disk_total_space('./');
        $free  = @disk_free_space('./');
        if ($total === false || $free === false || $total <= 0) {
            return ['total' => 0, 'free' => 0, 'used' => 0, 'percent' => 0];
        }
        $used = $total - $free;
        return [
            'total'   => (float) $total,
            'free'    => (float) $free,
            'used'    => (float) $used,
            'percent' => round($used / $total * 100, 1),
        ];
    }

    /** Linux 负载 / Windows 返回 null */
    private function loadAvg(): ?array
    {
        if (!function_exists('sys_getloadavg')) {
            return null;
        }
        $load = sys_getloadavg();
        return $load === false ? null : array_map(fn ($v) => round((float) $v, 2), $load);
    }

    /** 目录体积与文件数 */
    private function dirStat(string $dir): array
    {
        $size = 0; $files = 0;
        if (is_dir($dir)) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $f) {
                if ($f->isFile()) {
                    $size += $f->getSize();
                    $files++;
                }
            }
        }
        return ['files' => $files, 'size' => $size, 'size_text' => $this->humanSize($size)];
    }

    private function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $v = (float) $bytes;
        while ($v >= 1024 && $i < count($units) - 1) {
            $v /= 1024;
            $i++;
        }
        return round($v, 2) . ' ' . $units[$i];
    }

    private function humanDuration(int $seconds): string
    {
        $d = intdiv($seconds, 86400);
        $h = intdiv($seconds % 86400, 3600);
        $m = intdiv($seconds % 3600, 60);
        return ($d > 0 ? $d . '天' : '') . $h . '小时' . $m . '分';
    }
}
