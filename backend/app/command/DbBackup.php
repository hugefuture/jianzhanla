<?php
declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

/**
 * 数据库备份
 *
 * 用法：
 *   php think db:backup                    # 备份到 runtime/backup，保留最近 20 份
 *   php think db:backup --keep=30          # 自定义保留份数
 *   php think db:backup --dir=/data/bak    # 自定义备份目录
 *
 * 说明：
 *  - 依赖 mysqldump 可执行文件，优先级：--mysqldump 参数 > 配置 [BACKUP] MYSQLDUMP > 自动探测
 *  - 只做备份不做恢复：恢复请用 mysql 命令行导入（有确认成本，避免误灌库）
 *  - 密码通过临时配置文件传递，不出现在进程命令行里
 */
class DbBackup extends Command
{
    protected function configure()
    {
        $this->setName('db:backup')
            ->addOption('keep', null, Option::VALUE_OPTIONAL, '保留份数', null)
            ->addOption('dir', null, Option::VALUE_OPTIONAL, '备份目录', '')
            ->addOption('mysqldump', null, Option::VALUE_OPTIONAL, 'mysqldump 可执行文件路径', '')
            ->setDescription('导出整库 SQL 备份，并按份数轮转清理');
    }

    protected function execute(Input $input, Output $output)
    {
        $cfg = $this->dbConfig();
        $keep = $input->getOption('keep') !== null
            ? max(1, (int) $input->getOption('keep'))
            : max(1, (int) (config('database.backup.keep') ?: config('backup.keep', 20)));
        $dir = (string) ($input->getOption('dir') ?: config('database.backup.dir') ?: config('backup.dir', ''));
        if ($dir === '') {
            // 统一放框架 runtime 下（backend/runtime/backup）
            $dir = $this->app->getRuntimePath() . 'backup';
        }
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            $output->writeln("<error>[db:backup] 备份目录不可创建：{$dir}</error>");
            return 1;
        }

        $bin = $this->resolveMysqldump((string) $input->getOption('mysqldump'), $output);
        if ($bin === '') {
            return 1;
        }

        $file = rtrim($dir, '/\\') . '/' . $cfg['database'] . '_' . date('Ymd_His') . '.sql';
        [$cpf, $cnf] = $this->writeAuthFile($cfg);

        // 单事务一致性 + 完整插入语句，兼容跨版本导入
        $cmd = escapeshellarg($bin)
            . ' --defaults-extra-file=' . escapeshellarg($cnf)
            . ' --single-transaction --quick --routines --triggers --default-character-set=' . $cfg['charset']
            . ' --host=' . escapeshellarg($cfg['host'])
            . ' --port=' . (int) $cfg['port']
            . ' ' . escapeshellarg($cfg['database'])
            . ' > ' . escapeshellarg($file) . ' 2>&1';

        $begin = microtime(true);
        $code = 0;
        $msg = '';
        exec($cmd, $msg, $code);
        @unlink($cnf);

        if ($code !== 0 || !is_file($file) || filesize($file) < 1024) {
            $tail = implode(' | ', array_slice(array_filter(array_map('trim', $msg)), -3));
            $output->writeln("<error>[db:backup] 备份失败：{$tail}</error>");
            @unlink($file);
            return 1;
        }

        $cost = round((microtime(true) - $begin) * 1000);
        // gzip 可选压缩
        $size = filesize($file);
        $output->writeln(sprintf(
            '<info>[db:backup] %s -> %s（%s，耗时 %dms）</info>',
            $cfg['database'], basename($file), $this->humanSize($size), $cost
        ));

        $removed = $this->rotate($dir, $keep);
        if ($removed > 0) {
            $output->writeln("<comment>[db:backup] 轮转清理 {$removed} 份旧备份（保留 {$keep} 份）</comment>");
        }
        return 0;
    }

    /** 从配置读取数据库连接信息 */
    private function dbConfig(): array
    {
        $default = config('database.default', 'mysql');
        $conn = (array) config('database.connections.' . $default, []);
        return [
            'host'     => (string) ($conn['hostname'] ?: '127.0.0.1'),
            'port'     => (int) ($conn['hostport'] ?: 3306),
            'database' => (string) ($conn['database'] ?: ''),
            'username' => (string) ($conn['username'] ?: ''),
            'password' => (string) ($conn['password'] ?: ''),
            'charset'  => str_replace('-', '', (string) ($conn['charset'] ?: 'utf8mb4')),
        ];
    }

    /** 定位 mysqldump：参数 > 配置 > 常见路径自动探测 */
    private function resolveMysqldump(string $param, Output $output): string
    {
        $candidates = array_filter([
            $param,
            config('database.backup.mysqldump', ''),
            config('backup.mysqldump', ''),
        ]);
        // Windows 常见 phpStudy 路径 + Unix PATH
        foreach (glob('D:/phpstudy_pro/Extensions/MySQL*/bin/mysqldump.exe') ?: [] as $p) {
            $candidates[] = $p;
        }
        $candidates[] = PHP_OS_FAMILY === 'Windows' ? 'C:/Program Files/MySQL/*/bin/mysqldump.exe' : '/usr/bin/mysqldump';
        foreach ($candidates as $p) {
            foreach (glob((string) $p) ?: [(string) $p] as $real) {
                if (is_file($real)) {
                    return $real;
                }
            }
        }
        $which = '';
        exec(PHP_OS_FAMILY === 'Windows' ? 'where mysqldump 2>nul' : 'which mysqldump 2>/dev/null', $w, $c);
        if ($c === 0 && !empty($w[0]) && is_file(trim($w[0]))) {
            return trim($w[0]);
        }
        $output->writeln('<error>[db:backup] 未找到 mysqldump，可通过 --mysqldump=路径 指定</error>');
        return '';
    }

    /** 写临时认证文件（避免密码出现在进程列表） */
    private function writeAuthFile(array $cfg): array
    {
        $tmp = sys_get_temp_dir() . '/jianzhanla_backup_' . getmypid() . '.cnf';
        $content = "[client]\nuser=\"{$cfg['username']}\"\npassword=\"{$cfg['password']}\"\n";
        file_put_contents($tmp, $content);
        @chmod($tmp, 0600);
        return [$tmp, $tmp];
    }

    /** 按份数轮转，保留最新的 keep 份 */
    private function rotate(string $dir, int $keep): int
    {
        $files = glob(rtrim($dir, '/\\') . '/*.sql') ?: [];
        if (count($files) <= $keep) {
            return 0;
        }
        usort($files, fn ($a, $b) => filemtime($a) <=> filemtime($b));
        $old = array_slice($files, 0, count($files) - $keep);
        $n = 0;
        foreach ($old as $f) {
            @unlink($f) && $n++;
        }
        return $n;
    }

    private function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $v = (float) $bytes; $i = 0;
        while ($v >= 1024 && $i < count($units) - 1) { $v /= 1024; $i++; }
        return round($v, 2) . ' ' . $units[$i];
    }
}
